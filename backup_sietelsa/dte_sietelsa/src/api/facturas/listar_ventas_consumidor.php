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

dte_require_permission('dte_ver', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

$idLibro = (int) ($_GET['id_libro'] ?? 0);
if ($idLibro <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'id_libro es requerido.']);
    exit;
}

$resultado = VentasLibroService::listarVentasConsumidor($pdo, $idLibro, (int) $_SESSION['id_usuario']);

if (($resultado['success'] ?? false) !== true) {
    http_response_code(422);
}

echo json_encode([
    'success' => (bool) ($resultado['success'] ?? false),
    'data'    => $resultado['data'] ?? null,
    'message' => $resultado['message'] ?? '',
]);
