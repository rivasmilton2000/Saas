<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../services/RetencionIvaService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

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

$idLibro    = (int) ($body['id_libro'] ?? 0);
$documentos = $body['facturas'] ?? [];

if ($idLibro <= 0 || !is_array($documentos)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Debes enviar id_libro y facturas.']);
    exit;
}

$idUsuario = (int) $_SESSION['id_usuario'];
$libro     = LibroModel::getById($pdo, $idLibro, $idUsuario);

if (!$libro || ($libro['tipo'] ?? '') !== 'retencion_iva') {
    http_response_code(403);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Libro no encontrado o sin permiso.']);
    exit;
}

$resultado = RetencionIvaService::importarRetencionIva($pdo, $idLibro, $idUsuario, $documentos);
if (($resultado['success'] ?? false) !== true) {
    http_response_code(422);
}

echo json_encode([
    'success' => (bool) ($resultado['success'] ?? false),
    'data'    => $resultado['data'] ?? null,
    'message' => $resultado['message'] ?? '',
]);
