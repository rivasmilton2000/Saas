<?php
require_once __DIR__ . '/../config/countries.php';
require_once __DIR__ . '/../models/PendingRegistrationModel.php';
require_once __DIR__ . '/../models/PlanModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/BitacoraService.php';
require_once __DIR__ . '/StripeBillingService.php';

use Stripe\Event;

class PublicRegistrationService
{
    public static function getPlansForRegister(PDO $pdo): array
    {
        $plans = PlanModel::getActivePlansWithFeatures($pdo);

        foreach ($plans as &$plan) {
            $plan['precio_label'] = PlanModel::formatPriceLabel($plan);
            $plan['is_free'] = (($plan['precio'] ?? null) !== null && (float) ($plan['precio'] ?? 0) <= 0);
            $plan['is_custom'] = (($plan['precio'] ?? null) === null) || dbBoolValue($plan['personalizado'] ?? false);
            $plan['supports_checkout'] = stripePlanSupportsCheckout($plan);
        }
        unset($plan);

        return $plans;
    }

    public static function defaultPlanId(PDO $pdo): int
    {
        $freePlan = PlanModel::getBySlug($pdo, 'free');
        return (int) ($freePlan['id_plan'] ?? 0);
    }

    public static function start(PDO $pdo, array $input): array
    {
        $validation = self::validateInput($pdo, $input);
        if (!($validation['ok'] ?? false)) {
            return $validation;
        }

        $data = $validation['data'];
        $plan = $validation['plan'];

        if (dbBoolValue($plan['personalizado'] ?? false) || ($plan['precio'] ?? null) === null) {
            return [
                'ok' => false,
                'message' => 'El plan Enterprise se coordina con un asesor. Usa el boton de ventas para personalizar la propuesta.',
                'flash_type' => 'warning',
                'old' => $data['old'],
                'contact_url' => saasPublicUrl('contact.php?plan=enterprise'),
            ];
        }

        if ((float) ($plan['precio'] ?? 0) <= 0) {
            $userId = UsuarioModel::create($pdo, [
                'username' => $data['username'],
                'nombre_completo' => $data['nombre_completo'],
                'email' => $data['email'],
                'password' => $data['password_hash'],
                'rol' => 'user',
                'estado' => true,
                'pais' => $data['pais'],
                'id_plan' => (int) ($plan['id_plan'] ?? 0),
                'suscripcion_estado' => 'free',
            ]);

            BitacoraService::registrar(
                $pdo,
                $userId,
                'auth',
                'register_free',
                'Registro publico con plan Free.',
                [
                    'username' => $data['username'],
                    'rol' => 'user',
                    'entidad_id' => $userId,
                    'contexto' => [
                        'detalle' => 'Plan: ' . (string) ($plan['nombre'] ?? 'Free'),
                    ],
                ]
            );

            return [
                'ok' => true,
                'mode' => 'free',
                'username' => $data['username'],
                'plan_nombre' => (string) ($plan['nombre'] ?? 'Free'),
            ];
        }

        if (!StripeBillingService::isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Stripe aun no esta configurado en este entorno. Agrega tus claves para cobrar las membresias pagas.',
                'flash_type' => 'danger',
                'old' => $data['old'],
            ];
        }

        $pendingId = PendingRegistrationModel::create($pdo, [
            'nombre_completo' => $data['nombre_completo'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password_hash' => $data['password_hash'],
            'pais' => $data['pais'],
            'id_plan' => (int) ($plan['id_plan'] ?? 0),
            'plan_slug_snapshot' => (string) ($plan['slug'] ?? ''),
            'plan_nombre_snapshot' => (string) ($plan['nombre'] ?? ''),
            'plan_precio_snapshot' => $plan['precio'] ?? null,
            'estado' => 'draft',
        ]);

        $session = StripeBillingService::createRegistrationCheckoutSession($pendingId, $plan, $data['email']);
        PendingRegistrationModel::updateStripeData($pdo, $pendingId, [
            'stripe_checkout_session_id' => (string) ($session->id ?? ''),
            'stripe_status' => (string) ($session->payment_status ?? 'unpaid'),
            'estado' => 'checkout_created',
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);

        return [
            'ok' => true,
            'mode' => 'checkout',
            'checkout_url' => (string) ($session->url ?? ''),
            'plan_nombre' => (string) ($plan['nombre'] ?? ''),
        ];
    }

    public static function finalizeCheckoutReturn(PDO $pdo, string $sessionId): array
    {
        $session = StripeBillingService::retrieveCheckoutSession($sessionId);
        return self::finalizeCheckoutSession($pdo, $session, 'return');
    }

    public static function handleStripeWebhook(PDO $pdo, Event $event): void
    {
        $type = (string) ($event->type ?? '');

        if ($type === 'checkout.session.completed') {
            $session = $event->data->object ?? null;
            $sessionId = trim((string) ($session->id ?? ''));
            if ($sessionId !== '') {
                $session = StripeBillingService::retrieveCheckoutSession($sessionId);
            }

            self::finalizeCheckoutSession($pdo, $session, 'webhook');
            return;
        }

        if ($type === 'checkout.session.expired') {
            $session = $event->data->object ?? null;
            $sessionId = trim((string) ($session->id ?? ''));
            if ($sessionId !== '') {
                $pending = PendingRegistrationModel::getByCheckoutSessionId($pdo, $sessionId);
                if ($pending !== null && (string) ($pending['estado'] ?? '') !== 'completed') {
                    PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
                        'estado' => 'expired',
                        'stripe_status' => 'expired',
                        'stripe_payload_json' => StripeBillingService::objectToArray($session),
                    ]);
                }
            }
            return;
        }

        if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted') {
            $subscription = $event->data->object ?? null;
            $subscriptionId = trim((string) ($subscription->id ?? ''));
            if ($subscriptionId === '') {
                return;
            }

            $usuario = UsuarioModel::getByStripeSubscriptionId($pdo, $subscriptionId);
            if ($usuario === null) {
                return;
            }

            UsuarioModel::updateSubscriptionData($pdo, (int) $usuario['id'], [
                'stripe_customer_id' => trim((string) ($subscription->customer ?? '')) ?: null,
                'stripe_subscription_id' => $subscriptionId,
                'suscripcion_estado' => trim((string) ($subscription->status ?? '')) ?: null,
                'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
            ]);
        }
    }

    private static function validateInput(PDO $pdo, array $input): array
    {
        UsuarioModel::ensureSchema($pdo);
        PendingRegistrationModel::ensureSchema($pdo);

        $nombreCompleto = trim((string) ($input['nombre_completo'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $pais = normalizeCountryValue($input['pais'] ?? 'El Salvador', 'El Salvador');
        $idPlan = (int) ($input['id_plan'] ?? 0);
        $plan = $idPlan > 0 ? PlanModel::getById($pdo, $idPlan) : PlanModel::getBySlug($pdo, 'free');
        $old = [
            'nombre_completo' => $nombreCompleto,
            'email' => $email,
            'username' => $username,
            'pais' => $pais,
            'id_plan' => (int) ($plan['id_plan'] ?? 0),
        ];

        $nombreLength = function_exists('mb_strlen') ? mb_strlen($nombreCompleto) : strlen($nombreCompleto);
        if ($nombreCompleto === '' || $nombreLength < 3) {
            return self::error('Escribe tu nombre completo para crear la cuenta.', $old);
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return self::error('Necesitamos un correo valido para la cuenta y el cobro.', $old);
        }

        if (UsuarioModel::emailExists($pdo, $email)) {
            return self::error('Ese correo ya esta registrado.', $old);
        }

        if (PendingRegistrationModel::emailExistsOpen($pdo, $email)) {
            return self::error('Ya tienes un registro pendiente con ese correo. Completa o cancela ese checkout antes de abrir otro.', $old);
        }

        if ($username === '') {
            return self::error('Debes elegir un nombre de usuario.', $old);
        }

        if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
            return self::error('Usa de 3 a 50 caracteres en tu usuario: letras, numeros, punto, guion o guion bajo.', $old);
        }

        if (UsuarioModel::usernameExists($pdo, $username)) {
            return self::error('Ese nombre de usuario ya esta ocupado.', $old);
        }

        if (PendingRegistrationModel::usernameExistsOpen($pdo, $username)) {
            return self::error('Ya existe un registro pendiente con ese nombre de usuario.', $old);
        }

        if ($password === '' || strlen($password) < 6) {
            return self::error('La clave debe tener al menos 6 caracteres.', $old);
        }

        if ($password !== $confirmPassword) {
            return self::error('Las claves no coinciden.', $old);
        }

        if ($plan === null || !dbBoolValue($plan['activo'] ?? true)) {
            return self::error('Selecciona una membresia valida para continuar.', $old);
        }

        return [
            'ok' => true,
            'plan' => $plan,
            'data' => [
                'nombre_completo' => $nombreCompleto,
                'email' => $email,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'pais' => $pais,
                'old' => $old,
            ],
        ];
    }

    private static function finalizeCheckoutSession(PDO $pdo, $session, string $source): array
    {
        if (!is_object($session)) {
            return [
                'ok' => false,
                'message' => 'No se pudo validar la sesion de Stripe recibida.',
            ];
        }

        $sessionId = trim((string) ($session->id ?? ''));
        $pending = $sessionId !== '' ? PendingRegistrationModel::getByCheckoutSessionId($pdo, $sessionId) : null;

        if ($pending === null) {
            $pendingId = (int) ($session->metadata->pending_registration_id ?? $session->client_reference_id ?? 0);
            $pending = $pendingId > 0 ? PendingRegistrationModel::getById($pdo, $pendingId) : null;
        }

        if ($pending === null) {
            return [
                'ok' => false,
                'message' => 'No encontramos el registro pendiente asociado a este pago.',
            ];
        }

        if ((string) ($pending['estado'] ?? '') === 'completed' && !empty($pending['created_user_id'])) {
            $usuario = UsuarioModel::getById($pdo, (int) $pending['created_user_id'], false);
            return [
                'ok' => true,
                'already_completed' => true,
                'user' => $usuario,
                'plan_nombre' => (string) ($pending['plan_nombre_snapshot'] ?? ''),
            ];
        }

        $paymentStatus = strtolower(trim((string) ($session->payment_status ?? '')));
        $subscription = $session->subscription ?? null;
        $subscriptionId = trim((string) (is_object($subscription) ? ($subscription->id ?? '') : $subscription));
        $subscriptionStatus = strtolower(trim((string) (is_object($subscription) ? ($subscription->status ?? '') : '')));
        $customerId = trim((string) (is_object($session->customer ?? null) ? (($session->customer)->id ?? '') : ($session->customer ?? '')));

        if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
                'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                'stripe_status' => $paymentStatus !== '' ? $paymentStatus : null,
                'estado' => 'processing',
                'stripe_payload_json' => StripeBillingService::objectToArray($session),
            ]);

            return [
                'ok' => false,
                'message' => 'Stripe aun esta confirmando el pago. Recarga esta pantalla en unos segundos.',
                'flash_type' => 'warning',
            ];
        }

        if (!StripeBillingService::subscriptionStatusAllowsAccess($subscriptionStatus)) {
            PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
                'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'stripe_customer_id' => $customerId !== '' ? $customerId : null,
                'stripe_status' => $subscriptionStatus !== '' ? $subscriptionStatus : $paymentStatus,
                'estado' => 'processing',
                'stripe_payload_json' => StripeBillingService::objectToArray($session),
            ]);

            return [
                'ok' => false,
                'message' => 'La suscripcion aun no termino de activarse en Stripe. Intenta entrar de nuevo en unos segundos.',
                'flash_type' => 'warning',
            ];
        }

        $existing = UsuarioModel::getByEmail($pdo, (string) ($pending['email'] ?? ''));
        if ($existing === null) {
            $existing = UsuarioModel::getByUsername($pdo, (string) ($pending['username'] ?? ''));
        }

        if ($existing !== null) {
            UsuarioModel::updateSubscriptionData($pdo, (int) $existing['id'], [
                'stripe_customer_id' => $customerId !== '' ? $customerId : null,
                'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'suscripcion_estado' => $subscriptionStatus,
                'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
                'id_plan' => (int) ($pending['id_plan'] ?? 0),
            ]);

            PendingRegistrationModel::markCompleted($pdo, (int) $pending['id'], (int) $existing['id'], [
                'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                'stripe_customer_id' => $customerId !== '' ? $customerId : null,
                'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'stripe_status' => $subscriptionStatus,
                'stripe_payload_json' => StripeBillingService::objectToArray($session),
            ]);

            return [
                'ok' => true,
                'already_completed' => true,
                'user' => $existing,
                'plan_nombre' => (string) ($pending['plan_nombre_snapshot'] ?? ''),
            ];
        }

        $userId = UsuarioModel::create($pdo, [
            'username' => (string) ($pending['username'] ?? ''),
            'nombre_completo' => (string) ($pending['nombre_completo'] ?? ''),
            'email' => (string) ($pending['email'] ?? ''),
            'password' => (string) ($pending['password_hash'] ?? ''),
            'rol' => 'user',
            'estado' => true,
            'pais' => (string) ($pending['pais'] ?? 'El Salvador'),
            'id_plan' => (int) ($pending['id_plan'] ?? 0),
            'stripe_customer_id' => $customerId !== '' ? $customerId : null,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'suscripcion_estado' => $subscriptionStatus,
            'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
        ]);

        PendingRegistrationModel::markCompleted($pdo, (int) $pending['id'], $userId, [
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'stripe_customer_id' => $customerId !== '' ? $customerId : null,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'stripe_status' => $subscriptionStatus,
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);

        BitacoraService::registrar(
            $pdo,
            $userId,
            'auth',
            $source === 'webhook' ? 'register_paid_webhook' : 'register_paid_return',
            'Registro pagado activado correctamente.',
            [
                'username' => (string) ($pending['username'] ?? ''),
                'rol' => 'user',
                'entidad_id' => $userId,
                'contexto' => [
                    'detalle' => 'Plan: ' . (string) ($pending['plan_nombre_snapshot'] ?? ''),
                ],
            ]
        );

        return [
            'ok' => true,
            'user_id' => $userId,
            'username' => (string) ($pending['username'] ?? ''),
            'plan_nombre' => (string) ($pending['plan_nombre_snapshot'] ?? ''),
        ];
    }

    private static function error(string $message, array $old): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'flash_type' => 'danger',
            'old' => $old,
        ];
    }
}
