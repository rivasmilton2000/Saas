<?php
require_once __DIR__ . '/../config/modulos.php';

$basePath = $basePath ?? '';
$modulos  = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_sidebar'] ?? false) === true
);
$currentPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$isDashboard = substr($currentPath, -10) === '/index.php';
$isBitacora  = substr($currentPath, -13) === '/bitacora.php';
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link <?php echo $isDashboard ? 'active' : ''; ?>" href="<?php echo $basePath; ?>index.php">
        <i class="icon-grid menu-icon"></i>
        <span class="menu-title">Dashboard</span>
      </a>
    </li>
    <?php foreach ($modulos as $modulo): ?>
    <?php
      $rutaModulo = (string) ($modulo['ruta'] ?? '');
      $esActivo = $rutaModulo !== '' && substr($currentPath, -strlen('/' . basename($rutaModulo))) === '/' . basename($rutaModulo);
    ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $esActivo ? 'active' : ''; ?>" href="<?php echo $basePath . $rutaModulo; ?>">
        <i class="<?php echo htmlspecialchars((string) ($modulo['icono'] ?? 'icon-layout')); ?> menu-icon"></i>
        <span class="menu-title"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $isBitacora ? 'active' : ''; ?>" href="<?php echo $basePath; ?>pages/bitacora.php">
        <i class="mdi mdi-history menu-icon"></i>
        <span class="menu-title">Bitacora</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo $basePath; ?>pages/samples/logout.php">
        <i class="icon-power menu-icon"></i>
        <span class="menu-title">Cerrar sesion</span>
      </a>
    </li>
  </ul>
</nav>
