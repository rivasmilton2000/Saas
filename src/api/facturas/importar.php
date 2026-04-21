<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../services/LibroImportService.php';

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

$normalizados = [];
foreach ($documentos as $indice => $payload) {
    if (!is_array($payload)) {
        continue;
    }

    $normalizados[] = [
        'archivo' => 'documento_' . ($indice + 1) . '.json',
        'payload' => $payload,
    ];
}

$resultado = LibroImportService::importarDocumentos($pdo, $idLibro, (int) $_SESSION['id_usuario'], $normalizados);

if (($resultado['success'] ?? false) === false) {
    http_response_code(422);
}

echo json_encode([
    'success' => (bool) ($resultado['success'] ?? false),
    'data'    => $resultado,
    'message' => $resultado['message'] ?? '',
]);
