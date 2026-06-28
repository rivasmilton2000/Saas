<?php
require_once __DIR__ . '/app.php';

if (($GLOBALS['pdo'] ?? null) instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
} else {
    $parentEnv = APP_PARENT_ROOT . '/config/env.php';
    if (file_exists($parentEnv)) {
        require_once $parentEnv;
    } else {
        $vendorAutoload = dirname(APP_PARENT_ROOT) . '/vendor/autoload.php';
        if (file_exists($vendorAutoload) && class_exists('Dotenv\\Dotenv') === false) {
            require_once $vendorAutoload;
        }

        if (class_exists('Dotenv\\Dotenv')) {
            $workspaceRoot = dirname(APP_PARENT_ROOT);
            if (file_exists($workspaceRoot . '/.env')) {
                try {
                    Dotenv\Dotenv::createImmutable($workspaceRoot)->safeLoad();
                } catch (Throwable $exception) {
                    // Si no se puede cargar .env, seguimos con getenv().
                }
            }
        }
    }

    $dsn = trim((string) (getenv('DTE_DB_DSN') ?: getenv('DB_DSN') ?: ''));
    $host = trim((string) (getenv('DTE_DB_HOST') ?: getenv('DB_HOST') ?: ''));
    $port = trim((string) (getenv('DTE_DB_PORT') ?: getenv('DB_PORT') ?: '5432'));
    $dbname = trim((string) (getenv('DTE_DB_NAME') ?: getenv('DB_NAME') ?: ''));
    $user = trim((string) (getenv('DTE_DB_USER') ?: getenv('DB_USER') ?: ''));
    $pass = (string) (getenv('DTE_DB_PASS') ?: getenv('DB_PASS') ?: '');

    if ($dsn === '' && $host !== '' && $dbname !== '' && $user !== '') {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port !== '' ? $port : '5432', $dbname);
    }

    if ($dsn !== '') {
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $GLOBALS['pdo'] = $pdo;
            $GLOBALS['dte_db_config'] = [
                'dsn' => $dsn,
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'user' => $user,
                'pass' => $pass,
            ];
        } catch (Throwable $exception) {
            $pdo = null;
        }
    }

    if (!($pdo instanceof PDO)) {
        $fallbackDb = APP_PARENT_ROOT . '/config/db.php';
        if (file_exists($fallbackDb)) {
            require_once $fallbackDb;
            if (($GLOBALS['pdo'] ?? null) instanceof PDO) {
                $pdo = $GLOBALS['pdo'];
            }
        }
    }
}

if (!($pdo instanceof PDO)) {
    throw new RuntimeException('No se pudo inicializar la conexion PostgreSQL del modulo DTE.');
}

$driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driverName !== 'pgsql') {
    throw new RuntimeException('El modulo DTE requiere una conexion PostgreSQL activa.');
}

$pdo->exec("SET TIME ZONE 'America/El_Salvador'");
$GLOBALS['pdo'] = $pdo;

if (!function_exists('dbDriverName')) {
    function dbDriverName(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}

if (!function_exists('dbBoolValue')) {
    function dbBoolValue($value): bool
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

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 't', 'true', 'y', 'yes', 'on'], true);
    }
}

if (!function_exists('dbBoolInt')) {
    function dbBoolInt($value): int
    {
        return dbBoolValue($value) ? 1 : 0;
    }
}

if (!function_exists('dbColumnExists')) {
    function dbColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([strtolower($table), strtolower($column)]);

        return $stmt->fetchColumn() !== false;
    }
}

if (!function_exists('dbConfig')) {
    function dbConfig(): array
    {
        $config = $GLOBALS['dte_db_config'] ?? [];
        if (is_array($config) && $config !== []) {
            return $config;
        }

        return [
            'dsn' => '',
            'host' => '',
            'port' => '',
            'dbname' => '',
            'user' => '',
            'pass' => '',
        ];
    }
}
