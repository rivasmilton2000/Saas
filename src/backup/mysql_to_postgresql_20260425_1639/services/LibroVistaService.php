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
                'duplicadas'       => $duplicadas,
                'duplicadas_total' => count($duplicadas),
                'invalidas'        => $invalidas,
                'invalidas_total'  => count($invalidas),
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
        $totales  = DteDataService::resumenLibro($facturas);
        $filas    = [];

        foreach ($facturas as $indice => $factura) {
            $filas[] = self::mapearFilaCompra($factura, $indice + 1);
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
                'totales'           => $totales,
                'cantidad_facturas' => count($facturas),
            ],
        ];
    }

    private static function mapearFilaCompra(array $factura, int $numero): array {
        return [
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
            'credito_fiscal'             => round((float) ($factura['credito_fiscal'] ?? 0), 2),
            'total_compras'              => round((float) ($factura['total_compras'] ?? 0), 2),
            'iva_percibido'              => round((float) ($factura['iva_percibido'] ?? 0), 2),
            'iva_retenido'               => round((float) ($factura['iva_retenido'] ?? 0), 2),
            'codigo_generacion'          => (string) ($factura['codigo_generacion'] ?? ''),
            'sello_recepcion'            => (string) ($factura['sello_recepcion'] ?? ''),
            'numero_control_completo'    => (string) ($factura['numero_control_completo'] ?? ''),
        ];
    }

    private static function filaTotalesCompra(array $totales): array {
        return [
            'no'                         => null,
            'fecha'                      => 'TOTALES DEL MES',
            'numero_control'             => '',
            'nrc'                        => '',
            'nit'                        => '',
            'nombre_proveedor'           => '',
            'ventas_internas'            => round((float) ($totales['ventas_internas'] ?? 0), 2),
            'ventas_importacion'         => round((float) ($totales['ventas_importacion'] ?? 0), 2),
            'ventas_internas_exentas'    => round((float) ($totales['ventas_internas_exentas'] ?? 0), 2),
            'ventas_importacion_exentas' => round((float) ($totales['ventas_importacion_exentas'] ?? 0), 2),
            'credito_fiscal'             => round((float) ($totales['credito_fiscal'] ?? 0), 2),
            'total_compras'              => round((float) ($totales['total_compras'] ?? 0), 2),
            'iva_percibido'              => round((float) ($totales['iva_percibido'] ?? 0), 2),
            'iva_retenido'               => round((float) ($totales['iva_retenido'] ?? 0), 2),
            'codigo_generacion'          => '',
            'sello_recepcion'            => '',
            'numero_control_completo'    => '',
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
                    'archivo' => $nombre,
                    'razon'   => 'El archivo no contiene un JSON valido.',
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
        $listas = ['facturas', 'documentos', 'dtes', 'comprobantes', 'items', 'data', 'resultado', 'lote'];

        foreach ($listas as $clave) {
            if (!isset($json[$clave]) || !is_array($json[$clave]) || !self::esLista($json[$clave])) {
                continue;
            }

            $documentos = [];
            foreach ($json[$clave] as $item) {
                if (is_string($item)) {
                    $item = DteDataService::decodeJsonText($item);
                }

                if (!is_array($item)) {
                    continue;
                }

                $documentos[] = DteDataService::normalizeDocumentPayload($item);
            }

            if (!empty($documentos)) {
                return $documentos;
            }
        }

        return [DteDataService::normalizeDocumentPayload($json)];
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
