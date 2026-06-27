<?php
require_once dte_main_root() . '/include/user_profile.php';

$basePath = $basePath ?? app_url('src/');
$basePath = rtrim((string) $basePath, '/') . '/';
$session  = $session ?? sessionData();
$empresaActivaNavbar = $empresaActivaNavbar ?? null;
$usuarioPrincipalNavbar = function_exists('obtener_usuario_actual') ? (obtener_usuario_actual() ?? []) : [];
$usuarioPerfilNavbar = (($GLOBALS['pdo'] ?? null) instanceof PDO)
    ? sietelsa_profile_current_user($GLOBALS['pdo'], $usuarioPrincipalNavbar)
    : $usuarioPrincipalNavbar;
$nombrePerfilNavbar = trim((string) ($usuarioPerfilNavbar['nombre'] ?? ''));
$usernamePerfilNavbar = trim((string) ($usuarioPerfilNavbar['username'] ?? $session['username'] ?? ''));
$fotoPerfilNavbar = sietelsa_profile_safe_asset_url($usuarioPerfilNavbar['foto_path'] ?? null, $basePath . 'assets/images/faces/face28.jpg');
$perfilPrincipal = strtolower(trim((string) ($session['main_profile'] ?? '')));
$esPerfilAdminPrincipal = in_array($perfilPrincipal, ['admin', 'administrador', 'developer'], true);
$backHref = $esPerfilAdminPrincipal ? '/admin/dashboard' : '/';
$backLabel = $esPerfilAdminPrincipal ? 'Dashboard' : 'Sitio';
?>
<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <a class="navbar-brand brand-logo me-2" href="<?php echo htmlspecialchars(app_url('')); ?>/">
      <img src="/assets/img/logos/logoSietelsa.webp" class="me-2" alt="Sietelsa" width="152" height="38" decoding="async" style="height:38px;width:auto;" />
      <span style="font-weight:700;color:#1f3552;">Sietelsa DTE</span>
    </a>
    <a class="navbar-brand brand-logo-mini" href="<?php echo htmlspecialchars(app_url('')); ?>/">
      <img src="/assets/img/logos/logoSietelsa.webp" alt="Sietelsa" width="120" height="30" decoding="async" style="height:30px;width:auto;" />
    </a>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="icon-menu"></span>
    </button>
    <ul class="navbar-nav me-auto">
      <li class="nav-item d-none d-lg-flex align-items-center">
        <span class="text-muted">
          Usuario: <strong><?php echo htmlspecialchars($usernamePerfilNavbar !== '' ? $usernamePerfilNavbar : (string) ($session['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
          <?php if ($nombrePerfilNavbar !== ''): ?>
            | <?php echo htmlspecialchars($nombrePerfilNavbar, ENT_QUOTES, 'UTF-8'); ?>
          <?php endif; ?>
          <?php if ($empresaActivaNavbar): ?>
            | Empresa activa: <strong><?php echo htmlspecialchars((string) $empresaActivaNavbar['nombre']); ?></strong>
          <?php endif; ?>
        </span>
      </li>
    </ul>
    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item d-none d-md-flex align-items-center me-2">
        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </li>
      <li class="nav-item d-flex align-items-center me-2">
        <button class="btn btn-sm btn-outline-primary admin-theme-toggle" type="button" data-sietelsa-theme-toggle aria-label="Activar modo oscuro" title="Cambiar tema">
          <i class="mdi mdi-weather-night me-1" data-sietelsa-theme-icon></i>
          <span class="d-none d-xl-inline" data-sietelsa-theme-label>Oscuro</span>
        </button>
      </li>
      <li class="nav-item nav-profile dropdown">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
          <span class="d-none d-xl-inline me-2 text-dark fw-semibold"><?php echo htmlspecialchars($nombrePerfilNavbar !== '' ? $nombrePerfilNavbar : ($usernamePerfilNavbar !== '' ? $usernamePerfilNavbar : 'Usuario'), ENT_QUOTES, 'UTF-8'); ?></span>
          <img src="<?php echo htmlspecialchars($fotoPerfilNavbar, ENT_QUOTES, 'UTF-8'); ?>" alt="profile" width="34" height="34" loading="lazy" decoding="async" style="object-fit:cover;" />
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
          <span class="dropdown-item-text px-3 fw-semibold"><?php echo htmlspecialchars($nombrePerfilNavbar !== '' ? $nombrePerfilNavbar : 'Usuario', ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="dropdown-item-text text-muted px-3 small"><?php echo htmlspecialchars($usernamePerfilNavbar !== '' ? $usernamePerfilNavbar : 'sin usuario', ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="dropdown-item-text text-muted px-3">Rol: <?php echo htmlspecialchars((string) ($session['rol'] ?? '')); ?></span>
          <div class="dropdown-divider"></div>
          <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModalDte">
            <i class="ti-user text-primary"></i> Ver perfil
          </button>
          <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModalDte">
            <i class="ti-image text-primary"></i> Cambiar foto
          </button>
          <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#perfilUsuarioModalDte">
            <i class="ti-lock text-primary"></i> Cambiar contrasena
          </button>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="/admin/auth/logout">
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
<?php
if (($GLOBALS['pdo'] ?? null) instanceof PDO) {
    render_sietelsa_profile_modal($GLOBALS['pdo'], $usuarioPerfilNavbar, (string) ($session['rol'] ?? ''), 'perfilUsuarioModalDte');
}
?>
