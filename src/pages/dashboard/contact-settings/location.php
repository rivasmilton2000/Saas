<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
require_once SIETELSA_ROOT.'/includes/website/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') sietelsa_abort(405);
if (!csrf_is_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) sietelsa_abort(419);
try {
    $changes=[];
    foreach (['address','latitude','longitude'] as $field) $changes['sections.contact.'.$field] = $_POST[$field] ?? '';
    (new WebsiteService(db()))->saveDraft((int)$currentUser['id'], $changes, (int)($_POST['revision'] ?? 0), (int)($_POST['draft_id'] ?? 0));
    flash_set('success','Ubicación guardada en borrador','El mapa, Google Maps, Waze y la dirección del footer se actualizarán al publicar el website.');
} catch (Throwable $exception) {
    flash_set('error','No se guardó la ubicación',user_safe_error_message($exception));
}
header('Location: '.app_url('src/dashboard/contact-settings/index.php'));exit;
