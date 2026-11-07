<?php
date_default_timezone_set('America/El_Salvador');
require_once dirname(__DIR__) . '/src/config/env.php';

function databaseConfig(): array {
    foreach (['DB_HOST', 'DB_USER', 'DB_NAME'] as $key) {
        if (trim((string) envValue($key, '')) === '') {
            throw new RuntimeException('Falta la variable de entorno ' . $key);
        }
    }
    return [
        'host' => (string) envValue('DB_HOST'),
        'port' => (int) envValue('DB_PORT', 3306),
        'username' => (string) envValue('DB_USER'),
        'password' => (string) envValue('DB_PASSWORD', ''),
        'database' => (string) envValue('DB_NAME'),
    ];
}

function databaseConnection(bool $selectDatabase = true): PDO {
    $config = databaseConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
    if ($selectDatabase) {
        $dsn .= ';dbname=' . $config['database'];
    }
    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '-06:00'");
    return $connection;
}
