<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/app.php';


if (!defined('SAAS_APP_ENTRY')) {
    header('Location: ' . saasUrl('src/app/index.php?page=dte.taxpayer-sales'));
    exit;
}

require __DIR__ . '/../../../pages/ventas_contribuyente.php';
