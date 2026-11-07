<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/admin/bootstrap.php';
require_once SIETELSA_ROOT.'/includes/companies/bootstrap.php';
require_once SIETELSA_ROOT.'/includes/accounting/bootstrap.php';
$currentUser=require_dashboard_module('dte_supervision');
if(($currentUser['rol']??'')!=='administrador')sietelsa_abort(403);
$dteSupervision=new DteSupervisionService(db());
$dashboardIncludeAnalytics=false;
$dashboardFlash=flash_pull();

function dte_admin_url(string $page,array $params=[]): string { $url=app_url('src/dashboard/dte/'.$page);return $params?$url.'?'.http_build_query($params):$url; }
function dte_admin_book_route(string $module): string { return ['compras'=>'purchases.php','ventas_consumidor'=>'consumer-sales.php','ventas_contribuyente'=>'taxpayer-sales.php','retencion_iva'=>'vat-withholding.php'][$module]??'purchases.php'; }
