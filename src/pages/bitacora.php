<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../services/BitacoraService.php';
require_once __DIR__ . '/../services/PageVisitService.php';

requireLogin();
requireAdmin();

$session   = sessionData();
$idUsuario = (int) ($session['id_usuario'] ?? 0);
$esAdmin   = isAdmin();
$basePath  = '../';

$idEmpresaActiva = getActiveEmpresaId();
$empresaActivaNavbar = $idEmpresaActiva ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;
$filtroUsuario = $esAdmin ? (int) ($_GET['usuario'] ?? 0) : 0;
$bitacora = BitacoraService::obtenerVista($pdo, $idUsuario, $esAdmin, $filtroUsuario > 0 ? $filtroUsuario : null);
PageVisitService::track($pdo, $session, 'bitacora', 'Bitacora');

$ultimaActividadTexto = '-';
if (!empty($bitacora['resumen']['ultima_actividad'])) {
    try {
        $ultimaActividadTexto = (new DateTimeImmutable((string) $bitacora['resumen']['ultima_actividad'], new DateTimeZone('America/El_Salvador')))
            ->format('d/m/Y h:i A');
    } catch (Throwable $exception) {
        $ultimaActividadTexto = (string) $bitacora['resumen']['ultima_actividad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Bitacora - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <style>
      .bitacora-shell .card {
        border: 1px solid rgba(75, 73, 172, 0.08);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
      }

      .bitacora-hero {
        border-radius: 24px;
        background: linear-gradient(135deg, #ffffff 0%, #f5f7ff 55%, #eef2ff 100%);
      }

      .bitacora-hero .card-body {
        padding: 1.5rem;
      }

      .bitacora-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.8rem;
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .bitacora-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.95fr);
        gap: 1.25rem;
      }

      .bitacora-hero-title {
        margin: 0;
        color: #111827;
        font-size: 2rem;
        font-weight: 700;
      }

      .bitacora-hero-copy {
        margin: 0.75rem 0 0;
        max-width: 42rem;
        color: #64748b;
        font-size: 0.95rem;
      }

      .bitacora-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .bitacora-summary-card {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.92);
      }

      .bitacora-summary-card span {
        display: block;
        margin-bottom: 0.25rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .bitacora-summary-card strong {
        display: block;
        color: #111827;
        font-size: 1.4rem;
        font-weight: 700;
      }

      .bitacora-summary-card small {
        color: #64748b;
      }

      .bitacora-role {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .bitacora-group + .bitacora-group {
        margin-top: 1.25rem;
      }

      .bitacora-table-wrap {
        overflow: auto;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
      }

      .bitacora-table {
        min-width: 1080px;
        margin-bottom: 0;
      }

      .bitacora-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #1f2a44;
        color: #ffffff;
        border-bottom: 0;
        white-space: nowrap;
        font-size: 0.74rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .bitacora-table td {
        vertical-align: top;
        background: #ffffff;
      }

      .bitacora-table tbody tr:nth-child(even) td {
        background: #fafbfe;
      }

      .bitacora-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        min-height: 32px;
        padding: 0 0.75rem;
        border-radius: 999px;
        background: #f8f9fd;
        border: 1px solid #e6eaf3;
        color: #374151;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .bitacora-empty {
        padding: 2.5rem 1.5rem;
        text-align: center;
      }

      .bitacora-empty i {
        display: inline-flex;
        width: 72px;
        height: 72px;
        align-items: center;
        justify-content: center;
        border-radius: 24px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 1.8rem;
        margin-bottom: 1rem;
      }

      @media (max-width: 1199.98px) {
        .bitacora-hero-grid,
        .bitacora-summary {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <div class="container-scroller">
      <?php include __DIR__ . '/../partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/../partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper bitacora-shell">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card bitacora-hero">
                  <div class="card-body">
                    <div class="bitacora-hero-grid">
                      <div>
                        <span class="bitacora-kicker"><i class="mdi mdi-history"></i> Seguimiento de plataforma</span>
                        <h1 class="bitacora-hero-title">Bitacora de movimientos</h1>
                        <p class="bitacora-hero-copy">
                          <?php echo $esAdmin
                            ? 'Revisa la actividad de toda la plataforma y separala por usuario para auditar cambios, importaciones, exportaciones y sesiones.'
                            : 'Consulta tus movimientos recientes dentro de la plataforma: accesos, libros abiertos, importaciones, exportaciones y cambios de trabajo.'; ?>
                        </p>
                      </div>
                      <div class="bitacora-summary">
                        <div class="bitacora-summary-card">
                          <span>Total movimientos</span>
                          <strong><?php echo (int) ($bitacora['resumen']['total_movimientos'] ?? 0); ?></strong>
                          <small><?php echo $esAdmin ? 'Ultimos registros visibles' : 'Tus registros visibles'; ?></small>
                        </div>
                        <div class="bitacora-summary-card">
                          <span><?php echo $esAdmin ? 'Usuarios con actividad' : 'Cobertura'; ?></span>
                          <strong><?php echo $esAdmin ? (int) ($bitacora['resumen']['usuarios_activos'] ?? 0) : 'Personal'; ?></strong>
                          <small><?php echo $esAdmin ? 'Agrupados por cuenta' : 'Solo ves tu propia actividad'; ?></small>
                        </div>
                        <div class="bitacora-summary-card">
                          <span>Ultima actividad</span>
                          <strong style="font-size:1.05rem;"><?php echo htmlspecialchars($ultimaActividadTexto); ?></strong>
                          <small>Hora local de El Salvador</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($esAdmin): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                      <div class="col-md-8 col-lg-6">
                        <label for="usuario" class="form-label fw-bold">Filtrar por usuario</label>
                        <select class="form-select" id="usuario" name="usuario">
                          <option value="0">Todos los usuarios</option>
                          <?php foreach (($bitacora['usuarios'] ?? []) as $usuario): ?>
                          <option value="<?php echo (int) ($usuario['id_usuario'] ?? 0); ?>" <?php echo $filtroUsuario === (int) ($usuario['id_usuario'] ?? 0) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) ($usuario['username'] ?? 'usuario')); ?>
                            (<?php echo htmlspecialchars((string) ($usuario['rol'] ?? 'user')); ?>)
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">Aplicar filtro</button>
                      </div>
                      <div class="col-md-auto">
                        <a href="bitacora.php" class="btn btn-outline-secondary">Limpiar</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if (empty($bitacora['grupos'])): ?>
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body bitacora-empty">
                    <i class="mdi mdi-clipboard-text-clock-outline"></i>
                    <h4 class="mb-2">Aun no hay movimientos registrados</h4>
                    <p class="text-muted mb-0">La bitacora se alimentara automaticamente cuando empieces a usar libros, dashboard, sesiones y exportaciones.</p>
                  </div>
                </div>
              </div>
            </div>
            <?php else: ?>
              <?php foreach (($bitacora['grupos'] ?? []) as $grupo): ?>
              <div class="row bitacora-group">
                <div class="col-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h4 class="mb-0"><?php echo htmlspecialchars((string) ($grupo['username'] ?? 'usuario')); ?></h4>
                            <span class="bitacora-role"><?php echo htmlspecialchars((string) ($grupo['rol'] ?? 'user')); ?></span>
                          </div>
                          <p class="text-muted mb-0"><?php echo (int) ($grupo['total_movimientos'] ?? 0); ?> movimientos registrados</p>
                        </div>
                        <span class="bitacora-badge">
                          <i class="mdi mdi-clock-outline"></i>
                          Ultima actividad: <?php echo htmlspecialchars((string) (($grupo['movimientos'][0]['fecha_hora_texto'] ?? '-') ?: '-')); ?>
                        </span>
                      </div>

                      <div class="bitacora-table-wrap">
                        <table class="table bitacora-table">
                          <thead>
                            <tr>
                              <th>Fecha</th>
                              <th>Modulo</th>
                              <th>Accion</th>
                              <th>Descripcion</th>
                              <th>Contexto</th>
                              <th>IP</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach (($grupo['movimientos'] ?? []) as $movimiento): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($movimiento['fecha_hora_texto'] ?? '-')); ?></td>
                              <td><span class="bitacora-badge"><?php echo htmlspecialchars((string) ($movimiento['modulo'] ?? 'general')); ?></span></td>
                              <td><?php echo htmlspecialchars((string) ($movimiento['accion'] ?? 'accion')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($movimiento['descripcion'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($movimiento['contexto_texto'] ?? '-')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($movimiento['ip_address'] ?? '-')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php include __DIR__ . '/../partials/_footer.php'; ?>
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
