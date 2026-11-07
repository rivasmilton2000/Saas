<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/audit/AuditQueryService.php';
$auditDteScope = ($auditDteScope??false)===true;
$currentUser=require_dashboard_module($auditDteScope?'dte_audit':'general_audit');
if(($currentUser['rol']??'')!=='administrador')sietelsa_abort(403);
$auditQuery=new AuditQueryService(db(),$currentUser);
$dashboardIncludeAnalytics=false;
header('Cache-Control: no-store, private');
