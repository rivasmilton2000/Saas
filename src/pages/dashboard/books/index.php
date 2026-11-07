<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/accounting/bootstrap.php';

$currentUser = require_dashboard_module('book_workspace');
CompanyPermissionService::require($currentUser, 'book.view');
$companyContextUser = company_context_user($currentUser);
$companyExternalContext = company_context_is_external($currentUser, $companyContextUser);
$companyService = new CompanyService(db());
$activeCompany = $companyService->activeCompany($currentUser, $companyContextUser);
$companyResult = $companyService->list((int)$companyContextUser['id'], ['status'=>'activa'], 1, 100);
$companies = $companyResult['items'];
$accountingService = new AccountingService(db(), new BookAccessService(db()));
$allowedModuleIds = array_column(dashboard_modules_for_user($currentUser), 'id');
$bookTypes = array_filter(AccountingCatalog::modules(), static fn(array $type): bool => in_array($type['permission'], $allowedModuleIds, true));
if (!$bookTypes) sietelsa_abort(403);

$selectedType = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if ($selectedType !== '' && !isset($bookTypes[$selectedType])) sietelsa_abort(403);
$periodFilter = is_string($_GET['period'] ?? null) ? mb_substr(trim($_GET['period']), 0, 7) : '';
$stateFilter = in_array($_GET['state'] ?? '', ['borrador','activo','cerrado','archivado'], true) ? $_GET['state'] : '';
$order = ($_GET['order'] ?? '') === 'asc' ? 'asc' : 'desc';
$filters = ['type'=>$selectedType, 'period'=>$periodFilter, 'state'=>$stateFilter, 'order'=>$order];
$books = $activeCompany
    ? $accountingService->listBooks((int)$activeCompany['id'], $selectedType ?: null, (int)$companyContextUser['id'], ['q'=>$periodFilter,'state'=>$stateFilter,'order'=>$order,'types'=>array_keys($bookTypes)], max(1,(int)($_GET['page'] ?? 1)), 12)
    : ['items'=>[], 'total'=>0, 'page'=>1, 'pages'=>1];
$limits = $accountingService->limits();
$modulePeriods = $bookRoutes = [];
foreach ($bookTypes as $key => $type) {
    $bookRoutes[$key] = app_url('src/dashboard/books/' . $type['route']);
    $modulePeriods[$key] = [];
    if ($activeCompany) {
        foreach ($accountingService->periods((int)$activeCompany['id'], $key, (int)$companyContextUser['id']) as $period) {
            $modulePeriods[$key][sprintf('%04d-%02d', (int)$period['periodo_anio'], (int)$period['periodo_mes'])] = $period;
        }
    }
}
$old = $_SESSION['workspace_create_old'] ?? [];
unset($_SESSION['workspace_create_old']);
if (!is_array($old) || (int)($old['company_id'] ?? 0) !== (int)($activeCompany['id'] ?? 0) || (int)($old['context_user_id'] ?? 0) !== (int)$companyContextUser['id']) $old = [];
$createType = isset($bookTypes[$old['module'] ?? '']) ? $old['module'] : ($selectedType ?: array_key_first($bookTypes));
$createYear = (int)($old['year'] ?? max($limits['min'], min($limits['max'], (int)date('Y'))));
$createMonth = (int)($old['month'] ?? date('n'));
$canCreate = CompanyPermissionService::can($currentUser, 'book.create');
$pageTitle = 'Libros | SIETELSA';
$dashboardIncludeAnalytics = false;
$dashboardPageScripts = ['includes/assets/js/accounting-books.js'];
$dashboardFlash = flash_pull();
$stateLabels = ['borrador'=>'Borrador', 'activo'=>'Activo', 'cerrado'=>'Cerrado', 'archivado'=>'Archivado'];
define('SIETELSA_DASHBOARD_RENDER', true);
?>
<!DOCTYPE html>
<html lang="es">
<?php require dirname(__DIR__) . '/partials/_head.php'; ?>
<body class="with-welcome-text">
<div class="container-scroller">
  <?php require dirname(__DIR__) . '/partials/_navbar.php'; ?>
  <div class="container-fluid page-body-wrapper">
    <?php require dirname(__DIR__) . '/partials/_sidebar.php'; ?>
    <div class="main-panel">
      <main class="content-wrapper">
        <?php if ($companyExternalContext): ?>
          <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><strong>Contexto administrativo activo.</strong> Libros de <?= e(trim($companyContextUser['nombre'].' '.$companyContextUser['apellido'])) ?>.</div>
            <form method="post" action="<?= e(app_url('src/dashboard/companies/context.php')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="exit">
              <button class="btn btn-warning" type="submit">Salir del contexto del usuario</button>
            </form>
          </div>
        <?php endif; ?>
        <section class="card card-rounded mb-4">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
              <div>
                <p class="text-primary text-uppercase small fw-bold mb-1">Gestión de libros</p>
                <h1 class="h3 mb-2">Libros</h1>
                <p class="text-muted mb-0">Crea un libro por tipo y período o abre uno de los libros de tu empresa.</p>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="#saved-books"><i class="mdi mdi-book-open-outline me-1" aria-hidden="true"></i>Abrir libro</a>
                <?php if ($activeCompany && $canCreate): ?>
                  <button class="btn btn-primary text-white" type="button" data-bs-toggle="modal" data-bs-target="#create-book"><i class="mdi mdi-book-plus-outline me-1" aria-hidden="true"></i>Crear libro</button>
                <?php endif; ?>
              </div>
            </div>
            <hr>
            <?php if ($companies): ?>
              <form class="row g-2 align-items-end" method="post" action="<?= e(app_url('src/dashboard/books/action.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="module" value="<?= e(array_key_first($bookTypes)) ?>">
                <input type="hidden" name="action" value="activate_company">
                <input type="hidden" name="workspace" value="1">
                <input type="hidden" name="context_user_id" value="<?= (int)$companyContextUser['id'] ?>">
                <div class="col-md-8 col-xl-6">
                  <label class="form-label" for="workspace-company">Empresa activa</label>
                  <select class="form-select" name="company_id" id="workspace-company" required>
                    <option value="">Seleccionar empresa</option>
                    <?php foreach ($companies as $company): ?>
                      <option value="<?= (int)$company['id'] ?>" <?= (int)($activeCompany['id'] ?? 0) === (int)$company['id'] ? 'selected' : '' ?>><?= e($company['nombre_comercial']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-auto"><button class="btn btn-outline-primary" type="submit">Seleccionar empresa</button></div>
              </form>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-3 mt-3">
              <a href="<?= e(app_url('src/dashboard/companies/index.php')) ?>">Ver empresas</a>
              <?php if ($currentUser['rol'] === 'administrador'): ?>
                <a href="<?= e(app_url('src/dashboard/dte/supervision.php')) ?>">Supervisión DTE</a>
                <a href="<?= e(app_url('src/dashboard/companies/index.php')) ?>">Seleccionar contexto de usuario</a>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <section class="card card-rounded" id="saved-books" aria-labelledby="saved-books-title">
          <div class="card-body">
            <h2 class="h4" id="saved-books-title">Libros creados</h2>
            <?php if (!$activeCompany): ?>
              <div class="sietelsa-empty-state py-4">
                <i class="mdi mdi-domain" aria-hidden="true"></i>
                <h3 class="h5">Selecciona una empresa para continuar</h3>
                <p class="text-muted">Los libros se crean y se consultan dentro de la empresa seleccionada.</p>
                <?php if ($currentUser['rol'] === 'administrador' && !$companyExternalContext): ?>
                  <p class="text-muted">Para gestionar libros de otro usuario, selecciona su contexto desde Empresas. La supervisión global continúa disponible en Supervisión DTE.</p>
                <?php elseif (!$companies): ?>
                  <a class="btn btn-primary text-white" href="<?= e(app_url('src/dashboard/companies/index.php')) ?>">Crear o seleccionar empresa</a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <p class="text-muted text-break"><?= e($activeCompany['nombre_comercial']) ?> · <?= (int)$books['total'] ?> libro(s)</p>
              <form class="row g-2 mb-4" method="get" action="<?= e(app_url('src/dashboard/books/index.php')) ?>">
                <div class="col-md-6 col-xl-3"><label class="form-label" for="filter-type">Tipo de libro</label><select class="form-select" id="filter-type" name="type"><option value="">Todos los tipos</option><?php foreach ($bookTypes as $key=>$type): ?><option value="<?= e($key) ?>" <?= $selectedType===$key?'selected':'' ?>><?= e($type['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="filter-period">Período</label><input class="form-control" id="filter-period" name="period" type="month" value="<?= e($periodFilter) ?>"></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="filter-state">Estado</label><select class="form-select" id="filter-state" name="state"><option value="">Todos</option><?php foreach ($stateLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $stateFilter===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="filter-order">Orden</label><select class="form-select" id="filter-order" name="order"><option value="desc" <?= $order==='desc'?'selected':'' ?>>Más reciente</option><option value="asc" <?= $order==='asc'?'selected':'' ?>>Más antiguo</option></select></div>
                <div class="col-xl-3 d-flex flex-wrap align-items-end gap-2"><button class="btn btn-outline-primary" type="submit">Filtrar</button><a class="btn btn-light" href="<?= e(app_url('src/dashboard/books/index.php')) ?>">Limpiar</a></div>
              </form>
              <?php if (!$books['items']): ?>
                <div class="sietelsa-empty-state border rounded py-5">
                  <i class="mdi mdi-book-open-outline" aria-hidden="true"></i>
                  <h3 class="h5">No hay libros para esta selección</h3>
                  <p class="text-muted">Puedes cambiar los filtros o crear un libro para esta empresa.</p>
                  <?php if ($canCreate): ?><button class="btn btn-primary text-white" type="button" data-bs-toggle="modal" data-bs-target="#create-book">Crear libro</button><?php endif; ?>
                </div>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach ($books['items'] as $item): $type=$bookTypes[$item['tipo']]; ?>
                    <div class="col-md-6 col-xl-4">
                      <article class="card h-100 sietelsa-period-card <?= $item['estado']==='activo'?'is-open':'' ?>" data-workspace-book="<?= (int)$item['id'] ?>">
                        <div class="card-body d-flex flex-column">
                          <p class="text-primary small fw-semibold"><i class="<?= e($type['icon']) ?> me-1" aria-hidden="true"></i><?= e($type['name']) ?></p>
                          <h3 class="h5"><?= e(AccountingCatalog::monthName((int)$item['periodo_mes']).' '.$item['periodo_anio']) ?></h3>
                          <p class="text-muted small text-break"><?= e($activeCompany['nombre_comercial']) ?></p>
                          <span class="badge badge-opacity-primary align-self-start mb-3"><?= e($stateLabels[$item['estado']] ?? $item['estado']) ?></span>
                          <p class="small mb-3"><?= (int)$item['documentos'] ?> documento(s) · <strong>$<?= number_format((float)$item['total'],2,'.',',') ?></strong></p>
                          <a class="btn btn-outline-primary mt-auto" href="<?= e($bookRoutes[$item['tipo']].'?book='.(int)$item['id']) ?>">Abrir libro</a>
                        </div>
                      </article>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php if ($books['pages'] > 1): ?>
                  <nav class="d-flex flex-wrap justify-content-center align-items-center gap-3 mt-4" aria-label="Paginación de libros">
                    <?php if ($books['page'] > 1): ?><a class="btn btn-outline-primary" href="?<?= e(http_build_query($filters+['page'=>$books['page']-1])) ?>">Anterior</a><?php endif; ?>
                    <span>Página <?= $books['page'] ?> de <?= $books['pages'] ?></span>
                    <?php if ($books['page'] < $books['pages']): ?><a class="btn btn-outline-primary" href="?<?= e(http_build_query($filters+['page'=>$books['page']+1])) ?>">Siguiente</a><?php endif; ?>
                  </nav>
                <?php endif; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </section>
      </main>
      <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
    </div>
  </div>
</div>
<?php if ($activeCompany && $canCreate) require __DIR__ . '/_workspace-create.php'; ?>
<?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
</body>
</html>
