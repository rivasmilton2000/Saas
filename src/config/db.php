<?php
$host   = 'localhost';
$port   = '5432';
$dbname = 'Saas';
$user   = 'postgres';
$pass   = '1581';

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET TIME ZONE 'America/El_Salvador'");
} catch (PDOException $e) {
    die('Error de conexion: ' . $e->getMessage());
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
        return dbBoolValue($value) ? 'true' : 'false';
    }

    function dbColumnExists(PDO $pdo, string $table, string $column): bool {
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
    function dbConfig(): array {
        global $host, $port, $dbname, $user, $pass;

        return [
            'host'   => (string) $host,
            'port'   => (string) $port,
            'dbname' => (string) $dbname,
            'user'   => (string) $user,
            'pass'   => (string) $pass,
        ];
    }
}
