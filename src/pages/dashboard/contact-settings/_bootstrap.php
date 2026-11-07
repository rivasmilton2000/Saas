<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/dashboard/modules.php';
require_once SIETELSA_ROOT.'/includes/contact/bootstrap.php';
$currentUser=require_dashboard_module('contact_settings');
$businessHoursService=new BusinessHoursService(db());
$dashboardIncludeAnalytics=false;
$pageTitle='Contacto y horarios | SIETELSA';
$dashboardFlash=flash_pull();
