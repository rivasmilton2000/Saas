<?php
declare(strict_types=1);

if (!defined('SAAS_APP_ENTRY')) {
    header('Location: /Saas/src/app/index.php?page=dte.taxpayer-sales');
    exit;
}

require __DIR__ . '/../../../pages/ventas_contribuyente.php';
