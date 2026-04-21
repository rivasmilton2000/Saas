<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/models/EmpresaModel.php';

requireLogin();

$session = sessionData();
$idUsuario = (int) $session['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_company') {
    $nombre       = trim((string) ($_POST['nombre'] ?? ''));
    $iniciales    = trim((string) ($_POST['iniciales'] ?? ''));
    $colorEmblema = trim((string) ($_POST['color_emblema'] ?? '#f97316'));
    $dui          = trim((string) ($_POST['dui'] ?? ''));
    $nit          = trim((string) ($_POST['nit'] ?? ''));
    $nrc          = trim((string) ($_POST['nrc'] ?? ''));
    $tipoLegal    = trim((string) ($_POST['tipo_legal'] ?? 'natural'));

    if ($nombre === '') {
        setFlash('dashboard', 'Debes escribir el nombre de la empresa.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        setFlash('dashboard', 'Tipo legal invalido.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NRC.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NIT.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $idEmpresa = EmpresaModel::create($pdo, [
        'id_usuario'    => $idUsuario,
        'nombre'        => $nombre,
        'iniciales'     => $iniciales,
        'color_emblema' => $colorEmblema,
        'dui'           => $dui,
        'nit'           => $nit,
        'nrc'           => $nrc,
        'tipo_legal'    => $tipoLegal,
    ]);

    setActiveEmpresaId($idEmpresa);
    setFlash('dashboard', 'Empresa creada correctamente. Ya puedes trabajar tus libros.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

$data = DashboardController::getData($idUsuario);
$flash = getFlash('dashboard');
$basePath = '';
$empresaActivaNavbar = $data['empresa_activa'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard - Saas Contabilidad</title>
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
  </head>
  <body>
    <div class="container-scroller">
      <?php include __DIR__ . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <h3 class="card-title mb-1">Dashboard</h3>
                    <p class="text-muted mb-0">
                      Gestiona tu primera empresa y entra a tus libros de facturas.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($flash): ?>
            <div class="row">
              <div class="col-12">
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>" role="alert">
                  <?php echo htmlspecialchars($flash['message']); ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div class="row">
              <div class="col-lg-6 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Agregar empresa</h4>
                    <p class="card-description">Crea la empresa que usaras en esta sesion y en el encabezado de tus libros.</p>
                    <form method="POST" action="">
                      <input type="hidden" name="action" value="create_company">
                      <div class="form-group">
                        <label for="nombre">Nombre de la empresa</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-4">
                          <label for="iniciales">Iniciales</label>
                          <input type="text" class="form-control" id="iniciales" name="iniciales" maxlength="4">
                        </div>
                        <div class="form-group col-md-4">
                          <label for="color_emblema">Color emblema</label>
                          <input type="color" class="form-control" id="color_emblema" name="color_emblema" value="#f97316">
                        </div>
                        <div class="form-group col-md-4">
                          <label for="tipo_legal">Tipo legal</label>
                          <select class="form-control" id="tipo_legal" name="tipo_legal">
                            <option value="natural">Natural</option>
                            <option value="juridica">Juridica</option>
                          </select>
                        </div>
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-4">
                          <label for="dui">DUI</label>
                          <input type="text" class="form-control" id="dui" name="dui">
                        </div>
                        <div class="form-group col-md-4">
                          <label for="nit">NIT</label>
                          <input type="text" class="form-control" id="nit" name="nit">
                        </div>
                        <div class="form-group col-md-4">
                          <label for="nrc">NRC</label>
                          <input type="text" class="form-control" id="nrc" name="nrc">
                        </div>
                      </div>
                      <button type="submit" class="btn btn-primary">Crear empresa</button>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Ver mis libros</h4>
                    <p class="card-description">Entra al modulo de Libro de Compras y trabaja con tu empresa activa.</p>
                    <div class="mb-3">
                      <strong>Empresas registradas:</strong> <?php echo count($data['empresas']); ?><br>
                      <strong>Libros de compras:</strong> <?php echo (int) $data['libros_count']; ?><br>
                      <strong>Facturas disponibles:</strong> <?php echo (int) ($data['cuota']['disponibles'] ?? 0); ?> de <?php echo (int) ($data['cuota']['total'] ?? 0); ?>
                    </div>

                    <?php if (!empty($data['empresa_activa'])): ?>
                    <div class="alert alert-info">
                      Empresa activa: <strong><?php echo htmlspecialchars((string) $data['empresa_activa']['nombre']); ?></strong>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($data['libros'])): ?>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Empresa</th>
                            <th>Periodo</th>
                            <th>Tipo</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (array_slice($data['libros'], 0, 5) as $libro): ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string) $libro['empresa_nombre']); ?></td>
                            <td><?php echo str_pad((string) $libro['mes'], 2, '0', STR_PAD_LEFT) . '/' . htmlspecialchars((string) $libro['anio']); ?></td>
                            <td><?php echo htmlspecialchars((string) $libro['tipo']); ?></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Todavia no has creado libros de compras.</p>
                    <?php endif; ?>

                    <a href="pages/compras.php" class="btn btn-outline-primary">Ir a mis libros</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php include __DIR__ . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
  </body>
</html>
