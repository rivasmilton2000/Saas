<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../models/FacturaModel.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

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

$libro = LibroModel::getById($pdo, $idLibro, (int) $_SESSION['id_usuario']);
if (!$libro) {
    http_response_code(403);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Libro no encontrado o sin permiso.']);
    exit;
}

echo json_encode([
    'success' => true,
    'data'    => FacturaModel::getByLibro($pdo, $idLibro),
    'message' => '',
]);
