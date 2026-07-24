<?php
require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../models/PendingRegistrationModel.php';
require_once __DIR__ . '/../models/PlanModel.php';
require_once __DIR__ . '/../models/SubscriptionModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/AuthSessionService.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/StripeBillingService.php';
require_once __DIR__ . '/UserPlanSelectionService.php';

class GoogleAuthService
{
    public static function authenticate(PDO $pdo, array $input): array
    {
        if (!googleIsConfigured()) {
            return self::error('Google aun no esta configurado en este entorno.');
        }

        $credential = trim((string) ($input['credential'] ?? ''));
        $context = strtolower(trim((string) ($input['context'] ?? 'login')));
        $expectedNonce = googlePeekAuthNonce();

        try {
            $claims = self::verifyIdToken($credential, $expectedNonce);
        } catch (Throwable $exception) {
            googleAuthNonce(true);
            return self::error($exception->getMessage());
        }

        googleAuthNonce(true);

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $googleId = trim((string) ($claims['sub'] ?? ''));
        $displayName = trim((string) ($claims['name'] ?? ''));
        $picture = trim((string) ($claims['picture'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return self::error('Google no devolvio un correo valido para esta cuenta.');
        }

        if (!filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
            return self::error('Solo aceptamos cuentas de Google con correo verificado.');
        }

        $user = UsuarioModel::getByGoogleId($pdo, $googleId);
        if ($user === null) {
            $user = UsuarioModel::getByEmail($pdo, $email);
        }

        $selectedPlanId = (int) ($input['id_plan'] ?? 0);
        $country = normalizeCountryValue($input['pais'] ?? 'El Salvador', 'El Salvador');
        $requestedUsername = trim((string) ($input['username'] ?? ''));
        $termsAccepted = !empty($input['terms']) || !empty($input['terms_accepted']);

        if ($user !== null) {
            UsuarioModel::updateAuthData($pdo, (int) $user['id'], [
                'google_id' => $googleId,
                'foto_perfil' => $picture !== '' ? $picture : ($user['foto_perfil'] ?? null),
                'email' => $email,
                'email_verificado_at' => date('Y-m-d H:i:s'),
                'auth_provider' => (string) ($user['auth_provider'] ?? '') === '' ? 'google' : (string) ($user['auth_provider'] ?? 'google'),
            ]);

            $freshUser = UsuarioModel::getById($pdo, (int) $user['id'], false) ?? $user;

            if (AuthSessionService::needsPlanSelection($freshUser)) {
                AuthSessionService::establish($pdo, $freshUser, 'google_login', 'Inicio de sesion con Google.');

                if ($context === 'register' && $selectedPlanId > 0) {
                    $planResult = UserPlanSelectionService::start($pdo, $freshUser, $selectedPlanId);
                    if (($planResult['ok'] ?? false) && ($planResult['mode'] ?? '') === 'free') {
                        $updatedUser = UsuarioModel::getById($pdo, (int) $freshUser['id'], false) ?? $freshUser;
                        AuthSessionService::refreshFromDatabase($pdo, (int) $updatedUser['id']);
                        return [
                            'ok' => true,
                            'redirect_url' => AuthSessionService::redirectPathForUser($updatedUser),
                        ];
                    }

                    if (($planResult['ok'] ?? false) && ($planResult['mode'] ?? '') === 'checkout') {
                        session_destroy();
                        return [
                            'ok' => true,
                            'redirect_url' => (string) ($planResult['checkout_url'] ?? ''),
                        ];
                    }

                    return self::error((string) ($planResult['message'] ?? 'No se pudo preparar la membresia seleccionada.'));
                }

                return [
                    'ok' => true,
                    'redirect_url' => AuthSessionService::redirectPathForUser($freshUser),
                ];
            }

            if (self::requiresActiveSubscription($freshUser) && !StripeBillingService::subscriptionStatusAllowsAccess($freshUser['suscripcion_estado'] ?? null)) {
                $sessionId = trim((string) ($freshUser['stripe_checkout_session_id'] ?? ''));
                return [
                    'ok' => true,
                    'redirect_url' => '/Saas/src/payments/payment_success.php' . ($sessionId !== '' ? '?session_id=' . rawurlencode($sessionId) : ''),
                ];
            }

            AuthSessionService::establish($pdo, $freshUser, 'google_login', 'Inicio de sesion con Google.');
            return [
                'ok' => true,
                'redirect_url' => AuthSessionService::redirectPathForUser($freshUser),
            ];
        }

        if ($context === 'register') {
            if (!$termsAccepted) {
                return self::error('Debes aceptar terminos y condiciones para registrarte con Google.');
            }

            $plan = $selectedPlanId > 0 ? PlanModel::getById($pdo, $selectedPlanId) : PlanModel::getBySlug($pdo, 'free');
            if ($plan === null || !dbBoolValue($plan['activo'] ?? true)) {
                return self::error('Selecciona una membresia valida para continuar.');
            }

            $username = self::resolveUsername($pdo, $requestedUsername, $email);
            if ($username === null) {
                return self::error('No pudimos generar un nombre de usuario valido para esta cuenta.');
            }

            if (stripePlanIsCustom($plan)) {
                return self::error('El plan Enterprise se coordina con ventas. Usa el enlace de contacto para personalizarlo.');
            }

            if (stripePlanIsFree($plan)) {
                $userId = UsuarioModel::create($pdo, [
                    'username' => $username,
                    'nombre_completo' => $displayName !== '' ? $displayName : self::fallbackNameFromEmail($email),
                    'foto_perfil' => $picture !== '' ? $picture : null,
                    'email' => $email,
                    'password' => null,
                    'rol' => 'user',
                    'estado' => true,
                    'pais' => $country,
                    'id_plan' => (int) ($plan['id_plan'] ?? 0),
                    'suscripcion_estado' => 'free',
                    'auth_provider' => 'google',
                    'google_id' => $googleId,
                    'email_verificado_at' => date('Y-m-d H:i:s'),
                ]);

                $newUser = UsuarioModel::getById($pdo, $userId, false);
                if ($newUser !== null) {
                    MailService::sendWelcomeFree($pdo, $newUser, $plan);
                    AuthSessionService::establish($pdo, $newUser, 'google_register_free', 'Registro con Google y plan Free.');
                }

                return [
                    'ok' => true,
                    'redirect_url' => '/Saas/src/app/index.php',
                ];
            }

            if (!StripeBillingService::isConfigured()) {
                return self::error('Stripe aun no esta configurado para cobrar las membresias pagas.');
            }

            $pendingId = PendingRegistrationModel::create($pdo, [
                'nombre_completo' => $displayName !== '' ? $displayName : self::fallbackNameFromEmail($email),
                'email' => $email,
                'username' => $username,
                'password_hash' => null,
                'pais' => $country,
                'id_plan' => (int) ($plan['id_plan'] ?? 0),
                'plan_slug_snapshot' => (string) ($plan['slug'] ?? ''),
                'plan_nombre_snapshot' => (string) ($plan['nombre'] ?? ''),
                'plan_precio_snapshot' => $plan['precio'] ?? null,
                'estado' => 'draft',
                'auth_provider' => 'google',
                'google_id' => $googleId,
                'foto_perfil' => $picture !== '' ? $picture : null,
                'email_verificado_at' => date('Y-m-d H:i:s'),
            ]);

            $session = StripeBillingService::createRegistrationCheckoutSession($pendingId, $plan, $email);
            PendingRegistrationModel::updateStripeData($pdo, $pendingId, [
                'stripe_checkout_session_id' => (string) ($session->id ?? ''),
                'stripe_status' => (string) ($session->payment_status ?? 'payment_pending'),
                'estado' => 'checkout_created',
                'stripe_payload_json' => StripeBillingService::objectToArray($session),
            ]);
            SubscriptionModel::upsert($pdo, [
                'user_id' => null,
                'plan_id' => (int) ($plan['id_plan'] ?? 0),
                'stripe_checkout_session_id' => (string) ($session->id ?? ''),
                'status' => 'payment_pending',
                'amount' => $plan['precio'] ?? null,
                'currency' => $plan['moneda'] ?? 'USD',
                'billing_interval' => $plan['billing_interval'] ?? $plan['periodo'] ?? null,
            ]);

            return [
                'ok' => true,
                'redirect_url' => (string) ($session->url ?? ''),
            ];
        }

        $username = self::resolveUsername($pdo, $requestedUsername, $email);
        if ($username === null) {
            return self::error('No pudimos preparar tu cuenta de Google para continuar.');
        }

        $userId = UsuarioModel::create($pdo, [
            'username' => $username,
            'nombre_completo' => $displayName !== '' ? $displayName : self::fallbackNameFromEmail($email),
            'foto_perfil' => $picture !== '' ? $picture : null,
            'email' => $email,
            'password' => null,
            'rol' => 'user',
            'estado' => true,
            'pais' => $country,
            'id_plan' => null,
            'suscripcion_estado' => 'plan_pending',
            'auth_provider' => 'google',
            'google_id' => $googleId,
            'email_verificado_at' => date('Y-m-d H:i:s'),
        ]);

        $newUser = UsuarioModel::getById($pdo, $userId, false);
        if ($newUser !== null) {
            AuthSessionService::establish($pdo, $newUser, 'google_register_pending', 'Registro con Google pendiente de plan.');
        }

        return [
            'ok' => true,
            'redirect_url' => '/Saas/src/pages/samples/select-plan.php',
        ];
    }

    private static function verifyIdToken(string $credential, ?string $expectedNonce): array
    {
        $credential = trim($credential);
        if ($credential === '') {
            throw new InvalidArgumentException('No recibimos una credencial valida de Google.');
        }

        $parts = explode('.', $credential);
        if (count($parts) !== 3) {
            throw new RuntimeException('La credencial de Google tiene un formato invalido.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        $signature = self::base64UrlDecode($encodedSignature);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('No se pudo leer la respuesta firmada de Google.');
        }

        if (($header['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('Google respondio con un algoritmo no soportado.');
        }

        $kid = trim((string) ($header['kid'] ?? ''));
        if ($kid === '') {
            throw new RuntimeException('La respuesta de Google no incluye una clave publica identificable.');
        }

        $certs = self::getGoogleCertificates();
        $certificate = (string) ($certs[$kid] ?? '');
        if ($certificate === '') {
            throw new RuntimeException('No encontramos la clave publica de Google para validar la sesion.');
        }

        $signedData = $encodedHeader . '.' . $encodedPayload;
        $verified = openssl_verify($signedData, $signature, $certificate, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new RuntimeException('No se pudo verificar la firma del token de Google.');
        }

        $config = googleConfig();
        $audience = trim((string) ($payload['aud'] ?? ''));
        if ($audience === '' || $audience !== $config['client_id']) {
            throw new RuntimeException('La credencial de Google no pertenece a este cliente.');
        }

        $issuer = trim((string) ($payload['iss'] ?? ''));
        if (!in_array($issuer, $config['issuers'], true)) {
            throw new RuntimeException('Google devolvio un emisor no reconocido.');
        }

        $now = time();
        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= 0 || $expiresAt < $now) {
            throw new RuntimeException('La credencial de Google ya expiro. Intenta de nuevo.');
        }

        if ($expectedNonce !== null) {
            $nonce = trim((string) ($payload['nonce'] ?? ''));
            if ($nonce === '' || !hash_equals($expectedNonce, $nonce)) {
                throw new RuntimeException('La sesion de Google no coincide con esta pantalla. Recarga e intenta otra vez.');
            }
        }

        return $payload;
    }

    private static function getGoogleCertificates(): array
    {
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zentra_google_certs.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (
                is_array($cached) &&
                !empty($cached['certs']) &&
                (int) ($cached['expires_at'] ?? 0) > time()
            ) {
                return (array) $cached['certs'];
            }
        }

        $config = googleConfig();
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $certsJson = @file_get_contents($config['certs_url'], false, $context);
        if ($certsJson === false || trim($certsJson) === '') {
            throw new RuntimeException('No pudimos descargar las claves publicas de Google.');
        }

        $certs = json_decode($certsJson, true);
        if (!is_array($certs) || $certs === []) {
            throw new RuntimeException('Google respondio con un juego de claves invalido.');
        }

        $headers = $http_response_header ?? [];
        $maxAge = self::extractMaxAge($headers);
        @file_put_contents($cacheFile, json_encode([
            'expires_at' => time() + $maxAge,
            'certs' => $certs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $certs;
    }

    private static function extractMaxAge(array $headers): int
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Cache-Control:') !== 0) {
                continue;
            }

            if (preg_match('/max-age=(\d+)/i', $header, $matches) === 1) {
                return max(60, (int) $matches[1]);
            }
        }

        return 3600;
    }

    private static function resolveUsername(PDO $pdo, string $requestedUsername, string $email): ?string
    {
        $candidate = trim($requestedUsername);
        if ($candidate !== '') {
            if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $candidate) !== 1) {
                return null;
            }

            if (!UsuarioModel::usernameExists($pdo, $candidate) && !PendingRegistrationModel::usernameExistsOpen($pdo, $candidate)) {
                return $candidate;
            }
        }

        $base = preg_replace('/[^a-z0-9._-]+/i', '', strtok($email, '@') ?: 'zentrauser');
        $base = trim((string) $base, '.-_');
        if ($base === '') {
            $base = 'zentrauser';
        }

        $base = substr($base, 0, 30);
        $suffix = 0;
        do {
            $candidate = $suffix === 0 ? $base : substr($base, 0, max(1, 30 - strlen((string) $suffix))) . $suffix;
            if (
                preg_match('/^[A-Za-z0-9._-]{3,50}$/', $candidate) === 1 &&
                !UsuarioModel::usernameExists($pdo, $candidate) &&
                !PendingRegistrationModel::usernameExistsOpen($pdo, $candidate)
            ) {
                return $candidate;
            }
            $suffix++;
        } while ($suffix < 1000);

        return null;
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);
        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    private static function fallbackNameFromEmail(string $email): string
    {
        $local = strtok($email, '@') ?: 'Usuario Zentra';
        return ucwords(str_replace(['.', '_', '-'], ' ', $local));
    }

    private static function requiresActiveSubscription(array $user): bool
    {
        $price = $user['plan_precio'] ?? null;
        return (string) ($user['rol'] ?? 'user') === 'user'
            && $price !== null
            && $price !== ''
            && (float) $price > 0;
    }

    private static function error(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
