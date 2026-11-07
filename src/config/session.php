<?php
require_once __DIR__ . '/app.php';
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
    static $validatedId = null;
    if (!isset($_SESSION['id_usuario'])) {
        return false;
    }
    if ($validatedId !== (int) $_SESSION['id_usuario']) {
        global $pdo;
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare('SELECT rol, estado FROM usuarios WHERE id = ?');
        $stmt->execute([(int) $_SESSION['id_usuario']]);
        $user = $stmt->fetch();
        if (!$user || !dbBoolValue($user['estado'])) {
            $_SESSION = [];
        } else {
            $_SESSION['rol'] = $user['rol'];
            $validatedId = (int) $_SESSION['id_usuario'];
        }
    }
    return isset($_SESSION['id_usuario']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . saasUrl('src/pages/samples/login.php'));
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        requireLogin();
        header('Location: ' . appUrl(isAdmin() ? 'src/admin/index.php' : 'src/app/index.php'));
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
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . saasUrl('src/app/index.php'));
        exit;
    }
}

function requireUser(): void {
    requireLogin();
    if (!isUser()) {
        header('Location: ' . saasUrl('src/admin/index.php'));
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
