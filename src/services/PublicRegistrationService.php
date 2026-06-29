<?php
require_once __DIR__ . '/../config/countries.php';
require_once __DIR__ . '/../models/PendingRegistrationModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../models/PlanModel.php';
require_once __DIR__ . '/../models/SubscriptionModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/BitacoraService.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/StripeBillingService.php';

use Stripe\Event;

class PublicRegistrationService
{
    public static function getPlansForRegister(PDO $pdo): array
    {
        $plans = PlanModel::getActivePlansWithFeatures($pdo);

        foreach ($plans as &$plan) {
            $plan['precio_label'] = PlanModel::formatPriceLabel($plan);
            $plan['is_free'] = stripePlanIsFree($plan);
            $plan['is_custom'] = stripePlanIsCustom($plan);
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

        if (stripePlanIsCustom($plan)) {
            return [
                'ok' => false,
                'message' => 'El plan Enterprise se coordina con un asesor. Usa el boton de ventas para personalizar la propuesta.',
                'flash_type' => 'warning',
                'old' => $data['old'],
                'contact_url' => saasPublicUrl('contact.php?plan=enterprise'),
            ];
        }

        if (stripePlanIsFree($plan)) {
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
                'auth_provider' => 'local',
            ]);

            $user = UsuarioModel::getById($pdo, $userId, false);
            if ($user !== null) {
                MailService::sendWelcomeFree($pdo, $user, $plan);
            }

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
                'user_id' => $userId,
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
            'auth_provider' => 'local',
            'estado' => 'draft',
        ]);

        $session = StripeBillingService::createRegistrationCheckoutSession($pendingId, $plan, $data['email']);
        PendingRegistrationModel::updateStripeData($pdo, $pendingId, [
            'stripe_checkout_session_id' => (string) ($session->id ?? ''),
            'stripe_status' => (string) ($session->payment_status ?? 'payment_pending'),
            'estado' => 'checkout_created',
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);
        self::recordDraftSubscription($pdo, null, $plan, $session);

        return [
            'ok' => true,
            'mode' => 'checkout',
            'checkout_url' => (string) ($session->url ?? ''),
            'plan_nombre' => (string) ($plan['nombre'] ?? ''),
        ];
    }

    public static function resumePendingCheckout(PDO $pdo, int $pendingId, int $planId = 0): array
    {
        PendingRegistrationModel::ensureSchema($pdo);

        $pending = PendingRegistrationModel::getById($pdo, $pendingId);
        if ($pending === null) {
            return self::error('No encontramos un registro pendiente para continuar con el pago.', []);
        }

        $state = strtolower(trim((string) ($pending['estado'] ?? 'draft')));
        if (in_array($state, ['completed', 'expired'], true)) {
            return self::error('Ese registro ya no se puede reutilizar para abrir un nuevo checkout.', []);
        }

        $storedPlanId = (int) ($pending['id_plan'] ?? 0);
        if ($planId > 0 && $storedPlanId > 0 && $planId !== $storedPlanId) {
            return self::error('El plan seleccionado no coincide con el registro pendiente.', []);
        }

        $plan = PlanModel::getById($pdo, $storedPlanId);
        if ($plan === null || !dbBoolValue($plan['activo'] ?? true)) {
            return self::error('El plan asociado a este registro ya no esta disponible.', []);
        }

        if (stripePlanIsCustom($plan)) {
            return [
                'ok' => false,
                'message' => 'El plan Enterprise se coordina con ventas. Usa el enlace de contacto para personalizarlo.',
                'flash_type' => 'warning',
                'contact_url' => saasPublicUrl('contact.php?plan=enterprise'),
            ];
        }

        if (stripePlanIsFree($plan)) {
            return self::error('El plan seleccionado no necesita Stripe Checkout.', []);
        }

        if (!StripeBillingService::isConfigured()) {
            return self::error('Stripe aun no esta configurado en este entorno. Agrega tus claves para cobrar las membresias pagas.', []);
        }

        $session = StripeBillingService::createRegistrationCheckoutSession((int) ($pending['id'] ?? 0), $plan, (string) ($pending['email'] ?? ''));
        PendingRegistrationModel::updateStripeData($pdo, (int) ($pending['id'] ?? 0), [
            'stripe_checkout_session_id' => (string) ($session->id ?? ''),
            'stripe_status' => (string) ($session->payment_status ?? 'payment_pending'),
            'estado' => 'checkout_created',
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);
        self::recordDraftSubscription($pdo, null, $plan, $session);

        return [
            'ok' => true,
            'mode' => 'checkout',
            'checkout_url' => (string) ($session->url ?? ''),
            'plan_nombre' => (string) ($plan['nombre'] ?? ''),
        ];
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

            self::processSuccessfulCheckoutSession($pdo, $session);
            return;
        }

        if ($type === 'invoice.paid') {
            self::handleInvoicePaid($pdo, $event->data->object ?? null);
            return;
        }

        if ($type === 'checkout.session.expired') {
            $session = $event->data->object ?? null;
            $sessionId = trim((string) ($session->id ?? ''));
            if ($sessionId === '') {
                return;
            }

            $pending = PendingRegistrationModel::getByCheckoutSessionId($pdo, $sessionId);
            if ($pending !== null && (string) ($pending['estado'] ?? '') !== 'completed') {
                PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
                    'estado' => 'expired',
                    'stripe_status' => 'expired',
                    'stripe_payload_json' => StripeBillingService::objectToArray($session),
                ]);
            }

            $subscription = SubscriptionModel::getByCheckoutSessionId($pdo, $sessionId);
            if ($subscription !== null) {
                SubscriptionModel::upsert($pdo, [
                    'stripe_checkout_session_id' => $sessionId,
                    'status' => 'expired',
                ]);
            }

            $user = UsuarioModel::getByCheckoutSessionId($pdo, $sessionId);
            if ($user !== null && !StripeBillingService::subscriptionStatusAllowsAccess($user['suscripcion_estado'] ?? null)) {
                UsuarioModel::updateSubscriptionData($pdo, (int) $user['id'], [
                    'suscripcion_estado' => 'payment_pending',
                ]);
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
            if ($usuario !== null) {
                UsuarioModel::updateSubscriptionData($pdo, (int) $usuario['id'], [
                    'stripe_customer_id' => trim((string) ($subscription->customer ?? '')) ?: null,
                    'stripe_subscription_id' => $subscriptionId,
                    'suscripcion_estado' => trim((string) ($subscription->status ?? '')) ?: null,
                    'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
                ]);

                SubscriptionModel::upsert($pdo, [
                    'user_id' => (int) ($usuario['id'] ?? 0),
                    'plan_id' => (int) ($usuario['id_plan'] ?? 0),
                    'stripe_customer_id' => trim((string) ($subscription->customer ?? '')) ?: null,
                    'stripe_subscription_id' => $subscriptionId,
                    'status' => trim((string) ($subscription->status ?? '')) ?: 'active',
                    'amount' => $usuario['plan_precio'] ?? null,
                    'currency' => $usuario['plan_moneda'] ?? 'USD',
                    'billing_interval' => $usuario['plan_billing_interval'] ?? $usuario['plan_periodo'] ?? null,
                    'current_period_start' => StripeBillingService::currentPeriodStartFromSubscription($subscription),
                    'current_period_end' => StripeBillingService::periodEndFromSubscription($subscription),
                ]);
            }
        }
    }

    public static function getCheckoutStatus(PDO $pdo, string $sessionId): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return [
                'status' => 'missing',
                'message' => 'No recibimos una referencia valida del checkout.',
            ];
        }

        $pending = PendingRegistrationModel::getByCheckoutSessionId($pdo, $sessionId);
        if ($pending !== null) {
            $state = (string) ($pending['estado'] ?? '');
            if ($state === 'completed') {
                return [
                    'status' => 'completed',
                    'message' => 'Tu pago ya fue confirmado. Ahora puedes iniciar sesion.',
                    'login_url' => '/Saas/src/pages/samples/login.php',
                ];
            }

            return [
                'status' => 'processing',
                'message' => 'Estamos confirmando tu pago con Stripe. Esta pantalla se actualizara cuando la suscripcion quede activa.',
                'login_url' => '/Saas/src/pages/samples/login.php',
            ];
        }

        $user = UsuarioModel::getByCheckoutSessionId($pdo, $sessionId);
        if ($user !== null) {
            if (StripeBillingService::subscriptionStatusAllowsAccess($user['suscripcion_estado'] ?? null)) {
                return [
                    'status' => 'completed',
                    'message' => 'Tu suscripcion ya esta activa. Inicia sesion para entrar a Zentra.',
                    'login_url' => '/Saas/src/pages/samples/login.php',
                ];
            }

            return [
                'status' => 'processing',
                'message' => 'Stripe aun esta terminando la confirmacion de tu membresia. Intenta iniciar sesion en unos segundos.',
                'login_url' => '/Saas/src/pages/samples/login.php',
            ];
        }

        return [
            'status' => 'not_found',
            'message' => 'No encontramos un registro asociado a esta sesion de pago.',
            'login_url' => '/Saas/src/pages/samples/login.php',
        ];
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
        $termsAccepted = !empty($input['terms']);
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

        if (!$termsAccepted) {
            return self::error('Debes aceptar terminos y condiciones para continuar.', $old);
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

    private static function processSuccessfulCheckoutSession(PDO $pdo, $session): void
    {
        if (!is_object($session)) {
            throw new RuntimeException('No se pudo validar la sesion de Stripe recibida.');
        }

        $paymentStatus = strtolower(trim((string) ($session->payment_status ?? '')));
        if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return;
        }

        $subscription = is_object($session->subscription ?? null) ? $session->subscription : null;
        $subscriptionStatus = strtolower(trim((string) ($subscription->status ?? '')));
        if (!StripeBillingService::subscriptionStatusAllowsAccess($subscriptionStatus)) {
            return;
        }

        $metadata = self::metadataFromSession($session);
        $flowType = strtolower(trim((string) ($metadata['flow_type'] ?? 'pending_registration')));

        if ($flowType === 'existing_user' && (int) ($metadata['user_id'] ?? 0) > 0) {
            self::finalizeExistingUserCheckout($pdo, (int) $metadata['user_id'], (int) ($metadata['plan_id'] ?? 0), $session);
            return;
        }

        self::finalizePendingRegistrationCheckout($pdo, (int) ($metadata['pending_registration_id'] ?? 0), $session);
    }

    private static function finalizePendingRegistrationCheckout(PDO $pdo, int $pendingId, $session): void
    {
        $sessionId = trim((string) ($session->id ?? ''));
        $subscription = is_object($session->subscription ?? null) ? $session->subscription : null;
        $subscriptionId = trim((string) ($subscription->id ?? ''));
        $subscriptionStatus = trim((string) ($subscription->status ?? ''));
        $customerId = StripeBillingService::sessionCustomerId($session);
        $invoiceData = StripeBillingService::extractInvoiceDataFromSession($session);

        $pending = $sessionId !== '' ? PendingRegistrationModel::getByCheckoutSessionId($pdo, $sessionId) : null;
        if ($pending === null && $pendingId > 0) {
            $pending = PendingRegistrationModel::getById($pdo, $pendingId);
        }
        if ($pending === null && $subscriptionId !== '') {
            $pending = PendingRegistrationModel::getByStripeSubscriptionId($pdo, $subscriptionId);
        }
        if ($pending === null) {
            throw new RuntimeException('No encontramos el registro pendiente asociado a este pago.');
        }

        PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
            'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
            'stripe_status' => $subscriptionStatus !== '' ? $subscriptionStatus : strtolower(trim((string) ($session->payment_status ?? ''))),
            'estado' => 'processing',
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);

        if ((string) ($pending['estado'] ?? '') === 'completed' && !empty($pending['created_user_id'])) {
            $user = UsuarioModel::getById($pdo, (int) $pending['created_user_id'], false);
            if ($user !== null) {
                UsuarioModel::updateSubscriptionData($pdo, (int) $user['id'], array_merge([
                    'id_plan' => (int) ($pending['id_plan'] ?? 0),
                    'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                    'stripe_customer_id' => $customerId,
                    'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                    'suscripcion_estado' => $subscriptionStatus,
                    'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
                ], self::invoiceDataToUserUpdate($invoiceData)));

                $freshUser = UsuarioModel::getById($pdo, (int) $user['id'], false) ?? $user;
                $plan = self::planFromPending($pdo, $pending);
                self::syncStripeRecordsForUser($pdo, $freshUser, $plan, $session, $invoiceData);
                self::sendActivatedPaidEmails($pdo, $freshUser, $plan, $invoiceData, $sessionId);
            }
            return;
        }

        $existing = UsuarioModel::getByEmail($pdo, (string) ($pending['email'] ?? ''));
        if ($existing === null) {
            $existing = UsuarioModel::getByUsername($pdo, (string) ($pending['username'] ?? ''));
        }

        if ($existing !== null) {
            UsuarioModel::updateAuthData($pdo, (int) $existing['id'], [
                'google_id' => $pending['google_id'] ?? null,
                'foto_perfil' => $pending['foto_perfil'] ?? null,
                'email' => $pending['email'] ?? null,
                'email_verificado_at' => $pending['email_verificado_at'] ?? null,
                'auth_provider' => (string) ($pending['auth_provider'] ?? '') !== '' ? $pending['auth_provider'] : ($existing['auth_provider'] ?? 'local'),
            ]);
            UsuarioModel::updateSubscriptionData($pdo, (int) $existing['id'], array_merge([
                'email' => $pending['email'] ?? null,
                'id_plan' => (int) ($pending['id_plan'] ?? 0),
                'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'suscripcion_estado' => $subscriptionStatus,
                'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
            ], self::invoiceDataToUserUpdate($invoiceData)));

            PendingRegistrationModel::markCompleted($pdo, (int) $pending['id'], (int) $existing['id'], [
                'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
                'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
                'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
                'stripe_status' => $subscriptionStatus,
                'stripe_payload_json' => StripeBillingService::objectToArray($session),
            ]);

            $freshUser = UsuarioModel::getById($pdo, (int) $existing['id'], false) ?? $existing;
            $plan = self::planFromPending($pdo, $pending);
            self::syncStripeRecordsForUser($pdo, $freshUser, $plan, $session, $invoiceData);
            self::sendActivatedPaidEmails($pdo, $freshUser, $plan, $invoiceData, $sessionId);
            return;
        }

        $userId = UsuarioModel::create($pdo, [
            'username' => (string) ($pending['username'] ?? ''),
            'nombre_completo' => (string) ($pending['nombre_completo'] ?? ''),
            'foto_perfil' => $pending['foto_perfil'] ?? null,
            'email' => (string) ($pending['email'] ?? ''),
            'password' => $pending['password_hash'] ?? null,
            'auth_provider' => (string) ($pending['auth_provider'] ?? '') !== '' ? $pending['auth_provider'] : 'local',
            'google_id' => $pending['google_id'] ?? null,
            'email_verificado_at' => $pending['email_verificado_at'] ?? null,
            'rol' => 'user',
            'estado' => true,
            'pais' => (string) ($pending['pais'] ?? 'El Salvador'),
            'id_plan' => (int) ($pending['id_plan'] ?? 0),
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
            'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
            'suscripcion_estado' => $subscriptionStatus,
            'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
        ]);

        PendingRegistrationModel::markCompleted($pdo, (int) $pending['id'], $userId, [
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
            'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
            'stripe_status' => $subscriptionStatus,
            'stripe_payload_json' => StripeBillingService::objectToArray($session),
        ]);

        BitacoraService::registrar(
            $pdo,
            $userId,
            'auth',
            'register_paid_webhook',
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

        $user = UsuarioModel::getById($pdo, $userId, false);
        if ($user !== null) {
            $plan = self::planFromPending($pdo, $pending);
            self::syncStripeRecordsForUser($pdo, $user, $plan, $session, $invoiceData);
            self::sendActivatedPaidEmails($pdo, $user, $plan, $invoiceData, $sessionId);
        }
    }

    private static function finalizeExistingUserCheckout(PDO $pdo, int $userId, int $planId, $session): void
    {
        $user = UsuarioModel::getById($pdo, $userId, false);
        if ($user === null) {
            throw new RuntimeException('No encontramos la cuenta asociada al checkout.');
        }

        $plan = $planId > 0 ? PlanModel::getById($pdo, $planId) : PlanModel::getById($pdo, (int) ($user['id_plan'] ?? 0));
        if ($plan === null) {
            throw new RuntimeException('No encontramos el plan pagado asociado al checkout.');
        }

        $subscription = is_object($session->subscription ?? null) ? $session->subscription : null;
        $subscriptionId = trim((string) ($subscription->id ?? ''));
        $subscriptionStatus = trim((string) ($subscription->status ?? ''));
        $customerId = StripeBillingService::sessionCustomerId($session);
        $invoiceData = StripeBillingService::extractInvoiceDataFromSession($session);

        UsuarioModel::updateSubscriptionData($pdo, $userId, array_merge([
            'id_plan' => (int) ($plan['id_plan'] ?? 0),
            'stripe_checkout_session_id' => trim((string) ($session->id ?? '')) ?: null,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'suscripcion_estado' => $subscriptionStatus,
            'suscripcion_renueva_at' => StripeBillingService::periodEndFromSubscription($subscription),
        ], self::invoiceDataToUserUpdate($invoiceData)));

        $freshUser = UsuarioModel::getById($pdo, $userId, false) ?? $user;

        self::syncStripeRecordsForUser($pdo, $freshUser, $plan, $session, $invoiceData);

        BitacoraService::registrar(
            $pdo,
            $userId,
            'auth',
            'activate_paid_plan_webhook',
            'Membresia activada correctamente desde Stripe.',
            [
                'username' => (string) ($freshUser['username'] ?? ''),
                'rol' => 'user',
                'entidad_id' => $userId,
                'contexto' => [
                    'detalle' => 'Plan: ' . (string) ($plan['nombre'] ?? ''),
                ],
            ]
        );

        self::sendActivatedPaidEmails($pdo, $freshUser, $plan, $invoiceData, trim((string) ($session->id ?? '')));
    }

    private static function handleInvoicePaid(PDO $pdo, $invoice): void
    {
        if (!is_object($invoice)) {
            return;
        }

        $subscriptionId = trim((string) ($invoice->subscription ?? ''));
        if ($subscriptionId === '') {
            return;
        }

        $invoiceData = StripeBillingService::extractInvoiceDataFromInvoice($invoice);
        $pending = PendingRegistrationModel::getByStripeSubscriptionId($pdo, $subscriptionId);
        if ($pending !== null) {
            PendingRegistrationModel::updateStripeData($pdo, (int) $pending['id'], [
                'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
                'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
                'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
            ]);
        }

        $user = UsuarioModel::getByStripeSubscriptionId($pdo, $subscriptionId);
        if ($user === null && $pending !== null && !empty($pending['created_user_id'])) {
            $user = UsuarioModel::getById($pdo, (int) $pending['created_user_id'], false);
        }

        if ($user === null) {
            return;
        }

        UsuarioModel::updateSubscriptionData($pdo, (int) $user['id'], self::invoiceDataToUserUpdate($invoiceData));
        $plan = PlanModel::getById($pdo, (int) ($user['id_plan'] ?? 0));
        if ($plan !== null) {
            self::syncInvoiceRecordsForUser($pdo, $user, $plan, $subscriptionId, $invoiceData);
            self::sendPaymentVoucherEmail($pdo, UsuarioModel::getById($pdo, (int) $user['id'], false) ?? $user, $plan, $invoiceData);
        }
    }

    private static function sendActivatedPaidEmails(PDO $pdo, array $user, array $plan, array $invoiceData, string $sessionId): void
    {
        $payment = array_merge($invoiceData, [
            'session_id' => $sessionId,
            'reference' => $invoiceData['reference'] ?? $sessionId,
        ]);

        MailService::sendWelcomePaid($pdo, $user, $plan, $payment);
        self::sendPaymentVoucherEmail($pdo, $user, $plan, $payment);
    }

    private static function sendPaymentVoucherEmail(PDO $pdo, array $user, array $plan, array $payment): void
    {
        if (
            empty($payment['invoice_id'])
            && empty($payment['invoice_url'])
            && empty($payment['invoice_pdf_url'])
            && empty($payment['reference'])
        ) {
            return;
        }

        MailService::sendPaymentVoucher($pdo, $user, $plan, $payment);
    }

    private static function metadataFromSession($session): array
    {
        $metadata = [];
        if (is_object($session->metadata ?? null)) {
            $metadata = StripeBillingService::objectToArray($session->metadata);
        } elseif (is_array($session->metadata ?? null)) {
            $metadata = $session->metadata;
        }

        if ($metadata !== []) {
            return $metadata;
        }

        $subscription = is_object($session->subscription ?? null) ? $session->subscription : null;
        if ($subscription !== null && is_object($subscription->metadata ?? null)) {
            return StripeBillingService::objectToArray($subscription->metadata);
        }

        return [];
    }

    private static function planFromPending(PDO $pdo, array $pending): array
    {
        $plan = PlanModel::getById($pdo, (int) ($pending['id_plan'] ?? 0));
        if ($plan !== null) {
            return $plan;
        }

        return [
            'id_plan' => (int) ($pending['id_plan'] ?? 0),
            'slug' => (string) ($pending['plan_slug_snapshot'] ?? ''),
            'nombre' => (string) ($pending['plan_nombre_snapshot'] ?? 'Plan'),
            'precio' => $pending['plan_precio_snapshot'] ?? null,
            'periodo' => 'mes',
            'billing_interval' => 'month',
            'descripcion' => '',
            'moneda' => 'USD',
        ];
    }

    private static function invoiceDataToUserUpdate(array $invoiceData): array
    {
        return [
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'stripe_invoice_url' => $invoiceData['invoice_url'] ?? null,
            'stripe_invoice_pdf_url' => $invoiceData['invoice_pdf_url'] ?? null,
        ];
    }

    private static function recordDraftSubscription(PDO $pdo, ?int $userId, array $plan, $session): void
    {
        $sessionId = trim((string) ($session->id ?? ''));
        if ($sessionId === '') {
            return;
        }

        SubscriptionModel::upsert($pdo, [
            'user_id' => $userId,
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'stripe_checkout_session_id' => $sessionId,
            'status' => 'payment_pending',
            'amount' => $plan['precio'] ?? null,
            'currency' => $plan['moneda'] ?? 'USD',
            'billing_interval' => $plan['billing_interval'] ?? $plan['periodo'] ?? null,
        ]);
    }

    private static function syncStripeRecordsForUser(PDO $pdo, array $user, array $plan, $session, array $invoiceData): void
    {
        $subscription = is_object($session->subscription ?? null) ? $session->subscription : null;
        $subscriptionStatus = trim((string) ($subscription->status ?? ''));
        $sessionId = trim((string) ($session->id ?? ''));
        $subscriptionRecord = SubscriptionModel::upsert($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'stripe_customer_id' => StripeBillingService::sessionCustomerId($session),
            'stripe_subscription_id' => StripeBillingService::sessionSubscriptionId($session),
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'status' => $subscriptionStatus !== '' ? $subscriptionStatus : strtolower(trim((string) ($session->payment_status ?? 'paid'))),
            'amount' => $invoiceData['amount_decimal'] ?? ($plan['precio'] ?? null),
            'currency' => $invoiceData['currency'] ?? ($plan['moneda'] ?? 'USD'),
            'billing_interval' => $plan['billing_interval'] ?? $plan['periodo'] ?? null,
            'current_period_start' => StripeBillingService::currentPeriodStartFromSubscription($subscription),
            'current_period_end' => StripeBillingService::periodEndFromSubscription($subscription),
        ]);

        if ($sessionId === '' && empty($invoiceData['invoice_id'])) {
            return;
        }

        PaymentModel::upsert($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'subscription_id' => (int) ($subscriptionRecord['id'] ?? 0),
            'stripe_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'amount' => $invoiceData['amount_decimal'] ?? ($plan['precio'] ?? null),
            'currency' => $invoiceData['currency'] ?? ($plan['moneda'] ?? 'USD'),
            'status' => 'paid',
            'receipt_url' => $invoiceData['invoice_url'] ?? ($invoiceData['invoice_pdf_url'] ?? null),
        ]);
    }

    private static function syncInvoiceRecordsForUser(PDO $pdo, array $user, array $plan, string $subscriptionId, array $invoiceData): void
    {
        $subscriptionRecord = SubscriptionModel::upsert($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'stripe_customer_id' => $user['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_checkout_session_id' => $user['stripe_checkout_session_id'] ?? null,
            'status' => $user['suscripcion_estado'] ?? 'active',
            'amount' => $invoiceData['amount_decimal'] ?? ($plan['precio'] ?? null),
            'currency' => $invoiceData['currency'] ?? ($plan['moneda'] ?? 'USD'),
            'billing_interval' => $plan['billing_interval'] ?? $plan['periodo'] ?? null,
            'current_period_end' => $user['suscripcion_renueva_at'] ?? null,
        ]);

        PaymentModel::upsert($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'subscription_id' => (int) ($subscriptionRecord['id'] ?? 0),
            'stripe_checkout_session_id' => $invoiceData['session_id'] ?? ($user['stripe_checkout_session_id'] ?? null),
            'stripe_invoice_id' => $invoiceData['invoice_id'] ?? null,
            'amount' => $invoiceData['amount_decimal'] ?? ($plan['precio'] ?? null),
            'currency' => $invoiceData['currency'] ?? ($plan['moneda'] ?? 'USD'),
            'status' => 'paid',
            'receipt_url' => $invoiceData['invoice_url'] ?? ($invoiceData['invoice_pdf_url'] ?? null),
        ]);
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
