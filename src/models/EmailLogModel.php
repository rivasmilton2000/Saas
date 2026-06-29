<?php

class EmailLogModel
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS email_logs (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NULL,
                email VARCHAR(190) NOT NULL,
                type VARCHAR(80) NOT NULL,
                reference_key VARCHAR(191) NULL,
                status VARCHAR(20) NOT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec("ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS reference_key VARCHAR(191) NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_user_type ON email_logs (user_id, type, created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_email_type ON email_logs ((LOWER(email)), type, created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_user_type_ref ON email_logs (user_id, type, reference_key, created_at DESC)");
        $pdo->exec(
            "DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'fk_email_logs_user'
                ) THEN
                    ALTER TABLE email_logs
                    ADD CONSTRAINT fk_email_logs_user
                    FOREIGN KEY (user_id) REFERENCES usuarios(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                END IF;
            END
            $$;"
        );

        self::$schemaReady = true;
    }

    public static function create(PDO $pdo, array $data): int
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO email_logs (user_id, email, type, reference_key, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?)
             RETURNING id"
        );
        $stmt->execute([
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            strtolower(trim((string) ($data['email'] ?? ''))),
            trim((string) ($data['type'] ?? 'general')),
            self::nullableText($data['reference_key'] ?? null),
            trim((string) ($data['status'] ?? 'queued')),
            self::nullableText($data['error_message'] ?? null),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function hasSuccessfulTypeForUser(PDO $pdo, int $userId, string $type): bool
    {
        return self::hasSuccessfulDelivery($pdo, $userId, $type, null);
    }

    public static function hasSuccessfulDelivery(PDO $pdo, int $userId, string $type, ?string $referenceKey = null): bool
    {
        self::ensureSchema($pdo);

        if ($userId <= 0 || trim($type) === '') {
            return false;
        }

        if ($referenceKey !== null && trim($referenceKey) !== '') {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM email_logs
                 WHERE user_id = ?
                   AND type = ?
                   AND reference_key = ?
                   AND status = 'sent'
                 LIMIT 1"
            );
            $stmt->execute([$userId, trim($type), trim($referenceKey)]);
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $pdo->prepare(
            "SELECT 1
             FROM email_logs
             WHERE user_id = ?
               AND type = ?
               AND status = 'sent'
             LIMIT 1"
        );
        $stmt->execute([$userId, trim($type)]);

        return $stmt->fetchColumn() !== false;
    }

    private static function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
