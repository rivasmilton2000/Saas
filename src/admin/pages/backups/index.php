<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../services/DatabaseBackupService.php';
require_once __DIR__ . '/../../../services/BitacoraService.php';

$backupConfig = dbConfig();
$adminUserId = (int) ($session['id_usuario'] ?? 0);

$redirectToBackups = static function () use ($adminBaseUrl): void {
    header('Location: ' . $adminBaseUrl . '?page=backups');
    exit;
};

$registerBackupLog = static function (
    string $action,
    string $description,
    array $context = []
) use ($pdo, $adminUserId, $session): void {
    BitacoraService::registrar(
        $pdo,
        $adminUserId,
        'admin_backups',
        $action,
        $description,
        [
            'username' => (string) ($session['username'] ?? ''),
            'rol' => (string) ($session['rol'] ?? 'admin'),
            'contexto' => $context,
        ]
    );
};

if (isset($_GET['download'])) {
    try {
        $backup = DatabaseBackupService::findBackupByName((string) ($_GET['download'] ?? ''));
        if ($backup === null) {
            throw new RuntimeException('El backup solicitado ya no esta disponible.');
        }

        $registerBackupLog(
            'descargar_backup',
            'Descargo un backup de base de datos.',
            [
                'archivo' => (string) ($backup['name'] ?? ''),
                'tipo' => (string) ($backup['type'] ?? ''),
                'tamano' => (string) ($backup['size_label'] ?? ''),
            ]
        );

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        DatabaseBackupService::streamBackup((string) ($backup['name'] ?? ''));
        exit;
    } catch (Throwable $exception) {
        setFlash('admin_backups', $exception->getMessage(), 'danger');
        $redirectToBackups();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'generate_manual_backup') {
        try {
            $result = DatabaseBackupService::createManualBackup($backupConfig);
            $backup = $result['backup'] ?? [];

            $registerBackupLog(
                'generar_backup_manual',
                'Genero un backup manual de PostgreSQL.',
                [
                    'archivo' => (string) ($backup['name'] ?? ''),
                    'tipo' => (string) ($backup['type'] ?? 'Manual'),
                    'tamano' => (string) ($backup['size_label'] ?? ''),
                ]
            );

            setFlash('admin_backups', (string) ($result['message'] ?? 'Backup manual generado correctamente.'), 'success');
        } catch (Throwable $exception) {
            setFlash('admin_backups', $exception->getMessage(), 'danger');
        }

        $redirectToBackups();
    }

    setFlash('admin_backups', 'Accion no reconocida.', 'danger');
    $redirectToBackups();
}

$flash = getFlash('admin_backups');
$flashClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'info',
};

$autoBackupResult = null;
$autoBackupError = null;

try {
    $autoBackupResult = DatabaseBackupService::ensureDailyBackup($backupConfig);
    if (($autoBackupResult['created'] ?? false) === true) {
        $backup = $autoBackupResult['backup'] ?? [];
        $registerBackupLog(
            'generar_backup_diario',
            'Genero el backup diario de PostgreSQL.',
            [
                'archivo' => (string) ($backup['name'] ?? ''),
                'tipo' => (string) ($backup['type'] ?? 'Diario'),
                'tamano' => (string) ($backup['size_label'] ?? ''),
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
    $overview = array_merge($overview, DatabaseBackupService::getOverview($backupConfig, 50));
} catch (Throwable $exception) {
    $autoBackupError = $autoBackupError ?? $exception->getMessage();

    try {
        $availableBackups = DatabaseBackupService::listBackups(50);
        $overview['backups'] = $availableBackups;
        $overview['latest_backup'] = $availableBackups[0] ?? null;
        $overview['total_backups'] = count(DatabaseBackupService::listBackups(500));
    } catch (Throwable $listException) {
        $autoBackupError = $autoBackupError ?? $listException->getMessage();
    }
}

$todayBackup = $overview['today_backup'] ?? null;
$latestBackup = $overview['latest_backup'] ?? null;
$backups = is_array($overview['backups'] ?? null) ? $overview['backups'] : [];
$totalBackups = (int) ($overview['total_backups'] ?? count($backups));
$lastGeneratedText = (string) ($latestBackup['created_at_label'] ?? '-');
$lastSizeText = (string) ($latestBackup['size_label'] ?? '-');
$backupDirectory = (string) ($overview['relative_directory'] ?? 'backup/postgresql_daily');
$pgDumpPath = (string) ($overview['pg_dump_path'] ?? 'No disponible');
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Backups</h3>
          <p class="text-muted mb-0">Gestiona respaldos diarios y copias manuales de la base PostgreSQL.</p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars($adminBaseUrl); ?>?page=backups">
          <input type="hidden" name="action" value="generate_manual_backup">
          <button type="submit" class="btn btn-primary">
            <i class="mdi mdi-database-plus mr-1"></i> Generar backup manual
          </button>
        </form>
      </div>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="alert alert-<?php echo htmlspecialchars($flashClass); ?>" role="alert">
    <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
  </div>
  <?php endif; ?>

  <?php if ($autoBackupError !== null): ?>
  <div class="alert alert-warning" role="alert">
    <?php echo htmlspecialchars($autoBackupError); ?>
  </div>
  <?php elseif (($autoBackupResult['created'] ?? false) === true): ?>
  <div class="alert alert-success" role="alert">
    <?php echo htmlspecialchars((string) ($autoBackupResult['message'] ?? 'Backup diario generado.')); ?>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Estado de hoy</p>
          <h4 class="font-weight-bold mb-2"><?php echo $todayBackup ? 'Cubierto' : 'Pendiente'; ?></h4>
          <span class="badge <?php echo $todayBackup ? 'badge-success' : 'badge-warning'; ?>">
            <?php echo $todayBackup ? 'Backup diario disponible' : 'Sin backup diario'; ?>
          </span>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Total backups</p>
          <h4 class="font-weight-bold mb-2"><?php echo $totalBackups; ?></h4>
          <span class="text-muted">Archivos SQL guardados</span>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Ultimo respaldo</p>
          <h4 class="font-weight-bold mb-2" style="font-size: 1rem;"><?php echo htmlspecialchars($lastGeneratedText); ?></h4>
          <span class="text-muted">Tamano: <?php echo htmlspecialchars($lastSizeText); ?></span>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Base activa</p>
          <h4 class="font-weight-bold mb-2"><?php echo htmlspecialchars((string) ($backupConfig['dbname'] ?? '-')); ?></h4>
          <span class="text-muted"><?php echo htmlspecialchars((string) ($backupConfig['host'] ?? 'localhost')); ?>:<?php echo htmlspecialchars((string) ($backupConfig['port'] ?? '5432')); ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-4 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Detalle operativo</h4>
          <p class="card-description">Informacion tecnica usada para crear los respaldos.</p>

          <div class="border rounded p-3 mb-3">
            <small class="text-muted d-block mb-1">Directorio</small>
            <code><?php echo htmlspecialchars($backupDirectory); ?></code>
          </div>

          <div class="border rounded p-3 mb-3">
            <small class="text-muted d-block mb-1">pg_dump</small>
            <code><?php echo htmlspecialchars($pgDumpPath); ?></code>
          </div>

          <?php if ($latestBackup): ?>
          <a class="btn btn-outline-primary btn-block" href="<?php echo htmlspecialchars($adminBaseUrl); ?>?page=backups&amp;download=<?php echo rawurlencode((string) ($latestBackup['name'] ?? '')); ?>">
            <i class="mdi mdi-download mr-1"></i> Descargar ultimo backup
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h4 class="card-title mb-1">Historial de backups</h4>
              <p class="card-description mb-0">Cada archivo queda disponible para descarga protegida desde el admin.</p>
            </div>
            <?php if ($todayBackup): ?>
            <span class="badge badge-success">
              Hoy: <?php echo htmlspecialchars((string) ($todayBackup['name'] ?? 'backup.sql')); ?>
            </span>
            <?php endif; ?>
          </div>

          <?php if (empty($backups)): ?>
          <div class="text-center py-5">
            <i class="mdi mdi-folder-alert-outline text-primary" style="font-size: 3rem;"></i>
            <h4 class="mt-3 mb-2">Aun no hay backups guardados</h4>
            <p class="text-muted mb-0">El primer respaldo aparecera aqui cuando se complete la exportacion.</p>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th>Tipo</th>
                  <th>Generado</th>
                  <th>Tamano</th>
                  <th class="text-right">Accion</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($backups as $backup): ?>
                <?php $backupName = (string) ($backup['name'] ?? ''); ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($backupName !== '' ? $backupName : 'backup.sql'); ?></strong>
                    <?php if (!empty($backup['is_today'])): ?>
                    <span class="badge badge-success ml-2">Hoy</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($backup['type'] ?? 'Diario')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($backup['created_at_label'] ?? '-')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($backup['size_label'] ?? '-')); ?></td>
                  <td class="text-right">
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($adminBaseUrl); ?>?page=backups&amp;download=<?php echo rawurlencode($backupName); ?>">
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
