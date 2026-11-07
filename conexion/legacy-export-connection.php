<?php
// Solo para la herramienta historica de exportacion; Nest no carga este archivo.
require_once __DIR__ . '/legacy-database.php';
$exportConfig = databaseConfig();
define('MYSQL_DSN', sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $exportConfig['host'], $exportConfig['port'], $exportConfig['database']));
define('MYSQL_USER', $exportConfig['username']);
define('MYSQL_PASS', $exportConfig['password']);
define('POSTGRES_HOST', (string) envValue('POSTGRES_HOST', ''));
define('POSTGRES_PORT', (string) envValue('POSTGRES_PORT', '5432'));
define('POSTGRES_ADMIN_DB', (string) envValue('POSTGRES_ADMIN_DB', 'postgres'));
define('POSTGRES_TARGET_DB', (string) envValue('POSTGRES_TARGET_DB', ''));
define('POSTGRES_USER', (string) envValue('POSTGRES_USER', ''));
define('POSTGRES_PASS', (string) envValue('POSTGRES_PASSWORD', ''));
unset($exportConfig);
function createPdo(string $dsn, string $user, string $pass): PDO {
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
