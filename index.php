<?php
declare(strict_types=1);
require_once __DIR__ . '/src/config/env.php';
$origin = rtrim((string) envValue('APP_URL', 'http://localhost:3000'), '/');
$request = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$relative = $base !== '' && strpos($request, $base . '/') === 0 ? substr($request, strlen($base)) : '/';
if ($relative === '/index.php') {
    $relative = '/';
}
header('Cache-Control: no-store');
header('Location: ' . $origin . '/' . ltrim($relative, '/'), true, 307);
exit;
