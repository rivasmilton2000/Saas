<?php
require_once __DIR__ . '/../config/modulos.php';

$sidebarModules = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_sidebar'] ?? false) === true && dte_modulo_habilitado($modulo)
);
$currentPath = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
$isDashboard = str_contains($currentPath, '/src/dte/index.php') || preg_match('#/src/dte/?$#', $currentPath) === 1;
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link <?php echo $isDashboard ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="icon-grid menu-icon"></i>
        <span class="menu-title">Inicio DTE</span>
      </a>
    </li>
    <?php foreach ($sidebarModules as $sidebarModule): ?>
      <?php
      $sidebarRoute = ltrim((string) ($sidebarModule['ruta'] ?? ''), '/');
      $sidebarHref = app_url($sidebarRoute);
      $sidebarActive = $sidebarRoute !== '' && str_contains($currentPath, '/' . basename($sidebarRoute));
      ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $sidebarActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($sidebarHref, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="<?php echo htmlspecialchars((string) ($sidebarModule['icono'] ?? 'icon-layout'), ENT_QUOTES, 'UTF-8'); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) ($sidebarModule['nombre'] ?? 'Modulo'), ENT_QUOTES, 'UTF-8'); ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(dte_parent_dashboard_url(), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="icon-arrow-left-circle menu-icon"></i>
        <span class="menu-title">Volver al SaaS</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars(dte_logout_url(), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="icon-power menu-icon"></i>
        <span class="menu-title">Cerrar sesion</span>
      </a>
    </li>
  </ul>
</nav>
