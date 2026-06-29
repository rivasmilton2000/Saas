<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../services/AuthSessionService.php';
require_once __DIR__ . '/../services/BitacoraService.php';
require_once __DIR__ . '/../services/PublicRegistrationService.php';

class AuthController {

    public static function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $username = trim((string) ($_POST['username'] ?? $_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $oldInput = [
            'username' => $username,
        ];

        if ($username === '' || $password === '') {
            self::redirect('Completa todos los campos.', $oldInput);
        }

        global $pdo;
        $usuario = self::findLoginUser($pdo, $username);

        if (!$usuario) {
            self::redirect('No encontramos una cuenta con ese usuario o correo.', $oldInput);
        }

        if (dbBoolInt($usuario['estado'] ?? false) !== 1) {
            self::redirect('Usuario inactivo. Contacta al administrador.', $oldInput);
        }

        if (trim((string) ($usuario['password'] ?? '')) === '') {
            self::redirect('Esta cuenta usa acceso con Google. Continúa desde ese botón.', $oldInput);
        }

        if (!password_verify($password, (string) $usuario['password'])) {
            self::redirect('Clave incorrecta.', $oldInput);
        }

        if (self::requiresActiveSubscription($usuario) && !self::subscriptionAllowsAccess($usuario['suscripcion_estado'] ?? null)) {
            self::redirect(
                'Tu membresia aun no esta activa. Completa el pago o espera la confirmacion de Stripe para continuar.',
                $oldInput
            );
        }

        AuthSessionService::establish($pdo, $usuario, 'login', 'Inicio de sesion correcto.');
        header('Location: ' . AuthSessionService::redirectPathForUser($usuario));
        exit;
    }

    public static function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        global $pdo;
        $allowAdminRole = UsuarioModel::canSelectAdminOnPublicRegister($pdo);
        $requestedRole = UsuarioModel::normalizeRole($_POST['rol'] ?? 'user');

        if ($allowAdminRole && $requestedRole === 'admin') {
            $validation = UsuarioModel::validateNewUser($pdo, $_POST, true);

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

        try {
            $result = PublicRegistrationService::start($pdo, $_POST);
        } catch (Throwable $exception) {
            setFlash('register', 'No se pudo iniciar el registro en este momento. Revisa la configuracion de pago e intenta de nuevo.', 'danger', [
                'old' => self::registerOldInput($_POST),
            ]);
            header('Location: /Saas/src/pages/samples/register.php');
            exit;
        }

        if (!($result['ok'] ?? false)) {
            $meta = [
                'old' => $result['old'] ?? self::registerOldInput($_POST),
            ];

            if (!empty($result['contact_url'])) {
                $meta['contact_url'] = (string) $result['contact_url'];
            }

            setFlash(
                'register',
                (string) ($result['message'] ?? 'No se pudo crear la cuenta.'),
                (string) ($result['flash_type'] ?? 'danger'),
                $meta
            );
            header('Location: /Saas/src/pages/samples/register.php');
            exit;
        }

        if (($result['mode'] ?? '') === 'checkout' && !empty($result['checkout_url'])) {
            header('Location: ' . (string) $result['checkout_url']);
            exit;
        }

        if (($result['mode'] ?? '') === 'checkout') {
            setFlash(
                'register',
                'No pudimos abrir Stripe Checkout en este momento. Revisa la configuracion del plan e intenta otra vez.',
                'danger',
                [
                    'old' => $result['old'] ?? self::registerOldInput($_POST),
                ]
            );
            header('Location: /Saas/src/pages/samples/register.php');
            exit;
        }

        $user = !empty($result['user_id'])
            ? UsuarioModel::getById($pdo, (int) $result['user_id'], false)
            : null;

        if ($user !== null) {
            AuthSessionService::establish($pdo, $user, 'register_free', 'Registro correcto con plan Free.');
            header('Location: ' . AuthSessionService::redirectPathForUser($user));
            exit;
        }

        setFlash('login', 'Tu cuenta fue creada correctamente. Inicia sesion para continuar.', 'success');
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

    private static function redirect(string $error, array $old = []): void {
        setFlash('login', $error, 'danger', [
            'old' => $old,
        ]);
        header('Location: /Saas/src/pages/samples/login.php');
        exit;
    }

    private static function findLoginUser(PDO $pdo, string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            $usuario = UsuarioModel::getByEmail($pdo, $identifier);
            if ($usuario !== null) {
                return $usuario;
            }
        }

        return UsuarioModel::getByUsername($pdo, $identifier);
    }

    private static function requiresActiveSubscription(array $usuario): bool
    {
        if ((string) ($usuario['rol'] ?? 'user') !== 'user') {
            return false;
        }

        $planSlug = strtolower(trim((string) ($usuario['plan_slug'] ?? '')));
        if ($planSlug === 'enterprise') {
            return true;
        }

        $planPrecio = $usuario['plan_precio'] ?? null;
        if ($planPrecio === null || $planPrecio === '') {
            return false;
        }

        return (float) $planPrecio > 0;
    }

    private static function subscriptionAllowsAccess(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['active', 'trialing'], true);
    }

    private static function registerOldInput(array $input): array
    {
        return [
            'nombre_completo' => trim((string) ($input['nombre_completo'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'username' => trim((string) ($input['username'] ?? '')),
            'pais' => trim((string) ($input['pais'] ?? 'El Salvador')),
            'id_plan' => (int) ($input['id_plan'] ?? 0),
        ];
    }
}
