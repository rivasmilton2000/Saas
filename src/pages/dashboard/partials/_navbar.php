<?php

declare(strict_types=1);

if (!defined('SIETELSA_DASHBOARD_RENDER')) {
    http_response_code(404);
    exit;
}

require_once SIETELSA_ROOT . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/users/AvatarManager.php';
require_once SIETELSA_ROOT . '/includes/notifications/bootstrap.php';
require_once SIETELSA_ROOT . '/includes/companies/bootstrap.php';

$dashboardUser = $currentUser ?? auth_user();
$isAdmin = is_array($dashboardUser) && $dashboardUser['rol'] === 'administrador';
$navbarCompanyContext = $companyContextUser ?? company_context_user($dashboardUser);
$navbarCompanyExternalContext = company_context_is_external($dashboardUser, $navbarCompanyContext);
if (!isset($activeCompany)) {
    try {
        $activeCompany = (new CompanyService(db()))->activeCompany($dashboardUser, $navbarCompanyContext);
    } catch (Throwable $exception) {
        $activeCompany = null;
    }
}
$navbarNotifications = [];
$navbarUnreadNotifications = 0;
if ($isAdmin) {
    try {
        $navbarNotificationService = new NotificationService(db());
        $navbarNotifications = $navbarNotificationService->latest(5);
        $navbarUnreadNotifications = $navbarNotificationService->unreadCount();
    } catch (Throwable $exception) {
        error_log('[SIETELSA][notifications] No fue posible cargar el indicador del navbar.');
    }
}
?>
<nav class="navbar sietelsa-dashboard-navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <div class="me-3">
      <button class="navbar-toggler align-self-center" type="button" data-sietelsa-toggle="minimize" aria-label="Contraer menú" aria-controls="sidebar">
        <span class="icon-menu"></span>
      </button>
    </div>
    <div>
      <a class="navbar-brand brand-logo" href="<?= e(dashboard_url_for($dashboardUser)) ?>">
        <img class="sietelsa-dashboard-logo" src="<?= e(app_url('includes/img/logoSietelsa.png')) ?>" alt="SIETELSA">
      </a>
      <a class="navbar-brand brand-logo-mini" href="<?= e(dashboard_url_for($dashboardUser)) ?>">
        <img class="sietelsa-dashboard-logo-mini" src="<?= e(app_url('includes/img/logoSietelsa.png')) ?>" alt="SIETELSA">
      </a>
    </div>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-center">
    <ul class="navbar-nav sietelsa-navbar-welcome">
      <li class="nav-item fw-semibold d-none d-xl-block ms-0">
        <h1 class="welcome-text">Bienvenido, <span class="text-black fw-bold"><?= e((string) $dashboardUser['nombre']) ?></span></h1>
        <?php if ($isAdmin): ?>
          <h3 class="welcome-sub-text">Rol: administrador<?= $navbarCompanyExternalContext ? ' · Supervisando información de: ' . e(trim((string)$navbarCompanyContext['nombre'].' '.(string)$navbarCompanyContext['apellido'])) . ($activeCompany ? ' · Empresa: '.e((string)$activeCompany['nombre_comercial']) : '') : ' · Supervisión global DTE' ?></h3>
        <?php else: ?>
          <h3 class="welcome-sub-text" title="<?= e($activeCompany['nombre_comercial'] ?? 'Sin empresa activa') ?>">Rol: <?= e((string)$dashboardUser['rol']) ?> · Empresa activa: <strong><?= e($activeCompany['nombre_comercial'] ?? 'Sin seleccionar') ?></strong></h3>
        <?php endif; ?>
      </li>
    </ul>
    <ul class="navbar-nav ms-auto sietelsa-navbar-actions">
      <?php if (!$isAdmin): ?><li class="nav-item d-none d-xl-flex align-items-center me-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('src/dashboard/companies/index.php')) ?>">Cambiar empresa</a></li><?php endif; ?>
      <?php if ($navbarCompanyExternalContext): ?>
        <li class="nav-item dropdown d-none d-lg-block">
          <a class="nav-link text-warning" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Contexto administrativo activo"><i class="mdi mdi-account-alert-outline"></i></a>
          <div class="dropdown-menu dropdown-menu-right navbar-dropdown p-3 sietelsa-company-context-menu"><p class="fw-semibold mb-1">Contexto administrativo</p><p class="small text-muted text-wrap">Gestionando empresas de <?= e((string)$navbarCompanyContext['correo']) ?> sin cambiar tu sesión real.</p><form method="post" action="<?= e(app_url('src/dashboard/companies/context.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="exit"><button class="btn btn-sm btn-warning w-100" type="submit">Salir del contexto del usuario</button></form></div>
        </li>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
        <li class="nav-item dropdown">
          <a class="nav-link count-indicator" id="NotificationDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones administrativas">
            <i class="icon-bell"></i>
            <?php if ($navbarUnreadNotifications > 0): ?>
              <span class="count"></span>
              <span class="visually-hidden"><?= min($navbarUnreadNotifications, 99) ?> notificaciones no leídas</span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0 sietelsa-notification-dropdown" aria-labelledby="NotificationDropdown">
            <a class="dropdown-item py-3 border-bottom" href="<?= e(app_url('src/dashboard/notifications/index.php')) ?>">
              <p class="mb-0 fw-medium float-start">Notificaciones</p>
              <span class="badge badge-pill badge-primary float-end"><?= $navbarUnreadNotifications ?> sin leer</span>
            </a>
            <?php if ($navbarNotifications === []): ?>
              <div class="dropdown-item-text text-muted">No hay notificaciones registradas.</div>
            <?php else: ?>
              <?php foreach ($navbarNotifications as $notification): ?>
                <a class="dropdown-item preview-item py-3<?= $notification['leida_en'] === null ? ' bg-light' : '' ?>" href="<?= e(app_url('src/dashboard/notifications/index.php')) ?>">
                  <div class="preview-thumbnail"><i class="mdi mdi-bell-outline m-auto text-primary"></i></div>
                  <div class="preview-item-content">
                    <h6 class="preview-subject fw-normal text-dark mb-1 text-wrap"><?= e((string) $notification['titulo']) ?></h6>
                    <p class="fw-light small-text mb-0"><?= e(date('d/m/Y H:i', strtotime((string) $notification['creado_en']))) ?></p>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </li>
      <?php endif; ?>
      <li class="nav-item dropdown user-dropdown">
        <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <img class="img-xs rounded-circle" data-avatar-user="<?= (int)$dashboardUser['id'] ?>" src="<?= e(user_avatar_url($dashboardUser)) ?>" alt="Perfil de <?= e((string) $dashboardUser['nombre']) ?>">
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
          <div class="dropdown-header text-center">
            <img class="img-md rounded-circle" data-avatar-user="<?= (int)$dashboardUser['id'] ?>" src="<?= e(user_avatar_url($dashboardUser)) ?>" alt="Perfil de <?= e((string) $dashboardUser['nombre']) ?>">
            <p class="mb-1 mt-3 fw-semibold"><?= e((string) $dashboardUser['nombre']) ?></p>
            <p class="fw-light text-muted mb-0"><?= e((string) $dashboardUser['correo']) ?></p>
            <span class="badge badge-primary mt-2"><?= e((string) $dashboardUser['rol']) ?></span>
            <?php if (!$isAdmin): ?>
              <p class="small text-muted mt-2 mb-0">Empresa activa: <?= e($activeCompany['nombre_comercial'] ?? 'Sin seleccionar') ?></p>
              <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(app_url('src/dashboard/companies/index.php')) ?>">Cambiar empresa</a>
            <?php elseif ($navbarCompanyExternalContext): ?>
              <p class="small text-muted mt-2 mb-0">Supervisando: <?= e(trim((string)$navbarCompanyContext['nombre'].' '.(string)$navbarCompanyContext['apellido'])) ?></p>
            <?php endif; ?>
          </div>
          <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#accountSettings"><i class="mdi mdi-account-cog-outline me-2"></i>Configuración de cuenta</button>
          <form method="post" action="<?= e(app_url('src/login/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start">
              <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Cerrar sesión
            </button>
          </form>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-sietelsa-toggle="offcanvas" aria-label="Abrir menú" aria-controls="sidebar">
      <span class="mdi mdi-menu"></span>
    </button>
  </div>
</nav>
<?php require SIETELSA_ROOT . '/src/dashboard/profile/_account-modal.php'; ?>
