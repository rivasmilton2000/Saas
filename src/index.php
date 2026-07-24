<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/app.php';

if (!isLoggedIn()) {
    header('Location: ' . saasUrl('src/pages/samples/login.php'));
    exit;
}

$destination = isAdmin()
    ? saasUrl('src/admin/index.php')
    : saasUrl('src/app/index.php');

header('Location: ' . $destination);
exit;
