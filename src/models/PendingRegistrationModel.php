<?php

class PendingRegistrationModel
{
    private static bool $schemaReady = false;
    private const OPEN_STATES = ['draft', 'checkout_created', 'processing'];

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS registros_pago_pendientes (
                id SERIAL PRIMARY KEY,
                nombre_completo VARCHAR(150) NOT NULL,
                email VARCHAR(160) NOT NULL,
                username VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NULL,
                pais VARCHAR(80) NOT NULL,
                id_plan INTEGER NOT NULL,
                plan_slug_snapshot VARCHAR(40) NOT NULL,
                plan_nombre_snapshot VARCHAR(80) NOT NULL,
                plan_precio_snapshot NUMERIC(10, 2) NULL,
                auth_provider VARCHAR(30) NULL,
                google_id VARCHAR(191) NULL,
                foto_perfil VARCHAR(255) NULL,
                email_verificado_at TIMESTAMP NULL,
                stripe_checkout_session_id VARCHAR(120) NULL,
                stripe_customer_id VARCHAR(120) NULL,
                stripe_subscription_id VARCHAR(120) NULL,
                stripe_invoice_id VARCHAR(120) NULL,
                stripe_invoice_url TEXT NULL,
                stripe_invoice_pdf_url TEXT NULL,
                stripe_status VARCHAR(40) NULL,
                estado VARCHAR(30) NOT NULL DEFAULT 'draft',
                stripe_payload_json JSONB NULL,
                created_user_id INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                CONSTRAINT fk_registros_pago_pendientes_plan
                    FOREIGN KEY (id_plan) REFERENCES planes(id_plan) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_registros_pago_pendientes_usuario
                    FOREIGN KEY (created_user_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
            )"
        );
        $pdo->exec("ALTER TABLE registros_pago_pendientes ALTER COLUMN password_hash DROP NOT NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(30) NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS google_id VARCHAR(191) NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS email_verificado_at TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(120) NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS stripe_invoice_url TEXT NULL");
        $pdo->exec("ALTER TABLE registros_pago_pendientes ADD COLUMN IF NOT EXISTS stripe_invoice_pdf_url TEXT NULL");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registros_pago_estado ON registros_pago_pendientes (estado, created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registros_pago_email ON registros_pago_pendientes ((LOWER(email)))");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registros_pago_username ON registros_pago_pendientes ((LOWER(username)))");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registros_pago_google_id ON registros_pago_pendientes (google_id)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_registros_pago_checkout_unique ON registros_pago_pendientes (stripe_checkout_session_id) WHERE stripe_checkout_session_id IS NOT NULL");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_registros_pago_subscription_unique ON registros_pago_pendientes (stripe_subscription_id) WHERE stripe_subscription_id IS NOT NULL");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_registros_pago_google_id_unique ON registros_pago_pendientes (google_id) WHERE google_id IS NOT NULL");

        self::$schemaReady = true;
    }

    public static function create(PDO $pdo, array $data): int
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO registros_pago_pendientes (
                nombre_completo,
                email,
                username,
                password_hash,
                pais,
                id_plan,
                plan_slug_snapshot,
                plan_nombre_snapshot,
                plan_precio_snapshot,
                auth_provider,
                google_id,
                foto_perfil,
                email_verificado_at,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id"
        );

        $stmt->execute([
            trim((string) ($data['nombre_completo'] ?? '')),
            strtolower(trim((string) ($data['email'] ?? ''))),
            trim((string) ($data['username'] ?? '')),
            self::nullableText($data['password_hash'] ?? null),
            trim((string) ($data['pais'] ?? 'El Salvador')),
            (int) ($data['id_plan'] ?? 0),
            trim((string) ($data['plan_slug_snapshot'] ?? '')),
            trim((string) ($data['plan_nombre_snapshot'] ?? '')),
            $data['plan_precio_snapshot'] ?? null,
            self::nullableText($data['auth_provider'] ?? null),
            self::nullableText($data['google_id'] ?? null),
            self::nullableText($data['foto_perfil'] ?? null),
            self::nullableDateTime($data['email_verificado_at'] ?? null),
            trim((string) ($data['estado'] ?? 'draft')) ?: 'draft',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        self::ensureSchema($pdo);

        if ($id <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM registros_pago_pendientes
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getByCheckoutSessionId(PDO $pdo, string $sessionId): ?array
    {
        self::ensureSchema($pdo);

        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM registros_pago_pendientes
             WHERE stripe_checkout_session_id = ?
             LIMIT 1"
        );
        $stmt->execute([$sessionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getByStripeSubscriptionId(PDO $pdo, string $subscriptionId): ?array
    {
        self::ensureSchema($pdo);

        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM registros_pago_pendientes
             WHERE stripe_subscription_id = ?
             LIMIT 1"
        );
        $stmt->execute([$subscriptionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function emailExistsOpen(PDO $pdo, string $email): bool
    {
        self::ensureSchema($pdo);

        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT 1
             FROM registros_pago_pendientes
             WHERE LOWER(email) = LOWER(?)
               AND estado IN ('draft', 'checkout_created', 'processing')
             LIMIT 1"
        );
        $stmt->execute([$email]);

        return $stmt->fetchColumn() !== false;
    }

    public static function usernameExistsOpen(PDO $pdo, string $username): bool
    {
        self::ensureSchema($pdo);

        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT 1
             FROM registros_pago_pendientes
             WHERE LOWER(username) = LOWER(?)
               AND estado IN ('draft', 'checkout_created', 'processing')
             LIMIT 1"
        );
        $stmt->execute([$username]);

        return $stmt->fetchColumn() !== false;
    }

    public static function updateStripeData(PDO $pdo, int $id, array $data): bool
    {
        self::ensureSchema($pdo);

        if ($id <= 0) {
            return false;
        }

        $fields = ['updated_at = CURRENT_TIMESTAMP'];
        $params = [];

        foreach ([
            'stripe_checkout_session_id',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_invoice_id',
            'stripe_invoice_url',
            'stripe_invoice_pdf_url',
            'stripe_status',
            'estado',
            'created_user_id',
        ] as $column) {
            if (array_key_exists($column, $data)) {
                $fields[] = $column . ' = ?';
                $params[] = $data[$column];
            }
        }

        if (array_key_exists('stripe_payload_json', $data)) {
            $fields[] = 'stripe_payload_json = ?';
            $params[] = self::jsonValue($data['stripe_payload_json']);
        }

        if (!empty($data['completed_at'])) {
            $fields[] = 'completed_at = ?';
            $params[] = $data['completed_at'];
        }

        $params[] = $id;

        $stmt = $pdo->prepare(
            "UPDATE registros_pago_pendientes
             SET " . implode(', ', $fields) . "
             WHERE id = ?"
        );

        return $stmt->execute($params);
    }

    public static function markCompleted(PDO $pdo, int $id, int $createdUserId, array $stripeData = []): bool
    {
        return self::updateStripeData($pdo, $id, array_merge($stripeData, [
            'estado' => 'completed',
            'created_user_id' => $createdUserId,
            'completed_at' => date('Y-m-d H:i:s'),
        ]));
    }

    private static function jsonValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' ? $trimmed : null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private static function nullableDateTime($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
