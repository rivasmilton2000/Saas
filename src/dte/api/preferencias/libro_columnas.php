<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/modulos.php';
require_once __DIR__ . '/../../services/ModulePreferenceService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

dte_require_permission('dte_ver', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$moduleKey = trim((string) ($payload['module_key'] ?? ''));
$action = trim((string) ($payload['action'] ?? 'save'));
$module = getLibroModule($moduleKey);

if ($moduleKey === '' || $module === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Modulo no valido.']);
    exit;
}

$permission = dte_modulo_permiso($module);
if ($permission !== '') {
    dte_require_permission($permission, true);
}

$userId = dte_current_user_id();
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Sesion DTE no valida.']);
    exit;
}

try {
    if ($action === 'restore') {
        $preferences = ModulePreferenceService::restoreDefault($pdo, $moduleKey, $userId, $module);
        $message = 'Configuracion restaurada por defecto.';
    } else {
        $preferences = ModulePreferenceService::save($pdo, $moduleKey, $userId, $payload['preferences'] ?? [], $module);
        $message = 'Configuracion guardada.';
    }

    echo json_encode([
        'success' => true,
        'data' => ModulePreferenceService::clientPayload($moduleKey, $module, $preferences),
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'No se pudo guardar la configuracion: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
