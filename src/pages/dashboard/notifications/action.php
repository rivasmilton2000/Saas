<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    flash_set('error', 'Solicitud inválida', 'Actualiza la página e inténtalo nuevamente.');
    header('Location: ' . app_url('src/dashboard/notifications/index.php'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'read_all') {
    $count = $notificationService->markAllRead((int) $currentUser['id']);
    auth_audit('notificaciones_leidas', (int) $currentUser['id'], 'session', null, ['cantidad' => $count]);
    flash_set('success', 'Notificaciones actualizadas', 'Se marcaron como leídas las notificaciones pendientes.');
} elseif ($action === 'read') {
    $notificationService->markRead((int) ($_POST['id'] ?? 0), (int) $currentUser['id']);
    flash_set('success', 'Notificación actualizada', 'La notificación se marcó como leída.');
} else {
    flash_set('error', 'Acción no válida', 'La acción solicitada no está disponible.');
}

header('Location: ' . app_url('src/dashboard/notifications/index.php'));
exit;
