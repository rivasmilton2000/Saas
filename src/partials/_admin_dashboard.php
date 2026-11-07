<?php
$adminDashboard = is_array($data['admin_dashboard'] ?? null) ? $data['admin_dashboard'] : [];
$adminSummary = $adminDashboard['summary'] ?? [];
$adminPlans = $adminDashboard['plans'] ?? [];
$adminCountries = $adminDashboard['countries'] ?? [];
$adminRoutes = $adminDashboard['routes'] ?? [];
$backupOverview = is_array($backupDashboard['overview'] ?? null) ? $backupDashboard['overview'] : [];
$backupToday = $backupOverview['today_backup'] ?? null;
$backupLatest = $backupOverview['latest_backup'] ?? null;
?>
<div class="row mb-4">
  <div class="col-12">
    <div class="card admin-hub-hero">
      <div class="card-body">
        <div class="admin-hub-grid">
          <div>
            <span class="admin-hub-kicker"><i class="mdi mdi-shield-account-outline"></i> Panel administrativo Zentra</span>
            <h2 class="admin-hub-title">Supervisa cuentas, membresias y uso real de la plataforma</h2>
            <p class="admin-hub-copy">
              Este espacio concentra altas de cuentas, actividad por membresia, visitas recientes, paises con uso y accesos frecuentes sin mezclarlo con los modulos operativos de los clientes.
            </p>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#perfilModal">
                <i class="mdi mdi-account-plus-outline me-1"></i> Crear cuenta
              </button>
              <a href="pages/bitacora.php" class="btn btn-outline-primary">
                <i class="mdi mdi-history me-1"></i> Ver bitacora
              </a>
              <a href="pages/backups.php" class="btn btn-outline-secondary">
                <i class="mdi mdi-database-lock-outline me-1"></i> Backups
              </a>
            </div>
          </div>
          <div class="admin-hub-side">
            <div class="admin-hub-side-card">
              <span>Backup diario</span>
              <strong><?php echo $backupToday ? 'Listo hoy' : 'Pendiente'; ?></strong>
              <small>
                <?php if ($backupLatest): ?>
                  Ultimo: <?php echo htmlspecialchars((string) ($backupLatest['created_at_label'] ?? '-')); ?>
                <?php else: ?>
                  Aun no hay respaldo reciente para mostrar.
                <?php endif; ?>
              </small>
            </div>
            <div class="admin-hub-side-card">
              <span>Membresias activas</span>
              <strong><?php echo count($adminPlans); ?></strong>
              <small>Free, Light, Pro, Ultra y Enterprise cargados desde el documento.</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Clientes</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['customer_users'] ?? 0); ?></strong>
        <span class="admin-summary-note">Cuentas con rol usuario</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Admins</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['admin_users'] ?? 0); ?></strong>
        <span class="admin-summary-note">Cuentas internas de control</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Visitas 30d</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['page_visits_30d'] ?? 0); ?></strong>
        <span class="admin-summary-note">Paginas abiertas en la app</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Activos 7d</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['active_users_7d'] ?? 0); ?></strong>
        <span class="admin-summary-note">Usuarios cliente con actividad reciente</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Frecuentes 30d</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['frequent_users_30d'] ?? 0); ?></strong>
        <span class="admin-summary-note">Cinco o mas visitas recientes</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2 grid-margin stretch-card">
    <div class="card admin-summary-card">
      <div class="card-body">
        <span class="admin-summary-label">Paises 30d</span>
        <strong class="admin-summary-value"><?php echo (int) ($adminSummary['active_countries_30d'] ?? 0); ?></strong>
        <span class="admin-summary-note">Actividad geolocalizada por perfil</span>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-xl-7 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h4 class="card-title mb-1">Usuarios por membresia</h4>
            <p class="card-description mb-0">Precios y limites cargados segun el documento de Zentra.</p>
          </div>
        </div>
        <?php if ($adminPlans !== []): ?>
        <div class="admin-plan-grid">
          <?php foreach ($adminPlans as $plan): ?>
          <?php
            $limitParts = [];
            if (!empty($plan['limite_empresas'])) {
                $limitParts[] = (int) $plan['limite_empresas'] . ' empresas';
            }
            if (!empty($plan['limite_usuarios'])) {
                $limitParts[] = (int) $plan['limite_usuarios'] . ' usuarios';
            }
            if (!empty($plan['limite_documentos'])) {
                $limitParts[] = number_format((int) $plan['limite_documentos']) . ' docs';
            }
          ?>
          <div class="admin-plan-card <?php echo !empty($plan['destacado']) ? 'is-featured' : ''; ?>">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <span class="admin-plan-name"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></span>
                <strong class="admin-plan-price"><?php echo htmlspecialchars((string) ($plan['precio_label'] ?? 'Personalizado')); ?></strong>
              </div>
              <span class="admin-plan-count"><?php echo (int) ($plan['usuarios_total'] ?? 0); ?> usuarios</span>
            </div>
            <p class="admin-plan-copy"><?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?></p>
            <div class="admin-plan-meta">
              <span><i class="mdi mdi-chart-line me-1"></i><?php echo (int) ($plan['usuarios_activos_7d'] ?? 0); ?> activos 7d</span>
              <span><i class="mdi mdi-cursor-default-click-outline me-1"></i><?php echo (int) ($plan['visitas_30d'] ?? 0); ?> visitas 30d</span>
            </div>
            <div class="admin-plan-limits">
              <?php echo htmlspecialchars($limitParts !== [] ? implode(' | ', $limitParts) : 'Capacidad personalizada'); ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">Todavia no hay membresias activas configuradas.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-5 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title mb-1">Actividad por pais</h4>
        <p class="card-description">Resumen de usuarios cliente y visitas durante los ultimos 30 dias.</p>
        <?php if ($adminCountries !== []): ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Pais</th>
                <th>Usuarios</th>
                <th>Activos</th>
                <th>Visitas</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($adminCountries as $country): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) ($country['country'] ?? 'Sin pais')); ?></td>
                <td><?php echo (int) ($country['total_users'] ?? 0); ?></td>
                <td><?php echo (int) ($country['active_users_30d'] ?? 0); ?></td>
                <td><?php echo (int) ($country['total_visits'] ?? 0); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">Aun no hay actividad suficiente para construir el ranking por pais.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title mb-1">Paginas mas visitadas</h4>
        <p class="card-description">Rutas con mayor movimiento dentro de la aplicacion durante los ultimos 30 dias.</p>
        <?php if ($adminRoutes !== []): ?>
        <div class="row">
          <?php foreach ($adminRoutes as $route): ?>
          <div class="col-md-6 col-xl-3 mb-3">
            <div class="admin-route-card">
              <span><?php echo htmlspecialchars((string) ($route['route_label'] ?? 'Pagina')); ?></span>
              <strong><?php echo (int) ($route['total_visits'] ?? 0); ?> visitas</strong>
              <small><?php echo (int) ($route['unique_users'] ?? 0); ?> usuarios unicos</small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">Todavia no se registran visitas para mostrar las rutas mas usadas.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
