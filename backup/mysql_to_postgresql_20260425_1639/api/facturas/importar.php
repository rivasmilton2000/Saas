<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../services/BitacoraService.php';
require_once __DIR__ . '/../../services/LibroVistaService.php';

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

if (!$libro) {
    http_response_code(403);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Libro no encontrado o sin permiso.']);
    exit;
}

$resultado = LibroVistaService::importar($pdo, $libro, $idUsuario, $documentos);

if (($resultado['success'] ?? false) === true) {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($libro['tipo'] ?? 'libros'),
        'importar_api',
        'Importo documentos por API al libro ' . (string) ($libro['tipo'] ?? 'libros') . '.',
        [
            'username'     => (string) ($_SESSION['username'] ?? ''),
            'rol'          => (string) ($_SESSION['rol'] ?? 'user'),
            'entidad_tipo' => 'libro',
            'entidad_id'   => (int) ($libro['id'] ?? 0),
            'contexto'     => [
                'empresa'    => (string) ($libro['empresa_nombre'] ?? ''),
                'periodo'    => str_pad((string) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libro['anio'] ?? ''),
                'libro'      => (string) ($libro['tipo'] ?? 'Libro'),
                'documentos' => count($documentos),
            ],
        ]
    );
}

if (($resultado['success'] ?? false) === false) {
    http_response_code(422);
}

$data = $resultado['data'] ?? $resultado;
if (is_array($data)) {
    unset($data['success'], $data['message']);
}

echo json_encode([
    'success' => (bool) ($resultado['success'] ?? false),
    'data'    => $data,
    'message' => $resultado['message'] ?? '',
]);
