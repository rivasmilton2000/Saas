<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/EmpresaModel.php';

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

global $pdo;
$idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

if ($idEmpresa <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'id_empresa es requerido.']);
    exit;
}

$empresa = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);
if (!$empresa) {
    http_response_code(403);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Empresa no encontrada o sin permiso.']);
    exit;
}

$eliminada = EmpresaModel::delete($pdo, $idEmpresa, $idUsuario);
echo json_encode([
    'success' => $eliminada,
    'data'    => null,
    'message' => $eliminada ? 'Empresa eliminada.' : 'No se pudo eliminar la empresa.',
]);
