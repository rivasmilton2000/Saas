<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    flash_set('error', 'Solicitud inválida', 'Actualiza la página e inténtalo nuevamente.');
    header('Location: ' . app_url('src/dashboard/backups/index.php'));
    exit;
}

try {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $backup = $backupService->create(
            (string) ($_POST['type'] ?? ''),
            (int) $currentUser['id'],
            (string) ($_POST['scope'] ?? ''),
            isset($_POST['table']) ? (string) $_POST['table'] : null
        );
        flash_set(
            'success',
            'Copia completada',
            'El respaldo SQL se generó correctamente (' . number_format((int) $backup['tamano_bytes'] / 1024, 1) . ' KB).'
        );
    } elseif ($action === 'delete') {
        $backupService->delete((int) ($_POST['id'] ?? 0), (int) $currentUser['id']);
        flash_set('success', 'Copia eliminada', 'El archivo fue eliminado y el evento se conservó en el historial.');
    } else {
        throw new InvalidArgumentException('La acción solicitada no es válida.');
    }
} catch (Throwable $exception) {
    $message = $exception instanceof InvalidArgumentException || $exception instanceof DomainException
        ? $exception->getMessage()
        : 'No fue posible completar la operación de respaldo.';
    flash_set('error', 'Operación no completada', $message);
}

header('Location: ' . app_url('src/dashboard/backups/index.php'));
exit;
