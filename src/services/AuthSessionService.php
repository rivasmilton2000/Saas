<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/BitacoraService.php';

class AuthSessionService
{
    public static function establish(PDO $pdo, array $usuario, string $action = 'login', string $description = 'Inicio de sesion correcto.'): void
    {
        if (empty($usuario['id'])) {
            throw new InvalidArgumentException('No se puede iniciar sesion sin usuario.');
        }

        session_regenerate_id(true);

        $_SESSION['id_usuario'] = (int) $usuario['id'];
        $_SESSION['username'] = $usuario['username'];
        $_SESSION['email'] = $usuario['email'] ?? null;
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'] ?? null;
        $_SESSION['pais'] = $usuario['pais'] ?? 'El Salvador';
        $_SESSION['id_plan'] = $usuario['id_plan'] ?? null;
        $_SESSION['plan_nombre'] = $usuario['plan_nombre'] ?? null;
        $_SESSION['plan_slug'] = $usuario['plan_slug'] ?? null;
        $_SESSION['suscripcion_estado'] = $usuario['suscripcion_estado'] ?? null;

        UsuarioModel::markLogin($pdo, (int) $usuario['id']);

        BitacoraService::registrar(
            $pdo,
            (int) $usuario['id'],
            'auth',
            $action,
            $description,
            [
                'username' => (string) ($usuario['username'] ?? ''),
                'rol' => (string) ($usuario['rol'] ?? 'user'),
            ]
        );
    }

    public static function refreshFromDatabase(PDO $pdo, int $userId): ?array
    {
        $usuario = UsuarioModel::getById($pdo, $userId, false);
        if ($usuario === null) {
            return null;
        }

        $_SESSION['username'] = $usuario['username'];
        $_SESSION['email'] = $usuario['email'] ?? null;
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'] ?? null;
        $_SESSION['pais'] = $usuario['pais'] ?? 'El Salvador';
        $_SESSION['id_plan'] = $usuario['id_plan'] ?? null;
        $_SESSION['plan_nombre'] = $usuario['plan_nombre'] ?? null;
        $_SESSION['plan_slug'] = $usuario['plan_slug'] ?? null;
        $_SESSION['suscripcion_estado'] = $usuario['suscripcion_estado'] ?? null;

        return $usuario;
    }

    public static function redirectPathForUser(array $usuario): string
    {
      if ((string) ($usuario['rol'] ?? 'user') === 'admin') {
          return '/Saas/src/admin/index.php';
      }

      if (self::needsPlanSelection($usuario)) {
          return '/Saas/src/pages/samples/select-plan.php';
      }

      return '/Saas/src/index.php';
    }

    public static function needsPlanSelection(array $usuario): bool
    {
        if ((string) ($usuario['rol'] ?? 'user') !== 'user') {
            return false;
        }

        return (int) ($usuario['id_plan'] ?? 0) <= 0;
    }
}
