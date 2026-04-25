<?php

class BitacoraModel {

    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bitacora_movimientos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                username_snapshot VARCHAR(100) NOT NULL,
                rol_snapshot VARCHAR(20) DEFAULT NULL,
                modulo VARCHAR(80) NOT NULL,
                accion VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) NOT NULL,
                entidad_tipo VARCHAR(80) DEFAULT NULL,
                entidad_id INT DEFAULT NULL,
                contexto_json LONGTEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bitacora_usuario (id_usuario),
                INDEX idx_bitacora_fecha (created_at),
                INDEX idx_bitacora_modulo (modulo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    public static function registrar(PDO $pdo, array $data): int {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO bitacora_movimientos (
                id_usuario,
                username_snapshot,
                rol_snapshot,
                modulo,
                accion,
                descripcion,
                entidad_tipo,
                entidad_id,
                contexto_json,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            (int) ($data['id_usuario'] ?? 0),
            trim((string) ($data['username_snapshot'] ?? 'usuario')),
            trim((string) ($data['rol_snapshot'] ?? 'user')) ?: 'user',
            trim((string) ($data['modulo'] ?? 'general')) ?: 'general',
            trim((string) ($data['accion'] ?? 'accion')) ?: 'accion',
            trim((string) ($data['descripcion'] ?? 'Movimiento registrado.')) ?: 'Movimiento registrado.',
            self::nullableString($data['entidad_tipo'] ?? null),
            isset($data['entidad_id']) && (int) $data['entidad_id'] > 0 ? (int) $data['entidad_id'] : null,
            self::nullableString($data['contexto_json'] ?? null),
            self::nullableString($data['ip_address'] ?? null),
            self::nullableString($data['user_agent'] ?? null),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function getMovimientos(PDO $pdo, ?int $idUsuario = null, int $limit = 300): array {
        self::ensureSchema($pdo);

        $limit = max(1, min($limit, 1000));
        $sql = "SELECT
                    b.id,
                    b.id_usuario,
                    b.username_snapshot,
                    b.rol_snapshot,
                    b.modulo,
                    b.accion,
                    b.descripcion,
                    b.entidad_tipo,
                    b.entidad_id,
                    b.contexto_json,
                    b.ip_address,
                    b.user_agent,
                    b.created_at,
                    u.username AS username_actual,
                    u.rol AS rol_actual,
                    u.estado AS usuario_estado
                FROM bitacora_movimientos b
                LEFT JOIN usuarios u ON u.id = b.id_usuario";
        $params = [];

        if ($idUsuario !== null && $idUsuario > 0) {
            $sql .= " WHERE b.id_usuario = ?";
            $params[] = $idUsuario;
        }

        $sql .= " ORDER BY b.created_at DESC, b.id DESC LIMIT " . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getUsuariosConActividad(PDO $pdo): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                b.id_usuario,
                COALESCE(MAX(u.username), MAX(b.username_snapshot)) AS username,
                COALESCE(MAX(u.rol), MAX(b.rol_snapshot)) AS rol,
                MAX(b.created_at) AS ultima_actividad,
                COUNT(*) AS total_movimientos
             FROM bitacora_movimientos b
             LEFT JOIN usuarios u ON u.id = b.id_usuario
             GROUP BY b.id_usuario
             ORDER BY ultima_actividad DESC, username ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function nullableString($value): ?string {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
