<?php
$session = $session ?? sessionData();
$empresaActivaNavbar = $empresaActivaNavbar ?? null;
$displayName = trim((string) ($session['nombre_completo'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string) ($session['username'] ?? 'Usuario'));
}
?>
<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <a class="navbar-brand brand-logo me-2" href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">
      <img src="<?php echo htmlspecialchars(parent_app_url('assets/images/logo.svg'), ENT_QUOTES, 'UTF-8'); ?>" class="me-2" alt="SaaS" style="height:34px;width:auto;" />
      <span style="font-weight:700;color:#1f3552;">Modulo DTE</span>
    </a>
    <a class="navbar-brand brand-logo-mini" href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">
      <img src="<?php echo htmlspecialchars(parent_app_url('assets/images/logo-mini.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="SaaS" style="height:28px;width:auto;" />
    </a>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="icon-menu"></span>
    </button>
    <ul class="navbar-nav me-auto">
      <li class="nav-item d-none d-lg-flex align-items-center">
        <span class="text-muted">
          Usuario: <strong><?php echo htmlspecialchars((string) ($session['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
          <?php if ($displayName !== '' && $displayName !== (string) ($session['username'] ?? '')): ?>
            | <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
          <?php endif; ?>
          <?php if ($empresaActivaNavbar): ?>
            | Empresa activa: <strong><?php echo htmlspecialchars((string) ($empresaActivaNavbar['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
          <?php endif; ?>
        </span>
      </li>
    </ul>
    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item d-none d-md-flex align-items-center me-2">
        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(dte_parent_dashboard_url(), ENT_QUOTES, 'UTF-8'); ?>">
          SaaS
        </a>
      </li>
      <li class="nav-item nav-profile dropdown">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
          <span class="d-none d-xl-inline me-2 text-dark fw-semibold"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
          <img src="<?php echo htmlspecialchars(parent_app_url('assets/images/faces/face28.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="profile" />
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
          <span class="dropdown-item-text px-3 fw-semibold"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="dropdown-item-text text-muted px-3 small"><?php echo htmlspecialchars((string) ($session['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="dropdown-item-text text-muted px-3">Rol DTE: <?php echo htmlspecialchars((string) ($session['rol'] ?? 'user'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo htmlspecialchars(dte_logout_url(), ENT_QUOTES, 'UTF-8'); ?>">
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
