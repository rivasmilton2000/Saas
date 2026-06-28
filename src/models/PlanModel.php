<?php
require_once __DIR__ . '/../config/plan_catalog.php';

class PlanModel
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS planes (
                id_plan SERIAL PRIMARY KEY,
                slug VARCHAR(40) NOT NULL UNIQUE,
                nombre VARCHAR(80) NOT NULL,
                descripcion TEXT NULL,
                precio NUMERIC(10, 2) NULL,
                moneda VARCHAR(10) NOT NULL DEFAULT 'USD',
                periodo VARCHAR(20) NOT NULL DEFAULT 'mes',
                destacado BOOLEAN NOT NULL DEFAULT FALSE,
                personalizado BOOLEAN NOT NULL DEFAULT FALSE,
                activo BOOLEAN NOT NULL DEFAULT TRUE,
                orden INTEGER NOT NULL DEFAULT 0,
                limite_empresas INTEGER NULL,
                limite_usuarios INTEGER NULL,
                limite_documentos INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS planes_caracteristicas (
                id SERIAL PRIMARY KEY,
                id_plan INTEGER NOT NULL,
                caracteristica VARCHAR(255) NOT NULL,
                incluido BOOLEAN NOT NULL DEFAULT TRUE,
                orden INTEGER NOT NULL DEFAULT 0,
                CONSTRAINT fk_planes_caracteristicas_plan
                    FOREIGN KEY (id_plan) REFERENCES planes(id_plan) ON DELETE CASCADE ON UPDATE CASCADE
            )"
        );
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_planes_activo_orden ON planes (activo, orden)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_planes_caracteristicas_plan ON planes_caracteristicas (id_plan, orden)");

        self::syncCatalog($pdo);
        self::$schemaReady = true;
    }

    public static function getActivePlansWithFeatures(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                id_plan,
                slug,
                nombre,
                descripcion,
                precio,
                moneda,
                periodo,
                destacado,
                personalizado,
                activo,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE activo = TRUE
             ORDER BY orden ASC, id_plan ASC"
        );

        $planes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmtFeatures = $pdo->prepare(
            "SELECT caracteristica, incluido
             FROM planes_caracteristicas
             WHERE id_plan = ?
             ORDER BY orden ASC, id ASC"
        );

        foreach ($planes as &$plan) {
            $stmtFeatures->execute([(int) ($plan['id_plan'] ?? 0)]);
            $features = $stmtFeatures->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $plan['caracteristicas'] = array_map(
                static fn(array $feature): array => [
                    'caracteristica' => (string) ($feature['caracteristica'] ?? ''),
                    'incluido' => dbBoolValue($feature['incluido'] ?? true),
                ],
                $features
            );
        }
        unset($plan);

        return $planes;
    }

    public static function getSelectablePlans(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->query(
            "SELECT
                id_plan,
                slug,
                nombre,
                precio,
                periodo,
                destacado,
                personalizado,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE activo = TRUE
             ORDER BY orden ASC, id_plan ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getById(PDO $pdo, int $idPlan): ?array
    {
        self::ensureSchema($pdo);

        if ($idPlan <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT
                id_plan,
                slug,
                nombre,
                descripcion,
                precio,
                moneda,
                periodo,
                destacado,
                personalizado,
                activo,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE id_plan = ?
             LIMIT 1"
        );
        $stmt->execute([$idPlan]);

        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plan ?: null;
    }

    public static function getBySlug(PDO $pdo, string $slug): ?array
    {
        self::ensureSchema($pdo);

        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT
                id_plan,
                slug,
                nombre,
                descripcion,
                precio,
                moneda,
                periodo,
                destacado,
                personalizado,
                activo,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE slug = ?
             LIMIT 1"
        );
        $stmt->execute([$slug]);

        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plan ?: null;
    }

    public static function formatPriceLabel(array $plan): string
    {
        $precio = $plan['precio'] ?? null;
        if ($precio === null || $precio === '') {
            return 'Personalizado';
        }

        $periodo = trim((string) ($plan['periodo'] ?? 'mes'));
        $texto = '$' . number_format((float) $precio, 2);

        if ($periodo === '' || strtolower($periodo) === 'cotizacion') {
            return $texto;
        }

        return $texto . ' / ' . $periodo;
    }

    private static function syncCatalog(PDO $pdo): void
    {
        $insertPlan = $pdo->prepare(
            "INSERT INTO planes (
                slug,
                nombre,
                descripcion,
                precio,
                moneda,
                periodo,
                destacado,
                personalizado,
                activo,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (slug) DO UPDATE SET
                nombre = EXCLUDED.nombre,
                descripcion = EXCLUDED.descripcion,
                precio = EXCLUDED.precio,
                moneda = EXCLUDED.moneda,
                periodo = EXCLUDED.periodo,
                destacado = EXCLUDED.destacado,
                personalizado = EXCLUDED.personalizado,
                activo = EXCLUDED.activo,
                orden = EXCLUDED.orden,
                limite_empresas = EXCLUDED.limite_empresas,
                limite_usuarios = EXCLUDED.limite_usuarios,
                limite_documentos = EXCLUDED.limite_documentos
            RETURNING id_plan"
        );
        $deleteFeatures = $pdo->prepare("DELETE FROM planes_caracteristicas WHERE id_plan = ?");
        $insertFeature = $pdo->prepare(
            "INSERT INTO planes_caracteristicas (id_plan, caracteristica, incluido, orden)
             VALUES (?, ?, ?, ?)"
        );

        foreach (getPlanCatalog() as $plan) {
            $insertPlan->execute([
                (string) ($plan['slug'] ?? ''),
                (string) ($plan['nombre'] ?? ''),
                (string) ($plan['descripcion'] ?? ''),
                $plan['precio'],
                (string) ($plan['moneda'] ?? 'USD'),
                (string) ($plan['periodo'] ?? 'mes'),
                dbBoolParam($plan['destacado'] ?? false),
                dbBoolParam($plan['personalizado'] ?? false),
                dbBoolParam($plan['activo'] ?? true),
                (int) ($plan['orden'] ?? 0),
                isset($plan['limite_empresas']) ? (int) $plan['limite_empresas'] : null,
                isset($plan['limite_usuarios']) ? (int) $plan['limite_usuarios'] : null,
                isset($plan['limite_documentos']) ? (int) $plan['limite_documentos'] : null,
            ]);

            $idPlan = (int) $insertPlan->fetchColumn();
            $deleteFeatures->execute([$idPlan]);

            foreach (($plan['caracteristicas'] ?? []) as $index => $feature) {
                $insertFeature->execute([
                    $idPlan,
                    trim((string) ($feature['texto'] ?? '')),
                    dbBoolParam($feature['incluido'] ?? true),
                    $index + 1,
                ]);
            }
        }
    }
}
