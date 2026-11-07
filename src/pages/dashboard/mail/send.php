<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once dirname(__DIR__,3).'/includes/mail/bootstrap.php';
$currentUser=require_dashboard_module('mail_center');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid($_POST['csrf_token']??null))sietelsa_abort(419);
$mailContext=mail_list_context($_POST);
$repository=new MailRepository(db());
$mode=in_array($_POST['mode']??'', ['reply','forward'],true)?(string)$_POST['mode']:'new';
$original=$mode==='new'?null:$repository->find((int)($_POST['original_id']??0));
$recipient=mb_strtolower(trim((string)($_POST['recipient']??'')));
if($mode==='reply' && $original!==null)$recipient=(string)$original['remitente_correo'];
$subject=trim(strip_tags((string)($_POST['subject']??'')));
$body=trim(strip_tags((string)($_POST['body']??'')));
$returnToForm=static function()use($mode,$original,$recipient,$subject,$body,$mailContext):void {
    if($mode==='reply' && $original!==null) {
        $_SESSION['mail_reply_old'][(int)$original['id']]=$body;
        header('Location: '.mail_route_url('view.php',$mailContext,['id'=>(int)$original['id']]));
    } else {
        $_SESSION['mail_compose_old']=['mode'=>$mode,'original_id'=>(int)($original['id']??0),'recipient'=>$recipient,'subject'=>$subject,'body'=>$body];
        header('Location: '.mail_route_url('compose.php',$mailContext,['mode'=>$mode,'id'=>(int)($original['id']??0)]));
    }
};
if(filter_var($recipient,FILTER_VALIDATE_EMAIL)===false || preg_match('/[\r\n]/',$recipient) || mb_strlen($recipient)>190
    || mb_strlen($subject)<1 || mb_strlen($subject)>255 || mb_strlen($body)<1 || mb_strlen($body)>50000
    || ($mode!=='new' && $original===null) || ($mode==='reply' && $original['direccion']!=='entrante')) {
    flash_set('error','Datos inválidos','Revisa el destinatario, asunto y contenido del correo.');
    $returnToForm(); exit;
}
try { $repository->assertSendRate((int)$currentUser['id']); }
catch(DomainException $e) { flash_set('warning','Límite alcanzado',$e->getMessage());$returnToForm();exit; }
$kind=$mode==='new'?'nuevo':($mode==='reply'?'respuesta':'reenvio');
$template=MailTemplates::outgoing($body,$kind,$original);
$inReplyTo=$mode==='reply'?($original['message_id_externo']??null):null;
$references=$mode==='reply'?trim((string)($original['referencias']??'').' '.(string)($original['message_id_externo']??'')):null;
$from=trim((string)env('MAIL_FROM_ADDRESS',''));
if(filter_var($from,FILTER_VALIDATE_EMAIL)===false)$from='pendiente@sietelsa.invalid';
try {
$submission=MailSubmission::create(db(),(int)$currentUser['id'],(string)($_POST['submission_id']??''),[$mode,(int)($original['id']??0),$recipient,$subject,$body],static function()use($repository,$original,$subject,$kind,$from,$recipient,$template,$inReplyTo,$references,$currentUser):int {
return $repository->createMessage([
    'hilo_id'=>$original===null?$repository->createThread($subject):(int)$original['hilo_id'],'direccion'=>'saliente','origen'=>'administrador','tipo'=>$kind,
    'remitente_nombre'=>env('MAIL_FROM_NAME','SIETELSA'),'remitente_correo'=>$from,'destinatario_correo'=>$recipient,
    'asunto'=>$subject,'cuerpo_texto'=>$template['text'],'cuerpo_html'=>$template['html'],'estado'=>'pendiente',
    'in_reply_to'=>$inReplyTo,'referencias'=>$references,'respondido_por'=>(int)$currentUser['id'],
]);
});
} catch(DomainException $e) {flash_set('warning','Solicitud no enviada',$e->getMessage());$returnToForm();exit;}
$messageId=$submission['id'];
$threadId=(int)$repository->find($messageId)['hilo_id'];
try {
    (new MailDeliveryService(db(),$repository,new SietelsaMailer()))->deliver($messageId,(int)$currentUser['id']);
    if($mode==='reply') {
        $repository->markThreadAnswered($threadId,(int)$currentUser['id']);
        unset($_SESSION['mail_reply_old'][(int)$original['id']]);
    }
    flash_set('success','Mensaje aceptado','El proveedor SMTP aceptó el mensaje; la entrega no está confirmada.');
} catch(Throwable $e) {
    $fresh=$repository->find($messageId);
    if(in_array($fresh['smtp_estado']??null,['aceptado','entregado','diferido'],true)) {
        error_log('[SIETELSA][mail] Error posterior a la aceptación SMTP; no se repetirá el envío.');
        flash_set('warning','Mensaje aceptado','SMTP aceptó el mensaje, pero no se pudo completar una actualización interna. No vuelvas a enviarlo.');
    } else {
        if(($fresh['smtp_estado']??null)===null && $e instanceof MailTransportException)$repository->updateDelivery($messageId,'fallido',null,$e->getMessage());
        flash_set('error','Envío no confirmado',$e instanceof DomainException||$e instanceof MailTransportException?$e->getMessage():'El mensaje se conservó. Revisa su estado antes de reintentar.');
    }
}
header('Location: '.mail_route_url('view.php',$mailContext,['id'=>$messageId]));
exit;
