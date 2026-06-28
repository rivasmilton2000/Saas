<?php

class PageVisitModel
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS analytics_page_visits (
                id SERIAL PRIMARY KEY,
                id_usuario INTEGER NOT NULL,
                username_snapshot VARCHAR(100) NOT NULL,
                rol_snapshot VARCHAR(20) NOT NULL DEFAULT 'user',
                pais_snapshot VARCHAR(80) NULL,
                route_key VARCHAR(80) NOT NULL,
                route_label VARCHAR(120) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_analytics_page_visit_usuario
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analytics_page_visit_user ON analytics_page_visits (id_usuario)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analytics_page_visit_date ON analytics_page_visits (created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analytics_page_visit_route ON analytics_page_visits (route_key, created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analytics_page_visit_country ON analytics_page_visits (pais_snapshot, created_at)");

        self::$schemaReady = true;
    }

    public static function track(PDO $pdo, array $data): int
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO analytics_page_visits (
                id_usuario,
                username_snapshot,
                rol_snapshot,
                pais_snapshot,
                route_key,
                route_label,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id"
        );
        $stmt->execute([
            (int) ($data['id_usuario'] ?? 0),
            trim((string) ($data['username_snapshot'] ?? 'usuario')) ?: 'usuario',
            trim((string) ($data['rol_snapshot'] ?? 'user')) ?: 'user',
            self::nullableString($data['pais_snapshot'] ?? null),
            trim((string) ($data['route_key'] ?? 'panel')) ?: 'panel',
            trim((string) ($data['route_label'] ?? 'Panel')) ?: 'Panel',
            self::nullableString($data['ip_address'] ?? null),
            self::nullableString($data['user_agent'] ?? null),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function getSummary(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $summary = $pdo->query(
            "SELECT
                COUNT(*) FILTER (WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day') AS page_visits_30d,
                COUNT(DISTINCT CASE
                    WHEN created_at >= CURRENT_TIMESTAMP - INTERVAL '7 day' AND rol_snapshot = 'user'
                    THEN id_usuario
                END) AS active_users_7d,
                COUNT(DISTINCT CASE
                    WHEN created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day' AND rol_snapshot = 'user'
                    THEN COALESCE(NULLIF(pais_snapshot, ''), 'Sin pais')
                END) AS active_countries_30d
             FROM analytics_page_visits"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $frequentUsers = $pdo->query(
            "SELECT COUNT(*)
             FROM (
                SELECT id_usuario
                FROM analytics_page_visits
                WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day'
                  AND rol_snapshot = 'user'
                GROUP BY id_usuario
                HAVING COUNT(*) >= 5
             ) AS frequent_users"
        )->fetchColumn();

        return [
            'page_visits_30d' => (int) ($summary['page_visits_30d'] ?? 0),
            'active_users_7d' => (int) ($summary['active_users_7d'] ?? 0),
            'frequent_users_30d' => (int) $frequentUsers,
            'active_countries_30d' => (int) ($summary['active_countries_30d'] ?? 0),
        ];
    }

    public static function getCountryBreakdown(PDO $pdo, int $limit = 8): array
    {
        self::ensureSchema($pdo);

        $limit = max(1, min($limit, 20));
        $sql = "
            WITH user_counts AS (
                SELECT
                    COALESCE(NULLIF(TRIM(pais), ''), 'Sin pais') AS country,
                    COUNT(*) AS total_users
                FROM usuarios
                WHERE estado = TRUE
                  AND rol = 'user'
                GROUP BY COALESCE(NULLIF(TRIM(pais), ''), 'Sin pais')
            ),
            visit_counts AS (
                SELECT
                    COALESCE(NULLIF(TRIM(pais_snapshot), ''), 'Sin pais') AS country,
                    COUNT(*) AS total_visits,
                    COUNT(DISTINCT id_usuario) AS active_users_30d
                FROM analytics_page_visits
                WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day'
                  AND rol_snapshot = 'user'
                GROUP BY COALESCE(NULLIF(TRIM(pais_snapshot), ''), 'Sin pais')
            )
            SELECT
                COALESCE(user_counts.country, visit_counts.country) AS country,
                COALESCE(user_counts.total_users, 0) AS total_users,
                COALESCE(visit_counts.total_visits, 0) AS total_visits,
                COALESCE(visit_counts.active_users_30d, 0) AS active_users_30d
            FROM user_counts
            FULL OUTER JOIN visit_counts
                ON user_counts.country = visit_counts.country
            ORDER BY total_visits DESC, total_users DESC, country ASC
            LIMIT " . $limit;

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getTopRoutes(PDO $pdo, int $limit = 8): array
    {
        self::ensureSchema($pdo);

        $limit = max(1, min($limit, 20));
        $sql = "
            SELECT
                route_key,
                route_label,
                COUNT(*) AS total_visits,
                COUNT(DISTINCT id_usuario) AS unique_users
            FROM analytics_page_visits
            WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day'
            GROUP BY route_key, route_label
            ORDER BY total_visits DESC, route_label ASC
            LIMIT " . $limit;

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getPlanActivity(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                u.id_plan,
                COUNT(v.id) FILTER (WHERE v.created_at >= CURRENT_TIMESTAMP - INTERVAL '30 day') AS visitas_30d,
                COUNT(DISTINCT CASE
                    WHEN v.created_at >= CURRENT_TIMESTAMP - INTERVAL '7 day'
                    THEN v.id_usuario
                END) AS usuarios_activos_7d
             FROM usuarios u
             LEFT JOIN analytics_page_visits v ON v.id_usuario = u.id
             WHERE u.estado = TRUE
               AND u.rol = 'user'
               AND u.id_plan IS NOT NULL
             GROUP BY u.id_plan"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];

        foreach ($rows as $row) {
            $result[(int) ($row['id_plan'] ?? 0)] = [
                'visitas_30d' => (int) ($row['visitas_30d'] ?? 0),
                'usuarios_activos_7d' => (int) ($row['usuarios_activos_7d'] ?? 0),
            ];
        }

        return $result;
    }

    private static function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
