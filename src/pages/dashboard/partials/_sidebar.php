<?php

declare(strict_types=1);

if (!defined('SIETELSA_DASHBOARD_RENDER')) {
    http_response_code(404);
    exit;
}

require_once SIETELSA_ROOT . '/includes/dashboard/modules.php';

$dashboardUser = $currentUser ?? auth_user();
$isAdmin = is_array($dashboardUser) && $dashboardUser['rol'] === 'administrador';
$availableModules = is_array($dashboardUser) ? dashboard_modules_for_user($dashboardUser) : [];
$bookModuleIds = ['book_purchases', 'book_consumer_sales', 'book_taxpayer_sales', 'book_vat_withholding'];
$dteModuleIds = ['dte_dashboard', 'dte_supervision', 'dte_audit'];
if (!$isAdmin) {
    $priority = ['user_portal' => 0, 'companies' => 1];
    usort($availableModules, static fn(array $a, array $b): int => ($priority[$a['id']] ?? 20) <=> ($priority[$b['id']] ?? 20));
}
$requestPath = str_replace('\\', '/', (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
$relativePath = substr($requestPath, strlen(rtrim(app_base_url(), '/')));
$booksOpen = str_starts_with($relativePath, '/src/dashboard/books/');
$dteActive = $isAdmin && str_starts_with($relativePath, '/src/dashboard/dte/');
$dteOpen = $dteActive || $booksOpen;
?>
<nav class="sidebar sietelsa-sidebar sidebar-offcanvas" id="sidebar" aria-label="Módulos de SIETELSA">
  <ul class="nav">
    <?php foreach ($availableModules as $moduleIndex => $module): ?>
      <?php if (in_array((string)$module['id'], $bookModuleIds, true)) continue; ?>
      <?php if ($isAdmin && (in_array((string)$module['id'], $dteModuleIds, true) || $module['id'] === 'companies')) continue; ?>
      <?php if ($moduleIndex === 1): ?>
        <li class="nav-item nav-category"><?= $isAdmin ? 'Administración' : 'Mi cuenta' ?></li>
      <?php endif; ?>
      <?php if (!$isAdmin && $module['id'] === 'book_workspace'): ?>
        <li class="nav-item nav-category">Libros DTE</li>
      <?php endif; ?>
      <?php $moduleActive = dashboard_module_is_active($module); ?>
      <li class="nav-item<?= $moduleActive ? ' active' : '' ?>">
        <a
          class="nav-link"
          href="<?= e(dashboard_module_url($module)) ?>"
          title="<?= e((string) $module['name']) ?>"
          <?= $moduleActive ? 'aria-current="page"' : '' ?>
        >
          <i class="menu-icon <?= e((string) $module['icon']) ?>" aria-hidden="true"></i>
          <span class="menu-title"><?= e(!$isAdmin && $module['id'] === 'user_portal' ? 'Dashboard' : (string)$module['name']) ?></span>
        </a>
      </li>
      <?php if($module['id']==='book_workspace'): $existingActive=$booksOpen&&!($relativePath==='/src/dashboard/books/index.php'&&($_GET['create']??'')==='1'); ?>
      <li class="nav-item<?= $existingActive?' active':'' ?>"><a class="nav-link" href="<?= e(app_url('src/dashboard/books/index.php?list=1#saved-books')) ?>" <?= $existingActive?'aria-current="page"':'' ?>><i class="menu-icon mdi mdi-book-open-outline" aria-hidden="true"></i><span class="menu-title">Libros existentes</span></a></li>
      <?php endif; ?>
      <?php if ($isAdmin && $module['id'] === 'users'): ?>
        <li class="nav-item">
          <a class="nav-link<?= $dteOpen ? '' : ' collapsed' ?>" data-bs-toggle="collapse" href="#dte-menu" aria-expanded="<?= $dteOpen ? 'true' : 'false' ?>" aria-controls="dte-menu" title="DTE">
            <i class="menu-icon mdi mdi-file-document-multiple-outline" aria-hidden="true"></i>
            <span class="menu-title">DTE</span>
            <i class="menu-arrow" aria-hidden="true"></i>
          </a>
          <div class="collapse<?= $dteOpen ? ' show' : '' ?>" id="dte-menu">
            <ul class="nav flex-column sub-menu">
              <?php foreach ($dteModuleIds as $dteId): $dteModule = dashboard_module($dteId); if (!$dteModule) continue; $dteItemActive = dashboard_module_is_active($dteModule); ?>
                <li class="nav-item"><a class="nav-link<?= $dteItemActive ? ' active' : '' ?>" href="<?= e(dashboard_module_url($dteModule)) ?>" <?= $dteItemActive ? 'aria-current="page"' : '' ?>><i class="<?= e((string)$dteModule['icon']) ?> me-2" aria-hidden="true"></i><span><?= e((string)$dteModule['name']) ?></span></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </li>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php
    // Componentes de demostración de la plantilla (UI, formularios, gráficas,
    // tablas, iconos y documentación).
    // Ocultos porque todavía no tienen una funcionalidad real en SIETELSA.
    ?>
    <li class="nav-item">
      <form method="post" action="<?= e(app_url('src/login/logout.php')) ?>" class="w-100">
        <?= csrf_field() ?>
        <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start" title="Cerrar sesión">
          <i class="menu-icon mdi mdi-power" aria-hidden="true"></i>
          <span class="menu-title">Cerrar sesión</span>
        </button>
      </form>
    </li>
  </ul>
</nav>
