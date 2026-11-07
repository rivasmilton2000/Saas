<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/companies/bootstrap.php';
$currentUser=require_dashboard_module('user_portal');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sietelsa_abort(405);
if(!csrf_is_valid((string)($_POST['csrf_token']??'')))sietelsa_abort(419);
try{(new CompanyService(db()))->activate((int)($_POST['company_id']??0),$currentUser,$currentUser);flash_set('success','Empresa activa actualizada','Los libros, indicadores y accesos rápidos ya utilizan el nuevo contexto.');}
catch(InvalidArgumentException|DomainException $e){flash_set('error','No fue posible cambiar la empresa',$e->getMessage());}
header('Location: '.app_url('src/dashboard/user.php'));exit;
