<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    [$backup, $path] = $backupService->downloadable((int) ($_GET['id'] ?? 0));
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename((string) $backup['nombre_archivo']) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    $bytes = readfile($path);
    if ($bytes === false || $bytes !== filesize($path)) throw new RuntimeException('No se completó la lectura del respaldo.');
    auth_audit('backup_descargado', (int) $currentUser['id'], 'session', null, ['backup_id' => (int) $backup['id']]);
    exit;
} catch (Throwable $exception) {
    flash_set('error', 'Descarga no disponible', 'No fue posible descargar el respaldo solicitado.');
    header('Location: ' . app_url('src/dashboard/backups/index.php'));
    exit;
}
