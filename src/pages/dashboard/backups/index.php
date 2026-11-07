<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$backups = $backupService->history();
$tables = $backupService->availableTables();
$dashboardPageScripts = ['includes/assets/js/backups.js'];
$storageReady = true;
try {
    $backupService->storageDirectory();
} catch (Throwable $exception) {
    $storageReady = false;
}
define('SIETELSA_DASHBOARD_RENDER', true);
$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
};
?>
<!DOCTYPE html>
<html lang="es">
  <?php require dirname(__DIR__) . '/partials/_head.php'; ?>
  <body class="with-welcome-text">
    <div class="container-scroller">
      <?php require dirname(__DIR__) . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require dirname(__DIR__) . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="mb-4">
              <nav aria-label="Ruta de navegación"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="<?= e(app_url('src/dashboard/index.php')) ?>">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Copias de seguridad</li></ol></nav>
              <h2 class="mb-1">Copias de seguridad</h2>
              <p class="text-muted mb-0">Archivos SQL importables, almacenados fuera del directorio público.</p>
            </div>

            <div class="card card-rounded mb-4">
              <div class="card-body">
                <h3 class="card-title">Generar copia</h3>
                <p class="text-muted">La operación se procesa por flujo o por bloques para evitar cargar toda la base en memoria.</p>
                <?php if (!$storageReady): ?><div class="alert alert-danger">La carpeta protegida de copias no está disponible. Revisa la configuración del entorno.</div><?php endif; ?>
                <form class="row g-3 align-items-end" method="post" action="<?= e(app_url('src/dashboard/backups/action.php')) ?>" data-confirm="Confirma que deseas generar esta copia. El archivo se almacenará fuera del directorio público.">
                  <?= csrf_field() ?><input type="hidden" name="action" value="create">
                  <div class="col-lg-5">
                    <label class="form-label" for="backup-type">Tipo de copia</label>
                    <select class="form-select" id="backup-type" name="type" required>
                      <option value="completo">Base completa: estructura y datos</option>
                      <option value="datos">Solo datos</option>
                      <option value="tabla">Una tabla: estructura y datos</option>
                    </select>
                    <div class="form-text" id="backup-description">Incluye todas las tablas, datos, índices, relaciones y objetos compatibles.</div>
                  </div>
                  <div class="col-lg-3 d-none" id="backup-scope-group">
                    <label class="form-label" for="backup-scope">Alcance de los datos</label>
                    <select class="form-select" id="backup-scope" name="scope" disabled><option value="todas_tablas">Todas las tablas</option><option value="tabla">Una tabla específica</option></select>
                  </div>
                  <div class="col-lg-3 d-none" id="backup-table-group">
                    <label class="form-label" for="backup-table">Tabla</label>
                    <select class="form-select" id="backup-table" name="table" disabled><option value="">Selecciona una tabla</option><?php foreach ($tables as $table): ?><option value="<?= e($table) ?>"><?= e($table) ?></option><?php endforeach; ?></select>
                  </div>
                  <div class="col-lg-4"><button class="btn btn-primary text-white" type="submit"<?= !$storageReady ? ' disabled' : '' ?>><i class="mdi mdi-database-plus me-1"></i>Generar copia</button></div>
                </form>
              </div>
            </div>

            <div class="card card-rounded">
              <div class="card-body">
                <h3 class="card-title">Historial de copias</h3>
                <?php if ($backups === []): ?><div class="sietelsa-empty-state"><i class="mdi mdi-database-off-outline"></i><p class="mb-0">Todavía no se han generado copias.</p></div><?php else: ?>
                  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Fecha</th><th>Tipo</th><th>Alcance</th><th>Tamaño</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                    <?php foreach ($backups as $backup): ?><tr>
                      <td><?= e(date('d/m/Y H:i', strtotime((string) $backup['creado_en']))) ?></td>
                      <td><?= e(ucfirst((string) $backup['tipo'])) ?></td>
                      <td><?= ($backup['alcance'] ?? 'base_completa') === 'tabla' ? 'Tabla: ' . e((string) $backup['tabla_nombre']) : (($backup['alcance'] ?? '') === 'todas_tablas' ? 'Todas las tablas' : 'Base completa') ?></td>
                      <td><?= e($formatBytes((int) $backup['tamano_bytes'])) ?></td>
                      <td><?= e(trim((string) ($backup['nombre'] ?? '') . ' ' . (string) ($backup['apellido'] ?? '')) ?: 'Sistema') ?></td>
                      <td><span class="badge badge-opacity-<?= $backup['estado'] === 'completado' ? 'success' : ($backup['estado'] === 'fallido' ? 'danger' : 'secondary') ?>"><?= e(ucfirst((string) $backup['estado'])) ?></span><?php if ($backup['error_publico'] !== null): ?><div class="small text-danger mt-1"><?= e((string) $backup['error_publico']) ?></div><?php endif; ?></td>
                      <td><div class="d-flex gap-2"><?php if ($backup['estado'] === 'completado'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('src/dashboard/backups/download.php?id=' . (int) $backup['id'])) ?>"><i class="mdi mdi-download me-1"></i>Descargar</a><form method="post" action="<?= e(app_url('src/dashboard/backups/action.php')) ?>" data-confirm="Esta acción eliminará el archivo SQL, pero conservará el registro del evento."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $backup['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button></form><?php else: ?><span class="text-muted">Sin acciones</span><?php endif; ?></div></td>
                    </tr><?php endforeach; ?>
                  </tbody></table></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
  </body>
</html>
