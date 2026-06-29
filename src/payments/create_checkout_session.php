<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/PendingRegistrationModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../services/AuthSessionService.php';
require_once __DIR__ . '/../services/PublicRegistrationService.php';
require_once __DIR__ . '/../services/UserPlanSelectionService.php';

$isJson = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
    || strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest';

$respond = static function (array $payload, int $statusCode = 200) use ($isJson): void {
    if ($isJson) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!empty($payload['redirect_url'])) {
        header('Location: ' . (string) $payload['redirect_url']);
        exit;
    }

    http_response_code($statusCode);
    echo htmlspecialchars((string) ($payload['message'] ?? 'No se pudo preparar Stripe Checkout.'), ENT_QUOTES, 'UTF-8');
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    $respond([
        'ok' => false,
        'message' => 'Usa POST para crear la sesion de checkout.',
    ], 405);
}

$input = $_POST;
if ($input === []) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$planId = (int) ($input['plan_id'] ?? $input['id_plan'] ?? 0);

try {
    if (isLoggedIn()) {
        $session = sessionData();
        $user = UsuarioModel::getById($pdo, (int) ($session['id_usuario'] ?? 0), false);
        if ($user === null) {
            throw new RuntimeException('No encontramos una sesion valida para continuar.');
        }

        $result = UserPlanSelectionService::start($pdo, $user, $planId);
        if (!($result['ok'] ?? false)) {
            $respond([
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'No se pudo preparar el checkout.'),
                'contact_url' => $result['contact_url'] ?? null,
            ], 422);
        }

        if (($result['mode'] ?? '') === 'checkout') {
            session_write_close();
            $respond([
                'ok' => true,
                'redirect_url' => (string) ($result['checkout_url'] ?? ''),
            ]);
        }

        $freshUser = UsuarioModel::getById($pdo, (int) ($user['id'] ?? 0), false);
        if ($freshUser !== null) {
            AuthSessionService::refreshFromDatabase($pdo, (int) $freshUser['id']);
        }

        $respond([
            'ok' => true,
            'redirect_url' => '/Saas/src/index.php',
        ]);
    }

    $pendingId = (int) ($input['pending_id'] ?? $input['pending_registration_id'] ?? 0);
    if ($pendingId <= 0) {
        throw new RuntimeException('Necesitas una sesion activa o un registro temporal para continuar con Stripe.');
    }

    $result = PublicRegistrationService::resumePendingCheckout($pdo, $pendingId, $planId);
    if (!($result['ok'] ?? false)) {
        $respond([
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'No se pudo preparar el checkout.'),
            'contact_url' => $result['contact_url'] ?? null,
        ], 422);
    }

    session_write_close();
    $respond([
        'ok' => true,
        'redirect_url' => (string) ($result['checkout_url'] ?? ''),
    ]);
} catch (Throwable $exception) {
    error_log('[Stripe Checkout] ' . $exception->getMessage());
    $respond([
        'ok' => false,
        'message' => 'No se pudo crear la sesion de Stripe Checkout en este momento.',
    ], 500);
}
