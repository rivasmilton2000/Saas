<?php
require_once __DIR__ . '/../models/PageVisitModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class PageVisitService
{
    public static function track(PDO $pdo, array $session, string $routeKey, string $routeLabel): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return;
        }

        $idUsuario = (int) ($session['id_usuario'] ?? 0);
        if ($idUsuario <= 0) {
            return;
        }

        $throttleKey = '__page_visit_' . trim($routeKey);
        $now = time();
        $lastVisit = (int) ($_SESSION[$throttleKey] ?? 0);

        if ($lastVisit > 0 && ($now - $lastVisit) < 60) {
            UsuarioModel::markActivity($pdo, $idUsuario);
            return;
        }

        $usuario = UsuarioModel::getById($pdo, $idUsuario, false);
        if ($usuario === null) {
            return;
        }

        try {
            PageVisitModel::track($pdo, [
                'id_usuario' => $idUsuario,
                'username_snapshot' => (string) ($usuario['username'] ?? ($session['username'] ?? 'usuario')),
                'rol_snapshot' => (string) ($usuario['rol'] ?? ($session['rol'] ?? 'user')),
                'pais_snapshot' => (string) ($usuario['pais'] ?? 'El Salvador'),
                'route_key' => $routeKey,
                'route_label' => $routeLabel,
                'ip_address' => self::resolveIp(),
                'user_agent' => self::resolveUserAgent(),
            ]);
            UsuarioModel::markActivity($pdo, $idUsuario);
            $_SESSION[$throttleKey] = $now;
        } catch (Throwable $exception) {
            return;
        }
    }

    private static function resolveIp(): ?string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $value));
                return $parts[0] !== '' ? $parts[0] : null;
            }

            return $value;
        }

        return null;
    }

    private static function resolveUserAgent(): ?string
    {
        $value = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $value !== '' ? substr($value, 0, 255) : null;
    }
}
