<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../services/BitacoraService.php';
require_once __DIR__ . '/../services/DatabaseBackupService.php';
require_once __DIR__ . '/../services/PageVisitService.php';

requireLogin();
requireAdmin();

$session   = sessionData();
$idUsuario = (int) ($session['id_usuario'] ?? 0);
$basePath  = '../';
$esAdmin   = true;

$idEmpresaActiva = getActiveEmpresaId();
$empresaActivaNavbar = $idEmpresaActiva ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;
PageVisitService::track($pdo, $session, 'backups_admin', 'Backups');

$registrarBitacora = static function (
    string $accion,
    string $descripcion,
    array $contexto = []
) use ($pdo, $idUsuario, $session): void {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        'backups',
        $accion,
        $descripcion,
        [
            'username' => (string) ($session['username'] ?? ''),
            'rol'      => (string) ($session['rol'] ?? 'admin'),
            'contexto' => $contexto,
        ]
    );
};

if (isset($_GET['download'])) {
    try {
        $backup = DatabaseBackupService::findBackupByName((string) ($_GET['download'] ?? ''));
        if ($backup === null) {
            throw new RuntimeException('El backup solicitado ya no esta disponible.');
        }

        $registrarBitacora(
            'descargar_backup',
            'Descargo un backup de base de datos.',
            [
                'detalle' => (string) ($backup['name'] ?? ''),
                'formato' => 'sql',
            ]
        );

        DatabaseBackupService::streamBackup((string) ($backup['name'] ?? ''));
        exit;
    } catch (Throwable $exception) {
        setFlash('database_backup', $exception->getMessage(), 'danger');
        header('Location: /Saas/src/pages/backups.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_manual_backup') {
    try {
        $resultado = DatabaseBackupService::createManualBackup(dbConfig());
        $backup = $resultado['backup'] ?? [];

        $registrarBitacora(
            'generar_backup_manual',
            'Genero un backup manual de PostgreSQL.',
            [
                'detalle' => (string) ($backup['name'] ?? 'backup_manual.sql'),
                'formato' => 'sql',
            ]
        );

        setFlash('database_backup', (string) ($resultado['message'] ?? 'Backup manual generado.'), 'success');
    } catch (Throwable $exception) {
        setFlash('database_backup', $exception->getMessage(), 'danger');
    }

    header('Location: /Saas/src/pages/backups.php');
    exit;
}

$flash = getFlash('database_backup');
$autoBackupResult = null;
$autoBackupError = null;

try {
    $autoBackupResult = DatabaseBackupService::ensureDailyBackup(dbConfig());
    if (($autoBackupResult['created'] ?? false) === true) {
        $backup = $autoBackupResult['backup'] ?? [];
        $registrarBitacora(
            'generar_backup_diario',
            'Genero el backup diario de PostgreSQL.',
            [
                'detalle' => (string) ($backup['name'] ?? 'backup_diario.sql'),
                'formato' => 'sql',
            ]
        );
    }
} catch (Throwable $exception) {
    $autoBackupError = $exception->getMessage();
}

$overview = [
    'today_backup' => null,
    'latest_backup' => null,
    'backups' => [],
    'total_backups' => 0,
    'relative_directory' => 'backup/postgresql_daily',
    'pg_dump_path' => 'No disponible',
];

try {
    $overview = array_merge($overview, DatabaseBackupService::getOverview(dbConfig()));
} catch (Throwable $exception) {
    $autoBackupError = $autoBackupError ?? $exception->getMessage();
}

$todayBackup = $overview['today_backup'] ?? null;
$latestBackup = $overview['latest_backup'] ?? null;
$backups = $overview['backups'] ?? [];
$totalBackups = (int) ($overview['total_backups'] ?? 0);
$lastGeneratedText = $latestBackup['created_at_label'] ?? '-';
$lastSizeText = $latestBackup['size_label'] ?? '-';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Backups - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <style>
      .backup-shell .card {
        border: 1px solid rgba(75, 73, 172, 0.08);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
      }

      .backup-hero {
        border-radius: 24px;
        background: linear-gradient(135deg, #ffffff 0%, #f5f7ff 58%, #eef2ff 100%);
      }

      .backup-hero .card-body {
        padding: 1.6rem;
      }

      .backup-kicker {
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

      .backup-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, 1fr);
        gap: 1.25rem;
      }

      .backup-hero-title {
        margin: 0;
        color: #111827;
        font-size: 2rem;
        font-weight: 700;
      }

      .backup-hero-copy {
        margin: 0.75rem 0 0;
        max-width: 42rem;
        color: #64748b;
        font-size: 0.95rem;
      }

      .backup-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .backup-summary-card {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.92);
      }

      .backup-summary-card span {
        display: block;
        margin-bottom: 0.25rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .backup-summary-card strong {
        display: block;
        color: #111827;
        font-size: 1.35rem;
        font-weight: 700;
      }

      .backup-summary-card small {
        color: #64748b;
      }

      .backup-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .backup-status-pill--ok {
        background: rgba(34, 197, 94, 0.12);
        color: #166534;
      }

      .backup-status-pill--warn {
        background: rgba(249, 115, 22, 0.14);
        color: #9a3412;
      }

      .backup-detail-list {
        display: grid;
        gap: 0.8rem;
      }

      .backup-detail-item {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
        background: #ffffff;
      }

      .backup-detail-item span {
        display: block;
        margin-bottom: 0.3rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .backup-detail-item strong,
      .backup-detail-item code {
        word-break: break-word;
      }

      .backup-table-wrap {
        overflow: auto;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
      }

      .backup-table {
        min-width: 880px;
        margin-bottom: 0;
      }

      .backup-table thead th {
        background: #1f2a44;
        color: #ffffff;
        border-bottom: 0;
        white-space: nowrap;
        font-size: 0.74rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .backup-table tbody tr:nth-child(even) td {
        background: #fafbfe;
      }

      .backup-empty {
        padding: 2.5rem 1.5rem;
        text-align: center;
      }

      .backup-empty i {
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
        .backup-hero-grid,
        .backup-summary {
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
          <div class="content-wrapper backup-shell">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card backup-hero">
                  <div class="card-body">
                    <div class="backup-hero-grid">
                      <div>
                        <span class="backup-kicker"><i class="mdi mdi-database-lock-outline"></i> Resguardo PostgreSQL</span>
                        <h1 class="backup-hero-title">Backups diarios de la plataforma</h1>
                        <p class="backup-hero-copy">
                          Este modulo asegura un respaldo diario de la base <strong><?php echo htmlspecialchars((string) dbConfig()['dbname']); ?></strong>,
                          genera copias manuales cuando lo necesites y deja la descarga protegida solo para administradores.
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                          <span class="backup-status-pill <?php echo $todayBackup ? 'backup-status-pill--ok' : 'backup-status-pill--warn'; ?>">
                            <i class="mdi <?php echo $todayBackup ? 'mdi-check-decagram-outline' : 'mdi-alert-outline'; ?>"></i>
                            <?php echo $todayBackup ? 'Backup de hoy cubierto' : 'Backup diario pendiente'; ?>
                          </span>
                          <span class="backup-status-pill backup-status-pill--ok">
                            <i class="mdi mdi-folder-download-outline"></i>
                            Carpeta: <?php echo htmlspecialchars((string) ($overview['relative_directory'] ?? 'backup/postgresql_daily')); ?>
                          </span>
                        </div>
                      </div>
                      <div class="backup-summary">
                        <div class="backup-summary-card">
                          <span>Total backups</span>
                          <strong><?php echo $totalBackups; ?></strong>
                          <small>Historico disponible para descarga</small>
                        </div>
                        <div class="backup-summary-card">
                          <span>Ultimo respaldo</span>
                          <strong style="font-size: 1.05rem;"><?php echo htmlspecialchars($lastGeneratedText); ?></strong>
                          <small>Tamano: <?php echo htmlspecialchars($lastSizeText); ?></small>
                        </div>
                        <div class="backup-summary-card">
                          <span>Ejecutable</span>
                          <strong style="font-size: 0.92rem;"><?php echo htmlspecialchars(basename((string) ($overview['pg_dump_path'] ?? 'pg_dump.exe'))); ?></strong>
                          <small>Usado para exportar la base diaria</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($flash): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> mb-0">
                  <?php echo htmlspecialchars((string) ($flash['message'] ?? 'Proceso completado.')); ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($autoBackupError !== null): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="alert alert-warning mb-0">
                  <?php echo htmlspecialchars($autoBackupError); ?>
                </div>
              </div>
            </div>
            <?php elseif (($autoBackupResult['created'] ?? false) === true): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="alert alert-success mb-0">
                  <?php echo htmlspecialchars((string) ($autoBackupResult['message'] ?? 'Backup diario generado.')); ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Acciones rapidas</h4>
                    <p class="card-description mb-3">El backup diario se genera automaticamente al entrar. Desde aqui puedes crear una copia adicional bajo demanda.</p>
                    <form method="POST" action="">
                      <input type="hidden" name="action" value="generate_manual_backup">
                      <button type="submit" class="btn btn-primary w-100">
                        Generar backup manual
                      </button>
                    </form>
                    <?php if ($latestBackup): ?>
                    <a href="backups.php?download=<?php echo rawurlencode((string) $latestBackup['name']); ?>" class="btn btn-outline-secondary w-100 mt-3">
                      Descargar ultimo backup
                    </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Detalle operativo</h4>
                    <p class="card-description mb-3">Resumen tecnico para validar que el respaldo diario esta al dia y que la herramienta de exportacion sigue disponible.</p>
                    <div class="backup-detail-list">
                      <div class="backup-detail-item">
                        <span>Base activa</span>
                        <strong><?php echo htmlspecialchars((string) dbConfig()['dbname']); ?></strong>
                      </div>
                      <div class="backup-detail-item">
                        <span>Servidor PostgreSQL</span>
                        <strong><?php echo htmlspecialchars((string) dbConfig()['host']); ?>:<?php echo htmlspecialchars((string) dbConfig()['port']); ?></strong>
                      </div>
                      <div class="backup-detail-item">
                        <span>Directorio de backups</span>
                        <code><?php echo htmlspecialchars((string) ($overview['relative_directory'] ?? 'backup/postgresql_daily')); ?></code>
                      </div>
                      <div class="backup-detail-item">
                        <span>pg_dump detectado</span>
                        <code><?php echo htmlspecialchars((string) ($overview['pg_dump_path'] ?? 'No disponible')); ?></code>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                      <div>
                        <h4 class="card-title mb-1">Historial de backups</h4>
                        <p class="card-description mb-0">Cada archivo queda listo para restauracion o descarga desde el panel de admin.</p>
                      </div>
                      <?php if ($todayBackup): ?>
                      <span class="backup-status-pill backup-status-pill--ok">
                        <i class="mdi mdi-calendar-check-outline"></i>
                        Hoy: <?php echo htmlspecialchars((string) $todayBackup['name']); ?>
                      </span>
                      <?php endif; ?>
                    </div>

                    <?php if (empty($backups)): ?>
                    <div class="backup-empty">
                      <i class="mdi mdi-folder-alert-outline"></i>
                      <h4 class="mb-2">Aun no hay backups guardados</h4>
                      <p class="text-muted mb-0">El primer respaldo aparecera aqui cuando se complete la exportacion de la base.</p>
                    </div>
                    <?php else: ?>
                    <div class="backup-table-wrap">
                      <table class="table backup-table">
                        <thead>
                          <tr>
                            <th>Archivo</th>
                            <th>Tipo</th>
                            <th>Generado</th>
                            <th>Tamano</th>
                            <th>Accion</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($backups as $backup): ?>
                          <tr>
                            <td>
                              <strong><?php echo htmlspecialchars((string) ($backup['name'] ?? 'backup.sql')); ?></strong>
                              <?php if (!empty($backup['is_today'])): ?>
                              <span class="badge badge-success ms-2">Hoy</span>
                              <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($backup['type'] ?? 'Diario')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($backup['created_at_label'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($backup['size_label'] ?? '-')); ?></td>
                            <td>
                              <a href="backups.php?download=<?php echo rawurlencode((string) ($backup['name'] ?? '')); ?>" class="btn btn-outline-primary btn-sm">
                                Descargar
                              </a>
                            </td>
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
          </div>
        </div>
      </div>
    </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
  </body>
</html>
