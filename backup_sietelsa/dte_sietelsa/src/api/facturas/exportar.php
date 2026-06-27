<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/modulos.php';
require_once __DIR__ . '/../../models/FacturaModel.php';
require_once __DIR__ . '/../../models/LibroModel.php';
require_once __DIR__ . '/../../services/DteDataService.php';
require_once __DIR__ . '/../../services/IvaWorkbookExportService.php';
require_once __DIR__ . '/../../services/HaciendaCsvExportService.php';
require_once __DIR__ . '/../../services/LibroExportService.php';
require_once __DIR__ . '/../../services/ModuloExportService.php';
require_once __DIR__ . '/../../services/LibroVistaService.php';
require_once __DIR__ . '/../../services/ModulePreferenceService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.']);
    exit;
}

dte_require_permission('dte_reportes', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
    exit;
}

$idLibro = (int) ($_GET['id_libro'] ?? 0);
$formato = trim((string) ($_GET['formato'] ?? 'excel'));
$modo    = trim((string) ($_GET['modo'] ?? 'descarga'));
$reporte = trim((string) ($_GET['reporte'] ?? 'libro'));

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
$tipoLibro     = (string) ($libro['tipo'] ?? '');
$modulePreferences = getModuleColumnConfig($pdo, $tipoLibro, $idUsuario, $modulo);
$hasCustomPreferences = ModulePreferenceService::hasCustomPreferences($pdo, $tipoLibro, $idUsuario);
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
$metaLibro = trim((string) ($libro['empresa_nombre'] ?? ''));
if ($metaLibro !== '' && $periodo !== '') {
    $metaLibro .= ' - ' . $periodo;
} elseif ($metaLibro === '') {
    $metaLibro = $periodo;
}

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
$report['meta'] = $metaLibro;

if ($hasCustomPreferences) {
    $columnasExportables = ModulePreferenceService::filterColumnMap($modulo['columnas'] ?? [], $modulePreferences, 'excel');
    $report['columns'] = array_map(static function ($clave, $label): array {
        return [
            'key'   => (string) $clave,
            'label' => (string) $label,
        ];
    }, array_keys($columnasExportables), array_values($columnasExportables));

    $report['rows'] = array_map(static function (array $row) use ($tipoLibro, $modulePreferences): array {
        $tipoDte = (string) ($row['tipo_dte'] ?? '');
        if (array_key_exists('tipo_documento_nombre', $row)) {
            $row['tipo_documento_nombre'] = getDocumentTypeLabelForModule(
                $tipoLibro,
                $tipoDte,
                $modulePreferences,
                (string) ($row['tipo_documento_nombre'] ?? '')
            );
        }
        $row['_highlight_document_type'] = shouldHighlightDocumentType($tipoLibro, $tipoDte, $modulePreferences);

        return $row;
    }, is_array($report['rows'] ?? null) ? $report['rows'] : []);
}
$facturasLibro = null;
$obtenerFacturasLibro = static function () use (&$facturasLibro, $pdo, $idLibro): array {
    if ($facturasLibro === null) {
        $facturasLibro = DteDataService::hydrateFacturas(FacturaModel::getByLibro($pdo, $idLibro));
    }

    return $facturasLibro;
	};

$facturasCandidatas = null;
$obtenerFacturasCandidatas = static function () use (&$facturasCandidatas, $pdo, $idLibro): array {
    if ($facturasCandidatas === null) {
        $facturasCandidatas = FacturaModel::getByLibro($pdo, $idLibro, true);
    }

    return $facturasCandidatas;
};

$fueraPeriodoColumns = [
    'fecha'                => 'Fecha de emision',
    'tipo_documento_nombre'=> 'Tipo de documento',
    'numero_control'       => 'Numero de control',
    'codigo_generacion'    => 'Codigo de generacion',
    'nombre_proveedor'     => 'Emisor/proveedor',
    'nit'                  => 'NIT',
    'nrc'                  => 'NRC',
    'compras_exentas'      => 'Total exento',
    'compras_gravadas'     => 'Total gravado',
    'impuestos_calculados' => 'IVA',
    'fovial'               => 'FOVIAL',
    'cotrans'              => 'COTRANS',
    'total_compras'        => 'Total pagar',
    'motivo'               => 'Motivo',
];

$repetidasColumns = [
    'fecha'                 => 'Fecha de emision',
    'tipo_documento_nombre' => 'Tipo de documento',
    'numero_control'        => 'Numero de control',
    'codigo_generacion'     => 'Codigo generacion apartado',
    'codigo_generacion_original' => 'Codigo generacion original',
    'nombre_proveedor'      => 'Emisor/proveedor',
    'nit'                   => 'NIT',
    'nrc'                   => 'NRC',
    'total_compras'         => 'Total',
    'motivo'                => 'Motivo de exclusion',
    'documento_original'    => 'Documento original encontrado',
    'estado'                => 'Estado',
];

if ($reporte === 'fuera_periodo') {
    $facturasFuera = DteDataService::hydrateFacturas(FacturaModel::getFueraPeriodoByLibro($pdo, $idLibro));
    $rowsFuera = [];
    foreach ($facturasFuera as $indice => $facturaFuera) {
        $rowsFuera[] = LibroVistaService::mapearFilaFueraPeriodo($facturaFuera, $indice + 1);
    }

    $nombreArchivo .= '_fuera_periodo';
    $reportFuera = [
        'titulo'          => 'DTE no contabilizados',
        'meta'            => $metaLibro,
        'accent_color'    => (string) ($modulo['accent_color'] ?? '#4b49ac'),
        'summary'         => [
            [
                'label' => 'Documentos',
                'value' => (string) count($rowsFuera),
                'note'  => 'Fuera de periodo',
            ],
            [
                'label' => 'Periodo libro',
                'value' => $periodo,
                'note'  => 'Referencia contable',
            ],
        ],
        'columns'         => array_map(static function ($clave, $label): array {
            return [
                'key'   => (string) $clave,
                'label' => (string) $label,
            ];
        }, array_keys($fueraPeriodoColumns), array_values($fueraPeriodoColumns)),
        'rows'            => $rowsFuera,
        'numeric_keys'    => ['compras_exentas', 'compras_gravadas', 'impuestos_calculados', 'fovial', 'cotrans', 'total_compras'],
        'total_row_index' => null,
        'empty_message'   => 'No hay DTE fuera de periodo para exportar.',
    ];

    if ($modo === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data'    => [
                'columnas' => $reportFuera['columns'],
                'filas'    => $reportFuera['rows'],
                'libro'    => $libro,
                'formato'  => $formato,
            ],
            'message' => '',
        ]);
        exit;
    }

    if ($formato === 'csv') {
        ModuloExportService::descargarCsv($nombreArchivo, $reportFuera);
        exit;
    }

    if ($formato === 'excel') {
        ModuloExportService::descargarExcel($nombreArchivo, $reportFuera);
        exit;
    }

    if ($formato === 'pdf') {
        ModuloExportService::descargarPdf($nombreArchivo, $reportFuera);
        exit;
    }

    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Formato de exportacion no valido para fuera de periodo.']);
    exit;
}

if ($reporte === 'repetidas') {
    $facturasPrincipales = DteDataService::hydrateFacturas(FacturaModel::getByLibro($pdo, $idLibro));
    $originalesPorCodigo = [];
    $originalesPorSecundaria = [];
    foreach ($facturasPrincipales as $facturaPrincipal) {
        $codigoPrincipal = trim((string) ($facturaPrincipal['codigo_generacion'] ?? ''));
        if ($codigoPrincipal !== '' && !isset($originalesPorCodigo[$codigoPrincipal])) {
            $originalesPorCodigo[$codigoPrincipal] = $facturaPrincipal;
        }
        $partesSecundariaPrincipal = [
            trim((string) ($facturaPrincipal['tipo_dte'] ?? '')),
            trim((string) ($facturaPrincipal['numero_control'] ?? '')),
            trim((string) ($facturaPrincipal['nit'] ?? '')),
            trim((string) ($facturaPrincipal['sello_recepcion'] ?? '')),
        ];
        $secundariaPrincipal = implode('|', $partesSecundariaPrincipal);
        if (!in_array('', $partesSecundariaPrincipal, true) && !isset($originalesPorSecundaria[$secundariaPrincipal])) {
            $originalesPorSecundaria[$secundariaPrincipal] = $facturaPrincipal;
        }
    }

    $facturasRepetidas = DteDataService::hydrateFacturas(FacturaModel::getRepetidasByLibro($pdo, $idLibro));
    $rowsRepetidas = [];
    foreach ($facturasRepetidas as $indice => $facturaRepetida) {
        $codigoRepetido = trim((string) ($facturaRepetida['codigo_generacion'] ?? ''));
        $partesSecundariaRepetida = [
            trim((string) ($facturaRepetida['tipo_dte'] ?? '')),
            trim((string) ($facturaRepetida['numero_control'] ?? '')),
            trim((string) ($facturaRepetida['nit'] ?? '')),
            trim((string) ($facturaRepetida['sello_recepcion'] ?? '')),
        ];
        $secundariaRepetida = implode('|', $partesSecundariaRepetida);
        $originalRepetida = $codigoRepetido !== '' ? ($originalesPorCodigo[$codigoRepetido] ?? null) : null;
        if ($originalRepetida === null && !in_array('', $partesSecundariaRepetida, true)) {
            $originalRepetida = $originalesPorSecundaria[$secundariaRepetida] ?? null;
        }
        if ($originalRepetida === null || (int) ($originalRepetida['id'] ?? 0) <= 0) {
            continue;
        }

        $rowsRepetidas[] = LibroVistaService::mapearFilaApartada(
            $facturaRepetida,
            count($rowsRepetidas) + 1,
            'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.',
            $originalRepetida
        );
    }

    $nombreArchivo .= '_repetidas';
    $reportRepetidas = [
        'titulo'          => 'Documentos detectados como repetidos',
        'meta'            => $metaLibro,
        'accent_color'    => (string) ($modulo['accent_color'] ?? '#4b49ac'),
        'summary'         => [
            [
                'label' => 'Documentos',
                'value' => (string) count($rowsRepetidas),
                'note'  => 'Apartados por duplicidad',
            ],
            [
                'label' => 'Periodo libro',
                'value' => $periodo,
                'note'  => 'Referencia contable',
            ],
        ],
        'columns'         => array_map(static function ($clave, $label): array {
            return [
                'key'   => (string) $clave,
                'label' => (string) $label,
            ];
        }, array_keys($repetidasColumns), array_values($repetidasColumns)),
        'rows'            => $rowsRepetidas,
        'numeric_keys'    => ['total_compras'],
        'total_row_index' => null,
        'empty_message'   => 'No hay facturas repetidas apartadas.',
    ];

    if ($modo === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data'    => [
                'columnas' => $reportRepetidas['columns'],
                'filas'    => $reportRepetidas['rows'],
                'libro'    => $libro,
                'formato'  => $formato,
            ],
            'message' => '',
        ]);
        exit;
    }

    if ($formato === 'csv') {
        ModuloExportService::descargarCsv($nombreArchivo, $reportRepetidas);
        exit;
    }

    if ($formato === 'excel') {
        ModuloExportService::descargarExcel($nombreArchivo, $reportRepetidas);
        exit;
    }

    if ($formato === 'pdf') {
        ModuloExportService::descargarPdf($nombreArchivo, $reportRepetidas);
        exit;
    }

    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Formato de exportacion no valido para repetidas.']);
    exit;
}

$formatosHacienda = [
    'hacienda_compras' => ['tipo_libro' => 'compras', 'tipo_exportacion' => 'compras', 'sufijo' => 'anexo_compras'],
    'hacienda_contribuyentes' => ['tipo_libro' => 'ventas_contribuyente', 'tipo_exportacion' => 'contribuyentes', 'sufijo' => 'anexo_contribuyentes'],
    'hacienda_f14' => ['tipo_libro' => 'retencion_iva', 'tipo_exportacion' => 'f14', 'sufijo' => 'anexo_f14'],
    'hacienda_casilla_66' => ['tipo_libro' => 'retencion_iva', 'tipo_exportacion' => 'casilla_66', 'sufijo' => 'casilla_66'],
    'hacienda_casilla_162' => ['tipo_libro' => 'retencion_iva', 'tipo_exportacion' => 'casilla_162', 'sufijo' => 'casilla_162'],
    'hacienda_casilla_163' => ['tipo_libro' => 'retencion_iva', 'tipo_exportacion' => 'casilla_163', 'sufijo' => 'casilla_163'],
];

if (isset($formatosHacienda[$formato])) {
    try {
        $configHacienda = $formatosHacienda[$formato];
        if ((string) ($libro['tipo'] ?? '') !== $configHacienda['tipo_libro']) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Este formato Hacienda no corresponde al tipo de libro abierto.']);
            exit;
        }

        $preparadoHacienda = HaciendaCsvExportService::preparar(
            $configHacienda['tipo_exportacion'],
            $libro,
            $obtenerFacturasLibro(),
            $obtenerFacturasCandidatas()
        );

        if ($modo === 'json') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'libro' => $libro,
                    'formato' => $formato,
                    'expected_columns' => $preparadoHacienda['expected_columns'],
                    'summary' => $preparadoHacienda['summary'],
                    'errores' => $preparadoHacienda['errores'],
                    'filas' => $preparadoHacienda['rows'],
                ],
                'message' => '',
            ]);
            exit;
        }

        HaciendaCsvExportService::escribirCsvHacienda(
            $nombreArchivo . '_' . $configHacienda['sufijo'],
            $preparadoHacienda['rows'],
            (int) $preparadoHacienda['expected_columns']
        );
        exit;
    } catch (Throwable $exception) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'data'    => null,
            'message' => 'No se pudo generar la exportacion Hacienda: ' . $exception->getMessage(),
        ]);
        exit;
    }
}

if ($modo === 'json') {
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

try {
    if ($formato === 'csv') {
        if (IvaWorkbookExportService::soportaPlantilla($libro)) {
            IvaWorkbookExportService::descargarCsvLibro($nombreArchivo, $libro, $obtenerFacturasLibro());
            exit;
        }

        ModuloExportService::descargarCsv($nombreArchivo, $report);
        exit;
    }

    if ($formato === 'csv_libro_iva') {
        if (!IvaWorkbookExportService::soportaPlantilla($libro)) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Este modulo no tiene una plantilla Libro IVA asociada.']);
            exit;
        }

        IvaWorkbookExportService::descargarCsvLibro($nombreArchivo . '_libro_iva', $libro, $obtenerFacturasLibro());
        exit;
    }

    if ($formato === 'csv_anexo_iva') {
        if (!IvaWorkbookExportService::soportaPlantilla($libro)) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Este modulo no tiene una plantilla Anexo IVA asociada.']);
            exit;
        }

        IvaWorkbookExportService::descargarCsvAnexo($nombreArchivo . '_anexo_iva', $libro, $obtenerFacturasLibro());
        exit;
    }

    if ($formato === 'excel') {
        if (!$hasCustomPreferences && IvaWorkbookExportService::soportaPlantilla($libro)) {
            IvaWorkbookExportService::descargarExcelLibro($nombreArchivo, $libro, $obtenerFacturasLibro());
            exit;
        }

        ModuloExportService::descargarExcel($nombreArchivo, $report);
        exit;
    }

    if ($formato === 'pdf') {
        if (IvaWorkbookExportService::soportaPlantilla($libro)) {
            IvaWorkbookExportService::descargarPdfLibro($nombreArchivo, $libro, $obtenerFacturasLibro());
            exit;
        }

        ModuloExportService::descargarPdf($nombreArchivo, $report);
        exit;
    }

    if (($formato === 'anexo_mh_a3' || $formato === 'anexo') && IvaWorkbookExportService::soportaPlantilla($libro)) {
        IvaWorkbookExportService::descargarCsvAnexo($nombreArchivo . '_anexo_mh_a3', $libro, $obtenerFacturasLibro());
        exit;
    }

    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Formato de exportacion no valido.']);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'data'    => null,
        'message' => 'No se pudo generar la exportacion solicitada: ' . $exception->getMessage(),
    ]);
}
