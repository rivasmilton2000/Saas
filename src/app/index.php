<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/app.php';

requireLogin();

if (isAdmin()) {
    header('Location: ' . saasUrl('src/admin/index.php'));
    exit;
}

requireUser();

define('SAAS_APP_ENTRY', true);

$appBaseUrl = saasUrl('src/app/index.php');
$routeKey = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));

require __DIR__ . '/routes.php';
