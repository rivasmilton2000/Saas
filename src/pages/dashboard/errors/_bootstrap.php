<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/errors/ErrorIncidentService.php';
$currentUser=require_dashboard_module('error_incidents');
$errorIncidentService=new ErrorIncidentService(db());
$dashboardIncludeAnalytics=false;$pageTitle='Errores del sistema | SIETELSA';$dashboardFlash=flash_pull();
function incident_filters(array $source): array { return ['from'=>substr((string)($source['from']??''),0,10),'to'=>substr((string)($source['to']??''),0,10),'user'=>mb_substr(trim((string)($source['user']??'')),0,190),'module'=>mb_substr(trim((string)($source['module']??'')),0,100),'code'=>(int)($source['code']??0)?:'','status'=>in_array(($source['status']??''),['nuevo','revisando','resuelto'],true)?$source['status']:'','q'=>mb_substr(trim((string)($source['q']??'')),0,190)]; }
