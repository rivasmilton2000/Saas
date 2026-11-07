<?php
require_once __DIR__ . '/DteDataService.php';

class HaciendaCsvExportService {

    private const COLUMNAS = [
        'compras' => 21,
        'contribuyentes' => 20,
        'f14' => 23,
        'casilla_66' => 13,
        'casilla_162' => 9,
        'casilla_163' => 9,
    ];

    public static function preparar(string $tipo, array $libro, array $facturas, array $candidatas = []): array {
        $tipo = self::normalizarTipo($tipo);
        $facturas = self::prepararFacturas($facturas);
        $candidatas = $candidatas !== [] ? self::prepararFacturas($candidatas) : $facturas;

        return match ($tipo) {
            'compras' => self::prepararCompras($libro, $facturas, $candidatas),
            'contribuyentes' => self::prepararContribuyentes($libro, $facturas, $candidatas),
            'f14' => self::prepararF14($libro, $facturas, $candidatas),
            'casilla_66' => self::prepararCasilla66($libro, $facturas, $candidatas),
            'casilla_162' => self::prepararCasilla162($libro, $facturas, $candidatas),
            'casilla_163' => self::prepararCasilla163($libro, $facturas, $candidatas),
            default => throw new InvalidArgumentException('Tipo de exportacion Hacienda no soportado.'),
        };
    }

    public static function descargar(string $filename, string $tipo, array $libro, array $facturas, array $candidatas = []): void {
        $resultado = self::preparar($tipo, $libro, $facturas, $candidatas);
        self::escribirCsvHacienda($filename, $resultado['rows'], $resultado['expected_columns']);
    }

    public static function escribirCsvHacienda(string $filename, array $rows, int $expectedColumns): void {
        foreach ($rows as $index => $row) {
            if (count($row) !== $expectedColumns) {
                throw new RuntimeException('Fila ' . ($index + 1) . ' tiene ' . count($row) . ' columnas; se esperaban ' . $expectedColumns . '.');
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . self::safeFilename($filename) . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $salida = fopen('php://output', 'wb');
        foreach ($rows as $row) {
            fputcsv($salida, array_map([self::class, 'csvValue'], $row), ';', '"', '');
        }
        fclose($salida);
    }

    private static function prepararCompras(array $libro, array $facturas, array $candidatas): array {
        $rows = [];
        $errores = [];

        foreach ($facturas as $index => $factura) {
            $motivo = self::validarRequeridos($factura, ['fecha', 'codigo_generacion', 'nit', 'nombre_proveedor']);
            if ($motivo !== null) {
                $errores[] = self::errorFila($factura, $motivo);
                continue;
            }

            $payload = self::payload($factura);
            $emisor = self::arrayValue($payload, 'emisor');
            $rows[] = [
                self::fecha($factura['fecha'] ?? null),
                self::claseDocumento($payload),
                self::tipoDte($factura),
                self::texto($factura['codigo_generacion'] ?? $factura['numero_control'] ?? ''),
                self::texto(self::firstNonEmpty([$factura['nit'] ?? null, $emisor['nit'] ?? null, $factura['nrc'] ?? null, $emisor['nrc'] ?? null])),
                self::texto(self::firstNonEmpty([$factura['nombre_proveedor'] ?? null, $emisor['nombre'] ?? null])),
                self::monto($factura['ventas_internas_exentas'] ?? $factura['compras_exentas'] ?? 0),
                self::monto($factura['ventas_importacion_exentas'] ?? 0),
                self::monto(0),
                self::monto($factura['ventas_internas'] ?? $factura['compras_gravadas'] ?? 0),
                self::monto($factura['ventas_importacion'] ?? 0),
                self::monto(0),
                self::monto(0),
                self::monto($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? 0),
                self::monto($factura['total_compras'] ?? 0),
                self::texto($emisor['dui'] ?? ''),
                '3',
                self::periodo($libro),
                self::texto($libro['anio'] ?? ''),
                '1',
                '2',
            ];
        }

        return self::resultado('compras', $rows, $candidatas, $errores);
    }

    private static function prepararContribuyentes(array $libro, array $facturas, array $candidatas): array {
        $rows = [];
        $errores = [];

        foreach ($facturas as $index => $factura) {
            $motivo = self::validarRequeridos($factura, ['fecha', 'codigo_generacion', 'sello_recepcion']);
            if ($motivo !== null) {
                $errores[] = self::errorFila($factura, $motivo);
                continue;
            }

            $payload = self::payload($factura);
            $receptor = self::arrayValue($payload, 'receptor');
            $documentoRelacionado = self::documentoRelacionado($factura);
            $rows[] = [
                self::fecha($factura['fecha'] ?? null),
                self::claseDocumento($payload),
                self::tipoDte($factura),
                self::texto($factura['numero_control'] ?? $factura['numero_control_interno'] ?? ''),
                self::texto($factura['sello_recepcion'] ?? ''),
                self::texto($factura['codigo_generacion'] ?? ''),
                (string) ($index + 1),
                self::texto(self::firstNonEmpty([$factura['nrc_cliente'] ?? null, $receptor['nit'] ?? null, $receptor['nrc'] ?? null])),
                self::texto(self::firstNonEmpty([$factura['nombre_cliente'] ?? null, $receptor['nombre'] ?? null])),
                self::monto($factura['ventas_exentas_contribuyente'] ?? 0),
                self::monto(0),
                self::monto($factura['ventas_internas_gravadas_contribuyente'] ?? 0),
                self::monto($factura['debito_fiscal_contribuyente'] ?? 0),
                self::monto((float) ($factura['ventas_exentas'] ?? 0) + (float) ($factura['ventas_internas_gravadas'] ?? 0)),
                self::monto((float) ($factura['iva_percibido'] ?? 0) + (float) ($factura['iva_retenido'] ?? 0)),
                self::monto($factura['ventas_totales'] ?? 0),
                $documentoRelacionado,
                '1',
                self::periodo($libro),
                '1',
            ];
        }

        return self::resultado('contribuyentes', $rows, $candidatas, $errores);
    }

    private static function prepararF14(array $libro, array $facturas, array $candidatas): array {
        $rows = [];
        $errores = [];

        foreach ($facturas as $factura) {
            $motivo = self::validarRequeridos($factura, ['fecha_emision_retencion', 'codigo_generacion']);
            if ($motivo !== null) {
                $errores[] = self::errorFila($factura, $motivo);
                continue;
            }

            $rows[] = [
                self::texto($factura['nit_agente_retencion'] ?? $factura['nit'] ?? ''),
                self::texto($factura['dui_agente_retencion'] ?? ''),
                self::fecha($factura['fecha_emision_retencion'] ?? $factura['fecha'] ?? null),
                self::texto($factura['tipo_documento_relacionado'] ?? $factura['tipo_dte'] ?? ''),
                self::texto($factura['serie_documento'] ?? ''),
                self::texto($factura['numero_documento'] ?? $factura['numero_control'] ?? ''),
                self::texto($factura['sello_recepcion'] ?? ''),
                self::texto($factura['codigo_generacion'] ?? ''),
                self::monto($factura['monto_sujeto_retencion'] ?? 0),
                self::monto($factura['retencion_iva_1'] ?? $factura['iva_retenido'] ?? 0),
                self::texto($factura['numero_anexo'] ?? ''),
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                self::periodo($libro),
            ];
        }

        return self::resultado('f14', $rows, $candidatas, $errores);
    }

    private static function prepararCasilla66(array $libro, array $facturas, array $candidatas): array {
        $rows = [];
        $errores = [];

        foreach ($facturas as $factura) {
            $identificacion = self::texto(self::firstNonEmpty([$factura['dui_agente_retencion'] ?? null, $factura['nit_agente_retencion'] ?? null, $factura['nit'] ?? null]));
            if ($identificacion === '') {
                $errores[] = self::errorFila($factura, 'Falta DUI/NIT');
                continue;
            }

            $rows[] = [
                '1',
                $identificacion,
                self::texto($factura['nombre_proveedor'] ?? $factura['nombre_cliente'] ?? ''),
                self::fecha($factura['fecha_emision_retencion'] ?? $factura['fecha'] ?? null),
                self::texto($factura['sello_recepcion'] ?? ''),
                self::texto($factura['codigo_generacion'] ?? ''),
                self::monto($factura['monto_sujeto_retencion'] ?? 0),
                self::monto($factura['retencion_iva_1'] ?? $factura['iva_retenido'] ?? 0),
                '1',
                '1',
                '1',
                self::periodo($libro),
                '66',
            ];
        }

        return self::resultado('casilla_66', $rows, $candidatas, $errores);
    }

    private static function prepararCasilla162(array $libro, array $facturas, array $candidatas): array {
        return self::prepararCasillaNueve('casilla_162', '162', $libro, $facturas, $candidatas);
    }

    private static function prepararCasilla163(array $libro, array $facturas, array $candidatas): array {
        return self::prepararCasillaNueve('casilla_163', '8', $libro, $facturas, $candidatas);
    }

    private static function prepararCasillaNueve(string $tipo, string $casilla, array $libro, array $facturas, array $candidatas): array {
        $rows = [];
        $errores = [];

        foreach ($facturas as $factura) {
            $nit = self::texto($factura['nit_agente_retencion'] ?? $factura['nit'] ?? '');
            if ($nit === '') {
                $errores[] = self::errorFila($factura, 'Falta NIT');
                continue;
            }

            $rows[] = [
                $nit,
                self::fecha($factura['fecha_emision_retencion'] ?? $factura['fecha'] ?? null),
                self::texto($factura['tipo_documento_relacionado'] ?? $factura['tipo_dte'] ?? ''),
                self::texto($factura['sello_recepcion'] ?? ''),
                self::texto($factura['codigo_generacion'] ?? ''),
                self::monto($factura['monto_sujeto_retencion'] ?? 0),
                self::monto($factura['retencion_iva_1'] ?? $factura['iva_retenido'] ?? 0),
                '',
                $casilla,
            ];
        }

        return self::resultado($tipo, $rows, $candidatas, $errores);
    }

    private static function resultado(string $tipo, array $rows, array $candidatas, array $errores): array {
        $expectedColumns = self::COLUMNAS[$tipo];
        foreach ($rows as $index => $row) {
            if (count($row) !== $expectedColumns) {
                $errores[] = [
                    'fila' => $index + 1,
                    'motivo' => 'Cantidad de columnas incorrecta',
                    'columnas' => count($row),
                ];
                unset($rows[$index]);
            }
        }

        $rows = array_values($rows);

        return [
            'tipo' => $tipo,
            'expected_columns' => $expectedColumns,
            'rows' => $rows,
            'summary' => self::resumen($candidatas, $rows, $errores),
            'errores' => $errores,
        ];
    }

    private static function resumen(array $candidatas, array $rows, array $errores): array {
        $duplicados = 0;
        $fueraPeriodo = 0;
        $noContabilizables = 0;
        $notasCredito = 0;
        $notasDebito = 0;

        foreach ($candidatas as $factura) {
            $duplicados += (int) ($factura['es_repetido'] ?? 0) === 1 ? 1 : 0;
            $fueraPeriodo += (int) ($factura['fuera_de_periodo'] ?? 0) === 1 ? 1 : 0;
            $noContabilizables += (int) ($factura['contabilizable'] ?? 1) !== 1 ? 1 : 0;
            $tipoDte = self::tipoDte($factura);
            $notasCredito += $tipoDte === '05' ? 1 : 0;
            $notasDebito += $tipoDte === '06' ? 1 : 0;
        }

        return [
            'total_documentos_candidatos' => count($candidatas),
            'total_incluidos' => count($rows),
            'total_excluidos_por_duplicado' => $duplicados,
            'total_excluidos_por_fuera_de_periodo' => $fueraPeriodo,
            'total_excluidos_no_contabilizables' => $noContabilizables,
            'total_excluidos_por_error_de_campos' => count($errores),
            'total_notas_credito' => $notasCredito,
            'total_notas_debito' => $notasDebito,
        ];
    }

    private static function prepararFacturas(array $facturas): array {
        return array_map(static function (array $factura): array {
            return DteDataService::hydrateFactura($factura);
        }, $facturas);
    }

    private static function validarRequeridos(array $factura, array $campos): ?string {
        foreach ($campos as $campo) {
            if (self::texto($factura[$campo] ?? '') !== '') {
                continue;
            }

            return match ($campo) {
                'nit' => 'Falta NIT',
                'fecha', 'fecha_emision_retencion' => 'Falta fecha',
                'codigo_generacion' => 'Falta codigo de generacion',
                'sello_recepcion' => 'Falta sello recibido',
                default => 'Falta ' . $campo,
            };
        }

        return null;
    }

    private static function errorFila(array $factura, string $motivo): array {
        return [
            'codigo_generacion' => self::texto($factura['codigo_generacion'] ?? ''),
            'numero_control' => self::texto($factura['numero_control'] ?? ''),
            'tipo_dte' => self::tipoDte($factura),
            'motivo' => $motivo,
        ];
    }

    private static function normalizarTipo(string $tipo): string {
        return match ($tipo) {
            'hacienda_compras', 'compras', 'anexo_compras' => 'compras',
            'hacienda_contribuyentes', 'contribuyentes', 'anexo_contribuyentes' => 'contribuyentes',
            'hacienda_f14', 'f14', 'anexo_f14' => 'f14',
            'hacienda_casilla_66', 'casilla_66' => 'casilla_66',
            'hacienda_casilla_162', 'casilla_162' => 'casilla_162',
            'hacienda_casilla_163', 'casilla_163' => 'casilla_163',
            default => $tipo,
        };
    }

    private static function safeFilename(string $filename): string {
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($filename));
        return $filename !== '' ? $filename : 'export_hacienda';
    }

    private static function csvValue($value): string {
        return is_float($value) || is_int($value) ? self::monto($value) : (string) $value;
    }

    private static function monto($value): string {
        $value = str_replace(',', '', trim((string) $value));
        $numero = is_numeric($value) ? (float) $value : 0.0;
        return number_format($numero, 2, '.', '');
    }

    private static function fecha($value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y', $timestamp) : $value;
    }

    private static function periodo(array $libro): string {
        $mes = str_pad((string) (int) ($libro['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
        $anio = (string) ($libro['anio'] ?? '');
        return $mes . $anio;
    }

    private static function tipoDte(array $factura): string {
        $tipo = trim((string) ($factura['tipo_dte'] ?? ''));
        return ctype_digit($tipo) ? str_pad($tipo, 2, '0', STR_PAD_LEFT) : $tipo;
    }

    private static function documentoRelacionado(array $factura): string {
        if (!DteDataService::permiteDocumentoRelacionado($factura['tipo_dte'] ?? '')) {
            return '';
        }

        return self::texto(self::firstNonEmpty([
            $factura['documento_relacionado_codigo'] ?? null,
            $factura['documento_relacionado_numero'] ?? null,
        ]));
    }

    private static function claseDocumento(array $payload): string {
        $modelo = strtolower(self::texto(self::firstNonEmpty([
            $payload['identificacion']['tipoModelo'] ?? null,
            $payload['tipoModelo'] ?? null,
        ])));

        return $modelo !== '' ? $modelo : '4';
    }

    private static function payload(array $factura): array {
        if (!is_string($factura['raw_json'] ?? null)) {
            return [];
        }

        $payload = DteDataService::decodeJsonText((string) $factura['raw_json']);
        return is_array($payload) ? DteDataService::normalizeDocumentPayload($payload) : [];
    }

    private static function arrayValue(array $payload, string $key): array {
        $value = $payload[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    private static function firstNonEmpty(array $values): ?string {
        foreach ($values as $value) {
            $value = self::texto($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function texto($value): string {
        return trim((string) $value);
    }
}
