<?php
require_once __DIR__ . '/app.php';

if (!function_exists('stripeApiVersion')) {
    function stripeApiVersion(): string
    {
        return '2026-02-25.clover';
    }
}

if (!function_exists('stripeBoolValue')) {
    function stripeBoolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'y', 'yes', 'on'], true);
    }
}

if (!function_exists('stripeConfig')) {
    function stripeConfig(): array
    {
        return [
            'secret_key'      => trim((string) envValue('STRIPE_SECRET_KEY', '')),
            'publishable_key' => trim((string) envValue('STRIPE_PUBLISHABLE_KEY', '')),
            'webhook_secret'  => trim((string) envValue('STRIPE_WEBHOOK_SECRET', '')),
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

if (!function_exists('stripeIsTestMode')) {
    function stripeIsTestMode(): bool
    {
        $config = stripeConfig();
        return strpos($config['secret_key'], 'sk_test_') === 0
            || strpos($config['publishable_key'], 'pk_test_') === 0;
    }
}

if (!function_exists('stripePriceIdForPlanSlug')) {
    function stripePriceIdForPlanSlug(string $slug): string
    {
        $slug = strtoupper(trim($slug));
        if ($slug === '') {
            return '';
        }

        return trim((string) envValue('STRIPE_PRICE_' . $slug, ''));
    }
}

if (!function_exists('stripePriceIdForPlan')) {
    function stripePriceIdForPlan(array $plan): string
    {
        $dbPriceId = trim((string) ($plan['stripe_price_id'] ?? ''));
        if ($dbPriceId !== '') {
            return $dbPriceId;
        }

        return stripePriceIdForPlanSlug((string) ($plan['slug'] ?? ''));
    }
}

if (!function_exists('stripeNormalizeBillingInterval')) {
    function stripeNormalizeBillingInterval($value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'day', 'daily', 'dia', 'dias' => 'day',
            'week', 'weekly', 'semana', 'semanal' => 'week',
            'month', 'monthly', 'mes', 'mensual' => 'month',
            'year', 'yearly', 'annual', 'annually', 'ano', 'anual' => 'year',
            default => null,
        };
    }
}

if (!function_exists('stripePlanIsCustom')) {
    function stripePlanIsCustom(array $plan): bool
    {
        return (($plan['precio'] ?? null) === null)
            || stripeBoolValue($plan['personalizado'] ?? ($plan['is_custom'] ?? false));
    }
}

if (!function_exists('stripePlanIsFree')) {
    function stripePlanIsFree(array $plan): bool
    {
        $price = $plan['precio'] ?? ($plan['price'] ?? null);
        return $price !== null && (float) $price <= 0;
    }
}

if (!function_exists('stripePlanSupportsDynamicCheckout')) {
    function stripePlanSupportsDynamicCheckout(array $plan): bool
    {
        if (!stripeBoolValue($plan['activo'] ?? ($plan['is_active'] ?? true))) {
            return false;
        }

        if (stripePlanIsCustom($plan) || stripePlanIsFree($plan)) {
            return false;
        }

        $price = $plan['precio'] ?? ($plan['price'] ?? null);
        $currency = strtoupper(trim((string) ($plan['moneda'] ?? ($plan['currency'] ?? 'USD'))));
        $billingInterval = $plan['billing_interval'] ?? $plan['periodo'] ?? null;

        if ($price === null || (float) $price <= 0) {
            return false;
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return false;
        }

        return stripeNormalizeBillingInterval($billingInterval) !== null;
    }
}

if (!function_exists('stripePlanSupportsCheckout')) {
    function stripePlanSupportsCheckout(array $plan): bool
    {
        return stripePlanSupportsDynamicCheckout($plan)
            || stripePriceIdForPlan($plan) !== '';
    }
}
