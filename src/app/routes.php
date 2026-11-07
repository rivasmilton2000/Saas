<?php
declare(strict_types=1);

$routes = [
    'dashboard' => [
        'title' => 'Inicio',
        'file' => __DIR__ . '/pages/dashboard.php',
    ],
    'dte.purchases' => [
        'title' => 'Libro de Compras',
        'file' => __DIR__ . '/pages/dte/purchases.php',
    ],
    'dte.consumer-sales' => [
        'title' => 'Ventas Consumidor Final',
        'file' => __DIR__ . '/pages/dte/consumer-sales.php',
    ],
    'dte.taxpayer-sales' => [
        'title' => 'Ventas a Contribuyentes',
        'file' => __DIR__ . '/pages/dte/taxpayer-sales.php',
    ],
    'dte.vat-withholdings' => [
        'title' => 'Retencion IVA 1%',
        'file' => __DIR__ . '/pages/dte/vat-withholdings.php',
    ],
];

if (!isset($routes[$routeKey])) {
    http_response_code(404);
    $routeKey = 'dashboard';
}

$currentRoute = $routes[$routeKey];
$pageTitle = $currentRoute['title'];
$pageFile = $currentRoute['file'];

if (!is_file($pageFile)) {
    http_response_code(500);
    exit('La pagina del usuario no existe.');
}

require $pageFile;
