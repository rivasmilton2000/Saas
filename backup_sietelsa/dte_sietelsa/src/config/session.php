<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/main_root.php';

function dte_main_root(): string
{
    return dte_main_system_root();
}

function dte_require_main_stack(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    global $pdo;

    $root = dte_main_root();
    require_once $root . '/include/conexion.php';
    if ($pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
    }

    require_once $root . '/include/auth.php';
    require_once $root . '/include/dte_integration.php';

    // Si conexion.php fue incluido en este scope, sincroniza tambien en $GLOBALS.
    if ($pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
    } elseif (($GLOBALS['pdo'] ?? null) instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    $loaded = true;
}

function dte_sync_session_context(bool $redirectOnAuthError = true): bool
{
    dte_require_main_stack();

    if (!function_exists('iniciar_sesion_segura')) {
        return false;
    }

    iniciar_sesion_segura();

    if (!usuario_autenticado()) {
        if ($redirectOnAuthError) {
            header('Location: /admin/login?error=auth');
            exit;
        }

        return false;
    }

    $usuarioPrincipal = obtener_usuario_actual() ?? [];
    if (!usuario_tiene_acceso_dte($usuarioPrincipal)) {
        if ($redirectOnAuthError) {
            mostrar_pagina_error(403);
            exit;
        }

        return false;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        if ($redirectOnAuthError) {
            header('Location: /admin/dashboard?error=dte_db');
            exit;
        }

        return false;
    }

    $contextoDte = dte_contexto_usuario($pdo, $usuarioPrincipal);
    if (!is_array($contextoDte) || (int) ($contextoDte['id_usuario'] ?? 0) <= 0) {
        if ($redirectOnAuthError) {
            header('Location: /admin/dashboard?error=dte_setup');
            exit;
        }

        return false;
    }

    $rolPrincipal = strtolower(trim((string) ($usuarioPrincipal['perfil'] ?? '')));
    $rolDte = in_array($rolPrincipal, ['admin', 'administrador', 'developer'], true)
        ? 'admin'
        : (string) ($contextoDte['rol'] ?? 'user');

    $_SESSION['id_usuario'] = (int) ($contextoDte['id_usuario'] ?? 0);
    $_SESSION['username'] = (string) ($contextoDte['username'] ?? '');
    $_SESSION['rol'] = $rolDte;
    $_SESSION['dte_main_user_id'] = (int) ($contextoDte['main_user_id'] ?? 0);
    $_SESSION['dte_main_profile'] = $rolPrincipal;

    if (($GLOBALS['pdo'] ?? null) instanceof PDO && function_exists('bitacora_registrar_request_si_aplica')) {
        bitacora_registrar_request_si_aplica(
            $GLOBALS['pdo'],
            [
                'id' => (int) ($contextoDte['main_user_id'] ?? 0),
                'nombre' => (string) ($usuarioPrincipal['nombre'] ?? ''),
                'username' => (string) ($usuarioPrincipal['username'] ?? ''),
                'email' => (string) ($usuarioPrincipal['email'] ?? ''),
                'perfil' => $rolPrincipal,
            ]
        );
    }

    return true;
}

if (session_status() === PHP_SESSION_NONE) {
    dte_sync_session_context(false);
}

function isLoggedIn(): bool
{
    if (!dte_sync_session_context(false)) {
        return false;
    }

    return isset($_SESSION['id_usuario']) && (int) ($_SESSION['id_usuario'] ?? 0) > 0;
}

function requireLogin(): void
{
    dte_sync_session_context(true);
}

function requireGuest(): void
{
    dte_sync_session_context(false);
    if (usuario_autenticado()) {
        header('Location: /admin/dte');
        exit;
    }
}

function isAdmin(): bool
{
    if (!dte_sync_session_context(false)) {
        return false;
    }

    return (($_SESSION['rol'] ?? 'user') === 'admin');
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        mostrar_pagina_error(403);
        exit;
    }
}

function dte_has_permission(string $permissionCode): bool
{
    dte_sync_session_context(false);

    $permissionCode = trim($permissionCode);
    if ($permissionCode === '') {
        return true;
    }

    if (function_exists('usuario_actual_es_admin') && usuario_actual_es_admin()) {
        return true;
    }

    $usuarioPrincipal = function_exists('obtener_usuario_actual') ? (obtener_usuario_actual() ?? []) : [];
    $perfilPrincipal = strtolower(trim((string) ($usuarioPrincipal['perfil'] ?? '')));
    if ($perfilPrincipal === 'dte') {
        $permisosDtePorDefecto = [
            'ver_dashboard',
            'ver_bitacora',
            'acceso_dte',
            'dte_ver',
            'dte_emitir',
            'dte_reportes',
        ];
        if (in_array($permissionCode, $permisosDtePorDefecto, true)) {
            return true;
        }
    }

    if (!function_exists('tiene_permiso')) {
        return false;
    }

    return tiene_permiso($permissionCode);
}

function dte_require_permission(string $permissionCode, bool $jsonResponse = false): void
{
    if (dte_has_permission($permissionCode)) {
        return;
    }

    if ($jsonResponse) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'No tienes permiso para ejecutar esta accion.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    mostrar_pagina_error(403);
    exit;
}

function dte_modulo_permiso(array $modulo): string
{
    return trim((string) ($modulo['permiso'] ?? ''));
}

function dte_modulo_habilitado(array $modulo): bool
{
    $permiso = dte_modulo_permiso($modulo);
    return dte_has_permission($permiso);
}

function sessionData(): array
{
    dte_sync_session_context(false);

    return [
        'id_usuario' => $_SESSION['id_usuario'] ?? null,
        'username'   => $_SESSION['username']   ?? null,
        'rol'        => $_SESSION['rol']        ?? null,
        'main_user_id' => $_SESSION['dte_main_user_id'] ?? null,
        'main_profile' => $_SESSION['dte_main_profile'] ?? null,
    ];
}

function setFlash(string $key, string $message, string $type = 'info', array $meta = []): void
{
    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type'    => $type,
        'meta'    => $meta,
    ];
}

function getFlash(string $key): ?array
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $flash = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $flash;
}

function setActiveEmpresaId(?int $idEmpresa): void
{
    if ($idEmpresa === null || $idEmpresa <= 0) {
        unset($_SESSION['empresa_activa_id']);
        return;
    }

    $_SESSION['empresa_activa_id'] = $idEmpresa;
}

function getActiveEmpresaId(): ?int
{
    $idEmpresa = $_SESSION['empresa_activa_id'] ?? null;
    if ($idEmpresa === null) {
        return null;
    }

    return (int) $idEmpresa;
}

function normalizeLibroScope(?string $scope): ?string
{
    $scope = trim((string) $scope);
    if ($scope === '') {
        return null;
    }

    return strtolower((string) preg_replace('/[^a-z0-9_]/i', '_', $scope));
}

function syncActiveLibroGlobalFromScopes(): void
{
    $scoped = $_SESSION['libro_activo_por_tipo'] ?? [];
    if (!is_array($scoped) || $scoped === []) {
        unset($_SESSION['libro_activo_id']);
        return;
    }

    $ultimoId = end($scoped);
    if ($ultimoId === false || (int) $ultimoId <= 0) {
        unset($_SESSION['libro_activo_id']);
        return;
    }

    $_SESSION['libro_activo_id'] = (int) $ultimoId;
}

function setActiveLibroId(?int $idLibro, ?string $scope = null): void
{
    $scope = normalizeLibroScope($scope);

    if ($scope !== null) {
        $scoped = $_SESSION['libro_activo_por_tipo'] ?? [];
        if (!is_array($scoped)) {
            $scoped = [];
        }

        if ($idLibro === null || $idLibro <= 0) {
            unset($scoped[$scope]);
        } else {
            unset($scoped[$scope]);
            $scoped[$scope] = (int) $idLibro;
        }

        if ($scoped === []) {
            unset($_SESSION['libro_activo_por_tipo']);
        } else {
            $_SESSION['libro_activo_por_tipo'] = $scoped;
        }

        syncActiveLibroGlobalFromScopes();
        return;
    }

    if ($idLibro === null || $idLibro <= 0) {
        unset($_SESSION['libro_activo_id']);
        return;
    }

    $_SESSION['libro_activo_id'] = $idLibro;
}

function getActiveLibroId(?string $scope = null): ?int
{
    $scope = normalizeLibroScope($scope);

    if ($scope !== null) {
        $idLibro = $_SESSION['libro_activo_por_tipo'][$scope] ?? null;
        if ($idLibro === null) {
            return null;
        }

        return (int) $idLibro;
    }

    $idLibro = $_SESSION['libro_activo_id'] ?? null;
    if ($idLibro === null) {
        return null;
    }

    return (int) $idLibro;
}
