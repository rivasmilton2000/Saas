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
    $dui          = EmpresaModel::normalizeDui($_POST['dui'] ?? '');
    $nit          = EmpresaModel::normalizeNit($_POST['nit'] ?? '');
    $nrc          = trim((string) ($_POST['nrc'] ?? ''));
    $tipoLegal    = trim((string) ($_POST['tipo_legal'] ?? 'natural'));
    $oldInput     = [
        'nombre'        => $nombre,
        'iniciales'     => strtoupper($iniciales),
        'color_emblema' => $colorEmblema !== '' ? $colorEmblema : '#f97316',
        'dui'           => $dui ?? '',
        'nit'           => $nit ?? '',
        'nrc'           => $nrc,
        'tipo_legal'    => in_array($tipoLegal, ['natural', 'juridica'], true) ? $tipoLegal : 'natural',
    ];

    if ($nombre === '') {
        setFlash('dashboard', 'Debes escribir el nombre de la empresa.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        setFlash('dashboard', 'Tipo legal invalido.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!EmpresaModel::isValidDui($dui)) {
        setFlash('dashboard', 'El DUI debe tener formato 12345678-9.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!EmpresaModel::isValidNit($nit)) {
        setFlash('dashboard', 'El NIT debe tener formato 0000-000000-000-0.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NRC.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NIT.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
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
$flashMeta = $flash['meta'] ?? [];
$companyFormData = array_merge([
    'nombre'        => '',
    'iniciales'     => '',
    'color_emblema' => '#f97316',
    'dui'           => '',
    'nit'           => '',
    'nrc'           => '',
    'tipo_legal'    => 'natural',
], is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : []);
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
    <style>
      .empresa-modal .modal-dialog {
        max-width: 980px;
        margin: 1.25rem auto;
      }

      .empresa-modal .modal-content {
        border: 1px solid rgba(75, 73, 172, 0.12);
        border-radius: 22px;
        background: #ffffff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
        max-height: calc(100vh - 2.5rem);
        overflow: hidden;
      }

      .empresa-modal form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
      }

      .empresa-modal .modal-header {
        align-items: flex-start;
        padding: 1.15rem 1.4rem;
        border-bottom: 1px solid rgba(75, 73, 172, 0.1);
        background: #ffffff;
        flex: 0 0 auto;
      }

      .empresa-modal .modal-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 0.9rem;
      }

      .empresa-modal .modal-badge {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 1.35rem;
        flex: 0 0 auto;
      }

      .empresa-modal .modal-title {
        margin: 0;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .empresa-modal .modal-subtitle {
        margin: 0.25rem 0 0;
        color: #6b7280;
        font-size: 0.92rem;
        max-width: 36rem;
      }

      .empresa-modal .modal-body {
        padding: 0;
        background: #f6f7fb;
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
      }

      .empresa-modal-layout {
        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        min-height: 100%;
      }

      .empresa-modal-sidebar {
        padding: 1.4rem;
        background: linear-gradient(180deg, #f8f9ff 0%, #eef2ff 100%);
        border-right: 1px solid rgba(75, 73, 172, 0.08);
      }

      .empresa-modal-kicker {
        display: inline-block;
        margin-bottom: 0.55rem;
        color: #4b49ac;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .empresa-modal-sidebar h6 {
        margin-bottom: 0.45rem;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .empresa-modal-copy {
        margin: 0;
        color: #6c7383;
        font-size: 0.92rem;
        line-height: 1.6;
      }

      .empresa-preview-panel {
        margin-top: 1.25rem;
        padding: 1rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 14px 35px rgba(30, 41, 59, 0.08);
      }

      .empresa-preview-caption {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.85rem;
        color: #4b49ac;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .empresa-preview-avatar {
        width: 84px;
        height: 84px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
      }

      .empresa-preview-label {
        margin-top: 0.95rem;
        margin-bottom: 0.18rem;
        color: #8b95a7;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .empresa-preview-name {
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
        word-break: break-word;
      }

      .empresa-preview-color {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.7rem;
        color: #6c7383;
        font-size: 0.87rem;
        font-weight: 600;
      }

      .empresa-preview-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.08);
      }

      .empresa-sidebar-list {
        display: grid;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .empresa-sidebar-item {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.9rem 0.95rem;
        border-radius: 16px;
        border: 1px solid rgba(75, 73, 172, 0.08);
        background: rgba(255, 255, 255, 0.78);
      }

      .empresa-sidebar-item i {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 1rem;
        flex: 0 0 auto;
      }

      .empresa-sidebar-item strong {
        display: block;
        color: #111827;
        font-size: 0.9rem;
        font-weight: 700;
      }

      .empresa-sidebar-item span {
        display: block;
        margin-top: 0.15rem;
        color: #6c7383;
        font-size: 0.83rem;
        line-height: 1.45;
      }

      .empresa-main-pane {
        padding: 1.4rem;
        display: grid;
        gap: 1rem;
      }

      .empresa-form-section {
        padding: 1.2rem 1.25rem;
        border: 1px solid rgba(124, 134, 153, 0.16);
        border-radius: 18px;
        background: #ffffff;
      }

      .empresa-form-section-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1rem;
      }

      .empresa-form-section-head h6 {
        margin: 0;
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
      }

      .empresa-form-section-head p {
        margin: 0.2rem 0 0;
        color: #7c8699;
        font-size: 0.86rem;
      }

      .empresa-section-tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 0 0.75rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
      }

      .empresa-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }

      .empresa-form-grid--three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }

      .empresa-modal .form-label {
        margin-bottom: 0.5rem;
        color: #6c7383;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .empresa-modal .form-control {
        min-height: 50px;
        border-radius: 14px;
        border-color: rgba(124, 134, 153, 0.22);
        background: #fcfcfe;
        box-shadow: none;
      }

      .empresa-modal .form-control:focus {
        border-color: rgba(75, 73, 172, 0.52);
        background: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(75, 73, 172, 0.11);
      }

      .empresa-modal-helper {
        display: block;
        margin-top: 0.45rem;
        color: #97a0af;
        font-size: 0.8rem;
        line-height: 1.45;
      }

      .empresa-color-field {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-height: 58px;
        padding: 0.75rem 0.9rem;
        border: 1px solid rgba(124, 134, 153, 0.22);
        border-radius: 14px;
        background: #fcfcfe;
        cursor: pointer;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
      }

      .empresa-color-field:hover {
        border-color: rgba(75, 73, 172, 0.32);
        background: #ffffff;
      }

      .empresa-color-field:focus-within {
        border-color: rgba(75, 73, 172, 0.52);
        box-shadow: 0 0 0 0.2rem rgba(75, 73, 172, 0.11);
        background: #ffffff;
      }

      .empresa-color-field input[type="color"] {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
      }

      .empresa-color-swatch {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        border: 3px solid rgba(255, 255, 255, 0.92);
        box-shadow: 0 0 0 1px rgba(17, 24, 39, 0.08);
        flex: 0 0 auto;
      }

      .empresa-color-copy strong {
        display: block;
        color: #111827;
        font-size: 0.95rem;
      }

      .empresa-color-copy span {
        display: block;
        color: #7c8699;
        font-size: 0.8rem;
      }

      .empresa-legal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
      }

      .empresa-legal-choice {
        position: relative;
      }

      .empresa-legal-choice input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }

      .empresa-legal-choice label {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        min-height: 52px;
        margin: 0;
        padding: 0 1rem;
        border: 1px solid rgba(124, 134, 153, 0.22);
        border-radius: 14px;
        background: #fcfcfe;
        color: #374151;
        font-weight: 700;
        transition: all 0.18s ease;
        cursor: pointer;
      }

      .empresa-legal-choice label::before {
        content: "";
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: rgba(124, 134, 153, 0.34);
        box-shadow: 0 0 0 4px rgba(124, 134, 153, 0.08);
        transition: all 0.18s ease;
      }

      .empresa-legal-choice input:checked + label {
        border-color: rgba(75, 73, 172, 0.35);
        background: rgba(75, 73, 172, 0.07);
        color: #312e81;
        box-shadow: inset 0 0 0 1px rgba(75, 73, 172, 0.12);
      }

      .empresa-legal-choice input:checked + label::before {
        background: #4b49ac;
        box-shadow: 0 0 0 4px rgba(75, 73, 172, 0.14);
      }

      .empresa-modal .modal-footer {
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.4rem 1.25rem;
        border-top: 1px solid rgba(75, 73, 172, 0.08);
        background: #ffffff;
        flex: 0 0 auto;
      }

      .empresa-modal-footer-note {
        color: #8b95a7;
        font-size: 0.85rem;
        line-height: 1.5;
        max-width: 32rem;
      }

      @media (max-width: 991.98px) {
        .empresa-modal-layout {
          grid-template-columns: 1fr;
        }

        .empresa-modal-sidebar {
          border-right: 0;
          border-bottom: 1px solid rgba(75, 73, 172, 0.08);
        }
      }

      @media (max-width: 767.98px) {
        .empresa-modal .modal-dialog {
          margin: 0.75rem;
        }

        .empresa-modal .modal-header,
        .empresa-modal .modal-footer,
        .empresa-modal-sidebar,
        .empresa-main-pane {
          padding-left: 1rem;
          padding-right: 1rem;
        }

        .empresa-form-grid,
        .empresa-form-grid--three,
        .empresa-legal-grid {
          grid-template-columns: 1fr;
        }

        .empresa-form-section-head {
          flex-direction: column;
        }

        .empresa-modal .modal-footer {
          flex-direction: column;
          align-items: stretch;
        }

        .empresa-modal-footer-note {
          max-width: none;
          text-align: center;
        }

        .empresa-modal .modal-footer .d-flex {
          width: 100%;
        }

        .empresa-modal .modal-footer .btn {
          flex: 1 1 auto;
        }
      }
    </style>
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

            <div class="row">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Empresas</h4>
                    <p class="card-description mb-3">Crea una empresa nueva sin ocupar espacio fijo en el dashboard.</p>
                    <div class="mb-3">
                      <strong>Registradas:</strong> <?php echo count($data['empresas']); ?><br>
                      <strong>Activa:</strong>
                      <?php if (!empty($data['empresa_activa'])): ?>
                        <?php echo htmlspecialchars((string) $data['empresa_activa']['nombre']); ?>
                      <?php else: ?>
                        Sin seleccionar
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($data['empresas'])): ?>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Empresa</th>
                            <th>NIT</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (array_slice($data['empresas'], 0, 3) as $empresa): ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string) $empresa['nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($empresa['nit'] ?: '-')); ?></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Todavia no has creado empresas.</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empresaModal">
                      Agregar empresa
                    </button>
                  </div>
                </div>
              </div>

              <div class="col-lg-8 grid-margin stretch-card">
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

          <div class="modal fade empresa-modal" id="empresaModal" tabindex="-1" aria-labelledby="empresaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered empresa-modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <div class="modal-title-wrap">
                    <div class="modal-badge">
                      <i class="mdi mdi-domain"></i>
                    </div>
                    <div>
                      <h5 class="modal-title" id="empresaModalLabel">Agregar empresa</h5>
                      <p class="modal-subtitle">Crea una empresa nueva y dejala lista para trabajar dentro del sistema.</p>
                    </div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="empresaForm">
                  <div class="modal-body">
                    <input type="hidden" name="action" value="create_company">
                    <div class="empresa-modal-layout">
                      <aside class="empresa-modal-sidebar">
                        <span class="empresa-modal-kicker">Nueva empresa</span>
                        <h6>Dejala lista para operar</h6>
                        <p class="empresa-modal-copy">Completa los datos base y el sistema usara esta empresa en la sesion actual y en el encabezado de tus libros.</p>

                        <div class="empresa-preview-panel">
                          <div class="empresa-preview-caption">
                            <i class="mdi mdi-eye-outline"></i>
                            Vista previa
                          </div>
                          <div
                            class="empresa-preview-avatar"
                            id="empresaPreviewAvatar"
                            style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                          >
                            <span id="empresaPreviewInitials"><?php echo htmlspecialchars((string) ($companyFormData['iniciales'] !== '' ? strtoupper((string) $companyFormData['iniciales']) : 'EMP')); ?></span>
                          </div>
                          <div class="empresa-preview-label">Nombre visible</div>
                          <div class="empresa-preview-name" id="empresaPreviewName">
                            <?php echo htmlspecialchars((string) ($companyFormData['nombre'] !== '' ? $companyFormData['nombre'] : 'Nueva empresa')); ?>
                          </div>
                          <div class="empresa-preview-color">
                            <span
                              class="empresa-preview-color-dot"
                              id="empresaPreviewColorDot"
                              style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                            ></span>
                            <span id="empresaPreviewColorHex"><?php echo htmlspecialchars(strtoupper((string) $companyFormData['color_emblema'])); ?></span>
                          </div>
                        </div>

                        <div class="empresa-sidebar-list">
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-check-decagram-outline"></i>
                            <div>
                              <strong>Uso inmediato</strong>
                              <span>Quedara disponible para seleccionarla en esta misma sesion.</span>
                            </div>
                          </div>
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-shield-check-outline"></i>
                            <div>
                              <strong>Validacion base</strong>
                              <span>DUI y NIT se revisan con formato salvadoreno antes de guardar.</span>
                            </div>
                          </div>
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-book-open-variant"></i>
                            <div>
                              <strong>Lista para libros</strong>
                              <span>Podras trabajar tus libros con la empresa activa apenas se cree.</span>
                            </div>
                          </div>
                        </div>
                      </aside>

                      <div class="empresa-main-pane">
                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identidad de la empresa</h6>
                              <p>Los datos principales que usaras para reconocerla dentro del sistema.</p>
                            </div>
                            <span class="empresa-section-tag">Base</span>
                          </div>

                          <div class="form-group mb-4">
                            <label class="form-label" for="nombre">Nombre de la empresa</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars((string) $companyFormData['nombre']); ?>" required>
                            <small class="empresa-modal-helper">Se mostrara en el dashboard, la sesion activa y los encabezados del modulo.</small>
                          </div>

                          <div class="empresa-form-grid">
                            <div class="form-group mb-0">
                              <label class="form-label" for="iniciales">Iniciales</label>
                              <input type="text" class="form-control" id="iniciales" name="iniciales" maxlength="4" value="<?php echo htmlspecialchars((string) $companyFormData['iniciales']); ?>">
                              <small class="empresa-modal-helper">Se sugieren automaticamente a partir del nombre, pero puedes afinarlas.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label d-block">Tipo legal</label>
                              <div class="empresa-legal-grid">
                                <div class="empresa-legal-choice">
                                  <input type="radio" id="tipo_legal_natural" name="tipo_legal" value="natural" <?php echo $companyFormData['tipo_legal'] === 'natural' ? 'checked' : ''; ?>>
                                  <label for="tipo_legal_natural">Natural</label>
                                </div>
                                <div class="empresa-legal-choice">
                                  <input type="radio" id="tipo_legal_juridica" name="tipo_legal" value="juridica" <?php echo $companyFormData['tipo_legal'] === 'juridica' ? 'checked' : ''; ?>>
                                  <label for="tipo_legal_juridica">Juridica</label>
                                </div>
                              </div>
                            </div>
                          </div>
                        </section>

                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identidad visual</h6>
                              <p>Un color sobrio ayuda a distinguir la empresa sin romper el estilo del panel.</p>
                            </div>
                            <span class="empresa-section-tag">Visual</span>
                          </div>

                          <div class="form-group mb-0">
                            <label class="form-label" for="color_emblema">Color emblema</label>
                            <label class="empresa-color-field mb-0" for="color_emblema">
                              <span
                                class="empresa-color-swatch"
                                id="empresaColorSwatch"
                                style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                              ></span>
                              <span class="empresa-color-copy">
                                <strong id="empresaColorFieldHex"><?php echo htmlspecialchars(strtoupper((string) $companyFormData['color_emblema'])); ?></strong>
                                <span>Haz clic para elegir el color del distintivo</span>
                              </span>
                              <input type="color" id="color_emblema" name="color_emblema" value="<?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>">
                            </label>
                            <small class="empresa-modal-helper">Mantiene la empresa identificable sin chocar con el estilo original del dashboard.</small>
                          </div>
                        </section>

                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identificacion fiscal</h6>
                              <p>Estos campos son opcionales, pero te dejan la empresa lista para procesos contables.</p>
                            </div>
                            <span class="empresa-section-tag">Fiscal</span>
                          </div>

                          <div class="empresa-form-grid empresa-form-grid--three">
                            <div class="form-group mb-0">
                              <label class="form-label" for="dui">DUI</label>
                              <input
                                type="text"
                                class="form-control"
                                id="dui"
                                name="dui"
                                maxlength="10"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="12345678-9"
                                value="<?php echo htmlspecialchars((string) $companyFormData['dui']); ?>"
                              >
                              <small class="empresa-modal-helper">Formato salvadoreno: 8 digitos y guion.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label" for="nit">NIT</label>
                              <input
                                type="text"
                                class="form-control"
                                id="nit"
                                name="nit"
                                maxlength="17"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0000-000000-000-0"
                                value="<?php echo htmlspecialchars((string) $companyFormData['nit']); ?>"
                              >
                              <small class="empresa-modal-helper">Formato salvadoreno completo de 14 digitos.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label" for="nrc">NRC</label>
                              <input type="text" class="form-control" id="nrc" name="nrc" value="<?php echo htmlspecialchars((string) $companyFormData['nrc']); ?>">
                              <small class="empresa-modal-helper">Util para dejar lista la empresa para procesos y reportes.</small>
                            </div>
                          </div>
                        </section>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <div class="empresa-modal-footer-note">Puedes guardar solo lo esencial ahora y completar datos fiscales despues si lo necesitas.</div>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check-circle-outline me-1"></i> Crear empresa
                      </button>
                    </div>
                  </div>
                </form>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const empresaModalElement = document.getElementById('empresaModal');
        const empresaModal = empresaModalElement && window.bootstrap
          ? new bootstrap.Modal(empresaModalElement)
          : null;
        const empresaForm = document.getElementById('empresaForm');
        const nombreInput = document.getElementById('nombre');
        const inicialesInput = document.getElementById('iniciales');
        const colorInput = document.getElementById('color_emblema');
        const duiInput = document.getElementById('dui');
        const nitInput = document.getElementById('nit');
        const previewAvatar = document.getElementById('empresaPreviewAvatar');
        const previewInitials = document.getElementById('empresaPreviewInitials');
        const previewName = document.getElementById('empresaPreviewName');
        const previewColorDot = document.getElementById('empresaPreviewColorDot');
        const previewColorHex = document.getElementById('empresaPreviewColorHex');
        const colorFieldHex = document.getElementById('empresaColorFieldHex');
        const flash = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function formatDui(value) {
          const digits = String(value || '').replace(/\D/g, '').slice(0, 9);
          if (digits.length <= 8) {
            return digits;
          }
          return digits.slice(0, 8) + '-' + digits.slice(8);
        }

        function formatNit(value) {
          const digits = String(value || '').replace(/\D/g, '').slice(0, 14);
          if (digits.length <= 4) {
            return digits;
          }
          if (digits.length <= 10) {
            return digits.slice(0, 4) + '-' + digits.slice(4);
          }
          if (digits.length <= 13) {
            return digits.slice(0, 4) + '-' + digits.slice(4, 10) + '-' + digits.slice(10);
          }
          return digits.slice(0, 4) + '-' + digits.slice(4, 10) + '-' + digits.slice(10, 13) + '-' + digits.slice(13);
        }

        function buildInitials(value) {
          return String(value || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map(function (part) {
              return part.charAt(0).toUpperCase();
            })
            .join('')
            .slice(0, 4);
        }

        function syncPreview() {
          const resolvedInitials = inicialesInput && inicialesInput.value.trim() !== ''
            ? inicialesInput.value.trim().toUpperCase().slice(0, 4)
            : buildInitials(nombreInput ? nombreInput.value : '');
          const resolvedName = nombreInput && nombreInput.value.trim() !== ''
            ? nombreInput.value.trim()
            : 'Nueva empresa';
          const resolvedColor = colorInput && colorInput.value
            ? colorInput.value.toUpperCase()
            : '#F97316';

          if (previewInitials) {
            previewInitials.textContent = resolvedInitials || 'EMP';
          }

          if (previewName) {
            previewName.textContent = resolvedName;
          }

          if (previewAvatar) {
            previewAvatar.style.background = resolvedColor;
          }

          if (previewColorDot) {
            previewColorDot.style.background = resolvedColor;
          }

          if (previewColorHex) {
            previewColorHex.textContent = resolvedColor;
          }

          if (colorFieldHex) {
            colorFieldHex.textContent = resolvedColor;
          }
        }

        function showFlashAlert(flashData) {
          if (!flashData || !window.Swal) {
            if (flashData && flashData.meta && flashData.meta.open_modal && empresaModal) {
              empresaModal.show();
            }
            return;
          }

          const iconMap = {
            success: 'success',
            danger: 'error',
            warning: 'warning',
            info: 'info'
          };

          Swal.fire({
            icon: iconMap[flashData.type] || 'info',
            text: flashData.message || '',
            confirmButtonText: 'Entendido'
          }).then(function () {
            if (flashData.meta && flashData.meta.open_modal && empresaModal) {
              empresaModal.show();
            }
          });
        }

        if (duiInput) {
          duiInput.addEventListener('input', function () {
            duiInput.value = formatDui(duiInput.value);
          });
          duiInput.value = formatDui(duiInput.value);
        }

        if (nitInput) {
          nitInput.addEventListener('input', function () {
            nitInput.value = formatNit(nitInput.value);
          });
          nitInput.value = formatNit(nitInput.value);
        }

        if (nombreInput && inicialesInput) {
          nombreInput.addEventListener('input', function () {
            if (inicialesInput.dataset.touched === 'true') {
              return;
            }
            inicialesInput.value = buildInitials(nombreInput.value);
          });

          inicialesInput.addEventListener('input', function () {
            inicialesInput.dataset.touched = inicialesInput.value.trim() !== '' ? 'true' : 'false';
            inicialesInput.value = inicialesInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4);
            syncPreview();
          });

          nombreInput.addEventListener('input', syncPreview);
        }

        if (colorInput) {
          colorInput.addEventListener('input', syncPreview);
        }

        if (empresaForm) {
          empresaForm.addEventListener('submit', function (event) {
            if (duiInput && duiInput.value.trim() !== '' && !/^\d{8}-\d$/.test(duiInput.value.trim())) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: 'El DUI debe tener formato 12345678-9.',
                confirmButtonText: 'Corregir'
              });
              duiInput.focus();
              return;
            }

            if (nitInput && nitInput.value.trim() !== '' && !/^\d{4}-\d{6}-\d{3}-\d$/.test(nitInput.value.trim())) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: 'El NIT debe tener formato 0000-000000-000-0.',
                confirmButtonText: 'Corregir'
              });
              nitInput.focus();
            }
          });
        }

        syncPreview();
        showFlashAlert(flash);
      });
    </script>
  </body>
</html>
