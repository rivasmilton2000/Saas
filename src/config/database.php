<?php

function databaseConfig(): array {
    $path = __DIR__ . '/db.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Copia src/config/db.local.php.example a db.local.php y configura XAMPP.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('La configuracion local de MySQL debe devolver un array.');
    }
    foreach (['host', 'port', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Falta una opcion en la configuracion local de MySQL: ' . $key);
        }
    }
    $config['database'] = 'zentra';
    return $config;
}

function databaseConnection(bool $selectDatabase = true): PDO {
    $config = databaseConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
    if ($selectDatabase) {
        $dsn .= ';dbname=' . $config['database'];
    }
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
