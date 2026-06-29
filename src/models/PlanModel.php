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
                billing_interval VARCHAR(20) NULL,
                destacado BOOLEAN NOT NULL DEFAULT FALSE,
                is_free BOOLEAN NOT NULL DEFAULT FALSE,
                personalizado BOOLEAN NOT NULL DEFAULT FALSE,
                activo BOOLEAN NOT NULL DEFAULT TRUE,
                trial_days INTEGER NULL,
                stripe_price_id VARCHAR(120) NULL,
                orden INTEGER NOT NULL DEFAULT 0,
                limite_empresas INTEGER NULL,
                limite_usuarios INTEGER NULL,
                limite_documentos INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec("ALTER TABLE planes ADD COLUMN IF NOT EXISTS billing_interval VARCHAR(20) NULL");
        $pdo->exec("ALTER TABLE planes ADD COLUMN IF NOT EXISTS is_free BOOLEAN NOT NULL DEFAULT FALSE");
        $pdo->exec("ALTER TABLE planes ADD COLUMN IF NOT EXISTS trial_days INTEGER NULL");
        $pdo->exec("ALTER TABLE planes ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120) NULL");
        $pdo->exec("ALTER TABLE planes ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
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
                billing_interval,
                destacado,
                is_free,
                personalizado,
                activo,
                trial_days,
                stripe_price_id,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE activo = TRUE
             ORDER BY orden ASC, id_plan ASC"
        );

        $planes = array_map([self::class, 'decoratePlanRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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
                descripcion,
                precio,
                moneda,
                periodo,
                billing_interval,
                destacado,
                is_free,
                personalizado,
                activo,
                trial_days,
                stripe_price_id,
                limite_empresas,
                limite_usuarios,
                limite_documentos
             FROM planes
             WHERE activo = TRUE
             ORDER BY orden ASC, id_plan ASC"
        );

        return array_map([self::class, 'decoratePlanRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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
                billing_interval,
                destacado,
                is_free,
                personalizado,
                activo,
                trial_days,
                stripe_price_id,
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
        return $plan ? self::decoratePlanRow($plan) : null;
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
                billing_interval,
                destacado,
                is_free,
                personalizado,
                activo,
                trial_days,
                stripe_price_id,
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
        return $plan ? self::decoratePlanRow($plan) : null;
    }

    public static function formatPriceLabel(array $plan): string
    {
        $precio = $plan['precio'] ?? null;
        if ($precio === null || $precio === '') {
            return 'Personalizado';
        }

        if ((float) $precio <= 0) {
            return 'Gratis';
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
                billing_interval,
                destacado,
                is_free,
                personalizado,
                activo,
                trial_days,
                orden,
                limite_empresas,
                limite_usuarios,
                limite_documentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (slug) DO UPDATE SET
                nombre = EXCLUDED.nombre,
                descripcion = EXCLUDED.descripcion,
                precio = EXCLUDED.precio,
                moneda = EXCLUDED.moneda,
                periodo = EXCLUDED.periodo,
                billing_interval = EXCLUDED.billing_interval,
                destacado = EXCLUDED.destacado,
                is_free = EXCLUDED.is_free,
                personalizado = EXCLUDED.personalizado,
                activo = EXCLUDED.activo,
                trial_days = EXCLUDED.trial_days,
                orden = EXCLUDED.orden,
                limite_empresas = EXCLUDED.limite_empresas,
                limite_usuarios = EXCLUDED.limite_usuarios,
                limite_documentos = EXCLUDED.limite_documentos,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id_plan"
        );
        $deleteFeatures = $pdo->prepare("DELETE FROM planes_caracteristicas WHERE id_plan = ?");
        $insertFeature = $pdo->prepare(
            "INSERT INTO planes_caracteristicas (id_plan, caracteristica, incluido, orden)
             VALUES (?, ?, ?, ?)"
        );

        foreach (getPlanCatalog() as $plan) {
            $catalogPlan = self::normalizeCatalogPlan($plan);

            $insertPlan->execute([
                (string) ($catalogPlan['slug'] ?? ''),
                (string) ($catalogPlan['nombre'] ?? ''),
                (string) ($catalogPlan['descripcion'] ?? ''),
                $catalogPlan['precio'],
                (string) ($catalogPlan['moneda'] ?? 'USD'),
                (string) ($catalogPlan['periodo'] ?? 'mes'),
                $catalogPlan['billing_interval'],
                dbBoolParam($catalogPlan['destacado'] ?? false),
                dbBoolParam($catalogPlan['is_free'] ?? false),
                dbBoolParam($catalogPlan['personalizado'] ?? false),
                dbBoolParam($catalogPlan['activo'] ?? true),
                isset($catalogPlan['trial_days']) ? (int) $catalogPlan['trial_days'] : null,
                (int) ($catalogPlan['orden'] ?? 0),
                isset($catalogPlan['limite_empresas']) ? (int) $catalogPlan['limite_empresas'] : null,
                isset($catalogPlan['limite_usuarios']) ? (int) $catalogPlan['limite_usuarios'] : null,
                isset($catalogPlan['limite_documentos']) ? (int) $catalogPlan['limite_documentos'] : null,
            ]);

            $idPlan = (int) $insertPlan->fetchColumn();
            $deleteFeatures->execute([$idPlan]);

            foreach (($catalogPlan['caracteristicas'] ?? []) as $index => $feature) {
                $insertFeature->execute([
                    $idPlan,
                    trim((string) ($feature['texto'] ?? '')),
                    dbBoolParam($feature['incluido'] ?? true),
                    $index + 1,
                ]);
            }
        }
    }

    private static function decoratePlanRow(array $plan): array
    {
        $plan['billing_interval'] = self::normalizeIntervalValue($plan['billing_interval'] ?? $plan['periodo'] ?? null);
        $plan['is_free'] = dbBoolValue($plan['is_free'] ?? false)
            || (($plan['precio'] ?? null) !== null && (float) ($plan['precio'] ?? 0) <= 0);
        $plan['is_active'] = dbBoolValue($plan['activo'] ?? true);
        $plan['is_recommended'] = dbBoolValue($plan['destacado'] ?? false);
        $plan['trial_days'] = isset($plan['trial_days']) && $plan['trial_days'] !== ''
            ? (int) $plan['trial_days']
            : null;
        $plan['id'] = (int) ($plan['id_plan'] ?? 0);
        $plan['name'] = (string) ($plan['nombre'] ?? '');
        $plan['description'] = (string) ($plan['descripcion'] ?? '');
        $plan['price'] = $plan['precio'] ?? null;
        $plan['currency'] = (string) ($plan['moneda'] ?? 'USD');
        $plan['company_limit'] = isset($plan['limite_empresas']) && $plan['limite_empresas'] !== ''
            ? (int) $plan['limite_empresas']
            : null;
        $plan['user_limit'] = isset($plan['limite_usuarios']) && $plan['limite_usuarios'] !== ''
            ? (int) $plan['limite_usuarios']
            : null;
        $plan['document_limit'] = isset($plan['limite_documentos']) && $plan['limite_documentos'] !== ''
            ? (int) $plan['limite_documentos']
            : null;

        return $plan;
    }

    private static function normalizeCatalogPlan(array $plan): array
    {
        $price = $plan['precio'] ?? null;
        $isFree = array_key_exists('is_free', $plan)
            ? dbBoolValue($plan['is_free'])
            : ($price !== null && (float) $price <= 0);

        return array_merge($plan, [
            'billing_interval' => self::normalizeIntervalValue($plan['billing_interval'] ?? $plan['periodo'] ?? null),
            'is_free' => $isFree,
            'trial_days' => isset($plan['trial_days']) ? (int) $plan['trial_days'] : null,
        ]);
    }

    private static function normalizeIntervalValue($value): ?string
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
