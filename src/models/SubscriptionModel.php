<?php
require_once __DIR__ . '/PlanModel.php';
require_once __DIR__ . '/UsuarioModel.php';

class SubscriptionModel
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        PlanModel::ensureSchema($pdo);
        UsuarioModel::ensureSchema($pdo);

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS subscriptions (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NULL,
                plan_id INTEGER NULL,
                stripe_customer_id VARCHAR(120) NULL,
                stripe_subscription_id VARCHAR(120) NULL,
                stripe_checkout_session_id VARCHAR(120) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'payment_pending',
                amount NUMERIC(10, 2) NULL,
                currency VARCHAR(10) NULL,
                billing_interval VARCHAR(20) NULL,
                current_period_start TIMESTAMP NULL,
                current_period_end TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_subscriptions_user
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_subscriptions_plan
                    FOREIGN KEY (plan_id) REFERENCES planes(id_plan) ON DELETE SET NULL ON UPDATE CASCADE
            )"
        );
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS amount NUMERIC(10, 2) NULL");
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS currency VARCHAR(10) NULL");
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS billing_interval VARCHAR(20) NULL");
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS current_period_start TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS current_period_end TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_stripe_subscription_unique
             ON subscriptions (stripe_subscription_id)
             WHERE stripe_subscription_id IS NOT NULL"
        );
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_checkout_session_unique
             ON subscriptions (stripe_checkout_session_id)
             WHERE stripe_checkout_session_id IS NOT NULL"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subscriptions_user_status ON subscriptions (user_id, status, updated_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subscriptions_plan_status ON subscriptions (plan_id, status, updated_at DESC)");

        self::$schemaReady = true;
    }

    public static function getByStripeSubscriptionId(PDO $pdo, string $stripeSubscriptionId): ?array
    {
        self::ensureSchema($pdo);

        $stripeSubscriptionId = trim($stripeSubscriptionId);
        if ($stripeSubscriptionId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM subscriptions
             WHERE stripe_subscription_id = ?
             LIMIT 1"
        );
        $stmt->execute([$stripeSubscriptionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getByCheckoutSessionId(PDO $pdo, string $checkoutSessionId): ?array
    {
        self::ensureSchema($pdo);

        $checkoutSessionId = trim($checkoutSessionId);
        if ($checkoutSessionId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM subscriptions
             WHERE stripe_checkout_session_id = ?
             LIMIT 1"
        );
        $stmt->execute([$checkoutSessionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function upsert(PDO $pdo, array $data): array
    {
        self::ensureSchema($pdo);

        $stripeSubscriptionId = trim((string) ($data['stripe_subscription_id'] ?? ''));
        $checkoutSessionId = trim((string) ($data['stripe_checkout_session_id'] ?? ''));

        $existing = null;
        if ($stripeSubscriptionId !== '') {
            $existing = self::getByStripeSubscriptionId($pdo, $stripeSubscriptionId);
        }
        if ($existing === null && $checkoutSessionId !== '') {
            $existing = self::getByCheckoutSessionId($pdo, $checkoutSessionId);
        }

        if ($existing !== null) {
            self::update($pdo, (int) ($existing['id'] ?? 0), $data);
            return [
                'id' => (int) ($existing['id'] ?? 0),
                'is_new' => false,
            ];
        }

        return [
            'id' => self::create($pdo, $data),
            'is_new' => true,
        ];
    }

    private static function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO subscriptions (
                user_id,
                plan_id,
                stripe_customer_id,
                stripe_subscription_id,
                stripe_checkout_session_id,
                status,
                amount,
                currency,
                billing_interval,
                current_period_start,
                current_period_end
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id"
        );
        $stmt->execute([
            self::nullableInt($data['user_id'] ?? null),
            self::nullableInt($data['plan_id'] ?? null),
            self::nullableText($data['stripe_customer_id'] ?? null),
            self::nullableText($data['stripe_subscription_id'] ?? null),
            self::nullableText($data['stripe_checkout_session_id'] ?? null),
            self::normalizeStatus($data['status'] ?? 'payment_pending'),
            self::normalizeAmount($data['amount'] ?? null),
            self::nullableText($data['currency'] ?? null),
            self::nullableText($data['billing_interval'] ?? null),
            self::nullableDateTime($data['current_period_start'] ?? null),
            self::nullableDateTime($data['current_period_end'] ?? null),
        ]);

        return (int) $stmt->fetchColumn();
    }

    private static function update(PDO $pdo, int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $fields = ['updated_at = CURRENT_TIMESTAMP'];
        $params = [];

        foreach ([
            'user_id' => 'nullableInt',
            'plan_id' => 'nullableInt',
            'stripe_customer_id' => 'nullableText',
            'stripe_subscription_id' => 'nullableText',
            'stripe_checkout_session_id' => 'nullableText',
            'currency' => 'nullableText',
            'billing_interval' => 'nullableText',
            'current_period_start' => 'nullableDateTime',
            'current_period_end' => 'nullableDateTime',
        ] as $column => $normalizer) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $fields[] = $column . ' = ?';
            $params[] = self::{$normalizer}($data[$column]);
        }

        if (array_key_exists('status', $data)) {
            $fields[] = 'status = ?';
            $params[] = self::normalizeStatus($data['status']);
        }

        if (array_key_exists('amount', $data)) {
            $fields[] = 'amount = ?';
            $params[] = self::normalizeAmount($data['amount']);
        }

        $params[] = $id;

        $stmt = $pdo->prepare(
            "UPDATE subscriptions
             SET " . implode(', ', $fields) . "
             WHERE id = ?"
        );

        return $stmt->execute($params);
    }

    private static function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private static function nullableInt($value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private static function nullableDateTime($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private static function normalizeAmount($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function normalizeStatus($value): string
    {
        $value = strtolower(trim((string) $value));
        return $value !== '' ? $value : 'payment_pending';
    }
}
