<?php
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/DteDataService.php';
require_once __DIR__ . '/ValidadorDTE.php';

class LibroImportService {

    public static function importarDesdeArchivos(PDO $pdo, int $idLibro, int $idUsuario, array $files, array $opciones = []): array {
        $normalizados = self::normalizarArchivosSubidos($files);
        $resultado = self::importarDocumentos($pdo, $idLibro, $idUsuario, $normalizados['documentos'], $opciones);

        if (!empty($normalizados['errores'])) {
            $resultado['invalidas'] = array_merge($resultado['invalidas'] ?? [], $normalizados['errores']);
            $resultado['errores_total'] = count($resultado['invalidas']);
            $resultado['invalidas_total'] = count($resultado['invalidas']);
            $resultado['total_leidos'] = (int) ($resultado['total_leidos'] ?? 0) + count($normalizados['errores']);

            if (($resultado['success'] ?? false) === true && ($resultado['importadas'] ?? 0) > 0) {
                $resultado['message'] .= ' Algunos archivos fueron omitidos por errores de lectura.';
            } else {
                $resultado['success'] = false;
                $resultado['message'] = 'No se pudieron procesar algunos archivos JSON.';
            }
        }

        return $resultado;
    }

    public static function importarDocumentos(PDO $pdo, int $idLibro, int $idUsuario, array $documentos, array $opciones = []): array {
        $strict = (bool) ($opciones['strict'] ?? false);
        $forceImport = (bool) ($opciones['force_import'] ?? true);
        $batchId = (string) ($opciones['import_batch_id'] ?? self::generarBatchId());
        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
        if (!$libro) {
            return [
                'success'          => false,
                'message'          => 'Libro no encontrado o sin permiso.',
                'importadas'       => 0,
                'importables'      => 0,
                'duplicadas'       => [],
                'invalidas'        => [],
                'total_leidos'     => count($documentos),
                'actualizadas'     => 0,
                'errores_total'    => 0,
                'tipos_validos'    => [],
                'cuota_restante'   => 0,
                'cuota_disponible' => 0,
            ];
        }

        $tipoLibro    = $libro['tipo'];
        $tiposValidos = ValidadorDTE::getTiposValidos($tipoLibro);
        $vistosLote   = [];
        $importables  = [];
        $actualizables = [];
        $duplicadas   = [];
        $invalidas    = [];
        $totalLeidos  = count($documentos);
        $indiceImportacion = 0;

        foreach ($documentos as $documento) {
            $indiceImportacion++;
            try {
                $resultado = self::clasificarDocumento(
                    $pdo,
                    $idLibro,
                    $idUsuario,
                    $libro,
                    $documento,
                    $vistosLote,
                    $strict,
                    $forceImport,
                    $batchId,
                    $indiceImportacion
                );
            } catch (Throwable $exception) {
                $invalidas[] = self::buildErrorDocumento($documento, 'No se pudo clasificar el documento: ' . $exception->getMessage());
                continue;
            }

            if ($resultado['estado'] === 'importable') {
                $importables[] = $resultado['data'];
                continue;
            }

            if ($resultado['estado'] === 'actualizable') {
                $actualizables[] = $resultado['data'];
                continue;
            }

            if ($resultado['estado'] === 'duplicada') {
                $duplicadas[] = $resultado['data'];
                continue;
            }

            $invalidas[] = $resultado['data'];
        }

        $cantidadImportables = count($importables);
        $cuota              = FacturasCuotaModel::ensure($pdo, $idUsuario);
        $cuotaDisponible    = max(0, (int) ($cuota['disponibles'] ?? 0));
        $omitidasPorCuota   = [];

        if (!$forceImport && $cantidadImportables > $cuotaDisponible) {
            $omitidasPorCuota = array_slice($importables, $cuotaDisponible);
            $importables      = array_slice($importables, 0, $cuotaDisponible);

            foreach ($omitidasPorCuota as $fila) {
                $invalidas[] = self::buildSinCuota($fila);
            }

            $cantidadImportables = count($importables);
        }

        if ($forceImport) {
            FacturaModel::prepararCargaMasivaFlexible($pdo);
        }

        $importadas = 0;
        $reparadas  = 0;
        $fueraPeriodo = 0;
        $incluidasLibro = 0;
        $idsInsertados = [];

        foreach ($importables as $fila) {
            try {
                $idInsertado = FacturaModel::insertarFactura($pdo, $fila);
                if ($idInsertado > 0) {
                    $idsInsertados[] = $idInsertado;
                }
                $importadas++;
                if (!empty($fila['fuera_de_periodo'])) {
                    $fueraPeriodo++;
                } elseif (empty($fila['es_repetido'])) {
                    $incluidasLibro++;
                }
            } catch (Throwable $exception) {
                if ($forceImport && self::isDuplicateDatabaseError($exception)) {
                    try {
                        FacturaModel::prepararCargaMasivaFlexible($pdo);
                        $idInsertado = FacturaModel::insertarFactura($pdo, $fila);
                        if ($idInsertado > 0) {
                            $idsInsertados[] = $idInsertado;
                        }
                        $importadas++;
                        if (!empty($fila['fuera_de_periodo'])) {
                            $fueraPeriodo++;
                        } elseif (empty($fila['es_repetido'])) {
                            $incluidasLibro++;
                        }
                        continue;
                    } catch (Throwable $retryException) {
                        $invalidas[] = self::buildErrorDocumento($fila, 'No se pudo insertar aun en modo forzado: ' . $retryException->getMessage());
                        continue;
                    }
                }

                $duplicadaDb = self::isDuplicateDatabaseError($exception)
                    ? FacturaModel::getPrincipalContabilizableByCodigoGeneracion($pdo, (string) ($fila['codigo_generacion'] ?? ''), $idLibro)
                    : null;

                if ($duplicadaDb !== null) {
                    if (self::tieneOriginalReal($duplicadaDb)) {
                        $fila = self::aplicarOriginalDuplicado($fila, $duplicadaDb);
                        $duplicadas[] = self::buildDuplicadaReal($fila, self::motivoDuplicadoReporte(
                            (string) ($fila['codigo_generacion'] ?? ''),
                            $duplicadaDb,
                            'El documento ya existe en este libro.'
                        ));
                    } else {
                        $invalidas[] = self::buildErrorDocumento($fila, 'Documento enviado a revision: no se encontro un original contabilizado para confirmar el duplicado.');
                    }
                    continue;
                }

                $invalidas[] = self::buildErrorDocumento($fila, 'No se pudo insertar: ' . $exception->getMessage());
            }
        }

        foreach ($actualizables as $fila) {
            try {
                FacturaModel::actualizarFactura($pdo, (int) ($fila['id_factura'] ?? 0), $fila);
                $reparadas++;
            } catch (Throwable $exception) {
                $invalidas[] = self::buildErrorDocumento($fila, 'No se pudo actualizar: ' . $exception->getMessage());
            }
        }

        $normalizacionDuplicados = FacturaModel::normalizarDuplicadosPorCodigo($pdo, $idLibro);
        $repetidosRestaurados = FacturaModel::restaurarRepetidosSinOriginal($pdo, $idLibro);
        foreach (FacturaModel::getRepetidasByIds($pdo, $idsInsertados) as $facturaRepetida) {
            $original = FacturaModel::getOriginalContabilizableForFactura($pdo, $facturaRepetida);
            if (!self::tieneOriginalReal($original)) {
                continue;
            }

            $facturaRepetida = self::aplicarOriginalDuplicado($facturaRepetida, $original);
            $duplicadas[] = self::buildDuplicadaReal(
                $facturaRepetida,
                self::motivoDuplicadoReporte(
                    (string) ($facturaRepetida['codigo_generacion'] ?? ''),
                    $original,
                    (string) ($facturaRepetida['motivo_exclusion'] ?? 'Documento repetido: no se incluye en sumatorias')
                )
            );
        }

        if ($importadas > 0) {
            FacturasCuotaModel::decrementar($pdo, $idUsuario, $importadas);
        }

        $cuotaFinal = FacturasCuotaModel::getByUsuario($pdo, $idUsuario) ?? FacturasCuotaModel::resumenVacio();

        $erroresTotal = count($invalidas);
        $duplicadasTotal = count($duplicadas);
        $incluidasLibro = max(0, $importadas - $fueraPeriodo - $duplicadasTotal);
        $success = $importadas > 0 || $reparadas > 0 || ($totalLeidos > 0 && $erroresTotal < $totalLeidos);
        $message = sprintf(
            'Leidos: %d. Insertados: %d. Incluidos en libro: %d. Fuera de periodo: %d. Actualizados: %d. Omitidos: %d. Con error: %d.',
            $totalLeidos,
            $importadas,
            $incluidasLibro,
            $fueraPeriodo,
            $reparadas,
            $duplicadasTotal,
            $erroresTotal
        );

        return [
            'success'          => $success,
            'message'          => trim($message),
            'importadas'       => $importadas,
            'importables'      => $cantidadImportables,
            'reparadas'        => $reparadas,
            'actualizadas'     => $reparadas,
            'incluidas_libro'   => $incluidasLibro,
            'fuera_periodo'     => $fueraPeriodo,
            'duplicadas'       => $duplicadas,
            'duplicadas_total' => $duplicadasTotal,
            'documentos_unicos_incluidos' => $incluidasLibro,
            'copias_duplicadas_apartadas' => $duplicadasTotal,
            'normalizacion_duplicados' => $normalizacionDuplicados,
            'repetidos_restaurados_sin_original' => $repetidosRestaurados,
            'invalidas'        => $invalidas,
            'invalidas_total'  => $erroresTotal,
            'errores_total'    => $erroresTotal,
            'total_leidos'     => $totalLeidos,
            'strict'           => $strict,
            'force_import'     => $forceImport,
            'import_batch_id'  => $batchId,
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
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                    'tipo_dte'       => '',
                    'tipoDte'        => '',
                    'numero_control' => '',
                    'numeroControl'  => '',
                    'codigo_generacion' => '',
                    'codigoGeneracion'  => '',
                    'razon'          => 'No se pudo subir el archivo. Codigo de error: ' . (int) $codigoError . '.',
                ];
                continue;
            }

            $contenido = @file_get_contents($temporales[$indice]);
            if ($contenido === false) {
                $errores[] = [
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                    'tipo_dte'       => '',
                    'tipoDte'        => '',
                    'numero_control' => '',
                    'numeroControl'  => '',
                    'codigo_generacion' => '',
                    'codigoGeneracion'  => '',
                    'razon'          => 'No se pudo leer el archivo.',
                ];
                continue;
            }

            $json = DteDataService::decodeJsonText($contenido);
            if (!is_array($json)) {
                $errores[] = [
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                    'tipo_dte'       => '',
                    'tipoDte'        => '',
                    'numero_control' => '',
                    'numeroControl'  => '',
                    'codigo_generacion' => '',
                    'codigoGeneracion'  => '',
                    'razon'          => 'El archivo no contiene un JSON valido.',
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
        array $libro,
        array $documento,
        array &$vistosLote,
        bool $strict = false,
        bool $forceImport = true,
        ?string $batchId = null,
        int $importIndex = 0
    ): array {
        $payload        = $documento['payload'] ?? [];
        $archivo        = $documento['archivo'] ?? 'Documento';
        $extraido       = DteDataService::extractDocumentoData($payload);
        $tipoLibro      = (string) ($libro['tipo'] ?? '');

        $codigoGeneracion = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $tipoDte          = ValidadorDTE::normalizarTipoDte((string) ($extraido['tipo_dte'] ?? ''));
        $extraido['tipo_dte'] = $tipoDte;

        $codigoOriginalVacio = $codigoGeneracion === '';
        if ($forceImport && $codigoOriginalVacio) {
            $codigoGeneracion = self::generarCodigoForzado($batchId, $importIndex);
            $extraido['codigo_generacion'] = $codigoGeneracion;
        }

        if (!$forceImport && $codigoGeneracion === '') {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalidaExtraida($archivo, $extraido, 'El documento no trae codigo de generacion.'),
            ];
        }

        if ($tipoDte === '') {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalidaExtraida($archivo, $extraido, 'El documento no trae tipoDte.'),
            ];
        }

        if (!ValidadorDTE::validarTipo($tipoDte, $tipoLibro)) {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalidaExtraida($archivo, $extraido, "Tipo de DTE {$tipoDte} no valido para libro {$tipoLibro}."),
            ];
        }

        $motivoDuplicado = null;
        $originalDuplicado = null;
        if (!$codigoOriginalVacio && isset($vistosLote[$codigoGeneracion]) && !$forceImport) {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalidaExtraida(
                    $archivo,
                    $extraido,
                    'Documento enviado a revision: se repite dentro del lote, pero aun no existe un original contabilizado en la base.'
                ),
            ];
        } elseif (!$codigoOriginalVacio) {
            $originalEncontrado = FacturaModel::getPrincipalContabilizableByCodigoGeneracion($pdo, $codigoGeneracion, $idLibro);
            if (self::tieneOriginalReal($originalEncontrado)) {
                $motivoDuplicado = 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.';
                $originalDuplicado = $originalEncontrado;
            }
        }

        if ($motivoDuplicado === null) {
            $fingerprint = self::duplicateSecondaryKey($extraido);
            if ($fingerprint !== '' && isset($vistosLote['sec:' . $fingerprint]) && !$forceImport) {
                return [
                    'estado' => 'invalida',
                    'data'   => self::buildInvalidaExtraida(
                        $archivo,
                        $extraido,
                        'Documento enviado a revision: se repite dentro del lote, pero aun no existe un original contabilizado en la base.'
                    ),
                ];
            } elseif ($fingerprint !== '') {
                $originalEncontrado = FacturaModel::getByDuplicateFingerprint(
                    $pdo,
                    $tipoDte,
                    (string) ($extraido['numero_control'] ?? ''),
                    (string) ($extraido['nit'] ?? ''),
                    (string) ($extraido['sello_recepcion'] ?? ''),
                    $idLibro
                );
                if (self::tieneOriginalReal($originalEncontrado)) {
                    $motivoDuplicado = 'Numero de control + emisor + sello ya existen.';
                    $originalDuplicado = $originalEncontrado;
                }
            }
        }

        $posibleDuplicado = $motivoDuplicado !== null && self::tieneOriginalReal($originalDuplicado);

        $filaImportable = self::buildFilaImportable(
            $idLibro,
            $idUsuario,
            $archivo,
            $payload,
            $extraido,
            $libro,
            $batchId,
            $importIndex,
            $posibleDuplicado,
            $motivoDuplicado,
            $originalDuplicado
        );

        if (!$posibleDuplicado && $codigoGeneracion !== '') {
            $vistosLote[$codigoGeneracion] = self::referenciaOriginal($filaImportable);
        }
        $fingerprint = self::duplicateSecondaryKey($extraido);
        if (!$posibleDuplicado && $fingerprint !== '') {
            $vistosLote['sec:' . $fingerprint] = self::referenciaOriginal($filaImportable);
        }

        if ($forceImport) {
            return [
                'estado' => 'importable',
                'data'   => $filaImportable,
            ];
        }

        $existente = FacturaModel::getByCodigoGeneracion($pdo, $codigoGeneracion, $idLibro);

        if ($existente !== null) {
            $originalExistente = FacturaModel::getPrincipalContabilizableByCodigoGeneracion($pdo, $codigoGeneracion, $idLibro);
            if (!self::tieneOriginalReal($originalExistente)) {
                return [
                    'estado' => 'invalida',
                    'data'   => self::buildInvalidaExtraida($archivo, $extraido, 'Documento enviado a revision: no se encontro un original contabilizado para confirmar el duplicado.'),
                ];
            }

            if ($posibleDuplicado) {
                return [
                    'estado' => 'duplicada',
                    'data'   => self::buildDuplicadaClasificada($archivo, $extraido, $originalExistente, 'El documento ya existe en este libro.'),
                ];
            }

            if (!$strict || self::debeRepararExistente($existente, $filaImportable)) {
                $filaImportable['id_factura'] = (int) ($existente['id'] ?? 0);

                return [
                    'estado' => 'actualizable',
                    'data'   => $filaImportable,
                ];
            }

            return [
                'estado' => 'duplicada',
                'data'   => self::buildDuplicadaClasificada($archivo, $extraido, $originalExistente, 'El documento ya existe en este libro.'),
            ];
        }

        return [
            'estado' => 'importable',
            'data'   => $filaImportable,
        ];
    }

    private static function expandirDocumento(array $json): array {
        return DteDataService::expandDocumentPayloads($json);
    }

    private static function buildFilaImportable(
        int $idLibro,
        int $idUsuario,
        string $archivo,
        array $payload,
        array $extraido,
        array $libro = [],
        ?string $batchId = null,
        int $importIndex = 0,
        bool $posibleDuplicado = false,
        ?string $motivoDuplicado = null,
        ?array $originalDuplicado = null
    ): array {
        $rawJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $clasificacionPeriodo = self::clasificarPeriodo($extraido['fecha'] ?? null, $libro);
        $originalReal = self::tieneOriginalReal($originalDuplicado);
        $esRepetido = ($posibleDuplicado && $originalReal) ? 1 : 0;
        $motivoExclusion = $esRepetido
            ? ($motivoDuplicado ?: 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.')
            : null;

        return [
            'id_libro'                   => $idLibro,
            'id_usuario'                 => $idUsuario,
            'archivo'                    => $archivo,
            'nombre_archivo'             => $archivo,
            'codigo_generacion'          => $extraido['codigo_generacion'] ?? null,
            'sello_recepcion'            => $extraido['sello_recepcion'] ?? null,
            'numero_control'             => $extraido['numero_control'] ?? null,
            'tipo_dte'                   => $extraido['tipo_dte'] ?? null,
            'tipo_documento_nombre'      => $extraido['tipo_documento_nombre'] ?? DteDataService::tipoDocumentoNombre($extraido['tipo_dte'] ?? null),
            'signo_contable'             => (int) ($extraido['signo_contable'] ?? DteDataService::signoContable($extraido['tipo_dte'] ?? null)),
            'fecha'                      => $extraido['fecha'] ?? date('Y-m-d'),
            'nrc'                        => $extraido['nrc'] ?? null,
            'nit'                        => $extraido['nit'] ?? null,
            'nombre_proveedor'           => $extraido['nombre_proveedor'] ?? null,
            'ventas_internas'            => $extraido['ventas_internas'] ?? 0,
            'ventas_importacion'         => $extraido['ventas_importacion'] ?? 0,
            'ventas_internas_exentas'    => $extraido['ventas_internas_exentas'] ?? 0,
            'ventas_importacion_exentas' => $extraido['ventas_importacion_exentas'] ?? 0,
            'compras_exentas'            => $extraido['compras_exentas'] ?? 0,
            'compras_gravadas'           => $extraido['compras_gravadas'] ?? 0,
            'credito_fiscal'             => $extraido['credito_fiscal'] ?? 0,
            'impuestos_calculados'       => $extraido['impuestos_calculados'] ?? $extraido['credito_fiscal'] ?? 0,
            'fovial_otros'               => $extraido['fovial_otros'] ?? 0,
            'total_compras'              => $extraido['total_compras'] ?? 0,
            'iva_percibido'              => $extraido['iva_percibido'] ?? 0,
            'iva_retenido'               => $extraido['iva_retenido'] ?? 0,
            'numero_control_completo'    => $extraido['numero_control_completo'] ?? null,
            'documento_relacionado_tipo' => $extraido['documento_relacionado_tipo'] ?? null,
            'documento_relacionado_numero' => $extraido['documento_relacionado_numero'] ?? null,
            'documento_relacionado_codigo' => $extraido['documento_relacionado_codigo'] ?? null,
            'documento_relacionado_fecha'  => $extraido['documento_relacionado_fecha'] ?? null,
            'import_batch_id'            => $batchId,
            'import_index'               => $importIndex > 0 ? $importIndex : null,
            'source_filename'            => $archivo,
            'raw_json_hash'              => is_string($rawJson) ? hash('sha256', $rawJson) : null,
            'posible_duplicado'          => $posibleDuplicado ? 1 : 0,
            'fuera_de_periodo'           => $clasificacionPeriodo['fuera_de_periodo'] ? 1 : 0,
            'motivo_no_contabilizado'    => $clasificacionPeriodo['motivo'],
            'es_repetido'                => $esRepetido,
            'motivo_exclusion'           => $motivoExclusion,
            'contabilizable'             => ($esRepetido || $clasificacionPeriodo['fuera_de_periodo']) ? 0 : 1,
            'documento_original'         => $originalReal ? self::formatearReferenciaOriginal($originalDuplicado) : '',
            'id_original'                => $originalReal ? (int) ($originalDuplicado['id'] ?? 0) : null,
            'codigo_generacion_original' => $originalReal ? (string) ($originalDuplicado['codigo_generacion'] ?? '') : '',
            'numero_control_original'    => $originalReal ? (string) ($originalDuplicado['numero_control'] ?? '') : '',
            'archivo_original'           => $originalReal ? (string) ($originalDuplicado['source_filename'] ?? $originalDuplicado['nombre_archivo'] ?? $originalDuplicado['archivo'] ?? '') : '',
            'estado_duplicado'           => $esRepetido ? 'Apartado, no contabilizado' : '',
            'raw_json'                   => $rawJson,
        ];
    }

    private static function clasificarPeriodo(?string $fechaEmision, array $libro): array {
        $fechaEmision = trim((string) $fechaEmision);
        $mesLibro = (int) ($libro['mes'] ?? 0);
        $anioLibro = (int) ($libro['anio'] ?? 0);

        if ($fechaEmision === '' || $mesLibro < 1 || $mesLibro > 12 || $anioLibro <= 0) {
            return [
                'fuera_de_periodo' => false,
                'motivo'           => null,
            ];
        }

        try {
            $fechaDte = new DateTimeImmutable($fechaEmision);
            $periodoLibro = new DateTimeImmutable(sprintf('%04d-%02d-01', $anioLibro, $mesLibro));
            $periodoDte = new DateTimeImmutable($fechaDte->format('Y-m-01'));
        } catch (Throwable $exception) {
            return [
                'fuera_de_periodo' => false,
                'motivo'           => null,
            ];
        }

        $mesesDiferencia = ((int) $periodoLibro->format('Y') - (int) $periodoDte->format('Y')) * 12
            + ((int) $periodoLibro->format('n') - (int) $periodoDte->format('n'));

        if ($mesesDiferencia > 3) {
            return [
                'fuera_de_periodo' => true,
                'motivo'           => 'Documento mayor a 3 meses / fuera de periodo',
            ];
        }

        return [
            'fuera_de_periodo' => false,
            'motivo'           => null,
        ];
    }

    private static function generarBatchId(): string {
        try {
            return 'bulk-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            return 'bulk-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        }
    }

    private static function generarCodigoForzado(?string $batchId, int $importIndex): string {
        $base = preg_replace('/[^A-Za-z0-9]/', '', (string) ($batchId ?: self::generarBatchId()));
        $base = substr($base, -24);
        return 'FORZADO-' . $base . '-' . str_pad((string) max(1, $importIndex), 6, '0', STR_PAD_LEFT);
    }

    private static function debeRepararExistente(array $existente, array $nuevaFila): bool {
        foreach (['nrc', 'nit', 'nombre_proveedor', 'sello_recepcion', 'numero_control_completo'] as $campo) {
            if (!self::hasMeaningfulValue($existente[$campo] ?? null) && self::hasMeaningfulValue($nuevaFila[$campo] ?? null)) {
                return true;
            }
        }

        foreach ([
            'ventas_internas',
            'ventas_importacion',
            'ventas_internas_exentas',
            'ventas_importacion_exentas',
            'compras_exentas',
            'compras_gravadas',
            'credito_fiscal',
            'impuestos_calculados',
            'fovial_otros',
            'total_compras',
            'iva_percibido',
            'iva_retenido',
        ] as $campo) {
            $actual = self::numericValue($existente[$campo] ?? 0);
            $nuevo  = self::numericValue($nuevaFila[$campo] ?? 0);

            if ($nuevo > 0 && abs($nuevo - $actual) > 0.0001) {
                return true;
            }
        }

        $rawActual = (string) ($existente['raw_json'] ?? '');
        $rawNuevo  = (string) ($nuevaFila['raw_json'] ?? '');

        if (strlen($rawNuevo) > strlen($rawActual) + 50) {
            return true;
        }

        return false;
    }

    private static function hasMeaningfulValue($value): bool {
        return trim((string) $value) !== '';
    }

    private static function numericValue($value): float {
        return round((float) $value, 4);
    }

    private static function buildSinCuota(array $fila): array {
        $archivo = trim((string) ($fila['nombre_archivo'] ?? $fila['archivo'] ?? 'Documento'));
        $tipoDte = trim((string) ($fila['tipo_dte'] ?? ''));
        $codigo  = trim((string) ($fila['codigo_generacion'] ?? ''));

        return [
            'archivo'           => $archivo,
            'nombre_archivo'    => $archivo,
            'tipo_dte'          => $tipoDte,
            'tipoDte'           => $tipoDte,
            'tipo_dte_nombre'   => ValidadorDTE::getNombreTipo($tipoDte),
            'nombreTipo'        => ValidadorDTE::getNombreTipo($tipoDte),
            'codigo_generacion' => $codigo,
            'codigoGeneracion'  => $codigo,
            'numero_control'    => (string) ($fila['numero_control'] ?? ''),
            'numeroControl'     => (string) ($fila['numero_control'] ?? ''),
            'razon'             => 'Sin cuota disponible para importar este documento.',
        ];
    }

    private static function buildErrorDocumento(array $documento, string $razon): array {
        $payload = is_array($documento['payload'] ?? null) ? $documento['payload'] : [];
        $extraido = $payload !== [] ? DteDataService::extractDocumentoData($payload) : [];
        $archivo = trim((string) ($documento['nombre_archivo'] ?? $documento['archivo'] ?? 'Documento'));

        return [
            'archivo'           => $archivo,
            'nombre_archivo'    => $archivo,
            'codigo_generacion' => (string) ($documento['codigo_generacion'] ?? $extraido['codigo_generacion'] ?? ''),
            'codigoGeneracion'  => (string) ($documento['codigo_generacion'] ?? $extraido['codigo_generacion'] ?? ''),
            'tipo_dte'          => (string) ($documento['tipo_dte'] ?? $extraido['tipo_dte'] ?? ''),
            'tipoDte'           => (string) ($documento['tipo_dte'] ?? $extraido['tipo_dte'] ?? ''),
            'tipo_dte_nombre'   => ValidadorDTE::getNombreTipo((string) ($documento['tipo_dte'] ?? $extraido['tipo_dte'] ?? '')),
            'nombreTipo'        => ValidadorDTE::getNombreTipo((string) ($documento['tipo_dte'] ?? $extraido['tipo_dte'] ?? '')),
            'numero_control'    => (string) ($documento['numero_control'] ?? $extraido['numero_control'] ?? ''),
            'numeroControl'     => (string) ($documento['numero_control'] ?? $extraido['numero_control'] ?? ''),
            'razon'             => $razon,
        ];
    }

    private static function buildInvalidaExtraida(string $archivo, array $extraido, string $razon): array {
        $tipoDte = ValidadorDTE::normalizarTipoDte((string) ($extraido['tipo_dte'] ?? ''));
        $codigo = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $numeroControl = trim((string) ($extraido['numero_control'] ?? ''));

        return [
            'archivo'           => $archivo,
            'nombre_archivo'    => $archivo,
            'codigo_generacion' => $codigo,
            'codigoGeneracion'  => $codigo,
            'tipo_dte'          => $tipoDte,
            'tipoDte'           => $tipoDte,
            'tipo_dte_nombre'   => ValidadorDTE::getNombreTipo($tipoDte),
            'nombreTipo'        => ValidadorDTE::getNombreTipo($tipoDte),
            'numero_control'    => $numeroControl,
            'numeroControl'     => $numeroControl,
            'razon'             => $razon,
        ];
    }

    private static function buildDuplicadaClasificada(string $archivo, array $extraido, ?array $original, string $razon): array {
        $tipoDte = ValidadorDTE::normalizarTipoDte((string) ($extraido['tipo_dte'] ?? ''));
        $codigo = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $numeroControl = trim((string) ($extraido['numero_control'] ?? ''));
        $originalReal = self::tieneOriginalReal($original);
        $motivo = $originalReal
            ? self::motivoDuplicadoReporte($codigo, $original, $razon)
            : 'Documento enviado a revision: no se encontro un original contabilizado para confirmar el duplicado.';

        return [
            'archivo'                 => $archivo,
            'nombre_archivo'          => $archivo,
            'tipo_dte'                => $tipoDte,
            'tipoDte'                 => $tipoDte,
            'tipo_dte_nombre'         => ValidadorDTE::getNombreTipo($tipoDte),
            'nombreTipo'              => ValidadorDTE::getNombreTipo($tipoDte),
            'codigo_generacion'       => $codigo,
            'codigoGeneracion'        => $codigo,
            'codigo_generacion_original' => $originalReal ? (string) ($original['codigo_generacion'] ?? '') : '',
            'numero_control'          => $numeroControl,
            'numeroControl'           => $numeroControl,
            'nombre_proveedor'        => (string) ($extraido['nombre_proveedor'] ?? ''),
            'razon'                   => $motivo,
            'motivo_exclusion'        => $motivo,
            'documento_original'      => $originalReal ? self::formatearReferenciaOriginal($original) : '',
            'id_original'             => $originalReal ? (int) ($original['id'] ?? 0) : null,
            'numero_control_original' => $originalReal ? (string) ($original['numero_control'] ?? '') : '',
            'archivo_original'        => $originalReal ? (string) ($original['source_filename'] ?? $original['nombre_archivo'] ?? $original['archivo'] ?? '') : '',
            'estado'                  => 'Apartado, no contabilizado',
            'estado_duplicado'        => 'Apartado, no contabilizado',
        ];
    }

    private static function buildDuplicadaReal(array $fila, string $razon): array {
        $archivo = trim((string) ($fila['nombre_archivo'] ?? $fila['archivo'] ?? 'Documento'));
        $tipoDte = trim((string) ($fila['tipo_dte'] ?? ''));
        $codigo  = trim((string) ($fila['codigo_generacion'] ?? ''));

        return [
            'archivo'           => $archivo,
            'nombre_archivo'    => $archivo,
            'tipo_dte'          => $tipoDte,
            'tipoDte'           => $tipoDte,
            'tipo_dte_nombre'   => ValidadorDTE::getNombreTipo($tipoDte),
            'nombreTipo'        => ValidadorDTE::getNombreTipo($tipoDte),
            'codigo_generacion' => $codigo,
            'codigoGeneracion'  => $codigo,
            'codigo_generacion_original' => (string) ($fila['codigo_generacion_original'] ?? $fila['codigo_generacion'] ?? ''),
            'fecha'             => (string) ($fila['fecha'] ?? ''),
            'numero_control'    => (string) ($fila['numero_control'] ?? ''),
            'sello_recepcion'   => (string) ($fila['sello_recepcion'] ?? ''),
            'nombre_proveedor'  => (string) ($fila['nombre_proveedor'] ?? ''),
            'nit'               => (string) ($fila['nit'] ?? ''),
            'nrc'               => (string) ($fila['nrc'] ?? ''),
            'total_compras'     => (float) ($fila['total_compras'] ?? 0),
            'razon'             => $razon,
            'motivo_exclusion'   => $razon,
            'documento_original' => (string) ($fila['documento_original'] ?? ''),
            'id_original'        => ((int) ($fila['id_original'] ?? 0)) > 0 ? (int) ($fila['id_original'] ?? 0) : null,
            'numero_control_original' => (string) ($fila['numero_control_original'] ?? ''),
            'archivo_original'   => (string) ($fila['archivo_original'] ?? ''),
            'estado'             => (string) ($fila['estado_duplicado'] ?? 'Apartado, no contabilizado'),
            'estado_duplicado'   => (string) ($fila['estado_duplicado'] ?? 'Apartado, no contabilizado'),
        ];
    }

    private static function motivoDuplicadoReporte(string $codigo, ?array $original, string $fallback): string {
        if (!self::tieneOriginalReal($original)) {
            return $fallback;
        }

        $codigoOriginal = trim((string) ($original['codigo_generacion'] ?? ''));
        if ($codigo !== '' && $codigoOriginal !== '' && $codigo === $codigoOriginal) {
            return 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.';
        }

        if (self::tieneOriginalReal($original)) {
            return 'Numero de control + emisor + sello ya existen.';
        }

        return $fallback;
    }

    private static function tieneOriginalReal(?array $original): bool {
        return is_array($original) && (int) ($original['id'] ?? 0) > 0;
    }

    private static function aplicarOriginalDuplicado(array $fila, array $original): array {
        if (!self::tieneOriginalReal($original)) {
            return $fila;
        }

        $fila['id_original'] = (int) ($original['id'] ?? 0);
        $fila['codigo_generacion_original'] = (string) ($original['codigo_generacion'] ?? '');
        $fila['numero_control_original'] = (string) ($original['numero_control'] ?? '');
        $fila['archivo_original'] = (string) ($original['source_filename'] ?? $original['nombre_archivo'] ?? $original['archivo'] ?? '');
        $fila['documento_original'] = self::formatearReferenciaOriginal($original);

        return $fila;
    }

    private static function referenciaOriginal(array $fila): array {
        return [
            'id' => (int) ($fila['id'] ?? 0),
            'codigo_generacion' => (string) ($fila['codigo_generacion'] ?? ''),
            'numero_control' => (string) ($fila['numero_control'] ?? ''),
            'nombre_proveedor' => (string) ($fila['nombre_proveedor'] ?? ''),
            'source_filename' => (string) ($fila['source_filename'] ?? $fila['nombre_archivo'] ?? $fila['archivo'] ?? ''),
            'contabilizable' => (int) ($fila['contabilizable'] ?? 1),
            'es_repetido' => (int) ($fila['es_repetido'] ?? 0),
        ];
    }

    private static function formatearReferenciaOriginal(?array $original): string {
        if (!self::tieneOriginalReal($original)) {
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

        $archivo = trim((string) ($original['source_filename'] ?? $original['nombre_archivo'] ?? $original['archivo'] ?? ''));
        if ($archivo !== '') {
            $partes[] = $archivo;
        }

        return $partes !== [] ? implode(' | ', $partes) : '';
    }

    private static function duplicateSecondaryKey(array $extraido): string {
        $parts = [
            trim((string) ($extraido['tipo_dte'] ?? '')),
            trim((string) ($extraido['numero_control'] ?? '')),
            trim((string) ($extraido['nit'] ?? '')),
            trim((string) ($extraido['sello_recepcion'] ?? '')),
        ];

        foreach ($parts as $part) {
            if ($part === '') {
                return '';
            }
        }

        return implode('|', $parts);
    }

    private static function isDuplicateDatabaseError(Throwable $exception): bool {
        return $exception instanceof PDOException
            && (($exception->errorInfo[0] ?? '') === '23000' || (string) $exception->getCode() === '23000');
    }

    private static function esLista(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
