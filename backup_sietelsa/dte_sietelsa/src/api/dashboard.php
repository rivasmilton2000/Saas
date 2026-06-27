<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

dte_require_permission('dte_ver', true);

$data = DashboardController::getData((int) $_SESSION['id_usuario']);

echo json_encode([
    'success' => true,
    'data'    => $data,
    'message' => '',
]);
