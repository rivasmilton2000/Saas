<?php
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/DteDataService.php';
require_once __DIR__ . '/ValidadorDTE.php';

class RetencionIvaService {

    public static function importarRetencionIva(PDO $pdo, int $idLibro, int $idUsuario, array $facturas): array {
        FacturaModel::ensureExtendedSchema($pdo);

        $libro = self::obtenerLibro($pdo, $idLibro, $idUsuario);
        if (!$libro) {
            return self::error('Libro no encontrado o sin permiso.', self::respuestaImportacionVacia());
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $normalizados = self::normalizarDocumentos($facturas);
        $documentos   = $normalizados['documentos'];
        $invalidas    = $normalizados['invalidas'];
        $duplicadas   = [];
        $importables  = [];
        $vistosLote   = [];

        foreach ($documentos as $documento) {
            $clasificacion = self::clasificarDocumento($pdo, $idLibro, $idUsuario, $documento, $vistosLote);

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

        if ($nuevas > $cuotaDisponible) {
            $omitidasPorCuota = array_slice($importables, $cuotaDisponible);
            $importables      = array_slice($importables, 0, $cuotaDisponible);

            foreach ($omitidasPorCuota as $fila) {
                $invalidas[] = self::buildSinCuota($fila);
            }
        }

        $importadas = 0;

        try {
            $pdo->beginTransaction();

            foreach ($importables as $fila) {
                self::insertarRetencion($pdo, $fila);
                $importadas++;
            }

            if ($importadas > 0 && !FacturasCuotaModel::decrementar($pdo, $idUsuario, $importadas)) {
                throw new RuntimeException('No se pudo descontar la cuota disponible.');
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return self::error('Error al importar retenciones: ' . $exception->getMessage(), [
                'importadas'       => 0,
                'duplicadas'       => $duplicadas,
                'duplicadas_total' => count($duplicadas),
                'invalidas'        => $invalidas,
                'invalidas_total'  => count($invalidas),
                'cuota_restante'   => (int) ($cuota['disponibles'] ?? 0),
            ]);
        }

        $cuotaFinal = FacturasCuotaModel::getByUsuario($pdo, $idUsuario) ?? FacturasCuotaModel::resumenVacio();

        $success = $importadas > 0 || empty($omitidasPorCuota);
        $message = $importadas > 0
            ? "Se importaron {$importadas} comprobante(s) de retencion."
            : (!empty($omitidasPorCuota)
                ? 'No tienes cuota disponible para importar documentos nuevos.'
                : 'No hubo documentos nuevos para importar.');

        if (!empty($omitidasPorCuota)) {
            $message .= ' ' . count($omitidasPorCuota) . ' documento(s) quedaron fuera por cuota disponible.';
        }

        return [
            'success' => $success,
            'message' => trim($message),
            'data'    => [
                'importadas'       => $importadas,
                'duplicadas'       => $duplicadas,
                'duplicadas_total' => count($duplicadas),
                'invalidas'        => $invalidas,
                'invalidas_total'  => count($invalidas),
                'cuota_restante'   => (int) ($cuotaFinal['disponibles'] ?? 0),
            ],
        ];
    }

    public static function listarRetencionIva(PDO $pdo, int $idLibro, int $idUsuario): array {
        FacturaModel::ensureExtendedSchema($pdo);

        $libro = self::obtenerLibro($pdo, $idLibro, $idUsuario);
        if (!$libro) {
            return self::error('Libro no encontrado o sin permiso.');
        }

        EmpresaModel::marcarUltimaUsada($pdo, (int) $libro['id_empresa'], $idUsuario);

        $facturas = FacturaModel::getByLibro($pdo, $idLibro);
        $totales  = FacturaModel::getTotalesMensualesRetencionIva($pdo, $idLibro);
        $filas    = [];

        foreach ($facturas as $indice => $factura) {
            $filas[] = self::formatearFilaRetencion($factura, $indice + 1);
        }

        $filaTotales = self::filaTotalesRetencion($totales);
        $filasConTotales = $filas;
        if (!empty($filas)) {
            $filasConTotales[] = $filaTotales;
        }

        return [
            'success' => true,
            'message' => '',
            'data'    => [
                'libro'             => $libro,
                'columnas'          => self::columnasRetencion(),
                'registros'         => $filas,
                'filas'             => $filasConTotales,
                'fila_totales'      => $filaTotales,
                'totales'           => $totales,
                'cantidad_facturas' => count($facturas),
            ],
        ];
    }

    public static function exportarRetencionIva(PDO $pdo, int $idLibro, int $idUsuario): array {
        $resultado = self::listarRetencionIva($pdo, $idLibro, $idUsuario);
        if (($resultado['success'] ?? false) !== true) {
            return $resultado;
        }

        $resultado['data']['tipo_exportacion'] = 'retencion_iva';
        return $resultado;
    }

    private static function clasificarDocumento(
        PDO $pdo,
        int $idLibro,
        int $idUsuario,
        array $documento,
        array &$vistosLote
    ): array {
        $payload          = DteDataService::normalizeDocumentPayload($documento['payload'] ?? []);
        $nombreArchivo    = $documento['nombre_archivo'] ?? 'documento.json';
        $extraido         = DteDataService::extractDocumentoData($payload);
        $codigoGeneracion = trim((string) ($extraido['codigo_generacion'] ?? ''));
        $tipoDte          = trim((string) ($extraido['tipo_dte'] ?? ''));
        $nombreEmisor     = self::pickText($payload, [['emisor', 'nombre']], ['nombreemisor', 'nombre']);

        if ($codigoGeneracion === '') {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'El documento no trae codigo de generacion.'),
            ];
        }

        if (!ValidadorDTE::validarTipo($tipoDte, 'retencion_iva')) {
            return [
                'estado' => 'invalida',
                'data'   => self::buildInvalida($nombreArchivo, $tipoDte, $nombreEmisor, $codigoGeneracion, 'Tipo de DTE no valido para Retencion IVA 1%.'),
            ];
        }

        if (isset($vistosLote[$codigoGeneracion]) || FacturaModel::existeFactura($pdo, $codigoGeneracion, $idLibro)) {
            return [
                'estado' => 'duplicada',
                'data'   => self::buildDuplicada($nombreArchivo, $tipoDte, $codigoGeneracion),
            ];
        }

        $vistosLote[$codigoGeneracion] = true;

        return [
            'estado' => 'importable',
            'data'   => array_merge(
                self::mapRetencion($payload, $idLibro, $idUsuario),
                [
                    'archivo'        => $nombreArchivo,
                    'nombre_archivo' => $nombreArchivo,
                ]
            ),
        ];
    }

    private static function mapRetencion(array $payload, int $idLibro, int $idUsuario): array {
        $extraido            = DteDataService::extractDocumentoData($payload);
        $emisor              = self::arrayValue($payload, 'emisor');
        $resumen             = self::arrayValue($payload, 'resumen');
        $referenciaPrincipal = self::obtenerReferenciaPrincipal($payload);
        $fecha               = self::normalizarFecha($extraido['fecha'] ?? null);
        $tipoDocumento       = self::firstNonEmpty([
            self::pickText($referenciaPrincipal, [
                ['tipoDocumento'],
                ['tipoDoc'],
                ['tipo'],
                ['claseDocumento'],
            ], ['tipodocumento', 'tipodoc', 'tipo', 'clasedocumento']),
            self::pickText($payload, [
                ['tipoDocumentoRelacionado'],
                ['tipoDocumento'],
            ], ['tipodocumentorelacionado', 'tipodocumento', 'tipodoc']),
        ]);
        $serieDocumento      = self::firstNonEmpty([
            self::pickText($referenciaPrincipal, [
                ['serie'],
                ['serieDocumento'],
                ['serieControl'],
            ], ['serie', 'seriedocumento', 'seriecontrol']),
            self::pickText($payload, [
                ['serieDocumento'],
                ['serie'],
            ], ['seriedocumento', 'serie']),
        ]);
        $numeroDocumento     = self::firstNonEmpty([
            self::pickText($referenciaPrincipal, [
                ['numeroDocumento'],
                ['numDocumento'],
                ['numeroControl'],
                ['numero'],
            ], ['numerodocumento', 'numdocumento', 'numerocontrol', 'numero']),
            self::pickText($payload, [
                ['numeroDocumento'],
                ['numDocumento'],
            ], ['numerodocumento', 'numdocumento']),
        ]);
        $montoSujeto         = self::firstDecimal([
            self::pickDecimal($referenciaPrincipal, [
                ['montoSujetoRetencion'],
                ['montoSujeto'],
                ['montoBase'],
                ['baseImponible'],
                ['monto'],
            ], ['montosujetoretencion', 'montosujeto', 'montobase', 'baseimponible', 'monto']),
            self::pickDecimal($resumen, [
                ['montoSujetoRetencion'],
                ['montoSujeto'],
                ['baseImponible'],
                ['subTotal'],
            ], ['montosujetoretencion', 'montosujeto', 'baseimponible', 'subtotal']),
            self::pickDecimal($payload, [
                ['montoSujetoRetencion'],
                ['montoSujeto'],
            ], ['montosujetoretencion', 'montosujeto']),
        ]);
        $retencionIva        = self::firstDecimal([
            round((float) ($extraido['iva_retenido'] ?? 0), 2),
            self::pickDecimal($resumen, [
                ['retencionIva1'],
                ['ivaRetenido1'],
                ['ivaRete1'],
                ['montoRetencion'],
                ['retencion'],
            ], ['retencioniva1', 'ivaretenido1', 'ivarete1', 'montoretenido', 'retencion']),
            self::pickDecimal($payload, [
                ['retencionIva1'],
                ['montoRetencion'],
            ], ['retencioniva1', 'montoretenido']),
        ]);
        $numeroAnexo         = self::firstNonEmpty([
            self::pickText($referenciaPrincipal, [
                ['numeroAnexo'],
                ['numAnexo'],
            ], ['numeroanexo', 'numanexo']),
            self::pickText($payload, [
                ['numeroAnexo'],
                ['numAnexo'],
            ], ['numeroanexo', 'numanexo']),
        ]);

        return [
            'id_libro'                   => $idLibro,
            'id_usuario'                 => $idUsuario,
            'codigo_generacion'          => trim((string) ($extraido['codigo_generacion'] ?? '')),
            'sello_recepcion'            => self::nullable($extraido['sello_recepcion'] ?? null),
            'numero_control'             => self::nullable($extraido['numero_control'] ?? null),
            'numero_control_completo'    => self::nullable($extraido['numero_control_completo'] ?? null),
            'tipo_dte'                   => trim((string) ($extraido['tipo_dte'] ?? '14')),
            'fecha'                      => $fecha,
            'iva_retenido'               => $retencionIva,
            'nit_agente_retencion'       => self::nullable($emisor['nit'] ?? null),
            'fecha_emision_retencion'    => $fecha,
            'tipo_documento_relacionado' => self::nullable($tipoDocumento),
            'serie_documento'            => self::nullable($serieDocumento),
            'numero_documento'           => self::nullable($numeroDocumento),
            'monto_sujeto_retencion'     => $montoSujeto,
            'retencion_iva_1'            => $retencionIva,
            'dui_agente_retencion'       => self::nullable($emisor['dui'] ?? null),
            'numero_anexo'               => self::nullable($numeroAnexo),
            'raw_json'                   => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function insertarRetencion(PDO $pdo, array $fila): void {
        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas (
                id_libro,
                id_usuario,
                codigo_generacion,
                sello_recepcion,
                numero_control,
                numero_control_completo,
                tipo_dte,
                fecha,
                iva_retenido,
                nit_agente_retencion,
                fecha_emision_retencion,
                tipo_documento_relacionado,
                serie_documento,
                numero_documento,
                monto_sujeto_retencion,
                retencion_iva_1,
                dui_agente_retencion,
                numero_anexo,
                raw_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            (int) $fila['id_libro'],
            (int) $fila['id_usuario'],
            $fila['codigo_generacion'],
            $fila['sello_recepcion'],
            $fila['numero_control'],
            $fila['numero_control_completo'],
            $fila['tipo_dte'],
            $fila['fecha'],
            $fila['iva_retenido'],
            $fila['nit_agente_retencion'],
            $fila['fecha_emision_retencion'],
            $fila['tipo_documento_relacionado'],
            $fila['serie_documento'],
            $fila['numero_documento'],
            $fila['monto_sujeto_retencion'],
            $fila['retencion_iva_1'],
            $fila['dui_agente_retencion'],
            $fila['numero_anexo'],
            $fila['raw_json'],
        ]);
    }

    private static function formatearFilaRetencion(array $factura, int $numero): array {
        $payload             = self::decodeRawJson($factura['raw_json'] ?? null);
        $referenciaPrincipal = self::obtenerReferenciaPrincipal($payload ?? []);
        $fecha               = self::firstNonEmpty([
            $factura['fecha_emision_retencion'] ?? null,
            $factura['fecha'] ?? null,
            self::pickText($payload ?? [], [['identificacion', 'fecEmi']], ['fecemi']),
        ]);

        return [
            '_id'                    => (int) ($factura['id'] ?? 0),
            'codigo_generacion'      => (string) ($factura['codigo_generacion'] ?? ''),
            'tipo_dte'                => (string) ($factura['tipo_dte'] ?? ''),
            'no'                     => $numero,
            'nit_agente_retencion'   => (string) ($factura['nit_agente_retencion'] ?? self::pickText($payload ?? [], [['emisor', 'nit']], ['nitagenteretencion', 'nit']) ?? ''),
            'fecha_emision'          => self::formatearFecha($fecha),
            'tipo_documento'         => (string) ($factura['tipo_documento_relacionado'] ?? self::pickText($referenciaPrincipal, [['tipoDocumento'], ['tipoDoc'], ['tipo']], ['tipodocumento', 'tipodoc', 'tipo']) ?? $factura['tipo_dte'] ?? ''),
            'serie_documento'        => (string) ($factura['serie_documento'] ?? self::pickText($referenciaPrincipal, [['serie'], ['serieDocumento']], ['serie', 'seriedocumento']) ?? ''),
            'numero_documento'       => (string) ($factura['numero_documento'] ?? self::pickText($referenciaPrincipal, [['numeroDocumento'], ['numDocumento'], ['numeroControl']], ['numerodocumento', 'numdocumento', 'numerocontrol']) ?? ''),
            'monto_sujeto_retencion' => round((float) ($factura['monto_sujeto_retencion'] ?? 0), 2),
            'retencion_iva_1'        => round((float) ($factura['retencion_iva_1'] ?? $factura['iva_retenido'] ?? 0), 2),
            'dui_agente_retencion'   => (string) ($factura['dui_agente_retencion'] ?? self::pickText($payload ?? [], [['emisor', 'dui']], ['duiagenteretencion', 'dui']) ?? ''),
            'numero_anexo'           => (string) ($factura['numero_anexo'] ?? '') !== ''
                ? (string) $factura['numero_anexo']
                : (string) $numero,
        ];
    }

    private static function filaTotalesRetencion(array $totales): array {
        return [
            'no'                     => null,
            'nit_agente_retencion'   => '',
            'fecha_emision'          => 'TOTALES DEL MES',
            'tipo_documento'         => '',
            'serie_documento'        => '',
            'numero_documento'       => '',
            'monto_sujeto_retencion' => round((float) ($totales['monto_sujeto_retencion'] ?? 0), 2),
            'retencion_iva_1'        => round((float) ($totales['retencion_iva_1'] ?? 0), 2),
            'dui_agente_retencion'   => '',
            'numero_anexo'           => '',
        ];
    }

    private static function columnasRetencion(): array {
        return [
            'no',
            'nit_agente_retencion',
            'fecha_emision',
            'tipo_documento',
            'serie_documento',
            'numero_documento',
            'monto_sujeto_retencion',
            'retencion_iva_1',
            'dui_agente_retencion',
            'numero_anexo',
        ];
    }

    private static function obtenerLibro(PDO $pdo, int $idLibro, int $idUsuario): ?array {
        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);
        if (!$libro || ($libro['tipo'] ?? null) !== 'retencion_iva') {
            return null;
        }

        return $libro;
    }

    private static function obtenerReferenciaPrincipal(array $payload): array {
        $candidatos = [
            self::arrayValue($payload, 'documentoRelacionado'),
            self::arrayValue($payload, 'documentoRelacionado1'),
            self::arrayValue($payload, 'documento'),
            self::arrayValue($payload, 'docRelacionado'),
        ];

        $cuerpo = $payload['cuerpoDocumento'] ?? [];
        if (is_array($cuerpo) && array_keys($cuerpo) === range(0, count($cuerpo) - 1)) {
            $primero = $cuerpo[0] ?? [];
            if (is_array($primero)) {
                $candidatos[] = $primero;
            }
        } elseif (is_array($cuerpo)) {
            $candidatos[] = $cuerpo;
        }

        foreach ($candidatos as $candidato) {
            if (!empty($candidato)) {
                return $candidato;
            }
        }

        return [];
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

            $documentosExpandibles = self::expandirDocumento($payload);
            foreach ($documentosExpandibles as $subIndice => $documento) {
                $documentos[] = [
                    'nombre_archivo' => count($documentosExpandibles) > 1
                        ? $nombreArchivo . ' #' . ($subIndice + 1)
                        : $nombreArchivo,
                    'payload'        => $documento,
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

    private static function respuestaImportacionVacia(): array {
        return [
            'importadas'       => 0,
            'duplicadas'       => [],
            'duplicadas_total' => 0,
            'invalidas'        => [],
            'invalidas_total'  => 0,
            'cuota_restante'   => 0,
        ];
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
        ];
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

    private static function firstDecimal(array $valores): float {
        foreach ($valores as $valor) {
            $numero = round((float) $valor, 2);
            if ($numero !== 0.0) {
                return $numero;
            }
        }

        return round((float) ($valores[0] ?? 0), 2);
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
