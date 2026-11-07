<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once dirname(__DIR__,3).'/includes/mail/bootstrap.php';
$currentUser=require_dashboard_module('mail_center');
$repository=new MailRepository(db());
$mailContext=mail_list_context($_GET);
if((int)($_GET['mensaje']??0)>0) {
    header('Location: '.mail_route_url('view.php',$mailContext,['id'=>(int)$_GET['mensaje']])); exit;
}
$listing=$repository->inbox([
    'box'=>$mailContext['bandeja'],'status'=>$mailContext['estado'],'origin'=>$mailContext['origen'],
    'search'=>$mailContext['buscar'],'from'=>$mailContext['desde'],'to'=>$mailContext['hasta'],
],$mailContext['pagina']);
$mailContext['pagina']=$listing['page'];
$mailCounts=$repository->counts();
$syncError=$repository->lastSynchronizationError();
$imapAvailable=extension_loaded('imap') && env_bool('IMAP_ENABLED',true) && trim((string)env('IMAP_HOST',''))!=='' && trim((string)env('IMAP_USERNAME',''))!=='';
$incoming=$mailContext['bandeja']==='recibidos';
$hasFilters=$mailContext['estado']!=='all'||$mailContext['origen']!==''||$mailContext['desde']!==''||$mailContext['hasta']!=='';
$pageTitle='Centro de correos | SIETELSA';
define('SIETELSA_MAIL_RENDER',true);
require __DIR__.'/_start.php';
?>
<?php if($syncError!==null): ?><div class="alert alert-warning" role="alert">La última sincronización no se completó. Los mensajes guardados siguen disponibles; revisa la configuración o disponibilidad del buzón antes de reintentar.</div><?php endif; ?>
<section class="card card-rounded">
 <div class="card-body p-0">
  <div class="sietelsa-mail-toolbar">
   <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="h5 mb-0"><?= e($mailBoxes[$mailContext['bandeja']][0]) ?></h2>
    <div class="d-flex gap-2 flex-wrap">
     <a class="btn btn-sm btn-outline-secondary" href="<?= e(mail_route_url('index.php',$mailContext)) ?>"><i class="mdi mdi-refresh me-1"></i>Actualizar lista</a>
     <?php if($imapAvailable): ?><form method="post" action="<?= e(app_url('src/dashboard/mail/sync.php')) ?>" data-loading-text="Sincronizando..." data-confirm="Se consultará el buzón configurado sin eliminar mensajes del servidor.">
      <?= csrf_field() ?><?php mail_context_fields($mailContext); ?><button class="btn btn-sm btn-outline-primary" type="submit"><i class="mdi mdi-sync me-1"></i>Sincronizar buzón</button>
     </form><?php endif; ?>
    </div>
   </div>
   <form method="get" action="<?= e(app_url('src/dashboard/mail/index.php')) ?>" data-loading-text="Buscando...">
    <input type="hidden" name="bandeja" value="<?= e($mailContext['bandeja']) ?>">
    <div class="d-flex flex-wrap gap-2">
     <label class="visually-hidden" for="mail-search">Buscar en esta bandeja</label>
     <input id="mail-search" class="form-control sietelsa-mail-search" type="search" name="buscar" maxlength="100" value="<?= e($mailContext['buscar']) ?>" placeholder="Buscar en esta bandeja">
     <button class="btn btn-primary text-white" type="submit"><i class="mdi mdi-magnify me-1"></i>Buscar</button>
     <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#mail-filters" aria-controls="mail-filters" aria-expanded="<?= $hasFilters?'true':'false' ?>"><i class="mdi mdi-filter-variant me-1"></i>Filtros</button>
    </div>
    <div id="mail-filters" class="collapse <?= $hasFilters?'show':'' ?>">
     <div class="row g-3 mt-1">
      <?php if($incoming): ?><div class="col-sm-6 col-xl-3"><label class="form-label" for="mail-status">Estado</label><select class="form-select" name="estado" id="mail-status"><?php foreach(['all'=>'Todos','unread'=>'No leídos','read'=>'Leídos','pending'=>'Pendientes de respuesta','answered'=>'Respondidos'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $mailContext['estado']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-sm-6 col-xl-3"><label class="form-label" for="mail-origin">Origen</label><select class="form-select" name="origen" id="mail-origin"><option value="">Todos</option><?php foreach($incoming?['website','imap']:['administrador','sistema','website','imap'] as $origin): ?><option value="<?= e($origin) ?>" <?= $mailContext['origen']===$origin?'selected':'' ?>><?= e(mail_origin_label($origin)) ?></option><?php endforeach; ?></select></div>
      <div class="col-sm-6 col-xl-3"><label class="form-label" for="mail-from">Desde</label><input class="form-control" type="date" name="desde" id="mail-from" value="<?= e($mailContext['desde']) ?>"></div>
      <div class="col-sm-6 col-xl-3"><label class="form-label" for="mail-to">Hasta</label><input class="form-control" type="date" name="hasta" id="mail-to" value="<?= e($mailContext['hasta']) ?>"></div>
      <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-outline-primary" type="submit">Aplicar filtros</button><a class="btn btn-light" href="<?= e(mail_route_url('index.php',mail_list_context(['bandeja'=>$mailContext['bandeja']]))) ?>">Limpiar</a></div>
     </div>
    </div>
   </form>
   <p class="small text-muted mt-3 mb-0"><?= (int)$listing['total'] ?> mensaje(s)<?= $hasFilters||$mailContext['buscar']!==''?' con los filtros actuales':'' ?> · página <?= $listing['page'] ?> de <?= $listing['pages'] ?></p>
  </div>
  <?php if(!$listing['items']): ?>
   <div class="sietelsa-empty-state py-5 px-3"><i class="mdi mdi-email-search-outline"></i><h3 class="h6">No hay mensajes para esta selección</h3><p class="text-muted mb-0"><?= $hasFilters||$mailContext['buscar']!==''?'Prueba otra búsqueda o limpia los filtros.':'Los mensajes aparecerán aquí cuando se reciban o se registren envíos.' ?></p></div>
  <?php else: ?>
   <div class="sietelsa-mail-row-heading" aria-hidden="true"><span><?= $incoming?'Remitente':'Destinatario' ?></span><span>Asunto y mensaje</span><span>Fecha</span></div>
   <ul class="sietelsa-mail-rows list-unstyled mb-0">
    <?php foreach($listing['items'] as $item):
      $unread=$incoming && !(bool)$item['leido'];
      $party=$incoming?($item['remitente_nombre']?:$item['remitente_correo']):($item['destinatario_nombre']?:$item['destinatario_correo']);
      $email=$incoming?$item['remitente_correo']:$item['destinatario_correo'];
      $date=$item['recibido_en']??$item['enviado_en']??$item['creado_en'];
    ?>
     <li><a class="sietelsa-mail-row <?= $unread?'is-unread':'' ?>" href="<?= e(mail_route_url('view.php',$mailContext,['id'=>(int)$item['id']])) ?>">
      <div class="sietelsa-mail-party"><span class="sietelsa-mail-party-name"><i class="mdi mdi-<?= $unread?'email':'email-open-outline' ?> me-1" aria-hidden="true"></i><?= e($party) ?></span><span class="small text-muted d-block text-truncate" title="<?= e($email) ?>"><?= e($email) ?></span></div>
      <div class="sietelsa-mail-preview"><span class="sietelsa-mail-subject d-block"><?= e($item['asunto']) ?></span><span class="sietelsa-mail-excerpt text-muted"><?= e(mb_substr(preg_replace('/\s+/u',' ',(string)$item['cuerpo_texto'])??'',0,180)) ?></span><div class="d-flex flex-wrap gap-1 mt-1"><?php if($unread): ?><span class="badge badge-opacity-primary">No leído</span><?php endif; ?><span class="badge badge-opacity-<?= $incoming?($item['respondido']?'success':'warning'):($item['estado']==='fallido'?'danger':($item['estado']==='enviado'?'success':'warning')) ?>"><?= $incoming?($item['respondido']?'Respondido':'Pendiente de respuesta'):e(ucfirst($item['estado'])) ?></span><span class="small text-muted"><?= e(mail_origin_label($item['origen'])) ?></span></div></div>
      <time class="sietelsa-mail-date" datetime="<?= e(date(DATE_ATOM,strtotime($date))) ?>"><?= e(date('d/m/Y',strtotime($date))) ?><span class="small text-muted d-block"><?= e(date('H:i',strtotime($date))) ?></span></time>
     </a></li>
    <?php endforeach; ?>
   </ul>
  <?php endif; ?>
  <?php if($listing['pages']>1): ?><nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top" aria-label="Paginación de mensajes">
   <?php if($listing['page']>1): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(mail_route_url('index.php',$mailContext,['pagina'=>$listing['page']-1])) ?>">Anterior</a><?php endif; ?>
   <span class="small">Página <?= $listing['page'] ?> de <?= $listing['pages'] ?></span>
   <?php if($listing['page']<$listing['pages']): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(mail_route_url('index.php',$mailContext,['pagina'=>$listing['page']+1])) ?>">Siguiente</a><?php endif; ?>
  </nav><?php endif; ?>
 </div>
</section>
<?php if(!$imapAvailable): ?><p class="small text-muted mt-3">La sincronización del buzón no está disponible. Puedes consultar los mensajes guardados y los contactos del sitio.</p><?php endif; ?>
<?php require __DIR__.'/_end.php'; ?>
