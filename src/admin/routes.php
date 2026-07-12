<?php
  declare(strict_types=1);

  $routes = [
      'dashboard' => [
          'title' => 'Dashboard',
          'file' => __DIR__ . '/pages/dashboard.php',
      ],
      'users' => [
          'title' => 'Usuarios',
          'file' => __DIR__ . '/pages/users/index.php',
      ],
      'companies' => [
          'title' => 'Empresas',
          'file' => __DIR__ . '/pages/companies/index.php',
      ],
      'plans' => [
          'title' => 'Planes',
          'file' => __DIR__ . '/pages/plans/index.php',
      ],
      'subscriptions' => [
          'title' => 'Suscripciones',
          'file' => __DIR__ . '/pages/subscriptions/index.php',
      ],
      'payments' => [
          'title' => 'Pagos',
          'file' => __DIR__ . '/pages/payments/index.php',
      ],
      'dte' => [
          'title' => 'Uso DTE',
          'file' => __DIR__ . '/pages/dte/usage.php',
      ],
      'audit' => [
          'title' => 'Bitacora',
          'file' => __DIR__ . '/pages/audit/bitacora.php',
      ],
      'backups' => [
          'title' => 'Backups',
          'file' => __DIR__ . '/pages/backups/index.php',
      ],
      'settings' => [
          'title' => 'Configuracion',
          'file' => __DIR__ . '/pages/settings/general.php',
      ],
  ];

  $routeKey = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));

  if (!isset($routes[$routeKey])) {
      http_response_code(404);
      $routeKey = 'dashboard';
  }

  $currentRoute = $routes[$routeKey];
  $pageTitle = $currentRoute['title'];
  $pageFile = $currentRoute['file'];

  if (!is_file($pageFile)) {
      http_response_code(500);
      exit('La pagina administrativa no existe.');
  }

  require $pageFile;