<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$type = preg_match('/^[a-z0-9_]{1,60}$/', (string) ($_GET['type'] ?? '')) === 1
    ? (string) $_GET['type']
    : '';
$notifications = $notificationService->list($type);
$types = $notificationService->types();
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
          <div class="content-wrapper">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
              <div>
                <h2 class="mb-1">Notificaciones</h2>
                <p class="text-muted mb-0">Historial de eventos administrativos importantes.</p>
              </div>
              <form method="post" action="<?= e(app_url('src/dashboard/notifications/action.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <button class="btn btn-outline-primary" type="submit">
                  <i class="mdi mdi-check-all me-1"></i>Marcar todas como leídas
                </button>
              </form>
            </div>

            <div class="card card-rounded mb-4">
              <div class="card-body">
                <form class="row g-3 align-items-end" method="get">
                  <div class="col-sm-8 col-lg-4">
                    <label class="form-label" for="notification-type">Filtrar por tipo</label>
                    <select class="form-select" id="notification-type" name="type">
                      <option value="">Todos los tipos</option>
                      <?php foreach ($types as $availableType): ?>
                        <option value="<?= e((string) $availableType['tipo']) ?>"<?= $type === $availableType['tipo'] ? ' selected' : '' ?>>
                          <?= e($notificationTypeLabel((string) $availableType['tipo'])) ?> (<?= (int) $availableType['total'] ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-4">
                    <button class="btn btn-primary text-white" type="submit">Aplicar filtro</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="card card-rounded">
              <div class="card-body">
                <?php if ($notifications === []): ?>
                  <div class="sietelsa-empty-state">
                    <i class="mdi mdi-bell-off-outline" aria-hidden="true"></i>
                    <p class="mb-0">No existen notificaciones para este filtro.</p>
                  </div>
                <?php else: ?>
                  <div class="sietelsa-notification-list">
                    <?php foreach ($notifications as $notification): ?>
                      <article class="sietelsa-notification-item<?= $notification['leida_en'] === null ? ' is-unread' : '' ?>">
                        <div class="flex-grow-1">
                          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="badge badge-opacity-primary"><?= e($notificationTypeLabel((string) $notification['tipo'])) ?></span>
                            <?php if ($notification['leida_en'] === null): ?>
                              <span class="badge badge-opacity-warning">No leída</span>
                            <?php endif; ?>
                          </div>
                          <h3 class="h6 mb-1"><?= e((string) $notification['titulo']) ?></h3>
                          <p class="text-muted mb-1"><?= e((string) $notification['mensaje']) ?></p>
                          <time class="small text-muted" datetime="<?= e((string) $notification['creado_en']) ?>">
                            <?= e(date('d/m/Y H:i', strtotime((string) $notification['creado_en']))) ?>
                          </time>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                          <?php if ($notification['ruta_modulo'] !== null): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url((string) $notification['ruta_modulo'])) ?>">Abrir</a>
                          <?php endif; ?>
                          <?php if ($notification['leida_en'] === null): ?>
                            <form method="post" action="<?= e(app_url('src/dashboard/notifications/action.php')) ?>">
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="read">
                              <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                              <button class="btn btn-sm btn-primary text-white" type="submit">Marcar leída</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
  </body>
</html>
