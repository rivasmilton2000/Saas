<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/LibroController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

LibroController::store();
