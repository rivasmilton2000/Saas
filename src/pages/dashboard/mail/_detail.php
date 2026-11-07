<?php
declare(strict_types=1);
if(!defined('SIETELSA_MAIL_RENDER')||!isset($message,$repository,$currentUser)) { http_response_code(404); exit; }
$incoming=$message['direccion']==='entrante';
$smtpState=$message['smtp_estado']??null;
$smtpLabels=['enviando'=>'Envío en curso o pendiente de conciliación','aceptado'=>'Aceptado por SMTP','temporal'=>'Fallo temporal','permanente'=>'Rechazo definitivo','incierto'=>'Resultado incierto: consultar al proveedor','rebotado'=>'Rebote confirmado','entregado'=>'Entrega confirmada por el proveedor','diferido'=>'Entrega diferida por el proveedor'];
$thread=$repository->thread((int)$message['hilo_id']);
$replyOld=$_SESSION['mail_reply_old'][(int)$message['id']]??'';
unset($_SESSION['mail_reply_old'][(int)$message['id']]);
?>
<article class="card card-rounded sietelsa-mail-detail">
 <div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
   <a class="btn btn-sm btn-outline-secondary" href="<?= e(mail_route_url('index.php',$mailContext)) ?>"><i class="mdi mdi-arrow-left me-1"></i>Volver a la lista</a>
   <div class="d-flex flex-wrap gap-2">
    <?php if($incoming): ?><a class="btn btn-sm btn-outline-primary" href="#mail-reply"><i class="mdi mdi-reply me-1"></i>Responder</a><?php endif; ?>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(mail_route_url('compose.php',$mailContext,['mode'=>'forward','id'=>(int)$message['id']])) ?>"><i class="mdi mdi-forward me-1"></i>Reenviar</a>
   </div>
  </div>
  <h2 class="h4 text-break"><?= e($message['asunto']) ?></h2>
  <div class="d-flex flex-wrap gap-2 mb-4"><span class="badge badge-opacity-primary"><?= e(mail_origin_label($message['origen'])) ?></span><span class="badge badge-opacity-<?= $incoming?($message['respondido']?'success':'warning'):($message['estado']==='fallido'?'danger':'info') ?>"><?= $incoming?($message['respondido']?'Respondido':'Pendiente de respuesta'):e(ucfirst($message['estado'])) ?></span></div>
  <dl class="row small sietelsa-mail-metadata">
   <dt class="col-sm-2">De</dt><dd class="col-sm-10"><?= e($message['remitente_nombre']?:'') ?> &lt;<?= e($message['remitente_correo']) ?>&gt; <button class="btn btn-link btn-sm p-0 sietelsa-copy" type="button" data-copy="<?= e($message['remitente_correo']) ?>">Copiar correo</button></dd>
   <dt class="col-sm-2">Para</dt><dd class="col-sm-10"><?= e($message['destinatario_nombre']?:'') ?> &lt;<?= e($message['destinatario_correo']) ?>&gt;</dd>
   <dt class="col-sm-2">Fecha</dt><dd class="col-sm-10"><?= e(date('d/m/Y H:i',strtotime($message['recibido_en']??$message['enviado_en']??$message['creado_en']))) ?></dd>
   <?php if($message['telefono']): ?><dt class="col-sm-2">Teléfono</dt><dd class="col-sm-10"><?= e($message['telefono']) ?> <button class="btn btn-link btn-sm p-0 sietelsa-copy" type="button" data-copy="<?= e($message['telefono']) ?>">Copiar teléfono</button></dd><?php endif; ?>
  </dl>
  <?php if(!$incoming&&$message['estado']==='enviado'&&$smtpState!=='entregado'): ?><p class="small text-muted">Aceptado por el servidor SMTP. La entrega al destinatario no está confirmada.</p><?php endif; ?>
  <?php if(!$incoming&&$smtpState==='entregado'): ?><p class="small text-muted">El proveedor confirmó la entrega. Esto no indica en qué carpeta lo clasificó el destinatario.</p><?php endif; ?>
  <?php if(!$incoming&&isset($smtpLabels[$smtpState??''])): ?><p class="small text-muted"><?= e($smtpLabels[$smtpState]) ?> · Intentos: <?= (int)$message['smtp_intentos'] ?><?php if($message['smtp_proximo_intento']): ?> · Próximo intento permitido: <?= e($message['smtp_proximo_intento']) ?><?php endif; ?></p><?php endif; ?>
  <?php if(!$incoming&&$message['estado']!=='enviado'): ?>
   <div class="alert alert-<?= $message['estado']==='fallido'?'danger':'warning' ?>" role="status">
    <?= $message['estado']==='fallido'?'El envío falló. El mensaje no figura como enviado.':'Este mensaje está pendiente de envío; aún no hay confirmación del proveedor.' ?>
    <?php if(!in_array($smtpState,['enviando','incierto','permanente','rebotado','entregado','diferido'],true) && (int)$message['smtp_intentos']<max(1,min(10,(int)env('MAIL_MAX_ATTEMPTS','5')))): ?>
    <form class="mt-2" method="post" action="<?= e(app_url('src/dashboard/mail/action.php')) ?>" data-loading-text="Reintentando..." data-confirm="Se intentará enviar este mensaje nuevamente al destinatario indicado.">
     <?= csrf_field() ?><?php mail_context_fields($mailContext); ?><input type="hidden" name="id" value="<?= (int)$message['id'] ?>"><input type="hidden" name="action" value="retry">
     <button class="btn btn-sm btn-outline-primary" type="submit">Reintentar envío</button>
    </form>
    <?php endif; ?>
   </div>
  <?php endif; ?>
  <div class="border rounded p-3 sietelsa-mail-body"><?= e((string)$message['cuerpo_texto']) ?></div>
  <?php if(count($thread)>1): ?>
   <details class="mt-4"><summary class="text-primary">Mensajes relacionados (<?= count($thread)-1 ?>)</summary>
    <ul class="list-unstyled mt-3"><?php foreach($thread as $related): if((int)$related['id']===(int)$message['id'])continue; ?>
     <li class="border rounded p-3 mb-2"><a class="d-block text-break" href="<?= e(mail_route_url('view.php',$mailContext,['id'=>(int)$related['id']])) ?>"><?= e($related['asunto']) ?></a><span class="small text-break"><?= e($related['remitente_correo'].' → '.$related['destinatario_correo']) ?></span><div class="small text-muted"><?= e(date('d/m/Y H:i',strtotime($related['enviado_en']??$related['recibido_en']??$related['creado_en']))) ?> · <?= e(ucfirst($related['estado'])) ?></div></li>
    <?php endforeach; ?></ul>
   </details>
  <?php endif; ?>
  <?php if($incoming): ?>
   <form method="post" action="<?= e(app_url('src/dashboard/mail/send.php')) ?>" class="mt-4" id="mail-reply" data-loading-text="Enviando respuesta...">
    <input type="hidden" name="submission_id" value="<?= e(MailSubmission::issue()) ?>">
    <?= csrf_field() ?><?php mail_context_fields($mailContext); ?><input type="hidden" name="mode" value="reply"><input type="hidden" name="original_id" value="<?= (int)$message['id'] ?>"><input type="hidden" name="recipient" value="<?= e($message['remitente_correo']) ?>"><input type="hidden" name="subject" value="<?= e(preg_match('/^re:/i',$message['asunto'])?$message['asunto']:'Re: '.$message['asunto']) ?>">
    <label class="form-label" for="reply-body">Responder a <?= e($message['remitente_correo']) ?></label>
    <textarea class="form-control" name="body" id="reply-body" rows="6" maxlength="50000" required placeholder="Escribe tu respuesta."><?= e((string)$replyOld) ?></textarea>
    <button class="btn btn-primary text-white mt-3" type="submit"><i class="mdi mdi-send me-1"></i>Enviar respuesta</button>
   </form>
   <div class="d-flex flex-wrap gap-2 mt-3">
    <form method="post" action="<?= e(app_url('src/dashboard/mail/action.php')) ?>"><?= csrf_field() ?><?php mail_context_fields($mailContext); ?><input type="hidden" name="id" value="<?= (int)$message['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-outline-secondary" type="submit">Marcar como no leído</button></form>
    <?php if(!(bool)$message['respondido']): ?><form method="post" action="<?= e(app_url('src/dashboard/mail/action.php')) ?>" data-confirm="Se marcará como respondido sin enviar un correo."><?= csrf_field() ?><?php mail_context_fields($mailContext); ?><input type="hidden" name="id" value="<?= (int)$message['id'] ?>"><input type="hidden" name="action" value="answered"><button class="btn btn-outline-success" type="submit">Marcar como respondido</button></form><?php endif; ?>
   </div>
  <?php endif; ?>
 </div>
</article>
