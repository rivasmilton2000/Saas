<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/modulos.php';

$basePath = $basePath ?? '';
$session = $session ?? sessionData();
$appBaseUrl = $appBaseUrl ?? saasUrl('src/app/index.php');
$routeKey = $routeKey ?? '';
$isAdminSidebar = function_exists('isAdmin') ? isAdmin() : (($session['rol'] ?? 'user') === 'admin');
$modulos = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_sidebar'] ?? false) === true
);
$moduleRoutes = [
    'compras' => 'dte.purchases',
    'ventas_consumidor' => 'dte.consumer-sales',
    'ventas_contribuyente' => 'dte.taxpayer-sales',
    'retencion_iva' => 'dte.vat-withholdings',
];
$currentPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$currentPage = basename($currentPath);
$isDashboard = $routeKey === 'dashboard' || ($routeKey === '' && $currentPage === 'index.php');
$isBitacora  = $currentPage === 'bitacora.php';
$isBackups   = $currentPage === 'backups.php';
?>
<style>
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link {
    background: transparent;
    color: #6C7383;
  }

  .shared-sidebar .nav:not(.sub-menu) > .nav-item.active,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active {
    background: transparent;
    color: #6C7383;
  }

  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link .menu-icon,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item.active > .nav-link .menu-icon,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link.active .menu-icon {
    color: #6C7383;
  }

  .shared-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus {
    background: #4B49AC;
    color: #ffffff;
  }

  .shared-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link .menu-icon,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:hover .menu-icon,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus .menu-title,
  .shared-sidebar .nav:not(.sub-menu) > .nav-item > .nav-link:focus .menu-icon {
    color: #ffffff;
  }
</style>
<nav class="sidebar sidebar-offcanvas shared-sidebar" id="sidebar">
  <ul class="nav">
    <?php if ($isAdminSidebar): ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(saasUrl('src/admin/index.php')); ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
        <i class="icon-grid menu-icon"></i>
        <span class="menu-title">Panel admin</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(saasUrl('src/admin/index.php?page=audit')); ?>" <?php echo $isBitacora ? 'aria-current="page"' : ''; ?>>
        <i class="mdi mdi-history menu-icon"></i>
        <span class="menu-title">Bitacora</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(saasUrl('src/admin/index.php?page=backups')); ?>" <?php echo $isBackups ? 'aria-current="page"' : ''; ?>>
        <i class="mdi mdi-database-lock-outline menu-icon"></i>
        <span class="menu-title">Backups</span>
      </a>
    </li>
    <?php else: ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars($appBaseUrl); ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">Inicio</span>
      </a>
    </li>
    <?php foreach ($modulos as $moduleKey => $modulo): ?>
    <?php
      $moduleRoute = $moduleRoutes[$moduleKey] ?? '';
      $moduleUrl = $moduleRoute !== ''
          ? $appBaseUrl . '?page=' . rawurlencode($moduleRoute)
          : saasUrl('src/' . ltrim((string) ($modulo['ruta'] ?? ''), '/'));
      $esActivo = $routeKey === $moduleRoute;
    ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars($moduleUrl); ?>" <?php echo $esActivo ? 'aria-current="page"' : ''; ?>>
        <i class="<?php echo htmlspecialchars((string) ($modulo['icono'] ?? 'icon-layout')); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <?php endif; ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(saasPublicUrl('index.php')); ?>">
        <i class="icon-arrow-left-circle menu-icon"></i>
        <span class="menu-title">Volver al sitio</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(saasUrl('src/pages/samples/logout.php')); ?>">
        <i class="icon-power menu-icon"></i>
        <span class="menu-title">Cerrar sesion</span>
      </a>
    </li>
  </ul>
</nav>
