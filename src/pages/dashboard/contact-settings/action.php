<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid((string)($_POST['csrf_token']??'')))sietelsa_abort(419);
try { $businessHoursService->save(is_array($_POST['contact']??null)?$_POST['contact']:[],is_array($_POST['days']??null)?$_POST['days']:[],(int)$currentUser['id']); auth_audit('contacto_horarios_actualizados',(int)$currentUser['id'],'session',null,[]); flash_set('success','Configuración guardada','El website ya usa la información de contacto y horarios actualizada.'); }
catch(InvalidArgumentException $e){ flash_set('error','Revisa los horarios',$e->getMessage()); }
header('Location: '.app_url('src/dashboard/contact-settings/index.php'));exit;
