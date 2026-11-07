<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/users/bootstrap.php';

$currentUser = require_dashboard_module('users');
$userService = user_service();
$invitationService = invitation_service();
$dashboardIncludeAnalytics = false;

function users_redirect(string $path = 'index.php'): void
{
    header('Location: ' . app_url('src/dashboard/users/' . ltrim($path, '/')));
    exit;
}

function users_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        users_redirect();
    }
    if (!csrf_is_valid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        flash_set('error', 'Solicitud inválida', 'Actualiza la página e inténtalo nuevamente.');
        users_redirect();
    }
}
