<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

class AuthController {

    public static function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username === '' || $password === '') {
            self::redirect('Completa todos los campos.');
        }

        global $pdo;
        $stmt = $pdo->prepare("SELECT id, username, password, rol, estado FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            self::redirect('Usuario no encontrado.');
        }

        if (!(bool)$usuario['estado']) {
            self::redirect('Usuario inactivo. Contacta al administrador.');
        }

        if (!password_verify($password, $usuario['password'])) {
            self::redirect('Contraseña incorrecta.');
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['username']   = $usuario['username'];
        $_SESSION['rol']        = $usuario['rol'];

        header('Location: ' . appUrl('src/index.php'));
        exit;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . appUrl('src/pages/samples/login.php'));
        exit;
    }

    private static function redirect(string $error): void {
        header('Location: ' . appUrl('src/pages/samples/login.php') . '?error=' . urlencode($error));
        exit;
    }
}
