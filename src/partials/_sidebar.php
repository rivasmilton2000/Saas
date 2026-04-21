<?php
$basePath = $basePath ?? '';
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link" href="<?php echo $basePath; ?>index.php">
        <i class="icon-grid menu-icon"></i>
        <span class="menu-title">Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo $basePath; ?>pages/compras.php">
        <i class="icon-layout menu-icon"></i>
        <span class="menu-title">Libro de Compras</span>
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
