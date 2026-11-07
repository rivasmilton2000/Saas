<?php
require_once __DIR__ . '/legacy-database.php';

try {
    $pdo = databaseConnection();
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        throw $e;
    }
    error_log('Zentra: error de conexion a MySQL (' . $e->getCode() . ').');
    http_response_code(503);
    exit('No se pudo conectar a Zentra. Revisa MySQL y la configuracion local.');
}

if (!function_exists('dbDriverName')) {
    function dbDriverName(PDO $pdo): string {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    function dbBoolValue($value): bool {
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

    function dbBoolInt($value): int {
        return dbBoolValue($value) ? 1 : 0;
    }

    function dbBoolParam($value): string {
        return dbBoolValue($value) ? '1' : '0';
    }

    function dbColumnExists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([strtolower($table), strtolower($column)]);

        return $stmt->fetchColumn() !== false;
    }
}


function dbConfig(): array {
    $config = databaseConfig();
    return ['driver' => 'mysql', 'host' => $config['host'], 'port' => (string)$config['port'], 'dbname' => $config['database'], 'user' => $config['username'], 'pass' => $config['password']];
}

function dbRequireTable(PDO $pdo, string $table): void {
    static $checked = [];
    if (isset($checked[$table])) {
        return;
    }
    if (!preg_match('/^[a-z_]+$/D', $table)) {
        throw new InvalidArgumentException('Tabla no valida.');
    }
    try {
        $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0')->closeCursor();
    } catch (PDOException $e) {
        throw new RuntimeException('Falta el esquema Zentra. Ejecuta php scripts/setup-database.php.', 0, $e);
    }
    $checked[$table] = true;
}
