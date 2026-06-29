<?php
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/PlanModel.php';

use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Subscription;
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

        return self::createSubscriptionCheckoutSession($plan, self::normalizeEmailForCheckout($email), [
            'cancel_url' => saasUrl('src/payments/payment_cancel.php?pending=' . $pendingId),
            'client_reference_id' => (string) $pendingId,
            'metadata' => [
                'flow_type' => 'pending_registration',
                'pending_registration_id' => (string) $pendingId,
                'plan_id' => (string) ((int) ($plan['id_plan'] ?? 0)),
                'plan_slug' => (string) ($plan['slug'] ?? ''),
                'plan_name' => (string) ($plan['nombre'] ?? ''),
                'source' => 'zentra_register',
            ],
        ]);
    }

    public static function createUserPlanCheckoutSession(int $userId, array $plan, string $email): Session
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('No se encontro la cuenta para abrir Stripe.');
        }

        return self::createSubscriptionCheckoutSession($plan, self::normalizeEmailForCheckout($email), [
            'cancel_url' => saasUrl('src/payments/payment_cancel.php?user_id=' . $userId),
            'client_reference_id' => (string) $userId,
            'metadata' => [
                'flow_type' => 'existing_user',
                'user_id' => (string) $userId,
                'plan_id' => (string) ((int) ($plan['id_plan'] ?? 0)),
                'plan_slug' => (string) ($plan['slug'] ?? ''),
                'plan_name' => (string) ($plan['nombre'] ?? ''),
                'source' => 'zentra_select_plan',
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
            'expand' => [
                'customer',
                'subscription',
                'subscription.latest_invoice',
            ],
        ]);
    }

    public static function retrieveSubscription(string $subscriptionId): Subscription
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new InvalidArgumentException('No se recibio una suscripcion valida de Stripe.');
        }

        return self::client()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['latest_invoice'],
        ]);
    }

    public static function retrieveInvoice(string $invoiceId): Invoice
    {
        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            throw new InvalidArgumentException('No se recibio una factura valida de Stripe.');
        }

        return self::client()->invoices->retrieve($invoiceId, []);
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
        $priceId = stripePriceIdForPlan($plan);
        if ($priceId === '') {
            $nombre = (string) ($plan['nombre'] ?? 'este plan');
            throw new RuntimeException('Falta un precio reutilizable de Stripe o un plan valido para ' . $nombre . '.');
        }

        return $priceId;
    }

    public static function subscriptionStatusAllowsAccess(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['active', 'trialing'], true);
    }

    public static function currentPeriodStartFromSubscription($subscription): ?string
    {
        return self::timestampFieldToDateTime(is_object($subscription) ? ($subscription->current_period_start ?? null) : null);
    }

    public static function periodEndFromSubscription($subscription): ?string
    {
        return self::timestampFieldToDateTime(is_object($subscription) ? ($subscription->current_period_end ?? null) : null);
    }

    public static function extractInvoiceDataFromSession($session): array
    {
        $subscription = self::subscriptionObjectFromSession($session);
        if ($subscription === null) {
            return [];
        }

        $invoice = $subscription->latest_invoice ?? null;
        return self::extractInvoiceDataFromInvoice($invoice, $session);
    }

    public static function extractInvoiceDataFromInvoice($invoice, $source = null): array
    {
        if (!is_object($invoice)) {
            return [];
        }

        $amountPaid = (int) ($invoice->amount_paid ?? 0);
        $currency = strtoupper((string) ($invoice->currency ?? 'USD'));
        $createdAt = (int) ($invoice->created ?? 0);

        return [
            'invoice_id' => trim((string) ($invoice->id ?? '')),
            'invoice_url' => trim((string) ($invoice->hosted_invoice_url ?? '')),
            'invoice_pdf_url' => trim((string) ($invoice->invoice_pdf ?? '')),
            'amount_paid' => $amountPaid,
            'amount_decimal' => self::amountDecimalFromMinor($amountPaid),
            'amount_label' => self::amountToLabel($amountPaid, $currency),
            'currency' => $currency,
            'reference' => trim((string) ($invoice->number ?? $invoice->id ?? ''))
                ?: trim((string) (is_object($source) ? ($source->id ?? '') : '')),
            'date_label' => self::formatTimestamp($createdAt),
            'session_id' => trim((string) (is_object($source) ? ($source->id ?? '') : '')),
        ];
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

    public static function sessionCustomerId($session): ?string
    {
        $customerId = trim((string) (is_object($session->customer ?? null) ? (($session->customer)->id ?? '') : ($session->customer ?? '')));
        return $customerId !== '' ? $customerId : null;
    }

    public static function sessionSubscriptionId($session): ?string
    {
        $subscription = self::subscriptionObjectFromSession($session);
        if ($subscription === null) {
            return null;
        }

        $subscriptionId = trim((string) ($subscription->id ?? ''));
        return $subscriptionId !== '' ? $subscriptionId : null;
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

    private static function createSubscriptionCheckoutSession(array $plan, string $email, array $options): Session
    {
        return self::client()->checkout->sessions->create([
            'mode' => 'subscription',
            'success_url' => saasUrl('src/payments/payment_success.php?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => (string) ($options['cancel_url'] ?? saasUrl('src/payments/payment_cancel.php')),
            'billing_address_collection' => 'auto',
            'allow_promotion_codes' => true,
            'client_reference_id' => (string) ($options['client_reference_id'] ?? ''),
            'customer_email' => $email,
            'locale' => 'es-419',
            'line_items' => [
                self::buildLineItemForPlan($plan),
            ],
            'subscription_data' => [
                'metadata' => (array) ($options['metadata'] ?? []),
            ],
            'metadata' => (array) ($options['metadata'] ?? []),
        ]);
    }

    private static function buildLineItemForPlan(array $plan): array
    {
        if (stripePlanSupportsDynamicCheckout($plan)) {
            $unitAmount = self::amountToMinor($plan['precio'] ?? $plan['price'] ?? null);
            $currency = strtolower(trim((string) ($plan['moneda'] ?? ($plan['currency'] ?? 'USD'))));
            $interval = stripeNormalizeBillingInterval($plan['billing_interval'] ?? ($plan['periodo'] ?? null));

            if ($unitAmount <= 0 || $interval === null) {
                $nombre = (string) ($plan['nombre'] ?? 'este plan');
                throw new RuntimeException('El plan ' . $nombre . ' no tiene una configuracion valida para Stripe Checkout.');
            }

            return [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Zentra ' . trim((string) ($plan['nombre'] ?? 'Plan')),
                        'description' => trim((string) ($plan['descripcion'] ?? '')),
                    ],
                    'unit_amount' => $unitAmount,
                    'recurring' => [
                        'interval' => $interval,
                    ],
                ],
                'quantity' => 1,
            ];
        }

        return [
            'price' => self::priceIdForPlan($plan),
            'quantity' => 1,
        ];
    }

    private static function normalizeEmailForCheckout(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new InvalidArgumentException('Debes indicar un correo antes de abrir Stripe.');
        }

        return $email;
    }

    private static function subscriptionObjectFromSession($session): ?object
    {
        if (!is_object($session)) {
            return null;
        }

        $subscription = $session->subscription ?? null;
        return is_object($subscription) ? $subscription : null;
    }

    private static function amountToMinor($amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    private static function amountDecimalFromMinor(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }

    private static function amountToLabel(int $amountMinor, string $currency): string
    {
        return '$' . number_format($amountMinor / 100, 2) . ' ' . $currency;
    }

    private static function formatTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return gmdate('d/m/Y H:i', $timestamp) . ' UTC';
    }

    private static function timestampFieldToDateTime($value): ?string
    {
        $timestamp = (int) $value;
        if ($timestamp <= 0) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
