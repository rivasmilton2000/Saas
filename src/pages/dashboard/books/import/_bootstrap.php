<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/dte/bootstrap.php';
require_authentication();

$moduleMap = [
    'compras' => ['book_purchases', 'purchases.php'],
    'ventas_consumidor' => ['book_consumer_sales', 'consumer-sales.php'],
    'ventas_contribuyente' => ['book_taxpayer_sales', 'taxpayer-sales.php'],
    'retencion_iva' => ['book_vat_withholding', 'vat-withholding.php'],
];
$accountingModule = (string) ($_REQUEST['module'] ?? '');
if (!isset($moduleMap[$accountingModule])) {
    sietelsa_abort(422, '', new InvalidArgumentException('Módulo contable inválido.'));
}
[$dashboardModuleId, $moduleRoute] = $moduleMap[$accountingModule];

$currentUser = require_dashboard_module($dashboardModuleId);
CompanyPermissionService::require($currentUser, 'book.import');
$companyService = new CompanyService(db());
$companyContextUser = company_context_user($currentUser);
$companyExternalContext = company_context_is_external($currentUser, $companyContextUser);
$activeCompany = $companyService->activeCompany($currentUser, $companyContextUser);
$accountingService = new AccountingService(db(), new BookAccessService(db()));
[$importService, $dteStorage, $dteParser] = dte_import_services();
$moduleInfo = AccountingCatalog::module($accountingModule);
$dashboardIncludeAnalytics = false;
$dashboardPageScripts = ['includes/assets/js/dte-import.js'];
$pageTitle = 'Importar JSON | '.$moduleInfo['name'];
$dashboardFlash = flash_pull();
