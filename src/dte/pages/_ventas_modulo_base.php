<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../services/DteDataService.php';
require_once __DIR__ . '/../services/VentasLibroService.php';
require_once __DIR__ . '/../services/ModulePreferenceService.php';

requireLogin();

$tipoLibro = trim((string) ($tipoLibroPagina ?? ''));
$modulo    = getLibroModule($tipoLibro);

if ($modulo === null || !in_array($tipoLibro, ['ventas_consumidor', 'ventas_contribuyente'], true)) {
    http_response_code(404);
    exit('Modulo no disponible.');
}

dte_require_permission(dte_modulo_permiso($modulo));

$session         = sessionData();
$idUsuario       = dte_current_user_id();
$flashKey        = 'libro_' . $tipoLibro;
$importSessionKey = '_import_result_' . $tipoLibro;
$rutaModulo      = app_url(ltrim((string) ($modulo['ruta'] ?? ''), '/'));
$meses           = [
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
$anioActual = (int) date('Y');
$anioMinimo = $anioActual - 2;
$anioMaximo = $anioActual + 2;
$anios      = range($anioMinimo, $anioMaximo);
$mesActual  = (int) date('n');

$leerDocumentosSubidos = static function (array $files): array {
    $documentos = [];
    $nombres    = $files['name'] ?? [];
    $tmpNames   = $files['tmp_name'] ?? [];
    $errores    = $files['error'] ?? [];

    if (!is_array($nombres)) {
        return [];
    }

    foreach ($nombres as $indice => $nombreArchivo) {
        $error   = $errores[$indice] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = $tmpNames[$indice] ?? '';

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        $contenido = file_get_contents($tmpName);
        if ($contenido === false) {
            continue;
        }

        $documentos[] = [
            'nombre_archivo' => trim((string) $nombreArchivo) !== '' ? trim((string) $nombreArchivo) : ('documento_' . ($indice + 1) . '.json'),
            'payload'        => $contenido,
        ];
    }

    return $documentos;
};

$empresas = EmpresaModel::getByUsuario($pdo, $idUsuario);
if (count($empresas) === 1 && getActiveEmpresaId() === null) {
    setActiveEmpresaId((int) $empresas[0]['id']);
    EmpresaModel::marcarUltimaUsada($pdo, (int) $empresas[0]['id'], $idUsuario);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'select_company') {
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $empresa   = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);

        if (!$empresa) {
            setFlash($flashKey, 'Selecciona una empresa valida.', 'danger');
        } else {
            setActiveEmpresaId($idEmpresa);
            setActiveLibroId(null);
            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            setFlash($flashKey, 'Empresa activa actualizada.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'create_book') {
        $idEmpresa = getActiveEmpresaId();
        $empresa   = $idEmpresa ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $mesInput  = trim((string) ($_POST['mes'] ?? ''));
        $anioInput = trim((string) ($_POST['anio'] ?? ''));
        $mes       = ctype_digit($mesInput) ? (int) $mesInput : 0;
        $anio      = ctype_digit($anioInput) ? (int) $anioInput : 0;

        if (!$empresa) {
            setFlash($flashKey, 'Primero debes seleccionar una empresa.', 'danger');
        } elseif ($anioInput === '' || !ctype_digit($anioInput) || $anio < $anioMinimo || $anio > $anioMaximo) {
            setFlash($flashKey, 'El año seleccionado no está permitido.', 'danger');
        } elseif ($mesInput === '' || !ctype_digit($mesInput) || $mes < 1 || $mes > 12) {
            setFlash($flashKey, 'El mes seleccionado no es válido.', 'danger');
        } else {
            $existente = LibroModel::findByEmpresaTipoPeriodo($pdo, (int) $empresa['id'], $tipoLibro, $mes, $anio);

            if ($existente) {
                setActiveLibroId((int) $existente['id']);
                EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                setFlash($flashKey, 'Ese libro ya existía. Se abrió el período guardado.', 'info');
            } else {
                $idLibro = LibroModel::create($pdo, [
                    'id_empresa' => (int) $empresa['id'],
                    'id_usuario' => $idUsuario,
                    'tipo'       => $tipoLibro,
                    'mes'        => $mes,
                    'anio'       => $anio,
                ]);

                setActiveLibroId($idLibro);
                EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                setFlash($flashKey, 'Libro creado correctamente.', 'success');
            }
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'import_json') {
        $idLibro    = getActiveLibroId();
        $libroActual = $idLibro ? LibroModel::getById($pdo, $idLibro, $idUsuario) : null;
        $documentos = $leerDocumentosSubidos($_FILES['json_files'] ?? []);

        if ($libroActual === null || ($libroActual['tipo'] ?? '') !== $tipoLibro) {
            setFlash($flashKey, 'Debes abrir un libro válido antes de importar.', 'danger');
        } elseif ($documentos === []) {
            setFlash($flashKey, 'Selecciona al menos un archivo JSON.', 'danger');
        } else {
            $resultado = $tipoLibro === 'ventas_consumidor'
                ? VentasLibroService::importarVentasConsumidor($pdo, (int) $idLibro, $idUsuario, $documentos)
                : VentasLibroService::importarVentasContribuyente($pdo, (int) $idLibro, $idUsuario, $documentos);

            $_SESSION[$importSessionKey] = $resultado;
            setFlash(
                $flashKey,
                (string) ($resultado['message'] ?? 'Importación procesada.'),
                ($resultado['success'] ?? false) ? 'success' : 'warning'
            );
        }

        header('Location: ' . $rutaModulo);
        exit;
    }
}

if (isset($_GET['open'])) {
    $idLibroAbrir = (int) $_GET['open'];
    $libroAbrir   = LibroModel::getById($pdo, $idLibroAbrir, $idUsuario);

    if (!$libroAbrir || ($libroAbrir['tipo'] ?? '') !== $tipoLibro) {
        setFlash($flashKey, 'No se encontró el libro solicitado.', 'danger');
    } else {
        setActiveEmpresaId((int) $libroAbrir['id_empresa']);
        setActiveLibroId((int) $libroAbrir['id']);
        EmpresaModel::marcarUltimaUsada($pdo, (int) $libroAbrir['id_empresa'], $idUsuario);
        setFlash($flashKey, 'Libro cargado correctamente.', 'success');
    }

    header('Location: ' . $rutaModulo);
    exit;
}

if (isset($_GET['clear_book'])) {
    setActiveLibroId(null);
    setFlash($flashKey, 'Libro activo limpiado.', 'info');
    header('Location: ' . $rutaModulo);
    exit;
}

$empresas        = EmpresaModel::getByUsuario($pdo, $idUsuario);
$idEmpresaActiva = getActiveEmpresaId();
$empresaActiva   = $idEmpresaActiva ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;

if (!$empresaActiva && !empty($empresas)) {
    $empresaDefecto = EmpresaModel::getUltimaUsada($pdo, $idUsuario) ?: $empresas[0];
    setActiveEmpresaId((int) $empresaDefecto['id']);
    $empresaActiva = EmpresaModel::getById($pdo, (int) $empresaDefecto['id'], $idUsuario);
}

$libros        = $empresaActiva ? LibroModel::getByEmpresaYTipo($pdo, (int) $empresaActiva['id'], $tipoLibro) : [];
$idLibroActivo = getActiveLibroId();
$libroActivo   = $idLibroActivo ? LibroModel::getById($pdo, $idLibroActivo, $idUsuario) : null;

if ($libroActivo && (($libroActivo['tipo'] ?? '') !== $tipoLibro || !$empresaActiva || (int) $libroActivo['id_empresa'] !== (int) $empresaActiva['id'])) {
    setActiveLibroId(null);
    $libroActivo = null;
}

$resultadoListado = [
    'success' => true,
    'data'    => [
        'columnas'          => [],
        'registros'         => [],
        'filas'             => [],
        'totales'           => [],
        'cantidad_facturas' => 0,
    ],
];

if ($libroActivo) {
    $resultadoListado = $tipoLibro === 'ventas_consumidor'
        ? VentasLibroService::listarVentasConsumidor($pdo, (int) $libroActivo['id'], $idUsuario)
        : VentasLibroService::listarVentasContribuyente($pdo, (int) $libroActivo['id'], $idUsuario);
}

$tablaData         = is_array($resultadoListado['data'] ?? null) ? $resultadoListado['data'] : [];
$columnas          = $tablaData['columnas'] ?? [];
$filas             = $tablaData['filas'] ?? [];
$cantidadFacturas  = (int) ($tablaData['cantidad_facturas'] ?? 0);
$totales           = $tablaData['totales'] ?? [];
$cuota             = FacturasCuotaModel::ensure($pdo, $idUsuario);
$cuotaLabel        = FacturasCuotaModel::formatDisponibleLabel($cuota);
$flash             = getFlash($flashKey);
$importResult      = $_SESSION[$importSessionKey] ?? null;
$importData        = is_array($importResult['data'] ?? null) ? $importResult['data'] : [];
$cuotaRestanteLabel = FacturasCuotaModel::formatRestanteLabel(
    isset($importData['cuota_restante']) ? (int) $importData['cuota_restante'] : (int) ($cuota['disponibles'] ?? 0),
    $cuota
);
$empresaActivaNavbar = $empresaActiva;
$periodoLibroActivo  = $libroActivo
    ? (($meses[(int) $libroActivo['mes']] ?? (string) $libroActivo['mes']) . ' ' . $libroActivo['anio'])
    : '';
$columnLabels     = $modulo['columnas'] ?? [];
$numericColumns   = $modulo['columnas_numericas'] ?? [];
$modulePreferences = getModuleColumnConfig($pdo, $tipoLibro, $idUsuario, $modulo);
$columnsPreferencePayload = ModulePreferenceService::clientPayload($tipoLibro, $modulo, $modulePreferences);
$columnas = ModulePreferenceService::filterColumns($columnas, $modulePreferences, 'table');

unset($_SESSION[$importSessionKey]);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars((string) $modulo['nombre']); ?> - <?php echo htmlspecialchars(app_name()); ?></title>
    <link rel="stylesheet" href="../../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=20260613e">
    <link rel="icon" href="<?php echo htmlspecialchars(parent_app_url('assets/images/favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>" />
    <style>
      .module-table th,
      .module-table td {
        white-space: nowrap;
        font-size: 0.84rem;
      }

      .module-table .is-total td {
        font-weight: 600;
        background: #f5f7ff;
      }

      .module-table .is-highlighted-document td {
        background: #fff1f2;
      }

      .document-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #172033;
        font-weight: 700;
        font-size: 0.76rem;
        white-space: nowrap;
      }

      .document-type-badge.is-credit-note {
        background: #fee2e2;
        color: #b91c1c;
      }

      .document-type-badge.is-tax-credit {
        background: #ccfbf1;
        color: #0f766e;
      }

      .document-type-badge.is-invoice {
        background: #e0f2fe;
        color: #075985;
      }

      .document-type-badge.is-debit-note {
        background: #fef3c7;
        color: #92400e;
      }

      .module-preference-table {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        overflow: hidden;
      }

      .module-preference-row {
        display: grid;
        grid-template-columns: minmax(170px, 1fr) 118px 118px;
        gap: 0.75rem;
        align-items: center;
        padding: 0.7rem 0.85rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      }

      .module-preference-row:last-child {
        border-bottom: 0;
      }

      .module-preference-head {
        background: #f8fafc;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .module-preference-types {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }

      .module-preference-type {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 1rem;
        background: #fff;
      }

      .module-preference-type-title {
        display: block;
        margin-bottom: 0.8rem;
        color: #111827;
        font-weight: 700;
      }

      .module-preference-type-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.65rem;
        color: #374151;
      }

      .module-preference-type-option:last-child {
        margin-bottom: 0;
      }

      .module-preference-type-option label {
        margin: 0;
        line-height: 1.3;
      }

      .module-preference-type-option .form-check-input {
        flex-shrink: 0;
        margin: 0;
      }

      @media (max-width: 768px) {
        .module-preference-types {
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
          <div class="content-wrapper">
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['level'] ?? 'info')); ?>" role="alert">
              <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                      <div>
                        <h3 class="mb-2"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></h3>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? '')); ?></p>
                      </div>
                      <div class="d-flex flex-wrap gap-2">
                        <a href="../index.php" class="btn btn-outline-secondary btn-sm">Volver al dashboard</a>
                        <?php if ($libroActivo): ?>
                        <a href="?clear_book=1" class="btn btn-outline-warning btn-sm">Limpiar libro activo</a>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="row mt-4">
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Empresa activa</small>
                          <strong><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Sin seleccionar')); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Período activo</small>
                          <strong><?php echo htmlspecialchars($periodoLibroActivo !== '' ? $periodoLibroActivo : 'Sin abrir'); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Importacion disponible</small>
                          <strong><?php echo htmlspecialchars($cuotaLabel); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Tipos DTE válidos</small>
                          <strong><?php echo htmlspecialchars((string) ($modulo['tipos_validos'] ?? '')); ?></strong>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (empty($empresas)): ?>
            <div class="card">
              <div class="card-body">
                <div class="alert alert-warning mb-0">
                  Necesitas crear al menos una empresa desde el dashboard para empezar a trabajar este módulo.
                </div>
              </div>
            </div>
            <?php else: ?>
            <div class="row">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Empresa de trabajo</h4>
                    <p class="card-description">Selecciona la empresa con la que quieres abrir o consultar libros.</p>
                    <form method="post">
                      <input type="hidden" name="action" value="select_company">
                      <div class="mb-3">
                        <label for="id_empresa" class="form-label">Empresa</label>
                        <select id="id_empresa" name="id_empresa" class="form-select">
                          <?php foreach ($empresas as $empresa): ?>
                          <option value="<?php echo (int) $empresa['id']; ?>" <?php echo (int) ($empresaActiva['id'] ?? 0) === (int) $empresa['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $empresa['nombre']); ?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-primary">Usar empresa</button>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Crear o abrir período</h4>
                    <p class="card-description">Si ya existe un libro del mismo mes y año, se abrirá automáticamente.</p>
                    <form method="post" id="periodCreateForm" data-year-min="<?php echo (int) $anioMinimo; ?>" data-year-max="<?php echo (int) $anioMaximo; ?>">
                      <input type="hidden" name="action" value="create_book">
                      <div class="mb-3">
                        <label for="anio" class="form-label">Año</label>
                        <select id="anio" name="anio" class="form-select" required>
                          <?php foreach ($anios as $anio): ?>
                          <option value="<?php echo (int) $anio; ?>" <?php echo $anio === $anioActual ? 'selected' : ''; ?>><?php echo (int) $anio; ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label for="mes" class="form-label">Mes</label>
                        <select id="mes" name="mes" class="form-select" required>
                          <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                          <option value="<?php echo (int) $numeroMes; ?>" <?php echo $numeroMes === $mesActual ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nombreMes); ?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-success" <?php echo $empresaActiva ? '' : 'disabled'; ?>>Abrir período</button>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Libros guardados</h4>
                    <p class="card-description">Períodos disponibles para la empresa activa.</p>
                    <?php if (!empty($libros)): ?>
                    <div class="list-group">
                      <?php foreach (array_slice($libros, 0, 6) as $libro): ?>
                      <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <strong><?php echo htmlspecialchars(($meses[(int) $libro['mes']] ?? (string) $libro['mes']) . ' ' . $libro['anio']); ?></strong>
                          <div class="text-muted small"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></div>
                        </div>
                        <a href="?open=<?php echo (int) $libro['id']; ?>" class="btn btn-outline-primary btn-sm">Abrir</a>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">No hay libros guardados para esta empresa en este módulo.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-5 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Importar JSON</h4>
                    <p class="card-description">Carga uno o varios archivos; el backend procesará cada DTE y reportará duplicados reales o errores sin detener todo el lote.</p>
                    <form method="post" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="import_json">
                      <div class="mb-3">
                        <label for="json_files" class="form-label">Archivos JSON</label>
                        <input id="json_files" type="file" name="json_files[]" class="form-control" accept=".json,application/json" multiple>
                      </div>
                      <button type="submit" class="btn btn-primary" <?php echo $libroActivo ? '' : 'disabled'; ?>>Importar archivos</button>
                    </form>
                    <?php if (!$libroActivo): ?>
                    <div class="alert alert-light mt-3 mb-0">
                      Abre un período para habilitar la importación.
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="col-lg-7 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Libro activo</h4>
                    <?php if ($libroActivo): ?>
                    <p class="card-description mb-3">
                      <?php echo htmlspecialchars((string) $libroActivo['empresa_nombre']); ?> · <?php echo htmlspecialchars($periodoLibroActivo); ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                      <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=excel">
                        Exportar Excel interno
                      </a>
                      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#columnPreferencesModal">
                        Columnas
                      </button>
                      <?php if ($tipoLibro === 'ventas_contribuyente'): ?>
                      <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_contribuyentes">
                        CSV Hacienda - Anexo Contribuyentes
                      </a>
                      <?php endif; ?>
                    </div>
                    <div class="row">
                      <div class="col-md-4 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Facturas</small>
                          <strong><?php echo $cantidadFacturas; ?></strong>
                        </div>
                      </div>
                      <div class="col-md-4 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Empresa</small>
                          <strong><?php echo htmlspecialchars((string) $libroActivo['empresa_nombre']); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Tipo</small>
                          <strong><?php echo htmlspecialchars((string) $modulo['nombre']); ?></strong>
                        </div>
                      </div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">Todavía no has abierto un libro para este módulo.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($importResult): ?>
            <div class="row">
              <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Resultado de importación</h4>
                    <p class="card-description"><?php echo htmlspecialchars((string) ($importResult['message'] ?? 'Importación procesada.')); ?></p>
                    <div class="row">
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Importadas</small>
                          <strong><?php echo (int) ($importData['importadas'] ?? 0); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Duplicadas</small>
                          <strong><?php echo (int) ($importData['duplicadas_total'] ?? 0); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3 mb-3 mb-md-0">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Inválidas</small>
                          <strong><?php echo (int) ($importData['invalidas_total'] ?? 0); ?></strong>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                          <small class="text-muted d-block">Disponibilidad restante</small>
                          <strong><?php echo htmlspecialchars($cuotaRestanteLabel); ?></strong>
                        </div>
                      </div>
                    </div>

                    <?php if (!empty($importData['invalidas'])): ?>
                    <div class="mt-4">
                      <h6>Documentos inválidos</h6>
                      <div class="table-responsive">
                        <table class="table table-sm">
                          <thead>
                            <tr>
                              <th>Archivo</th>
                              <th>Tipo DTE</th>
                              <th>Nombre tipo</th>
                              <th>Emisor</th>
                              <th>Código generación</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($importData['invalidas'] as $invalida): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombre_archivo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['tipoDte'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombreTipo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombreEmisor'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['codigoGeneracion'] ?? '')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
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
                    <h4 class="card-title">Registros del libro</h4>
                    <p class="card-description">Vista rápida de los documentos importados y la fila de totales del período.</p>
                    <?php if (!$libroActivo): ?>
                    <div class="alert alert-light mb-0">
                      Selecciona empresa, abre un período y luego importa documentos para ver la tabla.
                    </div>
                    <?php elseif (empty($filas)): ?>
                    <div class="alert alert-light mb-0">
                      Aún no hay facturas cargadas en este libro.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm module-table">
                        <thead>
                          <tr>
                            <?php foreach ($columnas as $columna): ?>
                            <th><?php echo htmlspecialchars((string) ($columnLabels[$columna] ?? $columna)); ?></th>
                            <?php endforeach; ?>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($filas as $fila): ?>
                          <?php $esFilaTotal = !isset($fila['no']) || $fila['no'] === null; ?>
                          <?php
                            $rowClass = $esFilaTotal ? 'is-total' : '';
                            if (!$esFilaTotal && shouldHighlightDocumentType($tipoLibro, (string) ($fila['tipo_dte'] ?? ''), $modulePreferences)) {
                                $rowClass = trim($rowClass . ' is-highlighted-document');
                            }
                          ?>
                          <tr class="<?php echo htmlspecialchars($rowClass); ?>">
                            <?php foreach ($columnas as $columna): ?>
                            <?php
                              $valor = $fila[$columna] ?? '';
                              $esNumerica = in_array($columna, $numericColumns, true);
                              if ($valor === null) {
                                  $valor = '';
                              }
                              if ($esNumerica && $valor !== '' && is_numeric($valor)) {
                                  $valorRender = DteDataService::formatDecimal($valor);
                              } else {
                                  $valorRender = (string) $valor;
                              }
                            ?>
                            <td class="<?php echo $esNumerica ? 'text-end' : ''; ?>">
                              <?php if ($columna === 'tipo_documento_nombre' && !$esFilaTotal): ?>
                              <?php
                                $tipoDteFila = (string) ($fila['tipo_dte'] ?? '');
                                $labelTipoDocumento = getDocumentTypeLabelForModule($tipoLibro, $tipoDteFila, $modulePreferences, $valorRender);
                              ?>
                              <?php if ($labelTipoDocumento !== ''): ?>
                              <span class="document-type-badge <?php echo htmlspecialchars(ModulePreferenceService::documentTypeClass($tipoDteFila)); ?>"><?php echo htmlspecialchars($labelTipoDocumento); ?></span>
                              <?php endif; ?>
                              <?php else: ?>
                              <?php echo htmlspecialchars($valorRender); ?>
                              <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
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
          </div>
          <?php include __DIR__ . '/../partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <script src="../../assets/vendors/js/vendor.bundle.base.js"></script>
    <div class="modal fade" id="columnPreferencesModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h3 class="mb-1">Personalizar columnas</h3>
              <p class="text-muted mb-0">Ajusta la vista en pantalla y el Excel interno de este modulo.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="columnPreferencesForm">
              <div class="mb-4">
                <div class="module-preference-table">
                  <div class="module-preference-row module-preference-head">
                    <span>Columna</span>
                    <span>Tabla</span>
                    <span>Excel</span>
                  </div>
                  <?php foreach (($columnsPreferencePayload['columns'] ?? []) as $columnPreference): ?>
                  <?php
                    $preferenceColumnKey = (string) ($columnPreference['key'] ?? '');
                    $preferenceColumnId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $preferenceColumnKey);
                  ?>
                  <div class="module-preference-row">
                    <strong><?php echo htmlspecialchars((string) ($columnPreference['label'] ?? $preferenceColumnKey)); ?></strong>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="prefTable_<?php echo htmlspecialchars($preferenceColumnId); ?>" data-pref-column-table="<?php echo htmlspecialchars($preferenceColumnKey); ?>" <?php echo !empty($columnPreference['table']) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="prefTable_<?php echo htmlspecialchars($preferenceColumnId); ?>">Mostrar</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="prefExcel_<?php echo htmlspecialchars($preferenceColumnId); ?>" data-pref-column-excel="<?php echo htmlspecialchars($preferenceColumnKey); ?>" <?php echo !empty($columnPreference['excel']) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="prefExcel_<?php echo htmlspecialchars($preferenceColumnId); ?>">Incluir</label>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php if (!empty($columnsPreferencePayload['document_types'])): ?>
              <h5 class="mb-3">Tipos de documento</h5>
              <div class="module-preference-types">
                <?php foreach ($columnsPreferencePayload['document_types'] as $typePreference): ?>
                <?php
                  $typeCode = (string) ($typePreference['code'] ?? '');
                  $typeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $typeCode);
                ?>
                <div class="module-preference-type">
                  <strong class="module-preference-type-title"><?php echo htmlspecialchars((string) ($typePreference['label'] ?? ('Tipo DTE ' . $typeCode))); ?></strong>
                  <div class="module-preference-type-option">
                    <label class="form-check-label" for="prefTypeShow_<?php echo htmlspecialchars($typeId); ?>">Mostrar nombre</label>
                    <input class="form-check-input" type="checkbox" role="switch" id="prefTypeShow_<?php echo htmlspecialchars($typeId); ?>" data-pref-type-show="<?php echo htmlspecialchars($typeCode); ?>" <?php echo !empty($typePreference['show']) ? 'checked' : ''; ?>>
                  </div>
                  <div class="module-preference-type-option">
                    <label class="form-check-label" for="prefTypeHighlight_<?php echo htmlspecialchars($typeId); ?>">Marcar visualmente</label>
                    <input class="form-check-input" type="checkbox" role="switch" id="prefTypeHighlight_<?php echo htmlspecialchars($typeId); ?>" data-pref-type-highlight="<?php echo htmlspecialchars($typeCode); ?>" <?php echo !empty($typePreference['highlight']) ? 'checked' : ''; ?>>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </form>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" id="restoreColumnPreferences">Restaurar por defecto</button>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary" id="saveColumnPreferences">Guardar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="../../assets/js/off-canvas.js"></script>
    <script src="../../assets/js/template.js?v=20260613e"></script>
    <script src="../../assets/js/settings.js"></script>
    <script src="../../assets/js/todolist.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const moduleKey = <?php echo json_encode($tipoLibro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const preferencesEndpoint = <?php echo json_encode(app_url('api/preferencias/libro_columnas.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function initPeriodCreateValidation() {
          const form = document.getElementById('periodCreateForm');
          if (!form) {
            return;
          }

          const yearField = form.querySelector('[name="anio"]');
          const monthField = form.querySelector('[name="mes"]');
          const minYear = parseInt(form.getAttribute('data-year-min') || '', 10);
          const maxYear = parseInt(form.getAttribute('data-year-max') || '', 10);

          function clearValidity() {
            if (yearField) {
              yearField.setCustomValidity('');
            }
            if (monthField) {
              monthField.setCustomValidity('');
            }
          }

          if (yearField) {
            yearField.addEventListener('change', clearValidity);
          }
          if (monthField) {
            monthField.addEventListener('change', clearValidity);
          }

          form.addEventListener('submit', function (event) {
            clearValidity();

            const yearValue = yearField ? String(yearField.value || '').trim() : '';
            const monthValue = monthField ? String(monthField.value || '').trim() : '';
            const year = /^\d+$/.test(yearValue) ? parseInt(yearValue, 10) : NaN;
            const month = /^\d+$/.test(monthValue) ? parseInt(monthValue, 10) : NaN;
            let invalidField = null;
            let message = '';

            if (!Number.isInteger(year) || !Number.isInteger(minYear) || !Number.isInteger(maxYear) || year < minYear || year > maxYear) {
              invalidField = yearField;
              message = 'El año seleccionado no está permitido.';
            } else if (!Number.isInteger(month) || month < 1 || month > 12) {
              invalidField = monthField;
              message = 'El mes seleccionado no es válido.';
            }

            if (invalidField) {
              event.preventDefault();
              event.stopImmediatePropagation();
              invalidField.setCustomValidity(message);
              form.reportValidity();
            }
          });
        }

        function collectColumnPreferences() {
          const tableColumns = {};
          const excelColumns = {};
          const documentTypes = {};

          document.querySelectorAll('[data-pref-column-table]').forEach(function (input) {
            tableColumns[input.getAttribute('data-pref-column-table')] = !!input.checked;
          });

          document.querySelectorAll('[data-pref-column-excel]').forEach(function (input) {
            excelColumns[input.getAttribute('data-pref-column-excel')] = !!input.checked;
          });

          document.querySelectorAll('[data-pref-type-show]').forEach(function (input) {
            const code = input.getAttribute('data-pref-type-show');
            documentTypes[code] = documentTypes[code] || {};
            documentTypes[code].show = !!input.checked;
          });

          document.querySelectorAll('[data-pref-type-highlight]').forEach(function (input) {
            const code = input.getAttribute('data-pref-type-highlight');
            documentTypes[code] = documentTypes[code] || {};
            documentTypes[code].highlight = !!input.checked;
          });

          return {
            table_columns: tableColumns,
            excel_columns: excelColumns,
            document_types: documentTypes
          };
        }

        async function postPreferences(action) {
          const response = await fetch(preferencesEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              action: action,
              module_key: moduleKey,
              preferences: action === 'restore' ? {} : collectColumnPreferences()
            })
          });
          const payload = await response.json().catch(function () {
            return { success: false, message: 'El servidor respondio con un formato inesperado.' };
          });

          if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'No se pudo guardar la configuracion.');
          }

          return payload;
        }

        function bindPreferenceButton(id, action) {
          const button = document.getElementById(id);
          if (!button) {
            return;
          }

          button.addEventListener('click', async function () {
            button.disabled = true;
            try {
              const payload = await postPreferences(action);
              window.alert(payload.message || 'Configuracion actualizada.');
              window.location.reload();
            } catch (error) {
              window.alert(error.message || 'No se pudo guardar la configuracion.');
            } finally {
              button.disabled = false;
            }
          });
        }

        bindPreferenceButton('saveColumnPreferences', 'save');
        bindPreferenceButton('restoreColumnPreferences', 'restore');
        initPeriodCreateValidation();
      });
    </script>
  </body>
</html>
