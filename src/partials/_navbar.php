<?php
$basePath = $basePath ?? '';
$session  = $session ?? sessionData();
$empresaActivaNavbar = $empresaActivaNavbar ?? null;
?>
<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <a class="navbar-brand brand-logo me-5" href="<?php echo $basePath; ?>index.php"><img src="<?php echo $basePath; ?>assets/images/logo.svg" class="me-2" alt="logo" /></a>
    <a class="navbar-brand brand-logo-mini" href="<?php echo $basePath; ?>index.php"><img src="<?php echo $basePath; ?>assets/images/logo-mini.svg" alt="logo" /></a>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="icon-menu"></span>
    </button>
    <ul class="navbar-nav me-auto">
      <li class="nav-item d-none d-lg-flex align-items-center">
        <span class="text-muted">
          Usuario: <strong><?php echo htmlspecialchars((string) ($session['username'] ?? '')); ?></strong>
          <?php if ($empresaActivaNavbar): ?>
            | Empresa activa: <strong><?php echo htmlspecialchars((string) $empresaActivaNavbar['nombre']); ?></strong>
          <?php endif; ?>
        </span>
      </li>
    </ul>
    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item nav-profile dropdown">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
          <img src="<?php echo $basePath; ?>assets/images/faces/face28.jpg" alt="profile" />
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
          <span class="dropdown-item-text text-muted px-3">Rol: <?php echo htmlspecialchars((string) ($session['rol'] ?? '')); ?></span>
          <a class="dropdown-item" href="<?php echo $basePath; ?>pages/samples/logout.php">
            <i class="ti-power-off text-primary"></i> Cerrar sesion
          </a>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
      <span class="icon-menu"></span>
    </button>
  </div>
</nav>
