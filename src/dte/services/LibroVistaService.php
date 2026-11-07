<?php
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/DteDataService.php';
require_once __DIR__ . '/LibroImportService.php';
require_once __DIR__ . '/VentasLibroService.php';
require_once __DIR__ . '/RetencionIvaService.php';

class LibroVistaService {

    public static function importar(PDO $pdo, array $libro, int $idUsuario, array $documentos): array {
        $tipoLibro = (string) ($libro['tipo'] ?? '');

        if ($tipoLibro === 'ventas_consumidor') {
            return VentasLibroService::importarVentasConsumidor($pdo, (int) $libro['id'], $idUsuario, $documentos);
        }

        if ($tipoLibro === 'ventas_contribuyente') {
            return VentasLibroService::importarVentasContribuyente($pdo, (int) $libro['id'], $idUsuario, $documentos);
        }

        if ($tipoLibro === 'retencion_iva') {
            return RetencionIvaService::importarRetencionIva($pdo, (int) $libro['id'], $idUsuario, $documentos);
        }

        $normalizados = self::normalizarDocumentos($documentos);
        $resultado    = LibroImportService::importarDocumentos($pdo, (int) $libro['id'], $idUsuario, $normalizados['documentos']);
        $invalidas    = array_merge($resultado['invalidas'] ?? [], $normalizados['invalidas']);
        $importadas   = (int) ($resultado['importadas'] ?? 0);
        $reparadas    = (int) ($resultado['reparadas'] ?? 0);
        $duplicadas   = $resultado['duplicadas'] ?? [];
        $cuotaRestante = (int) ($resultado['cuota_restante'] ?? $resultado['cuota_disponible'] ?? 0);
        $message      = (string) ($resultado['message'] ?? '');

        if (!empty($normalizados['invalidas']) && $message !== '') {
            $message .= ' Algunos archivos no se pudieron leer.';
        } elseif (!empty($normalizados['invalidas'])) {
            $message = 'Algunos archivos no se pudieron leer.';
        }

        return [
            'success' => (bool) ($resultado['success'] ?? false),
            'message' => $message,
            'data'    => [
                'importadas'       => $importadas,
                'reparadas'        => $reparadas,
                'actualizadas'     => (int) ($resultado['actualizadas'] ?? $reparadas),
                'incluidas_libro'   => (int) ($resultado['incluidas_libro'] ?? $importadas),
                'fuera_periodo'     => (int) ($resultado['fuera_periodo'] ?? 0),
                'total_leidos'     => (int) ($resultado['total_leidos'] ?? count($normalizados['documentos'])),
                'duplicadas'       => $duplicadas,
                'duplicadas_total' => count($duplicadas),
                'documentos_unicos_incluidos' => (int) ($resultado['documentos_unicos_incluidos'] ?? $resultado['incluidas_libro'] ?? $importadas),
                'copias_duplicadas_apartadas' => (int) ($resultado['copias_duplicadas_apartadas'] ?? count($duplicadas)),
                'invalidas'        => $invalidas,
                'invalidas_total'  => count($invalidas),
                'errores_total'    => count($invalidas),
                'cuota_restante'   => $cuotaRestante,
            ],
        ];
    }

    public static function listar(PDO $pdo, int $idLibro, int $idUsuario, string $tipoLibro): array {
        if ($tipoLibro === 'ventas_consumidor') {
            return VentasLibroService::listarVentasConsumidor($pdo, $idLibro, $idUsuario);
        }

        if ($tipoLibro === 'ventas_contribuyente') {
            return VentasLibroService::listarVentasContribuyente($pdo, $idLibro, $idUsuario);
        }

        if ($tipoLibro === 'retencion_iva') {
            return RetencionIvaService::listarRetencionIva($pdo, $idLibro, $idUsuario);
        }

        return self::listarCompras($pdo, $idLibro, $idUsuario);
    }

    public static function construirResumenTarjetas(array $modulo, array $listado): array {
        $data          = is_array($listado['data'] ?? null) ? $listado['data'] : [];
        $libro         = is_array($data['libro'] ?? null) ? $data['libro'] : [];
        $totales       = is_array($data['totales'] ?? null) ? $data['totales'] : [];
        $cantidad      = (int) ($data['cantidad_facturas'] ?? 0);
        $columnas      = $modulo['columnas'] ?? [];
        $numericas     = $modulo['columnas_numericas'] ?? [];
        $periodo       = self::formatearPeriodo($libro);
        $tarjetas      = [
            [
                'label' => 'Empresa',
                'value' => (string) ($libro['empresa_nombre'] ?? '-'),
                'note'  => 'Titular del libro',
            ],
            [
                'label' => 'Periodo',
                'value' => $periodo !== '' ? $periodo : '-',
                'note'  => 'Mes y año de trabajo',
            ],
            [
                'label' => 'Documentos',
                'value' => (string) $cantidad,
                'note'  => 'Registros importados',
            ],
        ];

        foreach ($numericas as $clave) {
            if (!array_key_exists($clave, $totales)) {
                continue;
            }

            $tarjetas[] = [
                'label' => (string) ($columnas[$clave] ?? $clave),
                'value' => number_format((float) $totales[$clave], 2),
                'note'  => 'Total del periodo',
            ];

            if (count($tarjetas) >= 5) {
                break;
            }
        }

        return $tarjetas;
    }

    private static function listarCompras(PDO $pdo, int $idLibro, int $idUsuario): array {
        FacturaModel::ensureExtendedSchema($pdo);
        FacturaModel::restaurarRepetidosSinOriginal($pdo, $idLibro);

        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
        if (!$libro || ($libro['tipo'] ?? '') !== 'compras') {
            return [
                'success' => false,
                'message' => 'Libro no encontrado o sin permiso.',
                'data'    => null,
            ];
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $modulo   = getLibroModule('compras') ?? ['columnas' => []];
        $facturas = DteDataService::hydrateFacturas(FacturaModel::getByLibro($pdo, $idLibro));
        $fueraPeriodo = DteDataService::hydrateFacturas(FacturaModel::getFueraPeriodoByLibro($pdo, $idLibro));
        $repetidas = DteDataService::hydrateFacturas(FacturaModel::getRepetidasByLibro($pdo, $idLibro));
        $totales  = DteDataService::resumenLibro($facturas);
        $totalesFueraPeriodo = DteDataService::resumenLibro($fueraPeriodo);
        $filas    = [];
        $filasFueraPeriodo = [];
        $filasRepetidas = [];
        $originalesPorCodigo = [];
        $originalesPorSecundaria = [];

        foreach ($facturas as $indice => $factura) {
            $filas[] = self::mapearFilaCompra($factura, $indice + 1);
            $codigo = trim((string) ($factura['codigo_generacion'] ?? ''));
            if ($codigo !== '' && !isset($originalesPorCodigo[$codigo])) {
                $originalesPorCodigo[$codigo] = $factura;
            }
            $secundaria = self::claveDuplicadoSecundaria($factura);
            if ($secundaria !== '' && !isset($originalesPorSecundaria[$secundaria])) {
                $originalesPorSecundaria[$secundaria] = $factura;
            }
        }

        foreach ($fueraPeriodo as $indice => $factura) {
            $filasFueraPeriodo[] = self::mapearFilaFueraPeriodo($factura, $indice + 1);
        }

        foreach ($repetidas as $indice => $factura) {
            $codigo = trim((string) ($factura['codigo_generacion'] ?? ''));
            $secundaria = self::claveDuplicadoSecundaria($factura);
            $original = $codigo !== '' ? ($originalesPorCodigo[$codigo] ?? null) : null;
            if ($original === null && $secundaria !== '') {
                $original = $originalesPorSecundaria[$secundaria] ?? null;
            }
            if ($original === null) {
                continue;
            }
            $filasRepetidas[] = self::mapearFilaApartada(
                $factura,
                count($filasRepetidas) + 1,
                'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.',
                $original
            );
        }

        $filaTotales = self::filaTotalesCompra($totales);
        $filasConTotales = $filas;
        if (!empty($filas)) {
            $filasConTotales[] = $filaTotales;
        }

        return [
            'success' => true,
            'message' => '',
            'data'    => [
                'libro'             => $libro,
                'columnas'          => array_keys($modulo['columnas'] ?? []),
                'registros'         => $filas,
                'filas'             => $filasConTotales,
                'fila_totales'      => $filaTotales,
                'fuera_periodo'     => $filasFueraPeriodo,
                'repetidas'         => $filasRepetidas,
                'totales_fuera_periodo' => $totalesFueraPeriodo,
                'totales'           => $totales,
                'cantidad_facturas' => count($facturas),
                'cantidad_fuera_periodo' => count($fueraPeriodo),
                'cantidad_repetidas' => count($filasRepetidas),
            ],
        ];
    }

    private static function mapearFilaCompra(array $factura, int $numero): array {
        $permiteRelacionado = DteDataService::permiteDocumentoRelacionado($factura['tipo_dte'] ?? '');
        $tipoDte = self::normalizarTipoDte($factura['tipo_dte'] ?? '');

        return [
            '_id'                        => (int) ($factura['id'] ?? 0),
            'tipo_dte'                    => $tipoDte,
            'tipo_documento_nombre'       => self::tipoDocumentoVisualCompra($tipoDte),
            'signo_contable'              => (int) ($factura['signo_contable'] ?? 1),
            'es_nota_credito'             => $tipoDte === '05',
            'no'                         => $numero,
            'fecha'                      => (string) ($factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? null)),
            'numero_control'             => (string) ($factura['numero_control'] ?? ''),
            'nrc'                        => (string) ($factura['nrc'] ?? ''),
            'nit'                        => (string) ($factura['nit'] ?? ''),
            'nombre_proveedor'           => (string) ($factura['nombre_proveedor'] ?? ''),
            'ventas_internas'            => round((float) ($factura['ventas_internas'] ?? 0), 2),
            'ventas_importacion'         => round((float) ($factura['ventas_importacion'] ?? 0), 2),
            'ventas_internas_exentas'    => round((float) ($factura['ventas_internas_exentas'] ?? 0), 2),
            'ventas_importacion_exentas' => round((float) ($factura['ventas_importacion_exentas'] ?? 0), 2),
            'compras_exentas'            => round((float) ($factura['compras_exentas'] ?? 0), 2),
            'compras_gravadas'           => round((float) ($factura['compras_gravadas'] ?? 0), 2),
            'credito_fiscal'             => round((float) ($factura['credito_fiscal'] ?? 0), 2),
            'impuestos_calculados'       => round((float) ($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? 0), 2),
            'fovial_otros'               => round((float) ($factura['fovial_otros'] ?? 0), 2),
            'total_compras'              => round((float) ($factura['total_compras'] ?? 0), 2),
            'iva_percibido'              => round((float) ($factura['iva_percibido'] ?? 0), 2),
            'iva_retenido'               => round((float) ($factura['iva_retenido'] ?? 0), 2),
            'codigo_generacion'          => (string) ($factura['codigo_generacion'] ?? ''),
            'sello_recepcion'            => (string) ($factura['sello_recepcion'] ?? ''),
            'numero_control_completo'    => (string) ($factura['numero_control_completo'] ?? ''),
            'documento_relacionado_numero' => $permiteRelacionado ? (string) ($factura['documento_relacionado_numero'] ?? '') : '',
            'documento_relacionado_codigo' => $permiteRelacionado ? (string) ($factura['documento_relacionado_codigo'] ?? '') : '',
            'documento_relacionado_fecha'  => $permiteRelacionado ? (string) ($factura['documento_relacionado_fecha'] ?? '') : '',
        ];
    }

    private static function tipoDocumentoVisualCompra(string $tipoDte): string {
        return self::normalizarTipoDte($tipoDte) === '05' ? 'Nota de Crédito' : '';
    }

    private static function normalizarTipoDte($tipoDte): string {
        $tipoDte = trim((string) $tipoDte);
        if ($tipoDte === '') {
            return '';
        }

        return ctype_digit($tipoDte) ? str_pad($tipoDte, 2, '0', STR_PAD_LEFT) : $tipoDte;
    }

    public static function mapearFilaFueraPeriodo(array $factura, int $numero): array {
        $tributosCombustible = self::sumarTributosCombustible($factura);

        return [
            'no'                    => $numero,
            'fecha'                 => (string) ($factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? null)),
            'tipo_dte'              => (string) ($factura['tipo_dte'] ?? ''),
            'tipo_documento_nombre' => (string) ($factura['tipo_documento_nombre'] ?? DteDataService::tipoDocumentoNombre($factura['tipo_dte'] ?? '')),
            'numero_control'        => (string) ($factura['numero_control'] ?? ''),
            'codigo_generacion'     => (string) ($factura['codigo_generacion'] ?? ''),
            'nombre_proveedor'      => (string) ($factura['nombre_proveedor'] ?? $factura['nombre_cliente'] ?? ''),
            'nit'                   => (string) ($factura['nit'] ?? ''),
            'nrc'                   => (string) ($factura['nrc'] ?? ''),
            'compras_exentas'       => round((float) ($factura['compras_exentas'] ?? $factura['ventas_exentas'] ?? 0), 2),
            'compras_gravadas'      => round((float) ($factura['compras_gravadas'] ?? $factura['ventas_internas_gravadas'] ?? 0), 2),
            'impuestos_calculados'  => round((float) ($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? $factura['debito_fiscal'] ?? 0), 2),
            'fovial'                => $tributosCombustible['fovial'],
            'cotrans'               => $tributosCombustible['cotrans'],
            'total_compras'         => round((float) ($factura['total_compras'] ?? $factura['ventas_totales'] ?? $factura['total_ventas_diarias_propias'] ?? 0), 2),
            'motivo'                => (string) ($factura['motivo_no_contabilizado'] ?? 'Documento mayor a 3 meses / fuera de periodo'),
        ];
    }

    public static function mapearFilaApartada(array $factura, int $numero, string $motivoDefecto, ?array $original = null): array {
        $originalReal = is_array($original) && (int) ($original['id'] ?? 0) > 0;

        return [
            'no'                    => $numero,
            'fecha'                 => (string) ($factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? null)),
            'tipo_documento_nombre' => (string) ($factura['tipo_documento_nombre'] ?? DteDataService::tipoDocumentoNombre($factura['tipo_dte'] ?? '')),
            'tipo_dte'              => (string) ($factura['tipo_dte'] ?? ''),
            'numero_control'        => (string) ($factura['numero_control'] ?? ''),
            'codigo_generacion'     => (string) ($factura['codigo_generacion'] ?? ''),
            'codigo_generacion_original' => $originalReal ? (string) ($original['codigo_generacion'] ?? '') : '',
            'sello_recepcion'       => (string) ($factura['sello_recepcion'] ?? ''),
            'nombre_proveedor'      => (string) ($factura['nombre_proveedor'] ?? $factura['nombre_cliente'] ?? ''),
            'nit'                   => (string) ($factura['nit'] ?? ''),
            'nrc'                   => (string) ($factura['nrc'] ?? ''),
            'total_compras'         => round((float) ($factura['total_compras'] ?? $factura['ventas_totales'] ?? $factura['total_ventas_diarias_propias'] ?? 0), 2),
            'motivo'                => self::motivoDuplicado($factura, $original, $motivoDefecto),
            'id_original'           => $originalReal ? (int) ($original['id'] ?? 0) : null,
            'documento_original'    => $originalReal ? self::formatearDocumentoOriginal($original) : '',
            'estado'                => 'Apartado, no contabilizado',
        ];
    }

    private static function motivoDuplicado(array $factura, ?array $original, string $motivoDefecto): string {
        if (is_array($original) && (int) ($original['id'] ?? 0) > 0) {
            $codigo = trim((string) ($factura['codigo_generacion'] ?? ''));
            $codigoOriginal = trim((string) ($original['codigo_generacion'] ?? ''));
            if ($codigo !== '' && $codigoOriginal !== '' && $codigo === $codigoOriginal) {
                return 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.';
            }

            return 'Numero de control + emisor + sello ya existen.';
        }

        return (string) ($factura['motivo_exclusion'] ?? $factura['motivo_no_contabilizado'] ?? $motivoDefecto);
    }

    private static function claveDuplicadoSecundaria(array $factura): string {
        $partes = [
            trim((string) ($factura['tipo_dte'] ?? '')),
            trim((string) ($factura['numero_control'] ?? '')),
            trim((string) ($factura['nit'] ?? '')),
            trim((string) ($factura['sello_recepcion'] ?? '')),
        ];

        foreach ($partes as $parte) {
            if ($parte === '') {
                return '';
            }
        }

        return implode('|', $partes);
    }

    private static function formatearDocumentoOriginal(?array $original): string {
        if (!is_array($original) || (int) ($original['id'] ?? 0) <= 0) {
            return '';
        }

        $partes = [];
        $id = (int) ($original['id'] ?? 0);
        if ($id > 0) {
            $partes[] = 'Original contabilizado ID ' . $id;
        }

        $codigoOriginal = trim((string) ($original['codigo_generacion'] ?? ''));
        if ($codigoOriginal !== '') {
            $partes[] = 'Codigo ' . $codigoOriginal;
        }

        $numeroControl = trim((string) ($original['numero_control'] ?? ''));
        if ($numeroControl !== '') {
            $partes[] = $numeroControl;
        }

        $proveedor = trim((string) ($original['nombre_proveedor'] ?? $original['nombre_cliente'] ?? ''));
        if ($proveedor !== '') {
            $partes[] = $proveedor;
        }

        return $partes !== [] ? implode(' | ', $partes) : '';
    }

    private static function sumarTributosCombustible(array $factura): array {
        $payload = is_string($factura['raw_json'] ?? null)
            ? DteDataService::decodeJsonText((string) $factura['raw_json'])
            : null;
        $totales = [
            'fovial'  => 0.0,
            'cotrans' => 0.0,
        ];

        self::sumarTributosCombustibleEnValor($payload, $totales);

        if ($totales['fovial'] === 0.0 && $totales['cotrans'] === 0.0) {
            $totales['fovial'] = round((float) ($factura['fovial_otros'] ?? 0), 2);
        }

        return [
            'fovial'  => round($totales['fovial'], 2),
            'cotrans' => round($totales['cotrans'], 2),
        ];
    }

    private static function sumarTributosCombustibleEnValor($value, array &$totales, int $depth = 0): void {
        if (!is_array($value) || $depth > 6) {
            return;
        }

        $codigo = strtolower((string) ($value['codigo'] ?? $value['codTributo'] ?? $value['codigoTributo'] ?? ''));
        $descripcion = strtolower((string) ($value['descripcion'] ?? $value['nombre'] ?? $value['tributo'] ?? ''));
        $valor = $value['valor'] ?? $value['monto'] ?? $value['valorTributo'] ?? $value['total'] ?? null;

        if (($codigo !== '' || $descripcion !== '') && is_numeric($valor)) {
            if ($codigo === 'd1' || str_contains($descripcion, 'fovial')) {
                $totales['fovial'] += (float) $valor;
            } elseif ($codigo === 'c8' || str_contains($descripcion, 'cotrans')) {
                $totales['cotrans'] += (float) $valor;
            }
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                self::sumarTributosCombustibleEnValor($item, $totales, $depth + 1);
            }
        }
    }

    private static function filaTotalesCompra(array $totales): array {
        return [
            'no'                         => null,
            'fecha'                      => 'TOTALES DEL MES',
            'tipo_documento_nombre'      => '',
            'numero_control'             => '',
            'nrc'                        => '',
            'nit'                        => '',
            'nombre_proveedor'           => '',
            'ventas_internas'            => round((float) ($totales['ventas_internas'] ?? 0), 2),
            'ventas_importacion'         => round((float) ($totales['ventas_importacion'] ?? 0), 2),
            'ventas_internas_exentas'    => round((float) ($totales['ventas_internas_exentas'] ?? 0), 2),
            'ventas_importacion_exentas' => round((float) ($totales['ventas_importacion_exentas'] ?? 0), 2),
            'compras_exentas'            => round((float) ($totales['compras_exentas'] ?? 0), 2),
            'compras_gravadas'           => round((float) ($totales['compras_gravadas'] ?? 0), 2),
            'credito_fiscal'             => round((float) ($totales['credito_fiscal'] ?? 0), 2),
            'impuestos_calculados'       => round((float) ($totales['impuestos_calculados'] ?? $totales['credito_fiscal'] ?? 0), 2),
            'fovial_otros'               => round((float) ($totales['fovial_otros'] ?? 0), 2),
            'total_compras'              => round((float) ($totales['total_compras'] ?? 0), 2),
            'iva_percibido'              => round((float) ($totales['iva_percibido'] ?? 0), 2),
            'iva_retenido'               => round((float) ($totales['iva_retenido'] ?? 0), 2),
            'codigo_generacion'          => '',
            'sello_recepcion'            => '',
            'numero_control_completo'    => '',
            'documento_relacionado_numero' => '',
            'documento_relacionado_codigo' => '',
            'documento_relacionado_fecha'  => '',
        ];
    }

    private static function normalizarDocumentos(array $documentos): array {
        $resultado = [];
        $invalidas = [];

        foreach ($documentos as $indice => $documento) {
            $nombre = 'documento_' . ($indice + 1) . '.json';
            $payload = $documento;

            if (is_array($documento) && array_key_exists('payload', $documento)) {
                $payload = $documento['payload'];
                $nombre  = trim((string) ($documento['nombre_archivo'] ?? $documento['archivo'] ?? $nombre));
            } elseif (is_array($documento)) {
                $nombre = trim((string) ($documento['nombre_archivo'] ?? $documento['archivo'] ?? $nombre));
            }

            if (is_string($payload)) {
                $payload = DteDataService::decodeJsonText($payload);
            }

            if (!is_array($payload)) {
                $invalidas[] = [
                    'archivo'        => $nombre,
                    'nombre_archivo' => $nombre,
                    'tipo_dte'       => '',
                    'tipoDte'        => '',
                    'numero_control' => '',
                    'numeroControl'  => '',
                    'codigo_generacion' => '',
                    'codigoGeneracion'  => '',
                    'razon'          => trim((string) ($documento['razon'] ?? '')) !== ''
                        ? trim((string) $documento['razon'])
                        : 'El archivo no contiene un JSON valido.',
                ];
                continue;
            }

            $documentosExpandibles = self::expandirDocumento($payload);
            foreach ($documentosExpandibles as $subIndice => $item) {
                $resultado[] = [
                    'archivo' => count($documentosExpandibles) > 1
                        ? $nombre . ' #' . ($subIndice + 1)
                        : $nombre,
                    'payload' => $item,
                ];
            }
        }

        return [
            'documentos' => $resultado,
            'invalidas'  => $invalidas,
        ];
    }

    private static function expandirDocumento(array $json): array {
        return DteDataService::expandDocumentPayloads($json);
    }

    private static function formatearPeriodo(array $libro): string {
        $mes = (int) ($libro['mes'] ?? 0);
        $anio = trim((string) ($libro['anio'] ?? ''));
        if ($mes <= 0 || $anio === '') {
            return '';
        }

        $meses = [
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

        return ($meses[$mes] ?? (string) $mes) . ' ' . $anio;
    }

    private static function esLista(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
