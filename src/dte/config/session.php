<?php
require_once __DIR__ . '/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('dte_root_session_snapshot')) {
    function dte_root_session_snapshot(): array
    {
        return [
            'main_user_id' => (int) ($_SESSION['id_usuario'] ?? 0),
            'main_username' => trim((string) ($_SESSION['username'] ?? '')),
            'main_role' => strtolower(trim((string) ($_SESSION['rol'] ?? 'user'))),
        ];
    }
}

if (!function_exists('dte_context_store')) {
    function dte_context_store(): array
    {
        $context = $_SESSION['_dte_context'] ?? [];
        return is_array($context) ? $context : [];
    }
}

if (!function_exists('dte_flash_store')) {
    function dte_flash_store(): array
    {
        $flash = $_SESSION['_dte_flash'] ?? [];
        return is_array($flash) ? $flash : [];
    }
}

if (!function_exists('dte_login_url')) {
    function dte_login_url(): string
    {
        return parent_app_url('pages/samples/login.php');
    }
}

if (!function_exists('dte_home_url')) {
    function dte_home_url(): string
    {
        return app_url('index.php');
    }
}

if (!function_exists('dte_parent_dashboard_url')) {
    function dte_parent_dashboard_url(): string
    {
        return parent_app_url('index.php');
    }
}

if (!function_exists('dte_logout_url')) {
    function dte_logout_url(): string
    {
        return parent_app_url('pages/samples/logout.php');
    }
}

if (!function_exists('dte_role_normalize')) {
    function dte_role_normalize(?string $role): string
    {
        $role = strtolower(trim((string) $role));
        return $role === 'admin' ? 'admin' : 'user';
    }
}

if (!function_exists('dte_password_placeholder')) {
    function dte_password_placeholder(): string
    {
        try {
            return password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        } catch (Throwable $exception) {
            return password_hash(uniqid('dte-', true), PASSWORD_DEFAULT);
        }
    }
}

if (!function_exists('dte_session_default_permissions')) {
    function dte_session_default_permissions(array $context): array
    {
        $base = [
            'acceso_dte',
            'dte_ver',
            'dte_emitir',
            'dte_reportes',
            'ver_dashboard',
        ];

        if (($context['rol'] ?? 'user') === 'admin') {
            $base[] = 'ver_bitacora';
        }

        return array_values(array_unique($base));
    }
}

if (!function_exists('dte_get_pdo')) {
    function dte_get_pdo(): ?PDO
    {
        if (($GLOBALS['pdo'] ?? null) instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        $dbFile = __DIR__ . '/db.php';
        if (file_exists($dbFile)) {
            require_once $dbFile;
        }

        return ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    }
}

if (!function_exists('dte_build_context_from_row')) {
    function dte_build_context_from_row(array $row, array $snapshot): array
    {
        return [
            'main_user_id' => (int) ($snapshot['main_user_id'] ?? 0),
            'main_username' => (string) ($snapshot['main_username'] ?? ''),
            'main_role' => dte_role_normalize((string) ($snapshot['main_role'] ?? 'user')),
            'id_usuario' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'nombre_completo' => (string) ($row['nombre_completo'] ?? ''),
            'rol' => dte_role_normalize((string) ($row['rol'] ?? 'user')),
            'estado' => (int) ($row['estado'] ?? 1),
            'permissions' => dte_session_default_permissions([
                'rol' => dte_role_normalize((string) ($row['rol'] ?? 'user')),
            ]),
        ];
    }
}

if (!function_exists('dte_sync_session_context')) {
    function dte_sync_session_context(bool $redirectOnAuthError = true): bool
    {
        $snapshot = dte_root_session_snapshot();
        if (($snapshot['main_user_id'] ?? 0) <= 0) {
            unset($_SESSION['_dte_context']);

            if ($redirectOnAuthError) {
                header('Location: ' . dte_login_url());
                exit;
            }

            return false;
        }

        $stored = dte_context_store();
        if (
            (int) ($stored['main_user_id'] ?? 0) === (int) $snapshot['main_user_id']
            && (int) ($stored['id_usuario'] ?? 0) > 0
        ) {
            return true;
        }

        $pdo = dte_get_pdo();
        if (!($pdo instanceof PDO)) {
            if ($redirectOnAuthError) {
                http_response_code(500);
                exit('No se pudo establecer la conexion DTE.');
            }

            return false;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT u.id, u.username, u.nombre_completo, u.rol, u.estado
                 FROM dte_user_bridge b
                 INNER JOIN dte_usuarios u ON u.id = b.dte_user_id
                 WHERE b.main_user_id = ?
                 LIMIT 1"
            );
            $stmt->execute([(int) $snapshot['main_user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
                $pdo->beginTransaction();

                $insertUser = $pdo->prepare(
                    "INSERT INTO dte_usuarios (username, nombre_completo, password, rol, estado)
                     VALUES (?, ?, ?, ?, 1)
                     RETURNING id"
                );
                $insertUser->execute([
                    $snapshot['main_username'] !== '' ? $snapshot['main_username'] : ('saas_user_' . (int) $snapshot['main_user_id']),
                    $snapshot['main_username'] !== '' ? $snapshot['main_username'] : null,
                    dte_password_placeholder(),
                    dte_role_normalize((string) ($snapshot['main_role'] ?? 'user')),
                ]);
                $dteUserId = (int) $insertUser->fetchColumn();

                $upsertBridge = $pdo->prepare(
                    "INSERT INTO dte_user_bridge (main_user_id, dte_user_id, created_at, updated_at)
                     VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                     ON CONFLICT (main_user_id) DO UPDATE
                     SET dte_user_id = EXCLUDED.dte_user_id,
                         updated_at = CURRENT_TIMESTAMP"
                );
                $upsertBridge->execute([
                    (int) $snapshot['main_user_id'],
                    $dteUserId,
                ]);

                $pdo->commit();

                $row = [
                    'id' => $dteUserId,
                    'username' => $snapshot['main_username'] !== '' ? $snapshot['main_username'] : ('saas_user_' . (int) $snapshot['main_user_id']),
                    'nombre_completo' => $snapshot['main_username'] ?? '',
                    'rol' => dte_role_normalize((string) ($snapshot['main_role'] ?? 'user')),
                    'estado' => 1,
                ];
            }

            $_SESSION['_dte_context'] = dte_build_context_from_row($row, $snapshot);
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($redirectOnAuthError) {
                http_response_code(500);
                exit('No se pudo sincronizar la sesion del modulo DTE.');
            }

            return false;
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return dte_sync_session_context(false);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        dte_sync_session_context(true);
    }
}

if (!function_exists('requireGuest')) {
    function requireGuest(): void
    {
        if (dte_sync_session_context(false)) {
            header('Location: ' . dte_home_url());
            exit;
        }
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        if (!dte_sync_session_context(false)) {
            return false;
        }

        return (sessionData()['rol'] ?? 'user') === 'admin';
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        if (!isAdmin()) {
            http_response_code(403);
            exit('No tienes permiso para acceder a este recurso.');
        }
    }
}

if (!function_exists('sessionData')) {
    function sessionData(): array
    {
        dte_sync_session_context(false);
        $context = dte_context_store();

        return [
            'id_usuario' => (int) ($context['id_usuario'] ?? 0),
            'username' => (string) ($context['username'] ?? ''),
            'nombre_completo' => (string) ($context['nombre_completo'] ?? ''),
            'rol' => dte_role_normalize((string) ($context['rol'] ?? 'user')),
            'main_user_id' => (int) ($context['main_user_id'] ?? 0),
            'main_username' => (string) ($context['main_username'] ?? ''),
            'main_profile' => dte_role_normalize((string) ($context['main_role'] ?? 'user')),
            'permissions' => is_array($context['permissions'] ?? null) ? $context['permissions'] : [],
        ];
    }
}

if (!function_exists('dte_current_user_id')) {
    function dte_current_user_id(): int
    {
        $session = sessionData();
        return (int) ($session['id_usuario'] ?? 0);
    }
}

if (!function_exists('dte_has_permission')) {
    function dte_has_permission(string $permissionCode): bool
    {
        $permissionCode = trim($permissionCode);
        if ($permissionCode === '') {
            return true;
        }

        if (!dte_sync_session_context(false)) {
            return false;
        }

        $session = sessionData();
        if (($session['rol'] ?? 'user') === 'admin') {
            return true;
        }

        $permissions = is_array($session['permissions'] ?? null) ? $session['permissions'] : [];
        return in_array($permissionCode, $permissions, true);
    }
}

if (!function_exists('dte_require_permission')) {
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

        http_response_code(403);
        exit('No tienes permiso para acceder a este recurso.');
    }
}

if (!function_exists('dte_modulo_permiso')) {
    function dte_modulo_permiso(array $modulo): string
    {
        return trim((string) ($modulo['permiso'] ?? ''));
    }
}

if (!function_exists('dte_modulo_habilitado')) {
    function dte_modulo_habilitado(array $modulo): bool
    {
        return dte_has_permission(dte_modulo_permiso($modulo));
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $key, string $message, string $type = 'info', array $meta = []): void
    {
        $_SESSION['_dte_flash'][$key] = [
            'message' => $message,
            'type' => $type,
            'meta' => $meta,
        ];
    }
}

if (!function_exists('getFlash')) {
    function getFlash(string $key): ?array
    {
        $flash = dte_flash_store();
        if (!isset($flash[$key])) {
            return null;
        }

        $result = $flash[$key];
        unset($_SESSION['_dte_flash'][$key]);

        return is_array($result) ? $result : null;
    }
}

if (!function_exists('setActiveEmpresaId')) {
    function setActiveEmpresaId(?int $idEmpresa): void
    {
        if ($idEmpresa === null || $idEmpresa <= 0) {
            unset($_SESSION['dte_empresa_activa_id']);
            return;
        }

        $_SESSION['dte_empresa_activa_id'] = $idEmpresa;
    }
}

if (!function_exists('getActiveEmpresaId')) {
    function getActiveEmpresaId(): ?int
    {
        $idEmpresa = $_SESSION['dte_empresa_activa_id'] ?? null;
        if ($idEmpresa === null) {
            return null;
        }

        return (int) $idEmpresa;
    }
}

if (!function_exists('normalizeLibroScope')) {
    function normalizeLibroScope(?string $scope): ?string
    {
        $scope = trim((string) $scope);
        if ($scope === '') {
            return null;
        }

        return strtolower((string) preg_replace('/[^a-z0-9_]/i', '_', $scope));
    }
}

if (!function_exists('syncActiveLibroGlobalFromScopes')) {
    function syncActiveLibroGlobalFromScopes(): void
    {
        $scoped = $_SESSION['dte_libro_activo_por_tipo'] ?? [];
        if (!is_array($scoped) || $scoped === []) {
            unset($_SESSION['dte_libro_activo_id']);
            return;
        }

        $lastId = end($scoped);
        if ($lastId === false || (int) $lastId <= 0) {
            unset($_SESSION['dte_libro_activo_id']);
            return;
        }

        $_SESSION['dte_libro_activo_id'] = (int) $lastId;
    }
}

if (!function_exists('setActiveLibroId')) {
    function setActiveLibroId(?int $idLibro, ?string $scope = null): void
    {
        $scope = normalizeLibroScope($scope);

        if ($scope !== null) {
            $scoped = $_SESSION['dte_libro_activo_por_tipo'] ?? [];
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
                unset($_SESSION['dte_libro_activo_por_tipo']);
            } else {
                $_SESSION['dte_libro_activo_por_tipo'] = $scoped;
            }

            syncActiveLibroGlobalFromScopes();
            return;
        }

        if ($idLibro === null || $idLibro <= 0) {
            unset($_SESSION['dte_libro_activo_id']);
            return;
        }

        $_SESSION['dte_libro_activo_id'] = (int) $idLibro;
    }
}

if (!function_exists('getActiveLibroId')) {
    function getActiveLibroId(?string $scope = null): ?int
    {
        $scope = normalizeLibroScope($scope);

        if ($scope !== null) {
            $idLibro = $_SESSION['dte_libro_activo_por_tipo'][$scope] ?? null;
            return $idLibro === null ? null : (int) $idLibro;
        }

        $idLibro = $_SESSION['dte_libro_activo_id'] ?? null;
        return $idLibro === null ? null : (int) $idLibro;
    }
}
