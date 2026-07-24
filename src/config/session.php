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
        header('Location: ' . (isAdmin() ? '/Saas/src/admin/index.php' : '/Saas/src/app/index.php'));
        exit;
    }
}

function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['rol'] ?? null) === 'admin');
}

function isUser(): bool {
    return isLoggedIn() && (($_SESSION['rol'] ?? 'user') === 'user');
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: /Saas/src/app/index.php');
        exit;
    }
}

function requireUser(): void {
    if (!isUser()) {
        header('Location: /Saas/src/admin/index.php');
        exit;
    }
}

function sessionData(): array {
    return [
        'id_usuario' => $_SESSION['id_usuario'] ?? null,
        'username'   => $_SESSION['username'] ?? null,
        'email'   => $_SESSION['email'] ?? null,
        'rol'        => $_SESSION['rol'] ?? null,
        'nombre_completo' => $_SESSION['nombre_completo'] ?? null,
        'pais' => $_SESSION['pais'] ?? null,
        'id_plan' => $_SESSION['id_plan'] ?? null,
        'plan_nombre' => $_SESSION['plan_nombre'] ?? null,
        'plan_slug' => $_SESSION['plan_slug'] ?? null,
        'suscripcion_estado' => $_SESSION['suscripcion_estado'] ?? null,
    ];
}

function setFlash(string $key, string $message, string $type = 'info', array $meta = []): void {
    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type'    => $type,
        'meta'    => $meta,
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
