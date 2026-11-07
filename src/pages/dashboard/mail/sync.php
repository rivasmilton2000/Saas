<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once dirname(__DIR__, 3) . '/includes/mail/bootstrap.php';

$currentUser = require_dashboard_module('mail_center');
$mailContext=mail_list_context($_POST);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    flash_set('error', 'Solicitud inválida', 'No se inició la sincronización.');
    header('Location: ' . app_url('src/dashboard/mail/index.php'));
    exit;
}

$repository = new MailRepository(db());
try {
    $result = (new ImapSynchronizer(db(), $repository))->sync();
    $repository->audit('imap_sincronizado', (int) $currentUser['id'], null, $result);
    flash_set(
        'success',
        'Sincronización completada',
        sprintf('%d mensaje(s) importado(s); %d duplicado(s) omitido(s).', $result['imported'], $result['duplicates'])
    );
} catch (Throwable $exception) {
    $repository->audit('imap_fallido', (int) $currentUser['id'], null);
    flash_set('error', 'Sincronización fallida', 'Revisa la configuración o disponibilidad del servidor IMAP.');
}

header('Location: ' . mail_route_url('index.php',$mailContext));
exit;
