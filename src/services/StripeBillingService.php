<?php
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/PlanModel.php';

use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeBillingService
{
    public static function isConfigured(): bool
    {
        return stripeIsConfigured();
    }

    public static function createRegistrationCheckoutSession(int $pendingId, array $plan, string $email): Session
    {
        if ($pendingId <= 0) {
            throw new InvalidArgumentException('No se pudo preparar el checkout de Stripe.');
        }

        $priceId = self::priceIdForPlan($plan);
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new InvalidArgumentException('Debes indicar un correo antes de abrir Stripe.');
        }

        return self::client()->checkout->sessions->create([
            'mode' => 'subscription',
            'success_url' => saasUrl('src/pages/samples/register.php?checkout=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => saasUrl('src/pages/samples/register.php?checkout=cancel&pending=' . $pendingId),
            'billing_address_collection' => 'auto',
            'allow_promotion_codes' => true,
            'client_reference_id' => (string) $pendingId,
            'customer_email' => $email,
            'locale' => 'es-419',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => [
                'metadata' => [
                    'pending_registration_id' => (string) $pendingId,
                    'plan_id' => (string) ((int) ($plan['id_plan'] ?? 0)),
                    'plan_slug' => (string) ($plan['slug'] ?? ''),
                ],
            ],
            'metadata' => [
                'pending_registration_id' => (string) $pendingId,
                'plan_id' => (string) ((int) ($plan['id_plan'] ?? 0)),
                'plan_slug' => (string) ($plan['slug'] ?? ''),
            ],
        ]);
    }

    public static function retrieveCheckoutSession(string $sessionId): Session
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new InvalidArgumentException('No se recibio una sesion de Stripe valida.');
        }

        return self::client()->checkout->sessions->retrieve($sessionId, [
            'expand' => ['customer', 'subscription'],
        ]);
    }

    public static function createCustomerPortalUrl(string $customerId, ?string $returnUrl = null): string
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            throw new InvalidArgumentException('No hay cliente de Stripe para abrir el portal.');
        }

        $session = self::client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl ?: saasUrl('src/index.php'),
        ]);

        return (string) $session->url;
    }

    public static function constructWebhookEvent(string $payload, string $signature): Event
    {
        $config = stripeConfig();
        if ($config['webhook_secret'] === '') {
            throw new RuntimeException('Falta STRIPE_WEBHOOK_SECRET para validar webhooks.');
        }

        return Webhook::constructEvent($payload, $signature, $config['webhook_secret']);
    }

    public static function priceIdForPlan(array $plan): string
    {
        $priceId = stripePriceIdForPlanSlug((string) ($plan['slug'] ?? ''));
        if ($priceId === '') {
            $nombre = (string) ($plan['nombre'] ?? 'este plan');
            throw new RuntimeException('Falta el Price ID de Stripe para ' . $nombre . '.');
        }

        return $priceId;
    }

    public static function subscriptionStatusAllowsAccess(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['active', 'trialing'], true);
    }

    public static function periodEndFromSubscription($subscription): ?string
    {
        if (!is_object($subscription) || empty($subscription->current_period_end)) {
            return null;
        }

        $timestamp = (int) $subscription->current_period_end;
        if ($timestamp <= 0) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    public static function objectToArray($object): array
    {
        if ($object === null) {
            return [];
        }

        $json = json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function client(): StripeClient
    {
        static $client = null;

        if ($client instanceof StripeClient) {
            return $client;
        }

        $config = stripeConfig();
        if ($config['secret_key'] === '') {
            throw new RuntimeException('Configura STRIPE_SECRET_KEY antes de cobrar membresias.');
        }

        $client = new StripeClient([
            'api_key' => $config['secret_key'],
            'stripe_version' => $config['api_version'],
        ]);

        return $client;
    }
}
