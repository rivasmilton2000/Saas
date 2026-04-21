<?php
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/DteDataService.php';
require_once __DIR__ . '/ValidadorDTE.php';

class LibroImportService {

    public static function importarDesdeArchivos(PDO $pdo, int $idLibro, int $idUsuario, array $files): array {
        $normalizados = self::normalizarArchivosSubidos($files);
        $resultado = self::importarDocumentos($pdo, $idLibro, $idUsuario, $normalizados['documentos']);

        if (!empty($normalizados['errores'])) {
            $resultado['invalidas'] = array_merge($resultado['invalidas'] ?? [], $normalizados['errores']);

            if (($resultado['success'] ?? false) === true && ($resultado['importadas'] ?? 0) > 0) {
                $resultado['message'] .= ' Algunos archivos fueron omitidos por errores de lectura.';
            } else {
                $resultado['success'] = false;
                $resultado['message'] = 'No se pudieron procesar algunos archivos JSON.';
            }
        }

        return $resultado;
    }

    public static function importarDocumentos(PDO $pdo, int $idLibro, int $idUsuario, array $documentos): array {
        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
        if (!$libro) {
            return [
                'success'          => false,
                'message'          => 'Libro no encontrado o sin permiso.',
                'importadas'       => 0,
                'importables'      => 0,
                'duplicadas'       => [],
                'invalidas'        => [],
                'tipos_validos'    => [],
                'cuota_restante'   => 0,
                'cuota_disponible' => 0,
            ];
        }

        $tipoLibro    = $libro['tipo'];
        $tiposValidos = ValidadorDTE::getTiposValidos($tipoLibro);
        $vistosLote   = [];
        $importables  = [];
        $duplicadas   = [];
        $invalidas    = [];

        foreach ($documentos as $documento) {
            $resultado = self::clasificarDocumento($pdo, $idLibro, $idUsuario, $tipoLibro, $documento, $vistosLote);

            if ($resultado['estado'] === 'importable') {
                $importables[] = $resultado['data'];
                continue;
            }

            if ($resultado['estado'] === 'duplicada') {
                $duplicadas[] = $resultado['data'];
                continue;
            }

            $invalidas[] = $resultado['data'];
        }

        $cantidadImportables = count($importables);
        $cuota = FacturasCuotaModel::ensure($pdo, $idUsuario);

        if ($cantidadImportables > 0 && !FacturasCuotaModel::tieneCuota($pdo, $idUsuario, $cantidadImportables)) {
            return [
                'success'          => false,
                'message'          => 'No tienes cuota suficiente para importar los documentos nuevos.',
                'importadas'       => 0,
                'importables'      => $cantidadImportables,
                'duplicadas'       => $duplicadas,
                'invalidas'        => $invalidas,
                'tipos_validos'    => $tiposValidos,
                'cuota_restante'   => (int) $cuota['disponibles'],
                'cuota_disponible' => (int) $cuota['disponibles'],
            ];
        }

        $importadas = 0;

        try {
            $pdo->beginTransaction();

            foreach ($importables as $fila) {
                FacturaModel::insertarFactura($pdo, $fila);
                $importadas++;
            }

            if ($importadas > 0) {
                FacturasCuotaModel::decrementar($pdo, $idUsuario, $importadas);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success'          => false,
                'message'          => 'Error al importar facturas: ' . $exception->getMessage(),
                'importadas'       => 0,
                'importables'      => $cantidadImportables,
                'duplicadas'       => $duplicadas,
                'invalidas'        => $invalidas,
                'tipos_validos'    => $tiposValidos,
                'cuota_restante'   => (int) ($cuota['disponibles'] ?? 0),
                'cuota_disponible' => (int) ($cuota['disponibles'] ?? 0),
            ];
        }

        $cuotaFinal = FacturasCuotaModel::getByUsuario($pdo, $idUsuario) ?? FacturasCuotaModel::resumenVacio();

        return [
            'success'          => true,
            'message'          => $importadas > 0
                ? "Se importaron {$importadas} factura(s)."
                : 'No hubo documentos nuevos para importar.',
            'importadas'       => $importadas,
            'importables'      => $cantidadImportables,
            'duplicadas'       => $duplicadas,
            'invalidas'        => $invalidas,
            'tipos_validos'    => $tiposValidos,
            'cuota_restante'   => (int) ($cuotaFinal['disponibles'] ?? 0),
            'cuota_disponible' => (int) ($cuotaFinal['disponibles'] ?? 0),
        ];
    }

    public static function normalizarArchivosSubidos(array $files): array {
        $documentos = [];
        $errores    = [];
        $nombres    = $files['name'] ?? [];
        $temporales = $files['tmp_name'] ?? [];
        $codigos    = $files['error'] ?? [];

        foreach ($nombres as $indice => $nombreArchivo) {
            $codigoError = $codigos[$indice] ?? UPLOAD_ERR_NO_FILE;
            if ($codigoError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($codigoError !== UPLOAD_ERR_OK) {
                $errores[] = [
                    'archivo' => $nombreArchivo,
                    'razon'   => 'No se pudo subir el archivo.',
                ];
                continue;
            }

            $contenido = @file_get_contents($temporales[$indice]);
            if ($contenido === false) {
                $errores[] = [
                    'archivo' => $nombreArchivo,
                    'razon'   => 'No se pudo leer el archivo.',
                ];
                continue;
            }

            $json = json_decode($contenido, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                $errores[] = [
                    'archivo' => $nombreArchivo,
                    'razon'   => 'El archivo no contiene un JSON valido.',
                ];
                continue;
            }

            $listaDocumentos = self::expandirDocumento($json);
            foreach ($listaDocumentos as $subIndice => $documento) {
                $documentos[] = [
                    'archivo'  => count($listaDocumentos) > 1
                        ? $nombreArchivo . ' #' . ($subIndice + 1)
                        : $nombreArchivo,
                    'payload'  => $documento,
                ];
            }
        }

        return [
            'documentos' => $documentos,
            'errores'    => $errores,
        ];
    }

    private static function clasificarDocumento(
        PDO $pdo,
        int $idLibro,
        int $idUsuario,
        string $tipoLibro,
        array $documento,
        array &$vistosLote
    ): array {
        $payload        = $documento['payload'] ?? [];
        $archivo        = $documento['archivo'] ?? 'Documento';
        $extraido       = DteDataService::extractDocumentoData($payload);

        $codigoGeneracion = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $tipoDte          = trim((string) ($extraido['tipo_dte'] ?? ''));

        if ($codigoGeneracion === '') {
            return [
                'estado' => 'invalida',
                'data'   => [
                    'archivo' => $archivo,
                    'razon'   => 'El documento no trae codigo de generacion.',
                ],
            ];
        }

        if ($tipoDte === '' || !ValidadorDTE::validarTipo($tipoDte, $tipoLibro)) {
            return [
                'estado' => 'invalida',
                'data'   => [
                    'archivo'             => $archivo,
                    'codigo_generacion'   => $codigoGeneracion,
                    'tipo_dte'            => $tipoDte,
                    'tipo_dte_nombre'     => ValidadorDTE::getNombreTipo($tipoDte),
                    'razon'               => "Tipo de DTE no valido para libro {$tipoLibro}.",
                ],
            ];
        }

        if (isset($vistosLote[$codigoGeneracion])) {
            return [
                'estado' => 'duplicada',
                'data'   => [
                    'archivo'           => $archivo,
                    'codigo_generacion' => $codigoGeneracion,
                    'tipo_dte'          => $tipoDte,
                    'razon'             => 'Duplicada dentro del mismo lote.',
                ],
            ];
        }

        if (FacturaModel::existeFactura($pdo, $codigoGeneracion, $idLibro)) {
            return [
                'estado' => 'duplicada',
                'data'   => [
                    'archivo'           => $archivo,
                    'codigo_generacion' => $codigoGeneracion,
                    'tipo_dte'          => $tipoDte,
                    'razon'             => 'El documento ya existe en este libro.',
                ],
            ];
        }

        $vistosLote[$codigoGeneracion] = true;

        return [
            'estado' => 'importable',
            'data'   => [
                'id_libro'                   => $idLibro,
                'id_usuario'                 => $idUsuario,
                'codigo_generacion'          => $codigoGeneracion,
                'sello_recepcion'            => $extraido['sello_recepcion'] ?? null,
                'numero_control'             => $extraido['numero_control'] ?? null,
                'tipo_dte'                   => $tipoDte,
                'fecha'                      => $extraido['fecha'] ?? date('Y-m-d'),
                'nrc'                        => $extraido['nrc'] ?? null,
                'nit'                        => $extraido['nit'] ?? null,
                'nombre_proveedor'           => $extraido['nombre_proveedor'] ?? null,
                'ventas_internas'            => $extraido['ventas_internas'] ?? 0,
                'ventas_importacion'         => $extraido['ventas_importacion'] ?? 0,
                'ventas_internas_exentas'    => $extraido['ventas_internas_exentas'] ?? 0,
                'ventas_importacion_exentas' => $extraido['ventas_importacion_exentas'] ?? 0,
                'credito_fiscal'             => $extraido['credito_fiscal'] ?? 0,
                'total_compras'              => $extraido['total_compras'] ?? 0,
                'iva_percibido'              => $extraido['iva_percibido'] ?? 0,
                'iva_retenido'               => $extraido['iva_retenido'] ?? 0,
                'numero_control_completo'    => $extraido['numero_control_completo'] ?? null,
                'raw_json'                   => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    private static function expandirDocumento(array $json): array {
        if (self::esLista($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        if (isset($json['facturas']) && is_array($json['facturas']) && self::esLista($json['facturas'])) {
            return array_values(array_filter($json['facturas'], 'is_array'));
        }

        return [$json];
    }
    private static function esLista(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
