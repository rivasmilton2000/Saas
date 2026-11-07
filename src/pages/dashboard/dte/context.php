<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid((string)($_POST['csrf_token']??'')))sietelsa_abort(419);
$userId=max(0,(int)($_POST['user_id']??0));$companyId=max(0,(int)($_POST['company_id']??0));$bookId=max(0,(int)($_POST['book_id']??0));$module=(string)($_POST['module']??'');$destination=(string)($_POST['destination']??'');
try{
    $owner=company_context_enter($currentUser,$userId);
    if($companyId>0)(new CompanyService(db()))->activate($companyId,$currentUser,$owner);
    $redirect=dte_admin_url('supervision.php',['user_id'=>$userId]);
    if($bookId>0){
        $book=(new BookAccessService(db()))->requireAuthorized($bookId,$userId,'admin_supervision_open');
        if((int)$book['empresa_id']!==$companyId||$book['tipo']!==$module)throw new DomainException('El libro no corresponde al alcance seleccionado.');
        AuditService::record(db(),'dte','administrador_abrio_libro_usuario',(int)$currentUser['id'],['contexto_usuario_id'=>$userId,'entidad'=>'libro','entidad_id'=>$bookId,'detalles'=>['modulo'=>$module]]);
        $s=db()->prepare('INSERT INTO bitacora_administrativa_dte(actor_usuario_id,contexto_usuario_id,empresa_id,libro_id,accion,origen,motivo,valor_nuevo,ip) VALUES(:actor,:owner,:company,:book,"administrador_abrio_libro_usuario","supervision","Consulta y asistencia administrativa",:value,:ip)');
        $s->execute(['actor'=>$currentUser['id'],'owner'=>$userId,'company'=>$companyId,'book'=>$bookId,'value'=>json_encode(['modulo'=>$module],JSON_UNESCAPED_UNICODE),'ip'=>request_ip()]);
        $redirect=app_url('src/dashboard/books/'.dte_admin_book_route($module)).'?book='.$bookId;
    }elseif($companyId>0){$redirect=$destination==='edit_company'?app_url('src/dashboard/companies/form.php').'?id='.$companyId:dte_admin_url('company.php',['user_id'=>$userId,'company_id'=>$companyId]);}
    header('Location: '.$redirect);exit;
}catch(InvalidArgumentException|DomainException $e){flash_set('error','No fue posible abrir la supervisión',$e->getMessage());header('Location: '.dte_admin_url('supervision.php'));exit;}
