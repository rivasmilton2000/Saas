<?php
require_once __DIR__ . '/../config/modulos.php';

$basePath = $basePath ?? app_url('src/');
$basePath = rtrim((string) $basePath, '/') . '/';
$session = $session ?? sessionData();
$sidebarModules = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_sidebar'] ?? false) === true && dte_modulo_habilitado($modulo)
);
$sidebarCurrentPath = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
$isDashboard = str_contains($sidebarCurrentPath, '/admin/dte') && !str_contains($sidebarCurrentPath, '/pages/');
$isBitacora = str_contains($sidebarCurrentPath, '/admin/bitacora');
$perfilPrincipal = strtolower(trim((string) ($session['main_profile'] ?? '')));
$esPerfilAdminPrincipal = in_array($perfilPrincipal, ['admin', 'administrador', 'developer'], true);
$backHref = $esPerfilAdminPrincipal ? '/admin/dashboard' : '/';
$backLabel = $esPerfilAdminPrincipal ? 'Volver al dashboard' : 'Volver al sitio';
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link <?php echo $isDashboard ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(app_url('')); ?>/">
        <i class="icon-grid menu-icon"></i>
        <span class="menu-title">Dashboard</span>
      </a>
    </li>
    <?php foreach ($sidebarModules as $sidebarModule): ?>
    <?php
      $sidebarRoute = ltrim((string) ($sidebarModule['ruta'] ?? ''), '/');
      $sidebarHref = app_url('src/' . $sidebarRoute);
      $sidebarIsActive = $sidebarRoute !== '' && str_contains($sidebarCurrentPath, '/' . basename($sidebarRoute));
    ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $sidebarIsActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($sidebarHref); ?>">
        <i class="<?php echo htmlspecialchars((string) ($sidebarModule['icono'] ?? 'icon-layout')); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) $sidebarModule['nombre']); ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="icon-arrow-left-circle menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></span>
      </a>
    </li>
    <?php if (dte_has_permission('ver_bitacora')): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $isBitacora ? 'active' : ''; ?>" href="/admin/bitacora">
        <i class="icon-notepad menu-icon"></i>
        <span class="menu-title">Bitacora</span>
      </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
      <a class="nav-link" href="/admin/auth/logout">
        <i class="icon-power menu-icon"></i>
        <span class="menu-title">Cerrar sesion</span>
      </a>
    </li>
  </ul>
</nav>
