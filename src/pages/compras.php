<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../services/LibroImportService.php';

requireLogin();

$session   = sessionData();
$idUsuario = (int) $session['id_usuario'];
$basePath  = '../';
$meses     = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];

$empresas = EmpresaModel::getByUsuario($pdo, $idUsuario);
if (count($empresas) === 1 && getActiveEmpresaId() === null) {
    setActiveEmpresaId((int) $empresas[0]['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'select_company') {
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $empresa   = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);

        if (!$empresa) {
            setFlash('compras', 'Selecciona una empresa valida.', 'danger');
        } else {
            setActiveEmpresaId($idEmpresa);
            setActiveLibroId(null);
            setFlash('compras', 'Empresa activa actualizada.', 'success');
        }

        header('Location: /Saas/src/pages/compras.php');
        exit;
    }

    if ($action === 'create_book') {
        $idEmpresa = getActiveEmpresaId();
        $empresa   = $idEmpresa ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $mes       = (int) ($_POST['mes'] ?? 0);
        $anio      = (int) ($_POST['anio'] ?? 0);

        if (!$empresa) {
            setFlash('compras', 'Primero debes seleccionar una empresa.', 'danger');
        } elseif ($mes < 1 || $mes > 12 || $anio < 2000) {
            setFlash('compras', 'Debes elegir un mes y anio validos.', 'danger');
        } else {
            $existente = LibroModel::findByEmpresaTipoPeriodo($pdo, (int) $empresa['id'], 'compras', $mes, $anio);
            if ($existente) {
                setActiveLibroId((int) $existente['id']);
                setFlash('compras', 'Ese libro ya existia. Se abrio el registro guardado.', 'info');
            } else {
                $idLibro = LibroModel::create($pdo, [
                    'id_empresa' => (int) $empresa['id'],
                    'id_usuario' => $idUsuario,
                    'tipo'       => 'compras',
                    'mes'        => $mes,
                    'anio'       => $anio,
                ]);
                setActiveLibroId($idLibro);
                setFlash('compras', 'Libro de Compras creado correctamente.', 'success');
            }
        }

        header('Location: /Saas/src/pages/compras.php');
        exit;
    }

    if ($action === 'import_json') {
        $idLibro = getActiveLibroId();
        if ($idLibro === null) {
            setFlash('compras', 'Debes abrir un libro antes de importar.', 'danger');
        } elseif (empty($_FILES['json_files']['name'][0])) {
            setFlash('compras', 'Selecciona al menos un archivo JSON.', 'danger');
        } else {
            $resultado = LibroImportService::importarDesdeArchivos($pdo, $idLibro, $idUsuario, $_FILES['json_files']);
            $_SESSION['_import_result'] = $resultado;
            setFlash(
                'compras',
                $resultado['message'] ?? 'Importacion procesada.',
                ($resultado['success'] ?? false) ? 'success' : 'warning'
            );
        }

        header('Location: /Saas/src/pages/compras.php');
        exit;
    }
}

if (isset($_GET['open'])) {
    $idLibro  = (int) $_GET['open'];
    $libroAbrir = LibroModel::getById($pdo, $idLibro, $idUsuario);

    if (!$libroAbrir || $libroAbrir['tipo'] !== 'compras') {
        setFlash('compras', 'No se encontro el libro solicitado.', 'danger');
    } else {
        setActiveEmpresaId((int) $libroAbrir['id_empresa']);
        setActiveLibroId((int) $libroAbrir['id']);
        setFlash('compras', 'Libro cargado correctamente.', 'success');
    }

    header('Location: /Saas/src/pages/compras.php');
    exit;
}

if (isset($_GET['clear_book'])) {
    setActiveLibroId(null);
    setFlash('compras', 'Libro activo limpiado.', 'info');
    header('Location: /Saas/src/pages/compras.php');
    exit;
}

$empresas       = EmpresaModel::getByUsuario($pdo, $idUsuario);
$idEmpresaActiva = getActiveEmpresaId();
$empresaActiva  = $idEmpresaActiva ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;

if (!$empresaActiva && !empty($empresas)) {
    setActiveEmpresaId((int) $empresas[0]['id']);
    $idEmpresaActiva = (int) $empresas[0]['id'];
    $empresaActiva = EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario);
}

$libros = $empresaActiva
    ? LibroModel::getByEmpresaYTipo($pdo, (int) $empresaActiva['id'], 'compras')
    : [];

$idLibroActivo = getActiveLibroId();
$libroActivo = $idLibroActivo ? LibroModel::getById($pdo, $idLibroActivo, $idUsuario) : null;

if ($libroActivo && (!$empresaActiva || (int) $libroActivo['id_empresa'] !== (int) $empresaActiva['id'])) {
    setActiveLibroId(null);
    $libroActivo = null;
}

$facturas     = $libroActivo ? FacturaModel::getByLibro($pdo, (int) $libroActivo['id']) : [];
$cuota        = FacturasCuotaModel::ensure($pdo, $idUsuario);
$flash        = getFlash('compras');
$importResult = $_SESSION['_import_result'] ?? null;
unset($_SESSION['_import_result']);
$empresaActivaNavbar = $empresaActiva;
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Libro de Compras - Saas Contabilidad</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
  </head>
  <body>
    <div class="container-scroller">
      <?php include __DIR__ . '/../partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/../partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex justify-content-between align-items-center">
                      <div>
                        <h3 class="card-title mb-1">Libro de Compras</h3>
                        <p class="text-muted mb-0">Selecciona empresa, crea o abre tu periodo y procesa facturas JSON.</p>
                      </div>
                      <div class="text-md-right mt-3 mt-md-0">
                        <div><strong>Cuota disponible:</strong> <?php echo (int) $cuota['disponibles']; ?> / <?php echo (int) $cuota['total']; ?></div>
                        <div><strong>Consumidas:</strong> <?php echo (int) $cuota['consumidas']; ?> (<?php echo htmlspecialchars((string) $cuota['porcentaje']); ?>%)</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (empty($empresas)): ?>
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body text-center">
                    <h4 class="card-title">No tienes empresas creadas</h4>
                    <p class="text-muted">Crea tu primera empresa desde el dashboard para comenzar a procesar libros.</p>
                    <a href="../index.php" class="btn btn-primary">Ir al dashboard</a>
                  </div>
                </div>
              </div>
            </div>
            <?php else: ?>

            <div class="row">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Empresa activa</h4>
                    <form method="POST" action="">
                      <input type="hidden" name="action" value="select_company">
                      <div class="form-group">
                        <label for="id_empresa">Seleccionar empresa</label>
                        <select class="form-control" id="id_empresa" name="id_empresa" required>
                          <?php foreach ($empresas as $empresa): ?>
                          <option value="<?php echo (int) $empresa['id']; ?>" <?php echo $empresaActiva && (int) $empresaActiva['id'] === (int) $empresa['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $empresa['nombre']); ?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-outline-primary">Usar empresa</button>
                      <a href="../index.php" class="btn btn-link">Crear otra empresa</a>
                    </form>

                    <?php if ($empresaActiva): ?>
                    <hr>
                    <p class="mb-1"><strong>Iniciales:</strong> <?php echo htmlspecialchars((string) ($empresaActiva['iniciales'] ?: '-')); ?></p>
                    <p class="mb-1"><strong>NIT:</strong> <?php echo htmlspecialchars((string) ($empresaActiva['nit'] ?: '-')); ?></p>
                    <p class="mb-1"><strong>NRC:</strong> <?php echo htmlspecialchars((string) ($empresaActiva['nrc'] ?: '-')); ?></p>
                    <p class="mb-0"><strong>Tipo legal:</strong> <?php echo htmlspecialchars((string) $empresaActiva['tipo_legal']); ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Crear libro nuevo</h4>
                    <p class="card-description">Si el periodo ya existe se abrira automaticamente.</p>
                    <form method="POST" action="">
                      <input type="hidden" name="action" value="create_book">
                      <div class="form-group">
                        <label for="anio">Año</label>
                        <select class="form-control" id="anio" name="anio">
                          <?php for ($anio = (int) date('Y') + 1; $anio >= 2024; $anio--): ?>
                          <option value="<?php echo $anio; ?>" <?php echo $anio === (int) date('Y') ? 'selected' : ''; ?>><?php echo $anio; ?></option>
                          <?php endfor; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="mes">Mes</label>
                        <select class="form-control" id="mes" name="mes">
                          <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                          <option value="<?php echo $numeroMes; ?>" <?php echo $numeroMes === (int) date('n') ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombreMes); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-primary">Crear o abrir libro</button>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Libros guardados</h4>
                    <?php if (!empty($libros)): ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Periodo</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($libros as $libro): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($meses[(int) $libro['mes']] ?? (string) $libro['mes']); ?> <?php echo htmlspecialchars((string) $libro['anio']); ?></td>
                            <td class="text-right">
                              <a href="?open=<?php echo (int) $libro['id']; ?>" class="btn btn-sm btn-outline-primary">Abrir</a>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">No hay libros de compras guardados para esta empresa.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($libroActivo): ?>
            <div class="row">
              <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex justify-content-between align-items-center">
                      <div>
                        <h4 class="card-title mb-1">Libro activo</h4>
                        <p class="mb-0">
                          <strong><?php echo htmlspecialchars((string) $libroActivo['empresa_nombre']); ?></strong>
                          |
                          <?php echo htmlspecialchars($meses[(int) $libroActivo['mes']] ?? (string) $libroActivo['mes']); ?>
                          <?php echo htmlspecialchars((string) $libroActivo['anio']); ?>
                        </p>
                      </div>
                      <div class="mt-3 mt-md-0">
                        <a href="../api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=excel" class="btn btn-outline-primary">Exportar Excel</a>
                        <a href="../api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=anexo_mh_a3" class="btn btn-outline-primary">Anexo MH A3</a>
                        <a href="?clear_book=1" class="btn btn-light">Cerrar libro</a>
                      </div>
                    </div>
                    <hr>
                    <form method="POST" action="" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="import_json">
                      <div class="form-group">
                        <label for="json_files">Importar archivos JSON</label>
                        <input type="file" class="form-control" id="json_files" name="json_files[]" accept=".json,application/json" multiple>
                      </div>
                      <button type="submit" class="btn btn-primary">Importar JSON</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($importResult): ?>
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Resultado de importacion</h4>
                    <p class="mb-3">
                      <strong>Importadas:</strong> <?php echo (int) ($importResult['importadas'] ?? 0); ?>
                      |
                      <strong>Duplicadas:</strong> <?php echo count($importResult['duplicadas'] ?? []); ?>
                      |
                      <strong>Invalidas:</strong> <?php echo count($importResult['invalidas'] ?? []); ?>
                    </p>

                    <?php if (!empty($importResult['invalidas'])): ?>
                    <div class="mb-3">
                      <h6>Documentos invalidos</h6>
                      <ul class="mb-0">
                        <?php foreach ($importResult['invalidas'] as $invalida): ?>
                        <li>
                          <?php echo htmlspecialchars((string) ($invalida['archivo'] ?? 'Documento')); ?>:
                          <?php echo htmlspecialchars((string) ($invalida['razon'] ?? 'Documento invalido')); ?>
                          <?php if (!empty($invalida['tipo_dte'])): ?>
                            (<?php echo htmlspecialchars((string) $invalida['tipo_dte']); ?>)
                          <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($importResult['duplicadas'])): ?>
                    <div class="mb-3">
                      <h6>Duplicadas</h6>
                      <ul class="mb-0">
                        <?php foreach ($importResult['duplicadas'] as $duplicada): ?>
                        <li>
                          <?php echo htmlspecialchars((string) ($duplicada['archivo'] ?? 'Documento')); ?>:
                          <?php echo htmlspecialchars((string) ($duplicada['codigo_generacion'] ?? '')); ?>
                          - <?php echo htmlspecialchars((string) ($duplicada['razon'] ?? 'Duplicada')); ?>
                        </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($importResult['tipos_validos'])): ?>
                    <div class="alert alert-warning mb-0">
                      Tipos validos para Libro de Compras:
                      <?php
                      $tipos = [];
                      foreach ($importResult['tipos_validos'] as $tipoValido) {
                          $tipos[] = $tipoValido['codigo'] . ' = ' . $tipoValido['nombre'];
                      }
                      echo htmlspecialchars(implode(', ', $tipos));
                      ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div class="row">
              <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Facturas del libro</h4>
                    <?php if (empty($facturas)): ?>
                    <p class="text-muted mb-0">Aun no hay facturas importadas en este libro.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-striped">
                        <thead>
                          <tr>
                            <th>No.</th>
                            <th>Fecha</th>
                            <th>N° Control</th>
                            <th>NRC</th>
                            <th>NIT</th>
                            <th>Nombre del proveedor</th>
                            <th>Internas</th>
                            <th>Import.</th>
                            <th>Internas exentas</th>
                            <th>Import. exentas</th>
                            <th>Credito fiscal</th>
                            <th>Total compras</th>
                            <th>IVA percibido 1%</th>
                            <th>IVA retenido 1%</th>
                            <th>Codigo de generacion</th>
                            <th>Sello de recepcion</th>
                            <th>Numero control completo</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($facturas as $indice => $factura): ?>
                          <tr>
                            <td><?php echo $indice + 1; ?></td>
                            <td><?php echo htmlspecialchars((string) $factura['fecha']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['numero_control'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['nrc'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['nit'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['nombre_proveedor'] ?? '')); ?></td>
                            <td><?php echo number_format((float) ($factura['ventas_internas'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['ventas_importacion'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['ventas_internas_exentas'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['ventas_importacion_exentas'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['credito_fiscal'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['total_compras'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['iva_percibido'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float) ($factura['iva_retenido'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['codigo_generacion'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['sello_recepcion'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['numero_control_completo'] ?? '')); ?></td>
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
            <?php endif; ?>

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const flash = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        if (!flash || !window.Swal) {
          return;
        }

        const iconMap = {
          success: 'success',
          danger: 'error',
          warning: 'warning',
          info: 'info'
        };

        Swal.fire({
          icon: iconMap[flash.type] || 'info',
          text: flash.message || '',
          confirmButtonText: 'Entendido'
        });
      });
    </script>
  </body>
</html>
