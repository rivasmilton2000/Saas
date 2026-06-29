<?php
require_once __DIR__ . '/../models/PlanModel.php';
require_once __DIR__ . '/../models/SubscriptionModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/StripeBillingService.php';

class UserPlanSelectionService
{
    public static function canAccessSelection(array $user): bool
    {
        if ((string) ($user['rol'] ?? 'user') !== 'user') {
            return false;
        }

        if ((int) ($user['id_plan'] ?? 0) <= 0) {
            return true;
        }

        $price = $user['plan_precio'] ?? null;
        if ($price === null || $price === '') {
            return false;
        }

        return (float) $price > 0 && !StripeBillingService::subscriptionStatusAllowsAccess($user['suscripcion_estado'] ?? null);
    }

    public static function start(PDO $pdo, array $user, int $planId): array
    {
        if ((int) ($user['id'] ?? 0) <= 0 || (string) ($user['rol'] ?? 'user') !== 'user') {
            return [
                'ok' => false,
                'message' => 'No encontramos una cuenta valida para seleccionar la membresia.',
                'flash_type' => 'danger',
            ];
        }

        $plan = PlanModel::getById($pdo, $planId);
        if ($plan === null || !dbBoolValue($plan['activo'] ?? true)) {
            return [
                'ok' => false,
                'message' => 'Selecciona una membresia valida para continuar.',
                'flash_type' => 'danger',
            ];
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
            UsuarioModel::updateSubscriptionData($pdo, (int) $user['id'], [
                'id_plan' => (int) ($plan['id_plan'] ?? 0),
                'suscripcion_estado' => 'free',
                'stripe_checkout_session_id' => null,
            ]);

            $freshUser = UsuarioModel::getById($pdo, (int) $user['id'], false);
            if ($freshUser !== null) {
                MailService::sendWelcomeFree($pdo, $freshUser, $plan);
            }

            return [
                'ok' => true,
                'mode' => 'free',
                'user' => $freshUser ?: $user,
                'plan_nombre' => (string) ($plan['nombre'] ?? 'Free'),
            ];
        }

        if (!StripeBillingService::isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Stripe aun no esta configurado en este entorno. Agrega tus claves para cobrar las membresias pagas.',
                'flash_type' => 'danger',
            ];
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false,
                'message' => 'Tu cuenta necesita un correo valido antes de continuar con Stripe.',
                'flash_type' => 'danger',
            ];
        }

        $session = StripeBillingService::createUserPlanCheckoutSession((int) $user['id'], $plan, $email);
        UsuarioModel::updateSubscriptionData($pdo, (int) $user['id'], [
            'id_plan' => (int) ($plan['id_plan'] ?? 0),
            'suscripcion_estado' => 'payment_pending',
            'stripe_checkout_session_id' => (string) ($session->id ?? ''),
        ]);
        SubscriptionModel::upsert($pdo, [
            'user_id' => (int) ($user['id'] ?? 0),
            'plan_id' => (int) ($plan['id_plan'] ?? 0),
            'stripe_checkout_session_id' => (string) ($session->id ?? ''),
            'status' => 'payment_pending',
            'amount' => $plan['precio'] ?? null,
            'currency' => $plan['moneda'] ?? 'USD',
            'billing_interval' => $plan['billing_interval'] ?? $plan['periodo'] ?? null,
        ]);

        return [
            'ok' => true,
            'mode' => 'checkout',
            'checkout_url' => (string) ($session->url ?? ''),
            'plan_nombre' => (string) ($plan['nombre'] ?? ''),
        ];
    }
}
