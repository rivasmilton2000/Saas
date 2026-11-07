<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/backups/bootstrap.php';

$currentUser = require_dashboard_module('backups');
$backupService = new BackupService(db());
$dashboardIncludeAnalytics = false;
$pageTitle = 'Copias de seguridad | SIETELSA';
$dashboardFlash = flash_pull();
