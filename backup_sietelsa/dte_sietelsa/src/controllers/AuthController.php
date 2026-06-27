<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

class AuthController {

    public static function login(): void {
        // El login del modulo DTE se delega al login principal del sistema.
        header('Location: /admin/login');
        exit;
    }

    public static function register(): void {
        // El alta de usuarios se realiza desde el panel principal unificado.
        header('Location: /admin/usuarios/registro');
        exit;
    }

    public static function logout(): void {
        header('Location: /admin/auth/logout');
        exit;
    }

    public static function redirectToMainLogin(string $error = ''): void {
        $url = '/admin/login';
        if ($error !== '') {
            $url .= '?error=' . urlencode($error);
        }

        header('Location: ' . $url);
        exit;
    }
}
