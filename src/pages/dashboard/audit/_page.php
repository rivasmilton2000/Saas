<?php
declare(strict_types=1);
if(!isset($auditQuery,$currentUser,$auditDteScope)){http_response_code(404);exit;}
$title=$auditDteScope?'Bitácora DTE':'Bitácora general';
$pageTitle=$title.' | SIETELSA';
$dashboardPageScripts=['includes/assets/js/searchable-select.js','includes/assets/js/audit.js'];
$selectedUser=null;$detail=null;$errorMessage=null;$incident=null;$userOptions=[];
$options=['roles'=>[],'modules'=>[],'actions'=>[],'coverage'=>null];
$filters=['user_id'=>0,'role'=>'','module'=>'','action'=>'','result'=>'','from'=>'','to'=>'','q'=>''];
$result=['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
try {
    $options=$auditQuery->options();
    $filters=$auditQuery->filters($_GET);
    $selectedUser=$auditQuery->selectedUser($filters['user_id'],$auditDteScope);
    $userOptions=$auditQuery->users('',1,$auditDteScope)['items'];
    if(!$auditDteScope||$selectedUser)$result=$auditQuery->page($filters,max(1,(int)($_GET['page']??1)),$auditDteScope);
    if(isset($_GET['event'])){
        if(!is_scalar($_GET['event'])||!ctype_digit((string)$_GET['event']))throw new InvalidArgumentException('Identificador de evento inválido.');
        $detail=$auditQuery->detail(max(0,(int)$_GET['event']),$filters['user_id'],$auditDteScope);
        if(!$detail){http_response_code(404);$errorMessage='El evento no existe o no pertenece al usuario seleccionado.';}
    }
}catch(InvalidArgumentException $e){http_response_code(422);$errorMessage=$e->getMessage();}
catch(Throwable $e){
    http_response_code(500);$incident=sietelsa_record_incident($e,500,['module'=>'audit','action'=>'query']);
    $errorMessage='No fue posible consultar la bitácora. Intenta nuevamente.';
}
$basePage=app_url($auditDteScope?'src/dashboard/dte/audit.php':'src/dashboard/audit/index.php');
$usersUrl=app_url($auditDteScope?'src/dashboard/dte/audit-users.php':'src/dashboard/audit/users.php');
$pageQuery=$filters;
if($selectedUser&&!in_array((int)$selectedUser['id'],array_map('intval',array_column($userOptions,'id')),true))array_unshift($userOptions,['id'=>$selectedUser['id'],'nombre'=>trim($selectedUser['nombre'].' '.$selectedUser['apellido']),'correo'=>$selectedUser['correo'],'rol'=>$selectedUser['rol']]);
define('SIETELSA_DASHBOARD_RENDER',true);
?>
<!DOCTYPE html><html lang="es"><?php require dirname(__DIR__).'/partials/_head.php'; ?>
<body class="with-welcome-text"><div class="container-scroller"><?php require dirname(__DIR__).'/partials/_navbar.php'; ?><div class="container-fluid page-body-wrapper"><?php require dirname(__DIR__).'/partials/_sidebar.php'; ?><div class="main-panel"><div class="content-wrapper sietelsa-audit-page">
<div class="mb-4"><h1 class="h2 mb-1"><?= e($title) ?></h1><p class="text-muted mb-0"><?= $auditDteScope?'Selecciona un usuario para consultar sus movimientos en todos los módulos.':'Consulta los movimientos de todos los usuarios, incluidos administradores.' ?></p></div>
<?php if($options['coverage']): ?><div class="alert alert-info small">Cobertura ampliada desde <?= e(date('d/m/Y H:i:s',strtotime($options['coverage']['ampliada_desde']))) ?> (<?= e($options['coverage']['zona_horaria']) ?>). El historial anterior incluye únicamente eventos conservados; el rol y el resultado aparecen como «No registrado» cuando la fuente no los registró.</div><?php endif; ?>
<?php if($errorMessage): ?><div class="alert alert-danger" role="alert"><?= e($errorMessage) ?><?php if($incident): ?><div class="small">Incidente: <?= e($incident) ?></div><?php endif; ?><a class="d-block mt-2" href="<?= e($basePage.'?'.http_build_query($pageQuery)) ?>">Intentar nuevamente</a></div><?php endif; ?>
<form class="card card-rounded mb-4" method="get" action="<?= e($basePage) ?>" data-loading-text="Cargando movimientos…" id="audit-filters"><div class="card-body"><div class="row g-3">
<div class="col-lg-6"><label class="form-label" for="audit-user">Usuario</label><select class="form-select" id="audit-user" name="user_id" data-fiscal-search-select data-audit-users="<?= e($usersUrl) ?>" data-audit-dte="<?= $auditDteScope?'1':'0' ?>" <?= $auditDteScope?'required':'' ?>>
<option value="" <?= !$selectedUser?'selected':'' ?>><?= $auditDteScope?'Selecciona un usuario':'Todos los usuarios' ?></option>
<?php foreach($userOptions as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $filters['user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['nombre'].' · '.$u['correo'].' · ID '.$u['id']) ?></option><?php endforeach; ?>
</select><div class="small text-muted mt-1" id="audit-users-status" role="status" aria-live="polite">Escribe para buscar en la lista.</div><button type="button" class="btn btn-sm btn-outline-secondary mt-1 d-none" id="audit-users-retry">Reintentar carga</button><button type="button" class="btn btn-sm btn-outline-secondary mt-1 d-none" id="audit-users-more">Más usuarios</button></div>
<div class="col-lg-3"><label class="form-label" for="audit-role">Rol al realizar la acción</label><select class="form-select" id="audit-role" name="role"><option value="">Todos los roles</option><?php foreach($options['roles'] as $r): ?><option value="<?= e($r) ?>" <?= $filters['role']===$r?'selected':'' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-3"><label class="form-label" for="audit-result">Resultado</label><select class="form-select" id="audit-result" name="result"><option value="">Todos</option><?php foreach(['correcto'=>'Correcto','fallido'=>'Fallido','denegado'=>'Denegado','desconocido'=>'No registrado (histórico)'] as $k=>$v): ?><option value="<?= e($k) ?>" <?= $filters['result']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-4"><label class="form-label" for="audit-module">Módulo</label><select class="form-select" id="audit-module" name="module"><option value="">Todos los módulos</option><?php foreach($options['modules'] as $m): ?><option value="<?= e($m) ?>" <?= $filters['module']===$m?'selected':'' ?>><?= e(AuditQueryService::moduleLabel($m)) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-4"><label class="form-label" for="audit-action">Acción</label><select class="form-select" id="audit-action" name="action"><option value="">Todas las acciones</option><?php foreach($options['actions'] as $a): ?><option value="<?= e($a) ?>" <?= $filters['action']===$a?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$a))) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-4"><label class="form-label" for="audit-text">Buscar texto</label><input class="form-control" id="audit-text" name="q" maxlength="150" value="<?= e($filters['q']) ?>" placeholder="Descripción, entidad o correlación"></div>
<div class="col-md-4"><label class="form-label" for="audit-from">Desde</label><input class="form-control" id="audit-from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
<div class="col-md-4"><label class="form-label" for="audit-to">Hasta</label><input class="form-control" id="audit-to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
<div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary text-white" type="submit">Aplicar filtros</button><a class="btn btn-outline-secondary" href="<?= e($basePage.($auditDteScope&&$selectedUser?'?user_id='.(int)$selectedUser['id']:'')) ?>">Limpiar filtros</a><?php if($auditDteScope&&$selectedUser): ?><a class="btn btn-outline-secondary" href="<?= e($basePage) ?>" aria-label="Limpiar usuario"><i class="mdi mdi-account-remove-outline"></i></a><?php endif; ?></div>
</div></div></form>
<?php if($detail): ?>
<section class="card card-rounded mb-4" aria-labelledby="audit-detail-title"><div class="card-body"><div class="d-flex justify-content-between gap-3 mb-3"><h2 class="h5" id="audit-detail-title">Detalle del evento #<?= (int)$detail['id'] ?></h2><a class="btn btn-sm btn-outline-secondary" href="<?= e($basePage.'?'.http_build_query($pageQuery+['page'=>$result['page']])) ?>">Cerrar detalle</a></div>
<dl class="row mb-0"><?php foreach(['creado_en'=>'Fecha y hora','zona_horaria'=>'Zona horaria','nombre_actual'=>'Usuario','rol'=>'Rol al actuar','modulo'=>'Módulo','accion'=>'Acción','descripcion'=>'Descripción','resultado'=>'Resultado','entidad'=>'Entidad','entidad_id'=>'ID afectado','contexto_usuario_id'=>'Usuario del contexto','correlacion'=>'Correlación','incidente_uuid'=>'Incidente técnico'] as $key=>$label): ?><dt class="col-sm-3"><?= e($label) ?></dt><dd class="col-sm-9 text-break"><?= e(($detail[$key]??'')?:'No registrado') ?></dd><?php endforeach; ?></dl>
<?php foreach(['anterior'=>'Valores anteriores','nuevo'=>'Valores nuevos','detalles'=>'Datos del evento'] as $key=>$label): if(empty($detail[$key]))continue; ?><h3 class="h6 mt-3"><?= e($label) ?></h3><pre class="bg-light p-3 rounded text-wrap mb-0"><?= e(json_encode(AuditService::safe(json_decode($detail[$key],true)?:[]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><?php endforeach; ?>
<?php if($detail['historico']): ?><p class="small text-muted mt-3 mb-0">Evento histórico conservado. El nombre mostrado corresponde a la cuenta actual; no se reconstruyen datos ausentes de la fuente.</p><?php endif; ?></div></section>
<?php endif; ?>
<section class="card card-rounded"><div class="card-body p-0"><div class="p-3 border-bottom d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">Movimientos</h2><p class="small text-muted mb-0"><?= (int)$result['total'] ?> registro(s) · 10 por página</p></div><span class="badge badge-opacity-primary">Página <?= $result['page'] ?> de <?= $result['pages'] ?></span></div>
<?php if(!$result['items']): ?><div class="sietelsa-empty-state"><i class="mdi mdi-clipboard-text-clock-outline" aria-hidden="true"></i><p><?= $errorMessage?'La consulta no pudo completarse.':($auditDteScope&&!$selectedUser?'Selecciona un usuario para ver sus movimientos.':'No se encontraron movimientos con los filtros seleccionados.') ?></p></div><?php else: ?>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Fecha y hora</th><th>Usuario / rol</th><th>Módulo</th><th>Acción</th><th>Resultado</th><th>Descripción</th><th>Detalle</th></tr></thead><tbody><?php foreach($result['items'] as $row): ?><tr><td class="text-nowrap"><?= e(date('d/m/Y H:i:s',strtotime($row['creado_en']))) ?><?php if($row['historico']): ?><span class="d-block small text-muted">Histórico</span><?php endif; ?></td><td><?= e($row['nombre_actual']?:'Sistema / cuenta no disponible') ?><span class="d-block small text-muted"><?= e($row['rol']?:'Rol no registrado') ?><?= $row['usuario_id']?' · ID '.(int)$row['usuario_id']:'' ?></span></td><td><?= e(AuditQueryService::moduleLabel($row['modulo'])) ?></td><td class="text-wrap"><?= e(ucfirst(str_replace('_',' ',$row['accion']))) ?></td><td><span class="badge <?= ['correcto'=>'badge-opacity-success','fallido'=>'badge-opacity-danger','denegado'=>'badge-opacity-warning'][''.$row['resultado']]??'badge-opacity-secondary' ?>"><?= e($row['resultado']==='desconocido'?'No registrado':ucfirst($row['resultado'])) ?></span></td><td class="text-wrap"><?= e($row['descripcion']) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e($basePage.'?'.http_build_query($pageQuery+['page'=>$result['page'],'event'=>$row['id']])) ?>" aria-label="Ver detalle del evento <?= (int)$row['id'] ?>">Ver detalle</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if($result['pages']>1): ?><nav class="p-3" aria-label="Paginación de bitácora"><ul class="pagination justify-content-center mb-0"><li class="page-item <?= $result['page']<=1?'disabled':'' ?>"><a class="page-link" href="<?= e($basePage.'?'.http_build_query($pageQuery+['page'=>max(1,$result['page']-1)])) ?>">Anterior</a></li><?php for($p=max(1,$result['page']-2);$p<=min($result['pages'],$result['page']+2);$p++): ?><li class="page-item <?= $p===$result['page']?'active':'' ?>"><a class="page-link" href="<?= e($basePage.'?'.http_build_query($pageQuery+['page'=>$p])) ?>" <?= $p===$result['page']?'aria-current="page"':'' ?>><?= $p ?></a></li><?php endfor; ?><li class="page-item <?= $result['page']>=$result['pages']?'disabled':'' ?>"><a class="page-link" href="<?= e($basePage.'?'.http_build_query($pageQuery+['page'=>min($result['pages'],$result['page']+1)])) ?>">Siguiente</a></li></ul></nav><?php endif; ?>
</div></section>
</div><?php require dirname(__DIR__).'/partials/_footer.php'; ?></div></div></div><?php require dirname(__DIR__).'/partials/_scripts.php'; ?></body></html>
