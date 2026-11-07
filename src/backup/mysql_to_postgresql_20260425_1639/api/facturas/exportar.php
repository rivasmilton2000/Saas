<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/modulos.php';
require_once __DIR__ . '/../../models/FacturaModel.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../services/LibroExportService.php';
require_once __DIR__ . '/../../services/ModuloExportService.php';
require_once __DIR__ . '/../../services/BitacoraService.php';
require_once __DIR__ . '/../../services/LibroVistaService.php';

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

$idUsuario = (int) $_SESSION['id_usuario'];
$libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
if (!$libro) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Libro no encontrado o sin permiso.']);
    exit;
}

$resultado = LibroVistaService::listar($pdo, $idLibro, $idUsuario, (string) ($libro['tipo'] ?? ''));
if (($resultado['success'] ?? false) !== true) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'data'    => $resultado['data'] ?? null,
        'message' => $resultado['message'] ?? 'No se pudo preparar el libro.',
    ]);
    exit;
}

$data          = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
$modulo        = getLibroModule((string) ($libro['tipo'] ?? '')) ?? ['nombre' => 'Libro', 'columnas' => [], 'columnas_numericas' => [], 'accent_color' => '#4b49ac'];
$nombreArchivo = 'libro_' . $libro['tipo'] . '_' . $libro['anio'] . '_' . str_pad((string) $libro['mes'], 2, '0', STR_PAD_LEFT);
$meses         = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
$periodo = (($meses[(int) ($libro['mes'] ?? 0)] ?? (string) ($libro['mes'] ?? '')) . ' ' . (string) ($libro['anio'] ?? ''));
$summary = LibroVistaService::construirResumenTarjetas($modulo, $resultado);
$report  = [
    'titulo'          => (string) ($modulo['nombre'] ?? 'Libro'),
    'meta'            => trim((string) ($libro['empresa_nombre'] ?? '') . ' · ' . $periodo),
    'accent_color'    => (string) ($modulo['accent_color'] ?? '#4b49ac'),
    'summary'         => $summary,
    'columns'         => array_map(static function ($clave, $label): array {
        return [
            'key'   => (string) $clave,
            'label' => (string) $label,
        ];
    }, array_keys($modulo['columnas'] ?? []), array_values($modulo['columnas'] ?? [])),
    'rows'            => $data['filas'] ?? [],
    'numeric_keys'    => $modulo['columnas_numericas'] ?? [],
    'total_row_index' => !empty($data['registros']) ? count($data['filas'] ?? []) - 1 : null,
    'empty_message'   => 'No hay registros para exportar en este periodo.',
];

if ($modo === 'json') {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($libro['tipo'] ?? 'libros'),
        'exportar',
        'Exporto un libro en formato JSON.',
        [
            'username'     => (string) ($_SESSION['username'] ?? ''),
            'rol'          => (string) ($_SESSION['rol'] ?? 'user'),
            'entidad_tipo' => 'libro',
            'entidad_id'   => (int) ($libro['id'] ?? 0),
            'contexto'     => [
                'empresa' => (string) ($libro['empresa_nombre'] ?? ''),
                'periodo' => str_pad((string) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libro['anio'] ?? ''),
                'formato' => 'json',
                'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
            ],
        ]
    );
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data'    => [
            'columnas' => $report['columns'],
            'filas'    => $report['rows'],
            'libro'    => $libro,
            'formato'  => $formato,
            'summary'  => $summary,
        ],
        'message' => '',
    ]);
    exit;
}

if ($formato === 'excel') {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($libro['tipo'] ?? 'libros'),
        'exportar',
        'Exporto un libro en formato Excel.',
        [
            'username'     => (string) ($_SESSION['username'] ?? ''),
            'rol'          => (string) ($_SESSION['rol'] ?? 'user'),
            'entidad_tipo' => 'libro',
            'entidad_id'   => (int) ($libro['id'] ?? 0),
            'contexto'     => [
                'empresa' => (string) ($libro['empresa_nombre'] ?? ''),
                'periodo' => str_pad((string) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libro['anio'] ?? ''),
                'formato' => 'excel',
                'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
            ],
        ]
    );
    ModuloExportService::descargarExcel($nombreArchivo, $report);
    exit;
}

if ($formato === 'pdf') {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($libro['tipo'] ?? 'libros'),
        'exportar',
        'Exporto un libro en formato PDF.',
        [
            'username'     => (string) ($_SESSION['username'] ?? ''),
            'rol'          => (string) ($_SESSION['rol'] ?? 'user'),
            'entidad_tipo' => 'libro',
            'entidad_id'   => (int) ($libro['id'] ?? 0),
            'contexto'     => [
                'empresa' => (string) ($libro['empresa_nombre'] ?? ''),
                'periodo' => str_pad((string) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libro['anio'] ?? ''),
                'formato' => 'pdf',
                'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
            ],
        ]
    );
    ModuloExportService::descargarPdf($nombreArchivo, $report);
    exit;
}

if (($formato === 'anexo_mh_a3' || $formato === 'anexo') && ($libro['tipo'] ?? '') === 'compras') {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($libro['tipo'] ?? 'libros'),
        'exportar',
        'Exporto un libro en formato anexo MH A3.',
        [
            'username'     => (string) ($_SESSION['username'] ?? ''),
            'rol'          => (string) ($_SESSION['rol'] ?? 'user'),
            'entidad_tipo' => 'libro',
            'entidad_id'   => (int) ($libro['id'] ?? 0),
            'contexto'     => [
                'empresa' => (string) ($libro['empresa_nombre'] ?? ''),
                'periodo' => str_pad((string) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libro['anio'] ?? ''),
                'formato' => 'anexo_mh_a3',
                'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
            ],
        ]
    );
    LibroExportService::descargarAnexoA3($nombreArchivo . '_anexo_mh_a3', FacturaModel::getByLibro($pdo, $idLibro));
    exit;
}

http_response_code(422);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'data' => null, 'message' => 'Formato de exportacion no valido.']);
