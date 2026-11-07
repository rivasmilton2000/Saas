<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once dirname(__DIR__, 3) . '/includes/mail/bootstrap.php';

$currentUser = require_dashboard_module('mail_center');
$repository = new MailRepository(db());
$message = $repository->find((int) ($_GET['id'] ?? 0));
if ($message === null) {
    sietelsa_abort(404);
}
$mailContext=mail_message_context($message,$_GET);
if ($message['direccion'] === 'entrante' && !(bool) $message['leido']) {
    $repository->setRead((int) $message['id'], true);
    $repository->audit('mensaje_leido', (int) $currentUser['id'], (int) $message['id']);
    $message['leido'] = 1;
}
$pageTitle = 'Detalle de correo | SIETELSA';
define('SIETELSA_MAIL_RENDER', true);
require __DIR__ . '/_start.php';
?>
<?php require __DIR__ . '/_detail.php'; ?>
<?php require __DIR__ . '/_end.php'; ?>
