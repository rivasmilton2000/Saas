<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function appUrl(string $path): string {
    $root = str_replace('\\', '/', dirname(__DIR__, 2));
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = '';
    if (strncasecmp($script, $root . '/', strlen($root) + 1) === 0) {
        $relative = substr($script, strlen($root));
        if (substr($scriptName, -strlen($relative)) === $relative) {
            $base = substr($scriptName, 0, -strlen($relative));
        }
    }
    return $base . '/' . ltrim($path, '/');
}

function isLoggedIn(): bool {
    return isset($_SESSION['id_usuario']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('src/pages/samples/login.php'));
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        header('Location: ' . appUrl('src/index.php'));
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
