<?php
require_once __DIR__ . '/database.php';

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
