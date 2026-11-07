<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/analytics/bootstrap.php';

$currentUser = require_dashboard_module('admin_dashboard');
$analyticsService = new AnalyticsService(db());
$filters = $analyticsService->filters($_GET);
$summary = $analyticsService->dashboardSummary($filters);
$latestActions = $analyticsService->latestAdministrativeActions();
$latestUsers = $analyticsService->latestUsers();
$latestMessages = $analyticsService->latestContactMessages();
$latestBackups = $analyticsService->latestBackups();
$pageTitle = 'Panel administrativo | SIETELSA';
$dashboardIncludeAnalytics = true;
define('SIETELSA_DASHBOARD_RENDER', true);

$cards = [
    ['key' => 'total_users', 'label' => 'Total de usuarios', 'detail' => 'Cuentas registradas', 'icon' => 'mdi-account-group-outline', 'tone' => 'primary'],
    ['key' => 'active_users', 'label' => 'Usuarios activos', 'detail' => 'Acceso habilitado', 'icon' => 'mdi-account-check-outline', 'tone' => 'success'],
    ['key' => 'inactive_users', 'label' => 'Usuarios desactivados', 'detail' => 'Acceso deshabilitado', 'icon' => 'mdi-account-off-outline', 'tone' => 'danger'],
    ['key' => 'new_users', 'label' => 'Usuarios nuevos', 'detail' => 'En el periodo', 'icon' => 'mdi-account-plus-outline', 'tone' => 'info'],
    ['key' => 'new_contact_messages', 'label' => 'Mensajes nuevos', 'detail' => 'Sin leer en el periodo', 'icon' => 'mdi-email-alert-outline', 'tone' => 'warning'],
    ['key' => 'pending_contact_messages', 'label' => 'Pendientes de respuesta', 'detail' => 'En el periodo', 'icon' => 'mdi-email-fast-outline', 'tone' => 'danger'],
    ['key' => 'answered_contact_messages', 'label' => 'Mensajes respondidos', 'detail' => 'En el periodo', 'icon' => 'mdi-email-check-outline', 'tone' => 'success'],
    ['key' => 'visits', 'label' => 'Visitas al sitio', 'detail' => 'Medición real del periodo', 'icon' => 'mdi-web', 'tone' => 'primary'],
];
$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / 1048576, 1) . ' MB';
};
?>
<!DOCTYPE html>
<html lang="es">
  <?php require __DIR__ . '/partials/_head.php'; ?>
  <body class="with-welcome-text">
    <div class="container-scroller">
      <?php require __DIR__ . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require __DIR__ . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row">
              <div class="col-sm-12">
                <div class="home-tab">
                  <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                    <ul class="nav nav-tabs" role="list">
                      <li class="nav-item"><a class="nav-link active ps-0" href="#overview" aria-current="page">Resumen</a></li>
                      <li class="nav-item"><a class="nav-link" href="#activity-chart">Actividad</a></li>
                      <li class="nav-item"><a class="nav-link border-0" href="#recent-activity">Últimos registros</a></li>
                    </ul>
                    <div class="btn-wrapper" aria-label="Accesos rápidos">
                      <a class="btn btn-outline-dark" href="<?= e(app_url('src/dashboard/users/index.php')) ?>"><i class="mdi mdi-account-group-outline"></i> Usuarios</a>
                      <a class="btn btn-outline-dark" href="<?= e(app_url('src/dashboard/mail/index.php')) ?>"><i class="mdi mdi-email-outline"></i> Mensajes</a>
                      <a class="btn btn-primary text-white me-0" href="<?= e(app_url('src/dashboard/backups/index.php')) ?>"><i class="mdi mdi-backup-restore"></i> Copias</a>
                    </div>
                  </div>
                  <div class="tab-content tab-content-basic">
                    <div class="tab-pane fade show active" id="overview">
            <form id="analytics-filters" class="card card-rounded mb-4" data-endpoint="<?= e(app_url('src/dashboard/api/analytics.php')) ?>">
              <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div>
                    <h3 class="card-title mb-1">Periodo de análisis</h3>
                    <p class="text-muted small mb-0">El país y el dispositivo se aplican solamente a las visitas.</p>
                  </div>
                </div>
                <div class="row g-3 align-items-end">
                  <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="period">Periodo</label>
                    <select class="form-select" id="period" name="period">
                      <?php foreach (['today' => 'Hoy', '7days' => 'Últimos 7 días', '30days' => 'Últimos 30 días', 'month' => 'Mes actual', 'year' => 'Año actual', 'custom' => 'Rango personalizado'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $filters['period'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-xl-2 sietelsa-custom-date">
                    <label class="form-label" for="from">Desde</label>
                    <input class="form-control" id="from" name="from" type="date" value="<?= e($filters['from']) ?>" max="<?= e(date('Y-m-d')) ?>">
                  </div>
                  <div class="col-sm-6 col-xl-2 sietelsa-custom-date">
                    <label class="form-label" for="to">Hasta</label>
                    <input class="form-control" id="to" name="to" type="date" value="<?= e($filters['to']) ?>" max="<?= e(date('Y-m-d')) ?>">
                  </div>
                  <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="country">País</label>
                    <select class="form-select" id="country" name="country">
                      <option value="">Todos</option>
                      <option value="UNKNOWN">Desconocido</option>
                      <?php
                      $countryStatement = db()->prepare(
                          'SELECT DISTINCT pais_codigo FROM visitas_website
                           WHERE pais_codigo IS NOT NULL ORDER BY pais_codigo'
                      );
                      $countryStatement->execute();
                      foreach ($countryStatement->fetchAll(PDO::FETCH_COLUMN) as $country):
                      ?>
                        <option value="<?= e((string) $country) ?>"<?= $filters['country'] === $country ? ' selected' : '' ?>><?= e((string) $country) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="device">Dispositivo</label>
                    <select class="form-select" id="device" name="device">
                      <option value="">Todos</option>
                      <?php foreach (['escritorio' => 'Escritorio', 'movil' => 'Móvil', 'tablet' => 'Tablet', 'otro' => 'Otro'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $filters['device'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-xl-2">
                    <button class="btn btn-primary text-white w-100" type="submit">
                      <i class="mdi mdi-filter-outline me-1"></i>Aplicar
                    </button>
                  </div>
                </div>
              </div>
            </form>

            <div class="statistics-details sietelsa-dashboard-statistics d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
              <?php foreach (array_slice($cards, 0, 6) as $card): ?>
                <div>
                  <p class="statistics-title"><?= e($card['label']) ?></p>
                  <h3 class="rate-percentage" data-stat="<?= e($card['key']) ?>"><?= (int) $summary[$card['key']] ?></h3>
                  <p class="text-<?= e($card['tone']) ?> d-flex align-items-center mb-0">
                    <i class="mdi <?= e($card['icon']) ?> me-1" aria-hidden="true"></i><span><?= e($card['detail']) ?></span>
                  </p>
                </div>
              <?php endforeach; ?>
            </div>

            <div id="analytics-status" class="alert alert-info d-none" role="status"></div>
            <div class="row" id="recent-activity">
              <div class="col-xl-8 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <h3 class="card-title">Actividad por fecha</h3>
                    <p class="text-muted small">Visitas, usuarios nuevos e inicios de sesión agregados por día.</p>
                    <div class="sietelsa-chart-wrap"><canvas id="activity-chart"></canvas></div>
                  </div>
                </div>
              </div>
              <div class="col-xl-4 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <h3 class="card-title">Dispositivos</h3>
                    <div class="sietelsa-chart-wrap"><canvas id="device-chart"></canvas></div>
                  </div>
                </div>
              </div>
              <div class="col-12 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <h3 class="card-title">Páginas más visitadas</h3>
                    <div class="sietelsa-chart-wrap"><canvas id="pages-chart"></canvas></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-xl-6 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h3 class="card-title mb-0">Últimos usuarios registrados</h3>
                      <a href="<?= e(app_url('src/dashboard/users/index.php')) ?>">Ver usuarios</a>
                    </div>
                    <?php if ($latestUsers === []): ?>
                      <div class="sietelsa-empty-state"><i class="mdi mdi-account-off-outline" aria-hidden="true"></i><p class="mb-0">Aún no hay usuarios registrados.</p></div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm align-middle">
                          <thead><tr><th>Usuario</th><th>Estado</th><th>Registro</th></tr></thead>
                          <tbody>
                            <?php foreach ($latestUsers as $user): ?>
                              <tr>
                                <td><a href="<?= e(app_url('src/dashboard/users/view.php?id=' . (int) $user['id'])) ?>"><?= e(trim((string) $user['nombre'] . ' ' . (string) $user['apellido']) ?: (string) $user['correo']) ?></a><small class="d-block text-muted"><?= e((string) $user['rol']) ?></small></td>
                                <td><span class="badge sietelsa-status-<?= e((string) $user['estado']) ?>"><?= e(ucfirst((string) $user['estado'])) ?></span></td>
                                <td><time datetime="<?= e((string) $user['creado_en']) ?>"><?= e(date('d/m/Y', strtotime((string) $user['creado_en']))) ?></time></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-xl-6 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h3 class="card-title mb-0">Últimos mensajes recibidos</h3>
                      <a href="<?= e(app_url('src/dashboard/mail/index.php')) ?>">Ver mensajes</a>
                    </div>
                    <?php if ($latestMessages === []): ?>
                      <div class="sietelsa-empty-state"><i class="mdi mdi-email-off-outline" aria-hidden="true"></i><p class="mb-0">No hay mensajes de contacto recibidos.</p></div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm align-middle">
                          <thead><tr><th>Mensaje</th><th>Seguimiento</th><th>Fecha</th></tr></thead>
                          <tbody>
                            <?php foreach ($latestMessages as $message): ?>
                              <tr>
                                <td><a href="<?= e(app_url('src/dashboard/mail/view.php?id=' . (int) $message['id'])) ?>"><?= e((string) $message['asunto']) ?></a><small class="d-block text-muted"><?= e((string) ($message['remitente_nombre'] ?: $message['remitente_correo'])) ?></small></td>
                                <td><span class="badge badge-opacity-<?= (int) $message['respondido'] === 1 ? 'success' : 'warning' ?>"><?= (int) $message['respondido'] === 1 ? 'Respondido' : 'Pendiente' ?></span></td>
                                <td><time datetime="<?= e((string) $message['fecha']) ?>"><?= e(date('d/m/Y', strtotime((string) $message['fecha']))) ?></time></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-xl-5 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h3 class="card-title mb-0">Copias de seguridad recientes</h3>
                      <a href="<?= e(app_url('src/dashboard/backups/index.php')) ?>">Administrar</a>
                    </div>
                    <?php if ($latestBackups === []): ?>
                      <div class="sietelsa-empty-state"><i class="mdi mdi-backup-restore" aria-hidden="true"></i><p class="mb-0">Todavía no se han generado copias de seguridad.</p></div>
                    <?php else: ?>
                      <div class="sietelsa-compact-list">
                        <?php foreach ($latestBackups as $backup): ?>
                          <div class="sietelsa-compact-item">
                            <span class="mdi mdi-backup-restore text-primary" aria-hidden="true"></span>
                            <div class="flex-grow-1">
                              <strong><?= e(ucfirst((string) $backup['tipo'])) ?></strong>
                              <span class="badge badge-opacity-<?= $backup['estado'] === 'completado' ? 'success' : ($backup['estado'] === 'fallido' ? 'danger' : 'warning') ?> float-end"><?= e(ucfirst((string) $backup['estado'])) ?></span>
                              <p class="small text-muted mb-0"><?= e($formatBytes((int) $backup['tamano_bytes'])) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $backup['creado_en']))) ?></p>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-xl-7 grid-margin stretch-card">
                <div class="card card-rounded">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h3 class="card-title mb-0">Actividad administrativa reciente</h3>
                      <a href="<?= e(app_url('src/dashboard/notifications/index.php')) ?>">Ver notificaciones</a>
                    </div>
                    <?php if ($latestActions === []): ?>
                      <div class="sietelsa-empty-state"><i class="mdi mdi-history" aria-hidden="true"></i><p class="mb-0">No existe actividad administrativa registrada.</p></div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm">
                          <thead><tr><th>Acción</th><th>Responsable</th><th>Fecha</th></tr></thead>
                          <tbody>
                            <?php foreach ($latestActions as $action): ?>
                              <tr>
                                <td><?= e(str_replace('_', ' ', ucfirst((string) $action['evento']))) ?></td>
                                <td><?= e((string) ($action['responsable_nombre'] ?? 'Sistema')) ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $action['creado_en']))) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <p class="small text-muted">
              Las estadísticas se recopilan desde
              <strong id="analytics-available-since">la activación de esta funcionalidad</strong>.
              Los visitantes únicos son una estimación anónima.
            </p>
            <?php
            // Widgets, reportes y tareas originales de Star Admin permanecen
            // fuera del render porque contenían cifras y acciones demostrativas.
            ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php require __DIR__ . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require __DIR__ . '/partials/_scripts.php'; ?>
  </body>
</html>
