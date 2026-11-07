<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once dirname(__DIR__,3).'/includes/mail/bootstrap.php';
$currentUser=require_dashboard_module('mail_center');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid($_POST['csrf_token']??null))sietelsa_abort(419);
$mailContext=mail_list_context($_POST);
$repository=new MailRepository(db());
$message=$repository->find((int)($_POST['id']??0));
if(!$message)sietelsa_abort(404);
$action=(string)($_POST['action']??'');
$messageId=(int)$message['id'];
$redirect=mail_route_url('view.php',$mailContext,['id'=>$messageId]);
try {
    if($action==='retry') {
            if(!$message || $message['direccion']!=='saliente' || !in_array($message['estado'],['pendiente','fallido'],true))
                throw new DomainException('Este mensaje no admite reintento; revisa su estado actual.');
            $replyTo=null;
            if($message['tipo']==='notificacion')foreach($repository->thread((int)$message['hilo_id']) as $related)if($related['direccion']==='entrante'){$replyTo=$related['remitente_correo'];break;}
            try {
                (new MailDeliveryService(db(),$repository,new SietelsaMailer()))->deliver($messageId,(int)$currentUser['id'],$replyTo);
                if($message['tipo']==='respuesta')$repository->markThreadAnswered((int)$message['hilo_id'],(int)$currentUser['id']);
                flash_set('success','Mensaje aceptado','El proveedor SMTP aceptó el mensaje; la entrega no está confirmada.');
            } catch(Throwable $e) {
                $fresh=$repository->find($messageId);
                if(in_array($fresh['smtp_estado']??null,['aceptado','entregado','diferido'],true)) {
                    error_log('[SIETELSA][mail] Error posterior a aceptación SMTP; envío conservado.');
                    flash_set('warning','Mensaje aceptado','SMTP aceptó el mensaje. Falló una actualización interna; no vuelvas a enviarlo.');
                } else flash_set('error','Reintento no confirmado',$e instanceof DomainException||$e instanceof MailTransportException?$e->getMessage():'Revisa el estado del mensaje antes de reintentar.');
            }
    } elseif(in_array($action,['read','unread','answered'],true)) {
        if($message['direccion']!=='entrante')throw new DomainException('Esta acción solo corresponde a mensajes recibidos.');
        if($action==='answered') {
            $repository->markThreadAnswered((int)$message['hilo_id'],(int)$currentUser['id']);
            $repository->audit('mensaje_marcado_respondido',(int)$currentUser['id'],$messageId);
            flash_set('success','Estado actualizado','Se marcó como respondido sin enviar un correo.');
        } else {
            $repository->setRead($messageId,$action==='read');
            $repository->audit($action==='read'?'mensaje_leido':'mensaje_no_leido',(int)$currentUser['id'],$messageId);
            flash_set('success','Estado actualizado','El estado de lectura fue actualizado.');
            $redirect=mail_route_url('index.php',$mailContext);
        }
    } else throw new DomainException('La acción solicitada no es válida.');
} catch(DomainException $e) { flash_set('warning','Acción no realizada',$e->getMessage()); }
header('Location: '.$redirect);exit;
