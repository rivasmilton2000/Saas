<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/companies/bootstrap.php';
$currentUser=require_dashboard_module('companies');
$companyService=new CompanyService(db());
$companyContextUser=company_context_user($currentUser);
$companyExternalContext=company_context_is_external($currentUser,$companyContextUser);
$activeCompany=$companyService->activeCompany($currentUser,$companyContextUser);
$dashboardIncludeAnalytics=false;$pageTitle='Empresas | SIETELSA';$dashboardFlash=flash_pull();
