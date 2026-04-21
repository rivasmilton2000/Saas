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

function setFlash(string $key, string $message, string $type = 'info'): void {
    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type'    => $type,
    ];
}

function getFlash(string $key): ?array {
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $flash = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $flash;
}

function setActiveEmpresaId(?int $idEmpresa): void {
    if ($idEmpresa === null || $idEmpresa <= 0) {
        unset($_SESSION['empresa_activa_id']);
        return;
    }

    $_SESSION['empresa_activa_id'] = $idEmpresa;
}

function getActiveEmpresaId(): ?int {
    $idEmpresa = $_SESSION['empresa_activa_id'] ?? null;
    if ($idEmpresa === null) {
        return null;
    }

    return (int) $idEmpresa;
}

function setActiveLibroId(?int $idLibro): void {
    if ($idLibro === null || $idLibro <= 0) {
        unset($_SESSION['libro_activo_id']);
        return;
    }

    $_SESSION['libro_activo_id'] = $idLibro;
}

function getActiveLibroId(): ?int {
    $idLibro = $_SESSION['libro_activo_id'] ?? null;
    if ($idLibro === null) {
        return null;
    }

    return (int) $idLibro;
}
