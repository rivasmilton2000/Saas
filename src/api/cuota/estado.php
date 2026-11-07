<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/FacturasCuotaModel.php';

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

$cuota = FacturasCuotaModel::ensure($pdo, (int) $_SESSION['id_usuario']);

echo json_encode([
    'success' => true,
    'data'    => [
        'total'       => $cuota['total'],
        'consumidas'  => $cuota['consumidas'],
        'disponibles' => $cuota['disponibles'],
        'porcentaje'  => $cuota['porcentaje'],
    ],
    'message' => '',
]);
