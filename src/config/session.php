<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['id_usuario']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /Saas/src/pages/samples/login.php');
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        header('Location: /Saas/src/index.php');
        exit;
    }
}
function sessionData(): array {
    return [
        'id_usuario' => $_SESSION['id_usuario'] ?? null,
        'username'   => $_SESSION['username']   ?? null,
        'rol'        => $_SESSION['rol']         ?? null,
    ];
}
