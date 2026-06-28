<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../services/BitacoraService.php';

class AuthController {

    public static function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            self::redirect('Completa todos los campos.');
        }

        global $pdo;
        $usuario = UsuarioModel::getByUsername($pdo, $username);

        if (!$usuario) {
            self::redirect('Usuario no encontrado.');
        }

        if (dbBoolInt($usuario['estado'] ?? false) !== 1) {
            self::redirect('Usuario inactivo. Contacta al administrador.');
        }

        if (!password_verify($password, (string) $usuario['password'])) {
            self::redirect('Clave incorrecta.');
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['username']   = $usuario['username'];
        $_SESSION['rol']        = $usuario['rol'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'] ?? null;
        $_SESSION['pais'] = $usuario['pais'] ?? 'El Salvador';
        $_SESSION['id_plan'] = $usuario['id_plan'] ?? null;
        $_SESSION['plan_nombre'] = $usuario['plan_nombre'] ?? null;

        UsuarioModel::markLogin($pdo, (int) $usuario['id']);

        BitacoraService::registrar(
            $pdo,
            (int) $usuario['id'],
            'auth',
            'login',
            'Inicio de sesion correcto.',
            [
                'username' => (string) $usuario['username'],
                'rol'      => (string) $usuario['rol'],
            ]
        );

        header('Location: /Saas/src/index.php');
        exit;
    }

    public static function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        global $pdo;
        $allowAdminRole = UsuarioModel::canSelectAdminOnPublicRegister($pdo);
        $validation = UsuarioModel::validateNewUser($pdo, $_POST, $allowAdminRole);

        if (!($validation['ok'] ?? false)) {
            setFlash('register', (string) ($validation['message'] ?? 'No se pudo crear la cuenta.'), 'danger', [
                'old' => $validation['old'] ?? [],
            ]);
            header('Location: /Saas/src/pages/samples/register.php');
            exit;
        }

        $idNuevoUsuario = UsuarioModel::create($pdo, $validation['data']);
        BitacoraService::registrar(
            $pdo,
            $idNuevoUsuario,
            'auth',
            'register',
            'Registro de cuenta nuevo.',
            [
                'username'   => (string) ($validation['data']['username'] ?? ''),
                'rol'        => (string) ($validation['data']['rol'] ?? 'user'),
                'entidad_id' => $idNuevoUsuario,
            ]
        );
        setFlash('login', 'Cuenta creada correctamente. Inicia sesion para continuar.', 'success');
        header('Location: /Saas/src/pages/samples/login.php');
        exit;
    }

    public static function logout(): void {
        global $pdo;
        $session = sessionData();

        if (!empty($session['id_usuario'])) {
            BitacoraService::registrar(
                $pdo,
                (int) $session['id_usuario'],
                'auth',
                'logout',
                'Cierre de sesion.',
                [
                    'username' => (string) ($session['username'] ?? ''),
                    'rol'      => (string) ($session['rol'] ?? 'user'),
                ]
            );
        }

        session_destroy();
        header('Location: /Saas/src/pages/samples/login.php');
        exit;
    }

    private static function redirect(string $error): void {
        header('Location: /Saas/src/pages/samples/login.php?error=' . urlencode($error));
        exit;
    }
}
