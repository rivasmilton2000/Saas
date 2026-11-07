<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/notifications/bootstrap.php';

$currentUser = require_dashboard_module('notifications');
$notificationService = new NotificationService(db());
$dashboardIncludeAnalytics = false;
$pageTitle = 'Notificaciones | SIETELSA';
$dashboardFlash = flash_pull();
$notificationTypeLabel = static fn (string $type): string => match ($type) {
    'contacto' => 'Contacto',
    'usuario_pendiente' => 'Usuario pendiente',
    'usuario_creado' => 'Usuario creado',
    'activacion' => 'Activación',
    'acceso_bloqueado' => 'Acceso bloqueado',
    'error_correo' => 'Error de correo',
    'backup' => 'Backup',
    'error_backup' => 'Error de backup',
    'cuenta_bloqueada' => 'Cuenta bloqueada',
    'cambio_rol' => 'Cambio de rol',
    default => 'Administrativo',
};
