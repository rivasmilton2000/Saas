<?php
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/DteDataService.php';
require_once __DIR__ . '/ValidadorDTE.php';

class VentasLibroService {

    public static function importarVentasConsumidor(PDO $pdo, int $idLibro, int $idUsuario, array $facturas): array {
        FacturaModel::ensureExtendedSchema($pdo);
        return self::importar($pdo, $idLibro, $idUsuario, 'ventas_consumidor', $facturas);
    }

    public static function importarVentasContribuyente(PDO $pdo, int $idLibro, int $idUsuario, array $facturas): array {
        FacturaModel::ensureExtendedSchema($pdo);
        return self::importar($pdo, $idLibro, $idUsuario, 'ventas_contribuyente', $facturas);
    }

    public static function listarVentasConsumidor(PDO $pdo, int $idLibro, int $idUsuario): array {
        FacturaModel::ensureExtendedSchema($pdo);
        $libro = self::obtenerLibro($pdo, $idLibro, $idUsuario, 'ventas_consumidor');
        if (!$libro) {
            return self::error('Libro no encontrado o sin permiso.');
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $facturas = FacturaModel::getByLibro($pdo, $idLibro);
        $totales  = FacturaModel::getTotalesMensualesVentasConsumidor($pdo, $idLibro);
        $filas    = [];

        foreach ($facturas as $indice => $factura) {
            $filas[] = self::formatearFilaVentasConsumidor($factura, $indice + 1);
        }

        $filaTotales = self::filaTotalesVentasConsumidor($totales);
        $filasConTotales = $filas;
        if (!empty($filas)) {
            $filasConTotales[] = $filaTotales;
        }

        return [
            'success' => true,
            'message' => '',
            'data'    => [
                'libro'             => $libro,
                'columnas'          => self::columnasVentasConsumidor(),
                'registros'         => $filas,
                'filas'             => $filasConTotales,
                'fila_totales'      => $filaTotales,
                'totales'           => $totales,
                'cantidad_facturas' => count($facturas),
            ],
        ];
    }

    public static function listarVentasContribuyente(PDO $pdo, int $idLibro, int $idUsuario): array {
        FacturaModel::ensureExtendedSchema($pdo);
        $libro = self::obtenerLibro($pdo, $idLibro, $idUsuario, 'ventas_contribuyente');
        if (!$libro) {
            return self::error('Libro no encontrado o sin permiso.');
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $facturas = FacturaModel::getByLibro($pdo, $idLibro);
        $totales  = self::totalesVentasContribuyenteVacios();
        $terceros = [
            'ventas_exentas_cuenta_terceros'             => 0.0,
            'ventas_internas_gravadas_cuenta_terceros'   => 0.0,
            'debito_fiscal_cuenta_terceros'              => 0.0,
        ];
        $filas = [];

        foreach ($facturas as $indice => $factura) {
            $fila = self::formatearFilaVentasContribuyente($factura, $indice + 1);
            $terceros['ventas_exentas_cuenta_terceros'] += (float) ($fila['ventas_exentas_cuenta_terceros'] ?? 0);
            $terceros['ventas_internas_gravadas_cuenta_terceros'] += (float) ($fila['ventas_internas_gravadas_cuenta_terceros'] ?? 0);
            $terceros['debito_fiscal_cuenta_terceros'] += (float) ($fila['debito_fiscal_cuenta_terceros'] ?? 0);
            foreach ($totales as $clave => $valor) {
                $totales[$clave] = $valor + (float) ($fila[$clave] ?? 0);
            }
            $filas[] = $fila;
        }

        foreach ($terceros as $clave => $valor) {
            $terceros[$clave] = round($valor, 2);
        }
        foreach ($totales as $clave => $valor) {
            $totales[$clave] = round($valor, 2);
        }

        $filaTotales = self::filaTotalesVentasContribuyente($totales, $terceros);
        $filasConTotales = $filas;
        if (!empty($filas)) {
            $filasConTotales[] = $filaTotales;
        }

        return [
            'success' => true,
            'message' => '',
            'data'    => [
                'libro'             => $libro,
                'columnas'          => self::columnasVentasContribuyente(),
                'registros'         => $filas,
                'filas'             => $filasConTotales,
                'fila_totales'      => $filaTotales,
                'totales'           => array_merge($totales, $terceros),
                'cantidad_facturas' => count($facturas),
            ],
        ];
    }

    public static function exportarVentasConsumidor(PDO $pdo, int $idLibro, int $idUsuario): array {
        $resultado = self::listarVentasConsumidor($pdo, $idLibro, $idUsuario);
        if (($resultado['success'] ?? false) !== true) {
            return $resultado;
        }

        $resultado['data']['tipo_exportacion'] = 'ventas_consumidor';
        return $resultado;
    }

    public static function exportarVentasContribuyente(PDO $pdo, int $idLibro, int $idUsuario): array {
        $resultado = self::listarVentasContribuyente($pdo, $idLibro, $idUsuario);
        if (($resultado['success'] ?? false) !== true) {
            return $resultado;
        }

        $resultado['data']['tipo_exportacion'] = 'ventas_contribuyente';
        return $resultado;
    }

    private static function importar(PDO $pdo, int $idLibro, int $idUsuario, string $tipoLibro, array $facturas): array {
        $libro = self::obtenerLibro($pdo, $idLibro, $idUsuario, $tipoLibro);
        if (!$libro) {
            return self::error('Libro no encontrado o sin permiso.', [
                'importadas'       => 0,
                'duplicadas'       => [],
                'duplicadas_total' => 0,
                'invalidas'        => [],
                'invalidas_total'  => 0,
                'cuota_restante'   => 0,
            ]);
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $normalizados = self::normalizarDocumentos($facturas);
        $documentos   = $normalizados['documentos'];
        $duplicadas   = [];
        $invalidas    = $normalizados['invalidas'];
        $importables  = [];
        $vistosLote   = [];
        $totalLeidos  = count($documentos);
        $forceImport  = true;

        foreach ($documentos as $documento) {
            try {
	                $clasificacion = $tipoLibro === 'ventas_consumidor'
	                    ? self::clasificarVentasConsumidor($pdo, $idLibro, $idUsuario, $libro, $documento, $vistosLote)
	                    : self::clasificarVentasContribuyente($pdo, $idLibro, $idUsuario, $libro, $documento, $vistosLote);
            } catch (Throwable $exception) {
                $invalidas[] = self::buildErrorDocumento($documento, 'No se pudo clasificar el documento: ' . $exception->getMessage());
                continue;
            }

            if ($clasificacion['estado'] === 'importable') {
                $importables[] = $clasificacion['data'];
                continue;
            }

            if ($clasificacion['estado'] === 'duplicada') {
                $duplicadas[] = $clasificacion['data'];
                continue;
            }

            $invalidas[] = $clasificacion['data'];
        }

        $nuevas           = count($importables);
        $cuota            = FacturasCuotaModel::ensure($pdo, $idUsuario);
        $cuotaDisponible  = max(0, (int) ($cuota['disponibles'] ?? 0));
        $omitidasPorCuota = [];

        if (!$forceImport && $nuevas > $cuotaDisponible) {
            $omitidasPorCuota = array_slice($importables, $cuotaDisponible);
            $importables      = array_slice($importables, 0, $cuotaDisponible);

            foreach ($omitidasPorCuota as $fila) {
                $invalidas[] = self::buildSinCuota($fila);
            }
        }

        FacturaModel::prepararCargaMasivaFlexible($pdo);

        $importadas = 0;
        foreach ($importables as $fila) {
            try {
                if ($tipoLibro === 'ventas_consumidor') {
                    self::insertarVentaConsumidor($pdo, $fila);
                } else {
                    self::insertarVentaContribuyente($pdo, $fila);
                }

                $importadas++;
            } catch (Throwable $exception) {
                if (self::isDuplicateDatabaseError($exception)) {
                    try {
                        FacturaModel::prepararCargaMasivaFlexible($pdo);
                        if ($tipoLibro === 'ventas_consumidor') {
                            self::insertarVentaConsumidor($pdo, $fila);
                        } else {
                            self::insertarVentaContribuyente($pdo, $fila);
                        }
                        $importadas++;
                        continue;
                    } catch (Throwable $retryException) {
                        $invalidas[] = self::buildErrorDocumento($fila, 'No se pudo insertar aun en modo forzado: ' . $retryException->getMessage());
                        continue;
                    }
                }

                $duplicadaDb = self::isDuplicateDatabaseError($exception)
                    ? FacturaModel::getByCodigoGeneracion($pdo, (string) ($fila['codigo_generacion'] ?? ''), $idLibro)
                    : null;

                if ($duplicadaDb !== null) {
                    $duplicadas[] = self::buildDuplicada(
                        (string) ($fila['nombre_archivo'] ?? $fila['archivo'] ?? 'Documento'),
                        (string) ($fila['tipo_dte'] ?? ''),
                        (string) ($fila['codigo_generacion'] ?? '')
                    );
                    continue;
                }

                $invalidas[] = self::buildErrorDocumento($fila, 'No se pudo insertar: ' . $exception->getMessage());
            }
        }

        if ($importadas > 0 && !FacturasCuotaModel::decrementar($pdo, $idUsuario, $importadas)) {
            $invalidas[] = [
                'nombre_archivo' => 'cuota',
                'archivo'        => 'cuota',
                'razon'          => 'No se pudo descontar la cuota de facturas.',
            ];
        }

        $cuotaFinal = FacturasCuotaModel::getByUsuario($pdo, $idUsuario) ?? FacturasCuotaModel::resumenVacio();

        $erroresTotal = count($invalidas);
        $duplicadasTotal = count($duplicadas);
        $success = $importadas > 0 || ($totalLeidos > 0 && $erroresTotal < $totalLeidos);
        $message = sprintf(
            'Leidos: %d. Insertados: %d. Actualizados: 0. Duplicados reales omitidos: %d. Con error: %d.',
            $totalLeidos,
            $importadas,
            $duplicadasTotal,
            $erroresTotal
        );

        return [
            'success' => $success,
            'message' => trim($message),
            'data'    => [
                'importadas'       => $importadas,
                'reparadas'        => 0,
                'actualizadas'     => 0,
                'duplicadas'       => $duplicadas,
                'duplicadas_total' => $duplicadasTotal,
                'invalidas'        => $invalidas,
                'invalidas_total'  => $erroresTotal,
                'errores_total'    => $erroresTotal,
                'total_leidos'     => $totalLeidos,
                'cuota_restante'   => (int) ($cuotaFinal['disponibles'] ?? 0),
            ],
        ];
    }

    private static function clasificarVentasConsumidor(
        PDO $pdo,
        int $idLibro,
        int $idUsuario,
        array $libro,
        array $documento,
        array &$vistosLote
    ): array {
        $payload           = DteDataService::normalizeDocumentPayload($documento['payload'] ?? []);
        $nombreArchivo     = $documento['nombre_archivo'] ?? 'documento.json';
        $extraido          = DteDataService::extractDocumentoData($payload);
        $codigoGeneracion  = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $tipoDte           = trim((string) ($extraido['tipo_dte'] ?? ''));
        $nombreEmisor      = self::pickText($payload, [['emisor', 'nombre']], ['nombreemisor', 'nombre']);

        if ($codigoGeneracion === '') {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'El documento no trae codigo de generacion.'),
            ];
        }

        if (!ValidadorDTE::validarTipo($tipoDte, 'ventas_consumidor')) {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'Tipo de DTE no valido para Ventas Consumidor Final.'),
            ];
        }

        $vistosLote[$codigoGeneracion] = true;

        return [
            'estado' => 'importable',
            'data'   => array_merge(
                self::mapVentaConsumidor($payload, $idLibro, $idUsuario),
                self::periodoFields($extraido['fecha'] ?? null, $libro),
                [
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                ]
            ),
        ];
    }

    private static function clasificarVentasContribuyente(
        PDO $pdo,
        int $idLibro,
        int $idUsuario,
        array $libro,
        array $documento,
        array &$vistosLote
    ): array {
        $payload           = DteDataService::normalizeDocumentPayload($documento['payload'] ?? []);
        $nombreArchivo     = $documento['nombre_archivo'] ?? 'documento.json';
        $extraido          = DteDataService::extractDocumentoData($payload);
        $codigoGeneracion  = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $tipoDte           = trim((string) ($extraido['tipo_dte'] ?? ''));
        $nombreEmisor      = self::pickText($payload, [['emisor', 'nombre']], ['nombreemisor', 'nombre']);

        if ($codigoGeneracion === '') {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'El documento no trae codigo de generacion.'),
            ];
        }

        if (!ValidadorDTE::validarTipo($tipoDte, 'ventas_contribuyente')) {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'Tipo de DTE no valido para Ventas a Contribuyentes.'),
            ];
        }

        $vistosLote[$codigoGeneracion] = true;

        return [
            'estado' => 'importable',
            'data'   => array_merge(
                self::mapVentaContribuyente($payload, $idLibro, $idUsuario),
                self::periodoFields($extraido['fecha'] ?? null, $libro),
                [
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                ]
            ),
        ];
    }

    private static function mapVentaConsumidor(array $payload, int $idLibro, int $idUsuario): array {
        $extraido             = DteDataService::extractDocumentoData($payload);
        $receptor             = self::arrayValue($payload, 'receptor');
        $resumen              = self::arrayValue($payload, 'resumen');
        $codigoGeneracion     = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $fecha                = self::normalizarFecha($extraido['fecha'] ?? null);
        $numeroControlInterno = self::pickText($payload, [
            ['identificacion', 'numeroControl'],
            ['numeroControl'],
        ], ['numerocontrol']);
        $numeroControlPre     = self::extraerNumeroControlPreimpreso($numeroControlInterno);
        $correlativo          = self::parseNumeroEntero($numeroControlPre);
        $ventasExentas        = self::pickDecimal($resumen, [
            ['totalExentas'],
            ['totalExenta'],
            ['exenta'],
        ], ['totalexentas', 'totalexenta', 'exenta']);
        $ventasGravadas       = self::pickDecimal($resumen, [
            ['subTotalVentas'],
            ['totalGravadas'],
            ['totalGravada'],
        ], ['subtotalventas', 'totalgravadas', 'totalgravada']);
        $exportaciones        = self::pickDecimal($resumen, [
            ['exportaciones'],
            ['exportacion'],
            ['totalExportaciones'],
        ], ['exportaciones', 'exportacion', 'totalexportaciones']);
        $totalVentasPropias   = round($ventasExentas + $ventasGravadas + $exportaciones, 2);

        return [
            'id_libro'                     => $idLibro,
            'id_usuario'                   => $idUsuario,
            'codigo_generacion'            => $codigoGeneracion,
            'sello_recepcion'              => self::nullable($extraido['sello_recepcion'] ?? null),
            'numero_control'               => self::nullable($numeroControlInterno),
            'numero_control_interno'       => self::nullable($numeroControlInterno),
            'numero_control_preimpreso'    => self::nullable($numeroControlPre),
            'numero_control_completo'      => self::nullable($numeroControlInterno),
            'tipo_dte'                     => trim((string) ($extraido['tipo_dte'] ?? '01')),
            'fecha'                        => $fecha,
            'nombre_cliente'               => self::nullable($receptor['nombre'] ?? null),
            'nrc_cliente'                  => self::nullable($receptor['nrc'] ?? null),
            'dia_emision'                  => $fecha,
            'del_numero'                   => $correlativo,
            'al_numero'                    => $correlativo,
            'codigo_generacion_desde'      => $codigoGeneracion,
            'codigo_generacion_hasta'      => $codigoGeneracion,
            'ventas_exentas'               => $ventasExentas,
            'ventas_internas_gravadas'     => $ventasGravadas,
            'exportaciones'                => $exportaciones,
            'total_ventas_diarias_propias' => $totalVentasPropias,
            'ventas_cuenta_terceros'       => 0.0,
            'iva_percibido'                => round((float) ($extraido['iva_percibido'] ?? 0), 2),
            'iva_retenido'                 => round((float) ($extraido['iva_retenido'] ?? 0), 2),
            'raw_json'                     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function periodoFields(?string $fechaEmision, array $libro): array {
        $fechaEmision = trim((string) $fechaEmision);
        $mesLibro = (int) ($libro['mes'] ?? 0);
        $anioLibro = (int) ($libro['anio'] ?? 0);

        if ($fechaEmision === '' || $mesLibro < 1 || $mesLibro > 12 || $anioLibro <= 0) {
            return [
                'fuera_de_periodo' => 0,
                'motivo_no_contabilizado' => null,
            ];
        }

        try {
            $fechaDte = new DateTimeImmutable($fechaEmision);
            $periodoLibro = new DateTimeImmutable(sprintf('%04d-%02d-01', $anioLibro, $mesLibro));
            $periodoDte = new DateTimeImmutable($fechaDte->format('Y-m-01'));
        } catch (Throwable $exception) {
            return [
                'fuera_de_periodo' => 0,
                'motivo_no_contabilizado' => null,
            ];
        }

        $mesesDiferencia = ((int) $periodoLibro->format('Y') - (int) $periodoDte->format('Y')) * 12
            + ((int) $periodoLibro->format('n') - (int) $periodoDte->format('n'));

        return [
            'fuera_de_periodo' => $mesesDiferencia > 3 ? 1 : 0,
            'motivo_no_contabilizado' => $mesesDiferencia > 3 ? 'Documento mayor a 3 meses / fuera de periodo' : null,
        ];
    }

    private static function mapVentaContribuyente(array $payload, int $idLibro, int $idUsuario): array {
        $extraido             = DteDataService::extractDocumentoData($payload);
        $receptor             = self::arrayValue($payload, 'receptor');
        $destinatario         = self::arrayValue($payload, 'destinatario');
        $resumen              = self::arrayValue($payload, 'resumen');
        $codigoGeneracion     = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $fecha                = self::normalizarFecha($extraido['fecha'] ?? null);
        $numeroControlInterno = self::pickText($payload, [
            ['identificacion', 'numeroControl'],
            ['numeroControl'],
        ], ['numerocontrol']);
        $numeroControlPre     = self::extraerNumeroControlPreimpreso($numeroControlInterno);
        $nombreCliente        = self::firstNonEmpty([
            $receptor['nombre'] ?? null,
            $destinatario['nombre'] ?? null,
        ]);
        $nrcCliente           = self::firstNonEmpty([
            $receptor['nrc'] ?? null,
            $destinatario['nrc'] ?? null,
        ]);
        $ventasExentas        = self::pickDecimal($resumen, [
            ['totalExentas'],
            ['totalExenta'],
            ['exenta'],
        ], ['totalexentas', 'totalexenta', 'exenta']);
        $ventasGravadas       = self::pickDecimal($resumen, [
            ['totalGravadas'],
            ['totalGravada'],
            ['gravada'],
            ['subTotalVentas'],
        ], ['totalgravadas', 'totalgravada', 'gravada', 'subtotalventas']);
        $debitoFiscal         = self::pickDecimal($resumen, [
            ['totalIva'],
            ['debitoFiscal'],
            ['ivaTotal'],
        ], ['totaliva', 'debitofiscal', 'ivatotal']);
        if ($debitoFiscal === 0.0) {
            $debitoFiscal = round((float) ($extraido['impuestos_calculados'] ?? $extraido['credito_fiscal'] ?? 0), 2);
        }
        $ivaPercibido         = round((float) ($extraido['iva_percibido'] ?? 0), 2);
        $ivaRetenido          = round((float) ($extraido['iva_retenido'] ?? 0), 2);
        $totalDte             = self::pickDecimal($resumen, [
            ['totalPagar'],
            ['montoTotalOperacion'],
            ['totalOperacion'],
        ], ['totalpagar', 'montototaloperacion', 'totaloperacion']);
        $ventasTotales        = $totalDte > 0.0
            ? $totalDte
            : round($ventasExentas + $ventasGravadas + $debitoFiscal + $ivaPercibido - $ivaRetenido, 2);

        return [
            'id_libro'                                 => $idLibro,
            'id_usuario'                               => $idUsuario,
            'codigo_generacion'                        => $codigoGeneracion,
            'sello_recepcion'                          => self::nullable($extraido['sello_recepcion'] ?? null),
            'numero_control'                           => self::nullable($numeroControlInterno),
            'numero_control_interno'                   => self::nullable($numeroControlInterno),
            'numero_control_preimpreso'                => self::nullable($numeroControlPre),
            'numero_control_completo'                  => self::nullable($numeroControlInterno),
            'tipo_dte'                                 => trim((string) ($extraido['tipo_dte'] ?? '03')),
            'fecha'                                    => $fecha,
            'nombre_cliente'                           => self::nullable($nombreCliente),
            'nrc_cliente'                              => self::nullable($nrcCliente),
            'ventas_exentas'                           => 0.0,
            'ventas_internas_gravadas'                 => 0.0,
            'debito_fiscal'                            => 0.0,
            'ventas_exentas_contribuyente'             => $ventasExentas,
            'ventas_internas_gravadas_contribuyente'   => $ventasGravadas,
            'debito_fiscal_contribuyente'              => $debitoFiscal,
            'iva_percibido'                            => $ivaPercibido,
            'iva_retenido'                             => $ivaRetenido,
            'ventas_totales'                           => $ventasTotales,
            'raw_json'                                 => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function insertarVentaConsumidor(PDO $pdo, array $fila): void {
        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas (
                id_libro,
                id_usuario,
                codigo_generacion,
                sello_recepcion,
                numero_control,
                numero_control_preimpreso,
                numero_control_interno,
                numero_control_completo,
                tipo_dte,
                fecha,
                nombre_cliente,
                nrc_cliente,
                dia_emision,
                del_numero,
                al_numero,
                codigo_generacion_desde,
                codigo_generacion_hasta,
                ventas_exentas,
                ventas_internas_gravadas,
                exportaciones,
                total_ventas_diarias_propias,
                ventas_cuenta_terceros,
	                iva_percibido,
	                iva_retenido,
	                fuera_de_periodo,
	                motivo_no_contabilizado,
	                raw_json
	            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            (int) $fila['id_libro'],
            (int) $fila['id_usuario'],
            $fila['codigo_generacion'],
            $fila['sello_recepcion'],
            $fila['numero_control'],
            $fila['numero_control_preimpreso'],
            $fila['numero_control_interno'],
            $fila['numero_control_completo'],
            $fila['tipo_dte'],
            $fila['fecha'],
            $fila['nombre_cliente'],
            $fila['nrc_cliente'],
            $fila['dia_emision'],
            $fila['del_numero'],
            $fila['al_numero'],
            $fila['codigo_generacion_desde'],
            $fila['codigo_generacion_hasta'],
            $fila['ventas_exentas'],
            $fila['ventas_internas_gravadas'],
            $fila['exportaciones'],
            $fila['total_ventas_diarias_propias'],
	            $fila['ventas_cuenta_terceros'],
	            $fila['iva_percibido'],
	            $fila['iva_retenido'],
	            $fila['fuera_de_periodo'] ?? 0,
	            $fila['motivo_no_contabilizado'] ?? null,
	            $fila['raw_json'],
	        ]);
    }

    private static function insertarVentaContribuyente(PDO $pdo, array $fila): void {
        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas (
                id_libro,
                id_usuario,
                codigo_generacion,
                sello_recepcion,
                numero_control,
                numero_control_preimpreso,
                numero_control_interno,
                numero_control_completo,
                tipo_dte,
                fecha,
                nombre_cliente,
                nrc_cliente,
                ventas_exentas,
                ventas_internas_gravadas,
                debito_fiscal,
                ventas_exentas_contribuyente,
                ventas_internas_gravadas_contribuyente,
                debito_fiscal_contribuyente,
	                iva_percibido,
	                iva_retenido,
	                ventas_totales,
	                fuera_de_periodo,
	                motivo_no_contabilizado,
	                raw_json
	            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            (int) $fila['id_libro'],
            (int) $fila['id_usuario'],
            $fila['codigo_generacion'],
            $fila['sello_recepcion'],
            $fila['numero_control'],
            $fila['numero_control_preimpreso'],
            $fila['numero_control_interno'],
            $fila['numero_control_completo'],
            $fila['tipo_dte'],
            $fila['fecha'],
            $fila['nombre_cliente'],
            $fila['nrc_cliente'],
            $fila['ventas_exentas'],
            $fila['ventas_internas_gravadas'],
            $fila['debito_fiscal'],
            $fila['ventas_exentas_contribuyente'],
            $fila['ventas_internas_gravadas_contribuyente'],
            $fila['debito_fiscal_contribuyente'],
	            $fila['iva_percibido'],
	            $fila['iva_retenido'],
	            $fila['ventas_totales'],
	            $fila['fuera_de_periodo'] ?? 0,
	            $fila['motivo_no_contabilizado'] ?? null,
	            $fila['raw_json'],
	        ]);
    }

    private static function obtenerLibro(PDO $pdo, int $idLibro, int $idUsuario, string $tipoEsperado): ?array {
        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
        if (!$libro || ($libro['tipo'] ?? null) !== $tipoEsperado) {
            return null;
        }

        return $libro;
    }

    private static function formatearFilaVentasConsumidor(array $factura, int $numero): array {
        $payload              = self::decodeRawJson($factura['raw_json'] ?? null);
        $numeroControlInterno = self::firstNonEmpty([
            $factura['numero_control_interno'] ?? null,
            $factura['numero_control'] ?? null,
            self::pickText($payload ?? [], [['identificacion', 'numeroControl']], ['numerocontrol']),
        ]);
        $numeroControlPre     = self::firstNonEmpty([
            $factura['numero_control_preimpreso'] ?? null,
            self::extraerNumeroControlPreimpreso($numeroControlInterno),
        ]);
        $correlativo          = self::parseNumeroEntero($numeroControlPre);
        $fechaDia             = self::firstNonEmpty([
            $factura['dia_emision'] ?? null,
            $factura['fecha'] ?? null,
            self::pickText($payload ?? [], [['identificacion', 'fecEmi']], ['fecemi']),
        ]);

        return [
            '_id'                         => (int) ($factura['id'] ?? 0),
            'codigo_generacion'           => (string) ($factura['codigo_generacion'] ?? ''),
            'tipo_dte'                     => (string) ($factura['tipo_dte'] ?? ''),
            'tipo_documento_nombre'        => (string) ($factura['tipo_documento_nombre'] ?? DteDataService::tipoDocumentoNombre($factura['tipo_dte'] ?? '')),
            'signo_contable'               => (int) ($factura['signo_contable'] ?? DteDataService::signoContable($factura['tipo_dte'] ?? '')),
            'es_nota_credito'              => DteDataService::esNotaCredito($factura['tipo_dte'] ?? ''),
            'no'                          => $numero,
            'dia_emision'                 => self::formatearFecha($fechaDia),
            'del_numero'                  => (int) ($factura['del_numero'] ?? $correlativo ?? 0),
            'al_numero'                   => (int) ($factura['al_numero'] ?? $correlativo ?? 0),
            'codigo_generacion_desde'     => (string) ($factura['codigo_generacion_desde'] ?? $factura['codigo_generacion'] ?? ''),
            'codigo_generacion_hasta'     => (string) ($factura['codigo_generacion_hasta'] ?? $factura['codigo_generacion'] ?? ''),
            'sello_recepcion'             => (string) ($factura['sello_recepcion'] ?? ''),
            'ventas_exentas'              => self::signedAmount($factura, 'ventas_exentas'),
            'ventas_internas_gravadas'    => self::signedAmount($factura, 'ventas_internas_gravadas'),
            'exportaciones'               => self::signedAmount($factura, 'exportaciones'),
            'total_ventas_diarias_propias'=> self::signedAmount($factura, 'total_ventas_diarias_propias'),
            'ventas_cuenta_terceros'      => self::signedAmount($factura, 'ventas_cuenta_terceros'),
        ];
    }

    private static function formatearFilaVentasContribuyente(array $factura, int $numero): array {
        $payload              = self::decodeRawJson($factura['raw_json'] ?? null);
        $payload              = is_array($payload) ? $payload : [];
        $extraido             = $payload !== [] ? DteDataService::extractDocumentoData($payload) : [];
        $receptor             = self::arrayValue($payload, 'receptor');
        $destinatario         = self::arrayValue($payload, 'destinatario');
        $numeroControlInterno = self::firstNonEmpty([
            $factura['numero_control_interno'] ?? null,
            $factura['numero_control'] ?? null,
            $extraido['numero_control'] ?? null,
            self::pickText($payload ?? [], [['identificacion', 'numeroControl']], ['numerocontrol']),
        ]);
        $numeroControlPre     = self::firstNonEmpty([
            $factura['numero_control_preimpreso'] ?? null,
            self::extraerNumeroControlPreimpreso($numeroControlInterno),
        ]);
        $fecha                = self::firstNonEmpty([
            $factura['fecha'] ?? null,
            self::pickText($payload ?? [], [['identificacion', 'fecEmi']], ['fecemi']),
        ]);

        return [
            '_id'                                       => (int) ($factura['id'] ?? 0),
            'tipo_dte'                                   => (string) ($factura['tipo_dte'] ?? ''),
            'tipo_documento_nombre'                      => self::firstNonEmpty([
                $factura['tipo_documento_nombre'] ?? null,
                $extraido['tipo_documento_nombre'] ?? null,
                DteDataService::tipoDocumentoNombre($factura['tipo_dte'] ?? ''),
            ]) ?? '',
            'signo_contable'                             => (int) ($factura['signo_contable'] ?? DteDataService::signoContable($factura['tipo_dte'] ?? '')),
            'es_nota_credito'                            => DteDataService::esNotaCredito($factura['tipo_dte'] ?? ''),
            'no'                                        => $numero,
            'fecha'                                     => self::formatearFecha($fecha),
            'numero_control_preimpreso'                 => (string) ($numeroControlPre ?? ''),
            'numero_control_interno'                    => (string) ($numeroControlInterno ?? ''),
            'codigo_generacion'                         => self::firstNonEmpty([
                $factura['codigo_generacion'] ?? null,
                $extraido['codigo_generacion'] ?? null,
            ]) ?? '',
            'sello_recepcion'                           => self::firstNonEmpty([
                $factura['sello_recepcion'] ?? null,
                $extraido['sello_recepcion'] ?? null,
            ]) ?? '',
            'nombre_cliente'                            => self::firstNonEmpty([
                $factura['nombre_cliente'] ?? null,
                $receptor['nombre'] ?? null,
                $destinatario['nombre'] ?? null,
            ]) ?? '',
            'nrc_cliente'                               => self::firstNonEmpty([
                $factura['nrc_cliente'] ?? null,
                $receptor['nrc'] ?? null,
                $destinatario['nrc'] ?? null,
            ]) ?? '',
            'ventas_exentas_contribuyente'              => self::resolveContribuyenteAmount($factura, $extraido, 'ventas_exentas_contribuyente', 'compras_exentas'),
            'ventas_internas_gravadas_contribuyente'    => self::resolveContribuyenteAmount($factura, $extraido, 'ventas_internas_gravadas_contribuyente', 'compras_gravadas'),
            'debito_fiscal_contribuyente'               => self::resolveContribuyenteAmount($factura, $extraido, 'debito_fiscal_contribuyente', 'impuestos_calculados'),
            'ventas_exentas_cuenta_terceros'            => self::signedAmount($factura, 'ventas_exentas'),
            'ventas_internas_gravadas_cuenta_terceros'  => self::signedAmount($factura, 'ventas_internas_gravadas'),
            'debito_fiscal_cuenta_terceros'             => self::signedAmount($factura, 'debito_fiscal'),
            'iva_percibido'                             => self::resolveContribuyenteAmount($factura, $extraido, 'iva_percibido', 'iva_percibido'),
            'iva_retenido'                              => self::resolveContribuyenteAmount($factura, $extraido, 'iva_retenido', 'iva_retenido'),
            'ventas_totales'                            => self::resolveContribuyenteTotal($factura, $extraido),
        ];
    }

    private static function filaTotalesVentasConsumidor(array $totales): array {
        return [
            'no'                           => null,
            'dia_emision'                  => 'TOTALES DEL MES',
            'tipo_documento_nombre'        => '',
            'del_numero'                   => null,
            'al_numero'                    => null,
            'codigo_generacion_desde'      => '',
            'codigo_generacion_hasta'      => '',
            'sello_recepcion'              => '',
            'ventas_exentas'               => round((float) ($totales['ventas_exentas'] ?? 0), 2),
            'ventas_internas_gravadas'     => round((float) ($totales['ventas_internas_gravadas'] ?? 0), 2),
            'exportaciones'                => round((float) ($totales['exportaciones'] ?? 0), 2),
            'total_ventas_diarias_propias' => round((float) ($totales['total_ventas_diarias_propias'] ?? 0), 2),
            'ventas_cuenta_terceros'       => round((float) ($totales['ventas_cuenta_terceros'] ?? 0), 2),
        ];
    }

    private static function filaTotalesVentasContribuyente(array $totales, array $terceros): array {
        return [
            'no'                                        => null,
            'fecha'                                     => 'TOTALES DEL MES',
            'tipo_documento_nombre'                     => '',
            'numero_control_preimpreso'                 => '',
            'numero_control_interno'                    => '',
            'codigo_generacion'                         => '',
            'sello_recepcion'                           => '',
            'nombre_cliente'                            => '',
            'nrc_cliente'                               => '',
            'ventas_exentas_contribuyente'              => round((float) ($totales['ventas_exentas_contribuyente'] ?? 0), 2),
            'ventas_internas_gravadas_contribuyente'    => round((float) ($totales['ventas_internas_gravadas_contribuyente'] ?? 0), 2),
            'debito_fiscal_contribuyente'               => round((float) ($totales['debito_fiscal_contribuyente'] ?? 0), 2),
            'ventas_exentas_cuenta_terceros'            => round((float) ($terceros['ventas_exentas_cuenta_terceros'] ?? 0), 2),
            'ventas_internas_gravadas_cuenta_terceros'  => round((float) ($terceros['ventas_internas_gravadas_cuenta_terceros'] ?? 0), 2),
            'debito_fiscal_cuenta_terceros'             => round((float) ($terceros['debito_fiscal_cuenta_terceros'] ?? 0), 2),
            'iva_percibido'                             => round((float) ($totales['iva_percibido'] ?? 0), 2),
            'iva_retenido'                              => round((float) ($totales['iva_retenido'] ?? 0), 2),
            'ventas_totales'                            => round((float) ($totales['ventas_totales'] ?? 0), 2),
        ];
    }

    private static function columnasVentasConsumidor(): array {
        return [
            'no',
            'dia_emision',
            'tipo_documento_nombre',
            'del_numero',
            'al_numero',
            'codigo_generacion_desde',
            'codigo_generacion_hasta',
            'sello_recepcion',
            'ventas_exentas',
            'ventas_internas_gravadas',
            'exportaciones',
            'total_ventas_diarias_propias',
            'ventas_cuenta_terceros',
        ];
    }

    private static function columnasVentasContribuyente(): array {
        return [
            'no',
            'fecha',
            'tipo_documento_nombre',
            'numero_control_preimpreso',
            'numero_control_interno',
            'codigo_generacion',
            'sello_recepcion',
            'nombre_cliente',
            'nrc_cliente',
            'ventas_exentas_contribuyente',
            'ventas_internas_gravadas_contribuyente',
            'debito_fiscal_contribuyente',
            'ventas_exentas_cuenta_terceros',
            'ventas_internas_gravadas_cuenta_terceros',
            'debito_fiscal_cuenta_terceros',
            'iva_percibido',
            'iva_retenido',
            'ventas_totales',
        ];
    }

    private static function signedAmount(array $factura, string $campo): float {
        return round((float) ($factura[$campo] ?? 0), 2);
    }

    private static function resolveContribuyenteAmount(array $factura, array $extraido, string $campoDb, string $campoExtraido): float {
        $actual = round((float) ($factura[$campoDb] ?? 0), 2);
        $desdeJson = round((float) ($extraido[$campoExtraido] ?? 0), 2);

        return $actual === 0.0 && $desdeJson !== 0.0 ? $desdeJson : $actual;
    }

    private static function resolveContribuyenteTotal(array $factura, array $extraido): float {
        $desdeJson = round((float) ($extraido['total_compras'] ?? 0), 2);
        if ($desdeJson !== 0.0) {
            return $desdeJson;
        }

        $actual = round((float) ($factura['ventas_totales'] ?? 0), 2);
        if ($actual !== 0.0) {
            return $actual;
        }

        return round(
            self::resolveContribuyenteAmount($factura, $extraido, 'ventas_exentas_contribuyente', 'compras_exentas')
            + self::resolveContribuyenteAmount($factura, $extraido, 'ventas_internas_gravadas_contribuyente', 'compras_gravadas')
            + self::resolveContribuyenteAmount($factura, $extraido, 'debito_fiscal_contribuyente', 'impuestos_calculados')
            + self::resolveContribuyenteAmount($factura, $extraido, 'iva_percibido', 'iva_percibido')
            - self::resolveContribuyenteAmount($factura, $extraido, 'iva_retenido', 'iva_retenido'),
            2
        );
    }

    private static function totalesVentasContribuyenteVacios(): array {
        return [
            'ventas_exentas_contribuyente'           => 0.0,
            'ventas_internas_gravadas_contribuyente' => 0.0,
            'debito_fiscal_contribuyente'            => 0.0,
            'iva_percibido'                          => 0.0,
            'iva_retenido'                           => 0.0,
            'ventas_totales'                         => 0.0,
        ];
    }

    private static function normalizarDocumentos(array $facturas): array {
        $documentos = [];
        $invalidas  = [];

        foreach ($facturas as $indice => $item) {
            $nombreArchivo = 'documento_' . ($indice + 1) . '.json';
            $payload       = $item;

            if (is_array($item) && array_key_exists('payload', $item)) {
                $payload       = $item['payload'];
                $nombreArchivo = trim((string) ($item['nombre_archivo'] ?? $item['archivo'] ?? $nombreArchivo));
            } elseif (is_array($item)) {
                $nombreArchivo = trim((string) ($item['nombre_archivo'] ?? $item['archivo'] ?? $nombreArchivo));
            }

            if (is_string($payload)) {
                $payload = DteDataService::decodeJsonText($payload);
            }

            if (!is_array($payload)) {
                $invalidas[] = self::buildInvalida($nombreArchivo, '', null, '');
                continue;
            }

            $lista = self::expandirDocumento($payload);
            foreach ($lista as $subIndice => $documento) {
                $documentos[] = [
                    'nombre_archivo' => count($lista) > 1
                        ? $nombreArchivo . ' #' . ($subIndice + 1)
                        : $nombreArchivo,
                    'payload' => $documento,
                ];
            }
        }

        return [
            'documentos' => $documentos,
            'invalidas'  => $invalidas,
        ];
    }

    private static function expandirDocumento(array $json): array {
        return DteDataService::expandDocumentPayloads($json);
    }

    private static function buildInvalida(string $nombreArchivo, string $tipoDte, ?string $nombreEmisor, string $codigoGeneracion, ?string $razon = null): array {
        return [
            'nombre_archivo'   => $nombreArchivo,
            'archivo'          => $nombreArchivo,
            'tipoDte'          => $tipoDte,
            'tipo_dte'         => $tipoDte,
            'nombreTipo'       => ValidadorDTE::getNombreTipo($tipoDte),
            'tipo_dte_nombre'  => ValidadorDTE::getNombreTipo($tipoDte),
            'nombreEmisor'     => self::nullable($nombreEmisor),
            'codigoGeneracion' => $codigoGeneracion,
            'razon'            => self::nullable($razon),
        ];
    }

    private static function buildDuplicada(string $nombreArchivo, string $tipoDte, string $codigoGeneracion): array {
        return [
            'nombre_archivo'   => $nombreArchivo,
            'archivo'          => $nombreArchivo,
            'tipoDte'          => $tipoDte,
            'tipo_dte'         => $tipoDte,
            'nombreTipo'       => ValidadorDTE::getNombreTipo($tipoDte),
            'tipo_dte_nombre'  => ValidadorDTE::getNombreTipo($tipoDte),
            'codigoGeneracion' => $codigoGeneracion,
            'codigo_generacion'=> $codigoGeneracion,
            'razon'            => 'El documento ya existe en este libro.',
        ];
    }

    private static function buildErrorDocumento(array $documento, string $razon): array {
        $payload = is_array($documento['payload'] ?? null) ? $documento['payload'] : [];
        $extraido = $payload !== [] ? DteDataService::extractDocumentoData($payload) : [];
        $archivo = trim((string) ($documento['nombre_archivo'] ?? $documento['archivo'] ?? 'Documento'));
        $tipoDte = (string) ($documento['tipo_dte'] ?? $extraido['tipo_dte'] ?? '');
        $codigo = (string) ($documento['codigo_generacion'] ?? $extraido['codigo_generacion'] ?? '');

        return [
            'nombre_archivo'    => $archivo,
            'archivo'           => $archivo,
            'tipoDte'           => $tipoDte,
            'tipo_dte'          => $tipoDte,
            'nombreTipo'        => ValidadorDTE::getNombreTipo($tipoDte),
            'tipo_dte_nombre'   => ValidadorDTE::getNombreTipo($tipoDte),
            'codigoGeneracion'  => $codigo,
            'codigo_generacion' => $codigo,
            'razon'             => $razon,
        ];
    }

    private static function isDuplicateDatabaseError(Throwable $exception): bool {
        return $exception instanceof PDOException
            && (($exception->errorInfo[0] ?? '') === '23000' || (string) $exception->getCode() === '23000');
    }

    private static function buildSinCuota(array $fila): array {
        $archivo = trim((string) ($fila['nombre_archivo'] ?? $fila['archivo'] ?? 'Documento'));
        $tipoDte = trim((string) ($fila['tipo_dte'] ?? ''));
        $codigo  = trim((string) ($fila['codigo_generacion'] ?? ''));

        return [
            'nombre_archivo'   => $archivo,
            'archivo'          => $archivo,
            'tipoDte'          => $tipoDte,
            'tipo_dte'         => $tipoDte,
            'nombreTipo'       => ValidadorDTE::getNombreTipo($tipoDte),
            'tipo_dte_nombre'  => ValidadorDTE::getNombreTipo($tipoDte),
            'codigoGeneracion' => $codigo,
            'codigo_generacion'=> $codigo,
            'razon'            => 'Sin cuota disponible para importar este documento.',
        ];
    }

    private static function error(string $message, array $data = []): array {
        return [
            'success' => false,
            'message' => $message,
            'data'    => $data ?: null,
        ];
    }

    private static function pickText(array $payload, array $paths = [], array $normalizedKeys = []): ?string {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);
        return self::firstNonEmpty($candidatos);
    }

    private static function pickDecimal(array $payload, array $paths = [], array $normalizedKeys = []): float {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);

        foreach ($candidatos as $candidato) {
            $normalizado = str_replace([',', ' '], ['', ''], (string) $candidato);
            if (is_numeric($normalizado)) {
                return round((float) $normalizado, 2);
            }
        }

        return 0.0;
    }

    private static function collectCandidates(array $payload, array $paths, array $normalizedKeys): array {
        $candidatos = [];

        foreach ($paths as $path) {
            $valor = self::getByPath($payload, $path);
            $texto = self::stringifyValue($valor);
            if ($texto !== null) {
                $candidatos[] = $texto;
            }
        }

        if (!empty($normalizedKeys)) {
            self::collectByNormalizedKeys($payload, $normalizedKeys, $candidatos);
        }

        return array_values(array_unique(array_filter($candidatos, static function ($valor) {
            return self::nullable($valor) !== null;
        })));
    }

    private static function getByPath(array $payload, array $path) {
        $actual = $payload;

        foreach ($path as $segmento) {
            if (!is_array($actual) || !array_key_exists($segmento, $actual)) {
                return null;
            }

            $actual = $actual[$segmento];
        }

        return $actual;
    }

    private static function collectByNormalizedKeys($payload, array $normalizedKeys, array &$candidatos): void {
        if (!is_array($payload)) {
            return;
        }

        foreach ($payload as $clave => $valor) {
            if (in_array(self::normalizeKey((string) $clave), $normalizedKeys, true)) {
                $texto = self::stringifyValue($valor);
                if ($texto !== null) {
                    $candidatos[] = $texto;
                }
            }

            if (is_array($valor)) {
                self::collectByNormalizedKeys($valor, $normalizedKeys, $candidatos);
            }
        }
    }

    private static function stringifyValue($value): ?string {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return self::nullable((string) $value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    return self::nullable((string) $item);
                }
            }
        }

        return null;
    }

    private static function normalizeKey(string $key): string {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
    }

    private static function normalizarFecha(?string $fecha): string {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $fecha;
        }

        $timestamp = strtotime($fecha);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private static function formatearFecha(?string $fecha): string {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        $timestamp = strtotime($fecha);
        return $timestamp ? date('d/m/Y', $timestamp) : $fecha;
    }

    private static function extraerNumeroControlPreimpreso(?string $numeroControl): ?string {
        $numeroControl = self::nullable($numeroControl);
        if ($numeroControl === null) {
            return null;
        }

        if (preg_match('/(\d+)(?!.*\d)/', $numeroControl, $matches) === 1) {
            return self::normalizarNumeroTexto($matches[1]);
        }

        $segmentos = explode('-', $numeroControl);
        return self::normalizarNumeroTexto((string) end($segmentos));
    }

    private static function normalizarNumeroTexto(string $valor): ?string {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $soloDigitos = preg_replace('/\D+/', '', $valor);
        if ($soloDigitos === '') {
            return $valor;
        }

        $sinCeros = ltrim($soloDigitos, '0');
        return $sinCeros === '' ? '0' : $sinCeros;
    }

    private static function parseNumeroEntero(?string $valor): ?int {
        $valor = self::nullable($valor);
        if ($valor === null) {
            return null;
        }

        $soloDigitos = preg_replace('/\D+/', '', $valor);
        if ($soloDigitos === '') {
            return null;
        }

        return (int) $soloDigitos;
    }

    private static function arrayValue(array $payload, string $key): array {
        $valor = $payload[$key] ?? [];
        return is_array($valor) ? $valor : [];
    }

    private static function nullable($value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function firstNonEmpty(array $values): ?string {
        foreach ($values as $value) {
            $value = self::nullable($value);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function decodeRawJson($raw): ?array {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function esLista(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
