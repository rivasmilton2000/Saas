<?php
$tipoLibroPagina = 'compras';
require __DIR__ . '/_libro_modulo_base.php';
return;

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../services/DteDataService.php';
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
            setFlash('compras', 'Debes elegir un mes y año válidos.', 'danger');
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

$facturasRaw  = $libroActivo ? FacturaModel::getByLibro($pdo, (int) $libroActivo['id']) : [];
$facturas     = DteDataService::hydrateFacturas($facturasRaw);
$resumenLibro = DteDataService::resumenLibro($facturas);
$cuota        = FacturasCuotaModel::ensure($pdo, $idUsuario);
$flash        = getFlash('compras');
$importResult = $_SESSION['_import_result'] ?? null;
unset($_SESSION['_import_result']);
$empresaActivaNavbar = $empresaActiva;
$periodoLibroActivo = $libroActivo
    ? (($meses[(int) $libroActivo['mes']] ?? (string) $libroActivo['mes']) . ' ' . $libroActivo['anio'])
    : '';
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
    <style>
      .compras-shell .card {
        border: 1px solid rgba(75, 73, 172, 0.08);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
      }

      .compras-hero {
        overflow: hidden;
        border-radius: 24px;
        background: linear-gradient(135deg, #ffffff 0%, #f4f6ff 55%, #eef2ff 100%);
      }

      .compras-hero .card-body {
        padding: 1.5rem;
      }

      .compras-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.75rem;
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .compras-hero-title {
        margin: 0;
        color: #111827;
        font-size: 2rem;
        font-weight: 700;
      }

      .compras-hero-copy {
        margin: 0.65rem 0 0;
        max-width: 42rem;
        color: #6b7280;
        font-size: 0.96rem;
      }

      .compras-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.9fr);
        gap: 1.25rem;
        align-items: start;
      }

      .compras-hero-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem;
      }

      .compras-stat-card {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.92);
      }

      .compras-stat-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .compras-stat-value {
        display: block;
        color: #111827;
        font-size: 1.4rem;
        font-weight: 700;
      }

      .compras-stat-note {
        display: block;
        margin-top: 0.2rem;
        color: #6b7280;
        font-size: 0.84rem;
      }

      .compras-panel-title {
        margin: 0;
        color: #111827;
        font-size: 1.05rem;
        font-weight: 700;
      }

      .compras-panel-copy {
        margin: 0.35rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
      }

      .compras-book-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 34px;
        padding: 0 0.9rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .compras-info-list {
        display: grid;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .compras-info-item {
        padding: 0.9rem 1rem;
        border-radius: 16px;
        background: #f8f9fd;
        border: 1px solid #e6eaf3;
      }

      .compras-info-item strong {
        display: block;
        color: #111827;
        font-size: 0.86rem;
        font-weight: 700;
      }

      .compras-info-item span {
        display: block;
        margin-top: 0.2rem;
        color: #6b7280;
        font-size: 0.83rem;
      }

      .compras-book-list {
        display: grid;
        gap: 0.8rem;
      }

      .compras-book-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 0.95rem 1rem;
        border-radius: 16px;
        border: 1px solid #e6eaf3;
        background: #f9faff;
      }

      .compras-book-item strong {
        display: block;
        color: #111827;
        font-weight: 700;
      }

      .compras-book-item span {
        display: block;
        margin-top: 0.15rem;
        color: #6b7280;
        font-size: 0.83rem;
      }

      .compras-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
        margin-top: 1.2rem;
      }

      .compras-summary-chip {
        padding: 0.95rem 1rem;
        border-radius: 18px;
        border: 1px solid #e4e8f3;
        background: #ffffff;
      }

      .compras-summary-chip b {
        display: block;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .compras-summary-chip span {
        display: block;
        margin-bottom: 0.2rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .compras-upload-box {
        padding: 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(75, 73, 172, 0.26);
        background: linear-gradient(135deg, rgba(75, 73, 172, 0.05), rgba(75, 73, 172, 0.02));
      }

      .compras-upload-box .form-control {
        min-height: 54px;
      }

      .compras-inline-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 0.9rem;
      }

      .compras-inline-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.8rem;
        border-radius: 999px;
        background: #f8f9fd;
        border: 1px solid #e5e8f1;
        color: #374151;
        font-size: 0.82rem;
        font-weight: 600;
      }

      .compras-result-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.9rem;
        margin-bottom: 1rem;
      }

      .compras-result-card {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
        background: #fafbfe;
      }

      .compras-result-card strong {
        display: block;
        color: #111827;
        font-size: 1.3rem;
        font-weight: 700;
      }

      .compras-result-card span {
        display: block;
        margin-bottom: 0.25rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .compras-result-list {
        display: grid;
        gap: 0.75rem;
      }

      .compras-result-item {
        padding: 0.85rem 1rem;
        border-radius: 16px;
        border: 1px solid #e6eaf3;
        background: #ffffff;
      }

      .compras-result-item strong {
        display: block;
        color: #111827;
        font-size: 0.9rem;
      }

      .compras-result-item small,
      .compras-result-item span {
        color: #6b7280;
      }

      .compras-table-wrap {
        overflow: auto;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
      }

      .compras-table {
        min-width: 1680px;
        margin-bottom: 0;
      }

      .compras-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #1f2a44;
        color: #ffffff;
        border-bottom: 0;
        white-space: nowrap;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .compras-table td {
        vertical-align: top;
        background: #ffffff;
      }

      .compras-table tbody tr:nth-child(even) td {
        background: #fafbfe;
      }

      .compras-cell-wrap {
        min-width: 120px;
        white-space: normal;
        word-break: break-word;
        line-height: 1.4;
      }

      .compras-cell-wrap--wide {
        min-width: 220px;
      }

      .compras-empty-state {
        padding: 2.4rem 1.5rem;
        text-align: center;
      }

      .compras-empty-state i {
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
        .compras-hero-grid,
        .compras-summary-strip,
        .compras-result-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 767.98px) {
        .compras-hero .card-body {
          padding: 1.1rem;
        }

        .compras-hero-title {
          font-size: 1.6rem;
        }

        .compras-hero-grid,
        .compras-hero-stats,
        .compras-summary-strip,
        .compras-result-grid {
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
          <div class="content-wrapper compras-shell">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card compras-hero">
                  <div class="card-body">
                    <div class="compras-hero-grid">
                      <div>
                        <span class="compras-kicker"><i class="mdi mdi-book-open-page-variant"></i> Libro de Compras</span>
                        <h1 class="compras-hero-title">Procesa JSON DTE con una vista más clara y exacta</h1>
                        <p class="compras-hero-copy">Selecciona tu empresa, abre el período de trabajo y revisa todo el detalle del libro con exportaciones más limpias y un sello de recepción completo.</p>
                      </div>
                      <div class="compras-hero-stats">
                        <div class="compras-stat-card">
                          <span class="compras-stat-label">Cuota disponible</span>
                          <span class="compras-stat-value"><?php echo (int) $cuota['disponibles']; ?> / <?php echo (int) $cuota['total']; ?></span>
                          <span class="compras-stat-note">Documentos que aún puedes importar</span>
                        </div>
                        <div class="compras-stat-card">
                          <span class="compras-stat-label">Facturas en libro</span>
                          <span class="compras-stat-value"><?php echo (int) ($resumenLibro['cantidad'] ?? 0); ?></span>
                          <span class="compras-stat-note"><?php echo $libroActivo ? 'Período abierto' : 'Abre un período para comenzar'; ?></span>
                        </div>
                        <div class="compras-stat-card">
                          <span class="compras-stat-label">Empresa activa</span>
                          <span class="compras-stat-value"><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Sin empresa')); ?></span>
                          <span class="compras-stat-note">Base de trabajo actual</span>
                        </div>
                        <div class="compras-stat-card">
                          <span class="compras-stat-label">Período activo</span>
                          <span class="compras-stat-value"><?php echo htmlspecialchars($periodoLibroActivo !== '' ? $periodoLibroActivo : 'Sin abrir'); ?></span>
                          <span class="compras-stat-note">Estado actual del libro</span>
                        </div>
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
                  <div class="card-body compras-empty-state">
                    <i class="mdi mdi-domain-off"></i>
                    <h4 class="compras-panel-title mb-2">Aún no tienes empresas creadas</h4>
                    <p class="text-muted mb-3">Crea tu primera empresa desde el dashboard y luego vuelve aquí para abrir tu Libro de Compras.</p>
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
                    <div class="d-flex justify-content-between align-items-start mb-3">
                      <div>
                        <h4 class="compras-panel-title">Empresa activa</h4>
                        <p class="compras-panel-copy">Cambia la empresa de trabajo sin salir del módulo.</p>
                      </div>
                      <span class="compras-book-badge"><i class="mdi mdi-domain"></i> <?php echo count($empresas); ?> registradas</span>
                    </div>
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
                      <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-outline-primary">Usar empresa</button>
                        <a href="../index.php" class="btn btn-light">Crear otra empresa</a>
                      </div>
                    </form>

                    <?php if ($empresaActiva): ?>
                    <div class="compras-info-list">
                      <div class="compras-info-item">
                        <strong><?php echo htmlspecialchars((string) $empresaActiva['nombre']); ?></strong>
                        <span>Empresa seleccionada para esta sesión de trabajo.</span>
                      </div>
                      <div class="compras-info-item">
                        <strong>Iniciales y tipo legal</strong>
                        <span><?php echo htmlspecialchars((string) ($empresaActiva['iniciales'] ?: '-')); ?> · <?php echo htmlspecialchars((string) $empresaActiva['tipo_legal']); ?></span>
                      </div>
                      <div class="compras-info-item">
                        <strong>Documentos fiscales</strong>
                        <span>NIT: <?php echo htmlspecialchars((string) ($empresaActiva['nit'] ?: '-')); ?> · NRC: <?php echo htmlspecialchars((string) ($empresaActiva['nrc'] ?: '-')); ?></span>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                      <div>
                        <h4 class="compras-panel-title">Crear o abrir período</h4>
                        <p class="compras-panel-copy">Si el mes ya existe, el sistema abrirá el libro guardado.</p>
                      </div>
                      <span class="compras-book-badge"><i class="mdi mdi-calendar-month-outline"></i> Compras</span>
                    </div>
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

                    <div class="compras-inline-badges">
                      <span class="compras-inline-badge"><i class="mdi mdi-check-circle-outline"></i> Tipos válidos 03, 05 y 06</span>
                      <span class="compras-inline-badge"><i class="mdi mdi-alert-circle-outline"></i> Se omiten duplicadas</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                      <div>
                        <h4 class="compras-panel-title">Libros guardados</h4>
                        <p class="compras-panel-copy">Accede rápido a períodos anteriores de la empresa activa.</p>
                      </div>
                      <span class="compras-book-badge"><i class="mdi mdi-book-multiple-outline"></i> <?php echo count($libros); ?> períodos</span>
                    </div>
                    <?php if (!empty($libros)): ?>
                    <div class="compras-book-list">
                      <?php foreach ($libros as $libro): ?>
                      <div class="compras-book-item">
                        <div>
                          <strong><?php echo htmlspecialchars($meses[(int) $libro['mes']] ?? (string) $libro['mes']); ?> <?php echo htmlspecialchars((string) $libro['anio']); ?></strong>
                          <span><?php echo (int) $libro['id'] === (int) ($libroActivo['id'] ?? 0) ? 'Libro activo actualmente' : 'Disponible para abrir'; ?></span>
                        </div>
                        <a href="?open=<?php echo (int) $libro['id']; ?>" class="btn btn-sm btn-outline-primary">Abrir</a>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="compras-empty-state py-4">
                      <i class="mdi mdi-book-remove-outline"></i>
                      <p class="text-muted mb-0">No hay libros de compras guardados para esta empresa.</p>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($libroActivo): ?>
            <div class="row">
              <div class="col-xl-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-md-flex justify-content-between align-items-start mb-3">
                      <div>
                        <span class="compras-kicker mb-2"><i class="mdi mdi-folder-open-outline"></i> Libro activo</span>
                        <h4 class="compras-panel-title"><?php echo htmlspecialchars((string) $libroActivo['empresa_nombre']); ?> · <?php echo htmlspecialchars($periodoLibroActivo); ?></h4>
                        <p class="compras-panel-copy">Exporta tu libro, revisa totales y conserva una vista más clara de cada documento.</p>
                      </div>
                      <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
                        <a href="../api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=excel" class="btn btn-outline-primary">Excel</a>
                        <a href="../api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=pdf" class="btn btn-outline-primary" target="_blank" rel="noopener">PDF</a>
                        <a href="../api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=anexo_mh_a3" class="btn btn-outline-primary">Anexo MH A3</a>
                        <a href="?clear_book=1" class="btn btn-light">Cerrar libro</a>
                      </div>
                    </div>

                    <div class="compras-summary-strip">
                      <div class="compras-summary-chip">
                        <span>Documentos</span>
                        <b><?php echo (int) ($resumenLibro['cantidad'] ?? 0); ?></b>
                      </div>
                      <div class="compras-summary-chip">
                        <span>Total compras</span>
                        <b><?php echo DteDataService::formatDecimal($resumenLibro['total_compras'] ?? 0); ?></b>
                      </div>
                      <div class="compras-summary-chip">
                        <span>Crédito fiscal</span>
                        <b><?php echo DteDataService::formatDecimal($resumenLibro['credito_fiscal'] ?? 0); ?></b>
                      </div>
                      <div class="compras-summary-chip">
                        <span>IVA retenido</span>
                        <b><?php echo DteDataService::formatDecimal($resumenLibro['iva_retenido'] ?? 0); ?></b>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-xl-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="compras-panel-title">Importar archivos JSON</h4>
                    <p class="compras-panel-copy">Sube uno o varios DTE para clasificarlos, validar duplicados y descontar solo lo realmente importado.</p>
                    <form method="POST" action="" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="import_json">
                      <div class="compras-upload-box">
                        <div class="form-group mb-3">
                          <label for="json_files">Seleccionar archivos</label>
                          <input type="file" class="form-control" id="json_files" name="json_files[]" accept=".json,application/json" multiple>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Importar JSON</button>
                      </div>
                    </form>

                    <div class="compras-inline-badges">
                      <span class="compras-inline-badge"><i class="mdi mdi-file-document-multiple-outline"></i> Lotes múltiples</span>
                      <span class="compras-inline-badge"><i class="mdi mdi-filter-check-outline"></i> Filtra DTE válidos</span>
                      <span class="compras-inline-badge"><i class="mdi mdi-shield-outline"></i> Respeta cuota disponible</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($importResult): ?>
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                      <div>
                        <h4 class="compras-panel-title">Resultado de importación</h4>
                        <p class="compras-panel-copy mb-0"><?php echo htmlspecialchars((string) ($importResult['message'] ?? 'Importación procesada.')); ?></p>
                      </div>
                      <span class="compras-book-badge"><i class="mdi mdi-counter"></i> Cuota restante: <?php echo (int) ($importResult['cuota_restante'] ?? $cuota['disponibles']); ?></span>
                    </div>

                    <div class="compras-result-grid">
                      <div class="compras-result-card">
                        <span>Importadas</span>
                        <strong><?php echo (int) ($importResult['importadas'] ?? 0); ?></strong>
                      </div>
                      <div class="compras-result-card">
                        <span>Duplicadas</span>
                        <strong><?php echo count($importResult['duplicadas'] ?? []); ?></strong>
                      </div>
                      <div class="compras-result-card">
                        <span>Inválidas</span>
                        <strong><?php echo count($importResult['invalidas'] ?? []); ?></strong>
                      </div>
                    </div>

                    <?php if (!empty($importResult['invalidas'])): ?>
                    <div class="mb-4">
                      <h5 class="mb-3">Documentos inválidos</h5>
                      <div class="compras-result-list">
                        <?php foreach ($importResult['invalidas'] as $invalida): ?>
                        <div class="compras-result-item">
                          <strong><?php echo htmlspecialchars((string) ($invalida['archivo'] ?? 'Documento')); ?></strong>
                          <span><?php echo htmlspecialchars((string) ($invalida['razon'] ?? 'Documento inválido')); ?></span>
                          <?php if (!empty($invalida['codigo_generacion']) || !empty($invalida['tipo_dte'])): ?>
                          <small>
                            <?php echo htmlspecialchars((string) ($invalida['codigo_generacion'] ?? '')); ?>
                            <?php if (!empty($invalida['tipo_dte'])): ?>
                              · <?php echo htmlspecialchars((string) $invalida['tipo_dte']); ?>
                            <?php endif; ?>
                          </small>
                          <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($importResult['duplicadas'])): ?>
                    <div class="mb-4">
                      <h5 class="mb-3">Duplicadas detectadas</h5>
                      <div class="compras-result-list">
                        <?php foreach ($importResult['duplicadas'] as $duplicada): ?>
                        <div class="compras-result-item">
                          <strong><?php echo htmlspecialchars((string) ($duplicada['archivo'] ?? 'Documento')); ?></strong>
                          <span><?php echo htmlspecialchars((string) ($duplicada['razon'] ?? 'Duplicada')); ?></span>
                          <small><?php echo htmlspecialchars((string) ($duplicada['codigo_generacion'] ?? '')); ?></small>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($importResult['tipos_validos'])): ?>
                    <div class="compras-inline-badges">
                      <?php foreach ($importResult['tipos_validos'] as $tipoValido): ?>
                      <span class="compras-inline-badge">
                        <i class="mdi mdi-check-circle-outline"></i>
                        <?php echo htmlspecialchars((string) $tipoValido['codigo']); ?> = <?php echo htmlspecialchars((string) $tipoValido['nombre']); ?>
                      </span>
                      <?php endforeach; ?>
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
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                      <div>
                        <h4 class="compras-panel-title">Facturas del libro</h4>
                        <p class="compras-panel-copy mb-0">La tabla muestra el sello de recepción completo, el número de control íntegro y todos los montos clave del libro.</p>
                      </div>
                      <span class="compras-book-badge"><i class="mdi mdi-table-large"></i> <?php echo (int) ($resumenLibro['cantidad'] ?? 0); ?> registros</span>
                    </div>

                    <?php if (empty($facturas)): ?>
                    <div class="compras-empty-state">
                      <i class="mdi mdi-file-document-outline"></i>
                      <h5 class="compras-panel-title mb-2">Aún no hay facturas en este libro</h5>
                      <p class="text-muted mb-0">Importa archivos JSON para empezar a construir tu período de compras.</p>
                    </div>
                    <?php else: ?>
                    <div class="compras-table-wrap">
                      <table class="table compras-table">
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
                            <th>Crédito fiscal</th>
                            <th>Total compras</th>
                            <th>IVA percibido 1%</th>
                            <th>IVA retenido 1%</th>
                            <th>Código de generación</th>
                            <th>Sello de recepción</th>
                            <th>Número control completo</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($facturas as $indice => $factura): ?>
                          <tr>
                            <td><?php echo $indice + 1; ?></td>
                            <td><?php echo htmlspecialchars((string) ($factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? ''))); ?></td>
                            <td><div class="compras-cell-wrap"><?php echo htmlspecialchars((string) ($factura['numero_control'] ?? '')); ?></div></td>
                            <td><div class="compras-cell-wrap"><?php echo htmlspecialchars((string) ($factura['nrc'] ?? '')); ?></div></td>
                            <td><div class="compras-cell-wrap"><?php echo htmlspecialchars((string) ($factura['nit'] ?? '')); ?></div></td>
                            <td><div class="compras-cell-wrap compras-cell-wrap--wide"><?php echo htmlspecialchars((string) ($factura['nombre_proveedor'] ?? '')); ?></div></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['ventas_internas'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['ventas_importacion'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['ventas_internas_exentas'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['ventas_importacion_exentas'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['credito_fiscal'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['total_compras'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['iva_percibido'] ?? 0); ?></td>
                            <td class="text-right"><?php echo DteDataService::formatDecimal($factura['iva_retenido'] ?? 0); ?></td>
                            <td><div class="compras-cell-wrap compras-cell-wrap--wide"><?php echo htmlspecialchars((string) ($factura['codigo_generacion'] ?? '')); ?></div></td>
                            <td><div class="compras-cell-wrap compras-cell-wrap--wide"><?php echo htmlspecialchars((string) ($factura['sello_recepcion'] ?? '')); ?></div></td>
                            <td><div class="compras-cell-wrap compras-cell-wrap--wide"><?php echo htmlspecialchars((string) ($factura['numero_control_completo'] ?? '')); ?></div></td>
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
