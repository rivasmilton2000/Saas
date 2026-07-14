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
<style>
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link {
    background: transparent;
    color: #6C7383;
  }

  .admin-sidebar .nav:not(.sub-menu) > .nav-item.active,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active {
    background: transparent;
    color: #6C7383;
  }

  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link .menu-icon,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link .menu-icon,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active .menu-icon {
    color: #6C7383;
  }

  .admin-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus {
    background: #4B49AC;
    color: #ffffff;
  }

  .admin-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link .menu-icon,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover .menu-icon,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus .menu-title,
  .admin-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus .menu-icon {
    color: #ffffff;
  }
</style>
<nav class="sidebar sidebar-offcanvas admin-sidebar" id="sidebar">
  <ul class="nav">
    <?php foreach ($adminNavItems as $key => $item): ?>
    <?php
      $isActive = $routeKey === $key
          || ($key === 'dte' && str_starts_with((string) $routeKey, 'dte.'));
    ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars($adminBaseUrl . '?page=' . $key); ?>" <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
        <i class="<?php echo htmlspecialchars((string) $item['icon']); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) $item['label']); ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    
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
