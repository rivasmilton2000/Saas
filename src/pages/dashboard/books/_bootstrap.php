<?php
declare(strict_types=1);
if(!isset($accountingModule,$dashboardModuleId))throw new LogicException('Ruta contable incompleta.');
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/accounting/bootstrap.php';
require_once SIETELSA_ROOT.'/includes/exports/bootstrap.php';
$currentUser=require_dashboard_module($dashboardModuleId);
$moduleInfo=AccountingCatalog::module($accountingModule);
$companyService=new CompanyService(db());
$companyContextUser=company_context_user($currentUser);
$companyExternalContext=company_context_is_external($currentUser,$companyContextUser);
$activeCompany=$companyService->activeCompany($currentUser,$companyContextUser);
$accountingService=new AccountingService(db(),new BookAccessService(db()));
[$exportService,$exportStorage]=accounting_export_services();
$dashboardIncludeAnalytics=false;$dashboardPageScripts=['includes/assets/js/accounting-books.js','includes/assets/js/book-record-tools.js','includes/assets/js/searchable-select.js','includes/assets/js/annex3.js'];
$pageTitle=$moduleInfo['name'].' | SIETELSA';$dashboardFlash=flash_pull();
