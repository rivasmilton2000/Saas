<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/main_root.php';

$rootPath = dte_main_system_root();
global $pdo;

if (!(($GLOBALS['pdo'] ?? null) instanceof PDO)) {
    // Fuerza carga de conexion en el scope actual para evitar
    // casos donde require_once se ejecuto en otro scope.
    require $rootPath . '/include/conexion.php';
    if ($pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
    }
}

if (($GLOBALS['pdo'] ?? null) instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('No se pudo reutilizar la conexion principal del sistema.');
}
