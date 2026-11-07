<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/modulos.php';
require_once __DIR__ . '/models/EmpresaModel.php';

requireLogin();

$session = sessionData();
$idUsuario = dte_current_user_id();
$modulos = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_dashboard'] ?? true) === true && dte_modulo_habilitado($modulo)
);
$empresas = EmpresaModel::getByUsuario($pdo, $idUsuario);
$empresaActiva = null;
$idEmpresaActiva = getActiveEmpresaId();

if ($idEmpresaActiva !== null) {
    $empresaActiva = EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario);
}

if ($empresaActiva === null && $empresas !== []) {
    $empresaActiva = EmpresaModel::getUltimaUsada($pdo, $idUsuario) ?: $empresas[0];
    if ($empresaActiva) {
        setActiveEmpresaId((int) $empresaActiva['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Modulo DTE - <?php echo htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="<?php echo htmlspecialchars(parent_app_url('assets/images/favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
      .dte-home-card {
        border: 1px solid rgba(31, 53, 82, 0.08);
        border-radius: 1rem;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
      }
      .dte-home-icon {
        width: 52px;
        height: 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        font-size: 1.3rem;
        background: rgba(75, 73, 172, 0.1);
        color: #4b49ac;
      }
    </style>
  </head>
  <body>
    <div class="container-scroller">
      <?php require __DIR__ . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require __DIR__ . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card dte-home-card">
                  <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                      <div>
                        <h3 class="mb-2">Modulo DTE listo para integrarse al SaaS</h3>
                        <p class="text-muted mb-0">
                          Esta extraccion trabaja sobre tablas `dte_*` y reutiliza la sesion principal del SaaS mediante `dte_user_bridge`.
                        </p>
                      </div>
                      <div class="text-lg-end">
                        <div class="text-muted small">Usuario DTE</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($session['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-muted small">
                          Empresa activa:
                          <strong><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Sin seleccionar'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <?php foreach ($modulos as $modulo): ?>
              <div class="col-md-6 col-xl-3 mb-4">
                <div class="card dte-home-card h-100">
                  <div class="card-body d-flex flex-column">
                    <div class="dte-home-icon mb-3">
                      <i class="<?php echo htmlspecialchars((string) ($modulo['icono'] ?? 'icon-layout'), ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <h4 class="mb-2"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="text-muted flex-grow-1"><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="small text-muted mb-3">Tipos DTE: <?php echo htmlspecialchars((string) ($modulo['tipos_validos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(app_url((string) ($modulo['ruta'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                      Abrir modulo
                    </a>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="row">
              <div class="col-12">
                <div class="card dte-home-card">
                  <div class="card-body">
                    <h5 class="mb-3">Estado de migracion</h5>
                    <p class="text-muted mb-2">
                      Los libros, empresas y facturas del modulo viven en tablas separadas para no mezclar el DTE con el resto del SaaS.
                    </p>
                    <p class="text-muted mb-0">
                      Si aun no has ejecutado `database/postgresql/dte_schema.sql`, hazlo antes de importar documentos JSON.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php require __DIR__ . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
  </body>
</html>
