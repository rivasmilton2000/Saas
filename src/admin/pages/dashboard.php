<?php
  declare(strict_types=1);

  require_once __DIR__ . '/../../services/AdminDashboardService.php';

  $data = AdminDashboardService::build($pdo);

  $summary = $data['summary'] ?? [];
  $plans = $data['plans'] ?? [];
  $countries = $data['countries'] ?? [];
  $routes = $data['routes'] ?? [];

  function adminNumber($value): string
  {
      return number_format((int) $value);
  }
  ?>

  <div class="content-wrapper">
    <div class="row mb-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h3 class="font-weight-bold mb-1">Dashboard administrativo</h3>
            <p class="text-muted mb-0">Resumen general del SaaS Zentra.</p>
          </div>

          <a href="<?php echo htmlspecialchars($adminBaseUrl . '?page=users'); ?>" class="btn btn-primary">
            Gestionar usuarios
          </a>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 col-xl-3 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <p class="text-muted mb-1">Clientes</p>
            <h3 class="mb-0"><?php echo adminNumber($summary['customer_users'] ?? 0); ?></h3>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <p class="text-muted mb-1">Administradores</p>
            <h3 class="mb-0"><?php echo adminNumber($summary['admin_users'] ?? 0); ?></h3>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <p class="text-muted mb-1">Usuarios activos 7 días</p>
            <h3 class="mb-0"><?php echo adminNumber($summary['active_users_7d'] ?? 0); ?></h3>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <p class="text-muted mb-1">Visitas 30 días</p>
            <h3 class="mb-0"><?php echo adminNumber($summary['page_visits_30d'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Planes</h4>

            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Plan</th>
                    <th>Precio</th>
                    <th>Usuarios</th>
                    <th>Activos 7d</th>
                    <th>Visitas 30d</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($plans as $plan): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?></td>
                    <td><?php echo adminNumber($plan['usuarios_total'] ?? 0); ?></td>
                    <td><?php echo adminNumber($plan['usuarios_activos_7d'] ?? 0); ?></td>
                    <td><?php echo adminNumber($plan['visitas_30d'] ?? 0); ?></td>
                  </tr>
                  <?php endforeach; ?>

                  <?php if ($plans === []): ?>
                  <tr>
                    <td colspan="5" class="text-muted">No hay planes disponibles.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Actividad</h4>

            <div class="mb-3">
              <p class="text-muted mb-1">Usuarios frecuentes 30 días</p>
              <h4><?php echo adminNumber($summary['frequent_users_30d'] ?? 0); ?></h4>
            </div>

            <div>
              <p class="text-muted mb-1">Países activos 30 días</p>
              <h4><?php echo adminNumber($summary['active_countries_30d'] ?? 0); ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Países con actividad</h4>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>País</th>
                    <th>Usuarios</th>
                    <th>Visitas</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($countries as $country): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) ($country['pais'] ?? 'Sin país')); ?></td>
                    <td><?php echo adminNumber($country['usuarios'] ?? 0); ?></td>
                    <td><?php echo adminNumber($country['visitas'] ?? 0); ?></td>
                  </tr>
                  <?php endforeach; ?>

                  <?php if ($countries === []): ?>
                  <tr>
                    <td colspan="3" class="text-muted">No hay actividad registrada.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Rutas más visitadas</h4>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Ruta</th>
                    <th>Etiqueta</th>
                    <th>Visitas</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($routes as $route): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) ($route['route_key'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($route['route_label'] ?? '')); ?></td>
                    <td><?php echo adminNumber($route['visitas'] ?? 0); ?></td>
                  </tr>
                  <?php endforeach; ?>

                  <?php if ($routes === []): ?>
                  <tr>
                    <td colspan="3" class="text-muted">No hay rutas registradas.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>