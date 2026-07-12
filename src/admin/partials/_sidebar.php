<?php
declare(strict_types=1);

$adminBaseUrl = $adminBaseUrl ?? 'index.php';
$routeKey = $routeKey ?? 'dashboard';

$adminNavItems = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'icon-grid',
    ],
    'users' => [
        'label' => 'Usuarios',
        'icon' => 'mdi mdi-account-group-outline',
    ],
    'companies' => [
        'label' => 'Empresas',
        'icon' => 'mdi mdi-domain',
    ],
    'plans' => [
        'label' => 'Planes',
        'icon' => 'mdi mdi-layers-outline',
    ],
    'subscriptions' => [
        'label' => 'Suscripciones',
        'icon' => 'mdi mdi-credit-card-sync-outline',
    ],
    'payments' => [
        'label' => 'Pagos',
        'icon' => 'mdi mdi-cash-multiple',
    ],
    'dte' => [
        'label' => 'Uso DTE',
        'icon' => 'mdi mdi-file-document-outline',
    ],
    'audit' => [
        'label' => 'Bitacora',
        'icon' => 'mdi mdi-history',
    ],
    'backups' => [
        'label' => 'Backups',
        'icon' => 'mdi mdi-database-lock-outline',
    ],
    'settings' => [
        'label' => 'Configuracion',
        'icon' => 'mdi mdi-cog-outline',
    ],
];
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <?php foreach ($adminNavItems as $key => $item): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $routeKey === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($adminBaseUrl . '?page=' . $key); ?>">
        <i class="<?php echo htmlspecialchars((string) $item['icon']); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) $item['label']); ?></span>
      </a>
    </li>
    <?php endforeach; ?>

    <li class="nav-item">
      <a class="nav-link" href="/Saas/src/index.php">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">Panel usuario</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="/Saas/public/index.php">
        <i class="icon-arrow-left-circle menu-icon"></i>
        <span class="menu-title">Volver al sitio</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="../pages/samples/logout.php">
        <i class="icon-power menu-icon"></i>
        <span class="menu-title">Cerrar sesion</span>
      </a>
    </li>
  </ul>
</nav>
