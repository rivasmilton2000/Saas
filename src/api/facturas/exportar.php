<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../models/FacturaModel.php';
require_once __DIR__ . '/../../services/LibroExportService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

$idLibro = (int) ($_GET['id_libro'] ?? 0);
$formato = trim((string) ($_GET['formato'] ?? 'excel'));
$modo    = trim((string) ($_GET['modo'] ?? 'descarga'));

if ($idLibro <= 0) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'id_libro es requerido.']);
    exit;
}

$libro = LibroModel::getById($pdo, $idLibro, (int) $_SESSION['id_usuario']);
if (!$libro) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Libro no encontrado o sin permiso.']);
    exit;
}

$facturas     = FacturaModel::getByLibro($pdo, $idLibro);
$nombreArchivo = 'libro_' . $libro['tipo'] . '_' . $libro['anio'] . '_' . str_pad((string) $libro['mes'], 2, '0', STR_PAD_LEFT);

if ($modo === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data'    => [
            'columnas' => LibroExportService::columnas(),
            'filas'    => LibroExportService::filas($facturas),
            'libro'    => $libro,
            'formato'  => $formato,
        ],
        'message' => '',
    ]);
    exit;
}

if ($formato === 'excel') {
    LibroExportService::descargarExcel($nombreArchivo, $libro, $facturas);
    exit;
}

if ($formato === 'pdf') {
    LibroExportService::descargarPdf($nombreArchivo, $libro, $facturas);
    exit;
}

if ($formato === 'anexo_mh_a3' || $formato === 'anexo') {
    LibroExportService::descargarAnexoA3($nombreArchivo . '_anexo_mh_a3', $facturas);
    exit;
}

http_response_code(422);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'data' => null, 'message' => 'Formato de exportacion no valido.']);
