<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once dirname(__DIR__, 3) . '/includes/mail/bootstrap.php';

$currentUser = require_dashboard_module('mail_center');
$repository = new MailRepository(db());
$mailContext=mail_list_context($_GET);
$mode = in_array($_GET['mode'] ?? '', ['reply', 'forward'], true) ? (string) $_GET['mode'] : 'new';
$original = $mode === 'new' ? null : $repository->find((int) ($_GET['id'] ?? 0));
if ($mode !== 'new' && ($original === null || ($mode==='reply' && $original['direccion']!=='entrante'))) {
    flash_set('error', 'Mensaje no encontrado', 'No se puede preparar el correo solicitado.');
    header('Location: ' . app_url('src/dashboard/mail/index.php'));
    exit;
}

$recipient = $mode === 'reply' && $original !== null
    ? (string) $original['remitente_correo']
    : '';
$subject = $original === null ? '' : (string) $original['asunto'];
if ($mode === 'reply' && !preg_match('/^re:/i', $subject)) {
    $subject = 'Re: ' . $subject;
} elseif ($mode === 'forward' && !preg_match('/^fwd:/i', $subject)) {
    $subject = 'Fwd: ' . $subject;
}
$old=$_SESSION['mail_compose_old']??[];unset($_SESSION['mail_compose_old']);
$body='';if(($old['mode']??'')===$mode&&(int)($old['original_id']??0)===(int)($original['id']??0)){$recipient=$old['recipient'];$subject=$old['subject'];$body=$old['body'];}
$pageTitle = 'Redactar correo | SIETELSA';
define('SIETELSA_MAIL_RENDER', true);
require __DIR__ . '/_start.php';
?>
<div class="row justify-content-center">
  <div class="col-12">
    <div class="card card-rounded sietelsa-mail-detail">
      <div class="card-body">
        <h2 class="h4 mb-4"><?= $mode === 'reply' ? 'Responder' : ($mode === 'forward' ? 'Reenviar' : 'Nuevo correo') ?></h2>
        <form method="post" action="<?= e(app_url('src/dashboard/mail/send.php')) ?>" data-loading-text="Enviando correo...">
          <?= csrf_field() ?><?php mail_context_fields($mailContext); ?>
          <input type="hidden" name="mode" value="<?= e($mode) ?>">
          <input type="hidden" name="submission_id" value="<?= e(MailSubmission::issue()) ?>">
          <input type="hidden" name="original_id" value="<?= (int) ($original['id'] ?? 0) ?>">
          <div class="mb-3">
            <label class="form-label" for="recipient">Destinatario</label>
            <input class="form-control" type="email" id="recipient" name="recipient" maxlength="190" value="<?= e($recipient) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="subject">Asunto</label>
            <input class="form-control" type="text" id="subject" name="subject" minlength="1" maxlength="255" value="<?= e($subject) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="body">Mensaje</label>
            <textarea class="form-control" id="body" name="body" rows="12" minlength="1" maxlength="50000" required><?= e($body) ?></textarea>
          </div>
          <?php if ($mode === 'forward' && $original !== null): ?>
            <div class="alert alert-light border">El contenido del mensaje original se agregará citado al enviarlo.</div>
          <?php endif; ?>
          <div class="d-flex flex-wrap justify-content-end gap-2">
            <a class="btn btn-light" href="<?= e(mail_route_url('index.php',$mailContext)) ?>">Cancelar</a>
            <button class="btn btn-primary text-white" type="submit">Enviar correo</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_end.php'; ?>
