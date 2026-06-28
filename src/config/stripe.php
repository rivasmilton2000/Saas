<?php
require_once __DIR__ . '/app.php';

if (!function_exists('stripeApiVersion')) {
    function stripeApiVersion(): string
    {
        return '2026-02-25.clover';
    }
}

if (!function_exists('stripeConfig')) {
    function stripeConfig(): array
    {
        return [
            'secret_key'      => trim((string) (getenv('STRIPE_SECRET_KEY') ?: '')),
            'publishable_key' => trim((string) (getenv('STRIPE_PUBLISHABLE_KEY') ?: '')),
            'webhook_secret'  => trim((string) (getenv('STRIPE_WEBHOOK_SECRET') ?: '')),
            'api_version'     => stripeApiVersion(),
        ];
    }
}

if (!function_exists('stripeIsConfigured')) {
    function stripeIsConfigured(): bool
    {
        $config = stripeConfig();
        return $config['secret_key'] !== '';
    }
}

if (!function_exists('stripePriceIdForPlanSlug')) {
    function stripePriceIdForPlanSlug(string $slug): string
    {
        $slug = strtoupper(trim($slug));
        if ($slug === '') {
            return '';
        }

        return trim((string) (getenv('STRIPE_PRICE_' . $slug) ?: ''));
    }
}

if (!function_exists('stripePlanSupportsCheckout')) {
    function stripePlanSupportsCheckout(array $plan): bool
    {
        $precio = $plan['precio'] ?? null;
        if ($precio === null || (float) $precio <= 0) {
            return false;
        }

        return stripePriceIdForPlanSlug((string) ($plan['slug'] ?? '')) !== '';
    }
}
