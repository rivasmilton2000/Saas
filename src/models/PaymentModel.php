<?php
require_once __DIR__ . '/PlanModel.php';
require_once __DIR__ . '/SubscriptionModel.php';
require_once __DIR__ . '/UsuarioModel.php';

class PaymentModel
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        PlanModel::ensureSchema($pdo);
        SubscriptionModel::ensureSchema($pdo);
        UsuarioModel::ensureSchema($pdo);

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payments (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NULL,
                plan_id INTEGER NULL,
                subscription_id INTEGER NULL,
                stripe_checkout_session_id VARCHAR(120) NULL,
                stripe_invoice_id VARCHAR(120) NULL,
                amount NUMERIC(10, 2) NULL,
                currency VARCHAR(10) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                receipt_url TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_payments_user
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_payments_plan
                    FOREIGN KEY (plan_id) REFERENCES planes(id_plan) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_payments_subscription
                    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL ON UPDATE CASCADE
            )"
        );
        $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_payments_invoice_unique
             ON payments (stripe_invoice_id)
             WHERE stripe_invoice_id IS NOT NULL"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_checkout_session ON payments (stripe_checkout_session_id, created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_user_status ON payments (user_id, status, created_at DESC)");

        self::$schemaReady = true;
    }

    public static function getByStripeInvoiceId(PDO $pdo, string $invoiceId): ?array
    {
        self::ensureSchema($pdo);

        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM payments
             WHERE stripe_invoice_id = ?
             LIMIT 1"
        );
        $stmt->execute([$invoiceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findCheckoutPayment(PDO $pdo, string $checkoutSessionId): ?array
    {
        self::ensureSchema($pdo);

        $checkoutSessionId = trim($checkoutSessionId);
        if ($checkoutSessionId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM payments
             WHERE stripe_checkout_session_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$checkoutSessionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function upsert(PDO $pdo, array $data): array
    {
        self::ensureSchema($pdo);

        $invoiceId = trim((string) ($data['stripe_invoice_id'] ?? ''));
        $checkoutSessionId = trim((string) ($data['stripe_checkout_session_id'] ?? ''));

        $existing = null;
        if ($invoiceId !== '') {
            $existing = self::getByStripeInvoiceId($pdo, $invoiceId);
        }
        if ($existing === null && $invoiceId === '' && $checkoutSessionId !== '') {
            $existing = self::findCheckoutPayment($pdo, $checkoutSessionId);
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
            "INSERT INTO payments (
                user_id,
                plan_id,
                subscription_id,
                stripe_checkout_session_id,
                stripe_invoice_id,
                amount,
                currency,
                status,
                receipt_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id"
        );
        $stmt->execute([
            self::nullableInt($data['user_id'] ?? null),
            self::nullableInt($data['plan_id'] ?? null),
            self::nullableInt($data['subscription_id'] ?? null),
            self::nullableText($data['stripe_checkout_session_id'] ?? null),
            self::nullableText($data['stripe_invoice_id'] ?? null),
            self::normalizeAmount($data['amount'] ?? null),
            self::nullableText($data['currency'] ?? null),
            self::normalizeStatus($data['status'] ?? 'pending'),
            self::nullableText($data['receipt_url'] ?? null),
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
            'subscription_id' => 'nullableInt',
            'stripe_checkout_session_id' => 'nullableText',
            'stripe_invoice_id' => 'nullableText',
            'currency' => 'nullableText',
            'receipt_url' => 'nullableText',
        ] as $column => $normalizer) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $fields[] = $column . ' = ?';
            $params[] = self::{$normalizer}($data[$column]);
        }

        if (array_key_exists('amount', $data)) {
            $fields[] = 'amount = ?';
            $params[] = self::normalizeAmount($data['amount']);
        }

        if (array_key_exists('status', $data)) {
            $fields[] = 'status = ?';
            $params[] = self::normalizeStatus($data['status']);
        }

        $params[] = $id;

        $stmt = $pdo->prepare(
            "UPDATE payments
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
        return $value !== '' ? $value : 'pending';
    }
}
