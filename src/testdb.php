<?php
// Compatibilidad historica: el diagnostico activo usa Nest y su proveedor MySQL.
require_once __DIR__ . '/config/app.php';
header('Location: ' . saasUrl('api/health'), true, 307);
exit;
