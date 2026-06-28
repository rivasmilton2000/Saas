<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../services/VentasLibroService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

dte_require_permission('dte_emitir', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'JSON invalido.']);
    exit;
}

$idLibro  = (int) ($body['id_libro'] ?? 0);
$facturas = $body['facturas'] ?? [];

if ($idLibro <= 0 || !is_array($facturas)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Debes enviar id_libro y facturas.']);
    exit;
}

$resultado = VentasLibroService::importarVentasContribuyente($pdo, $idLibro, dte_current_user_id(), $facturas);

if (($resultado['success'] ?? false) !== true) {
    http_response_code(422);
}

echo json_encode([
    'success' => (bool) ($resultado['success'] ?? false),
    'data'    => $resultado['data'] ?? null,
    'message' => $resultado['message'] ?? '',
]);
