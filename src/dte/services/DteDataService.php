<?php

class DteDataService {

    public static function tipoDocumentoNombre(?string $tipoDte): string {
        $tipoDte = self::normalizeTipoDte($tipoDte) ?? '';

        $map = [
            '01' => 'Factura',
            '03' => 'Crédito Fiscal',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
        ];

        return $map[$tipoDte] ?? ($tipoDte !== '' ? 'Otro / tipoDte ' . $tipoDte : 'Otro / tipoDte');
    }

    public static function signoContable(?string $tipoDte): int {
        return 1;
    }

    public static function esNotaCredito(?string $tipoDte): bool {
        return (self::normalizeTipoDte($tipoDte) ?? '') === '05';
    }

    public static function permiteDocumentoRelacionado(?string $tipoDte): bool {
        return in_array(self::normalizeTipoDte($tipoDte) ?? '', ['05', '06'], true);
    }

    public static function extractDocumentoData(array $payload): array {
        $payload         = self::normalizeDocumentPayload($payload);
        $identificacion = self::arrayValue($payload, 'identificacion');
        $emisor         = self::arrayValue($payload, 'emisor');
        $receptor       = self::arrayValue($payload, 'receptor');
        $sujeto         = self::arrayValue($payload, 'sujetoExcluido');
        $resumen        = self::arrayValue($payload, 'resumen');
        $proveedor      = $emisor !== [] ? $emisor : ($receptor !== [] ? $receptor : $sujeto);
        $tipoDte        = self::normalizeTipoDte(self::extractFirstText($payload, [
            ['tipoDte'],
            ['identificacion', 'tipoDte'],
        ], ['tipodte']));
        $tipoDocumentoNombre = self::tipoDocumentoNombre($tipoDte);
        $signoContable = self::signoContable($tipoDte);
        $documentoRelacionado = self::permiteDocumentoRelacionado($tipoDte)
            ? self::extractDocumentoRelacionado($payload)
            : self::emptyDocumentoRelacionado();

        $ventasInternas = self::extractDecimal($resumen, [
            ['totalGravada'],
            ['subTotalVentas'],
            ['comprasGravadas'],
            ['gravadasLocales'],
        ], ['totalgravada', 'subtotalventas', 'comprasgravadas', 'gravadaslocales']);
        $ventasInternasDetalle = self::sumCuerpoDocumentoDecimals($payload, [
            ['ventaGravada'],
            ['montoGravado'],
            ['gravada'],
        ], ['ventagravada', 'montogravado', 'gravada']);
        if ($ventasInternas === 0.0 && $ventasInternasDetalle > 0.0) {
            $ventasInternas = $ventasInternasDetalle;
        }
        $ventasImportacion = self::extractDecimal($resumen, [
            ['importacion'],
            ['totalImportaciones'],
            ['comprasImportacion'],
            ['gravadaImportacion'],
        ], ['importacion', 'totalimportaciones', 'comprasimportacion', 'gravadaimportacion']);
        $ventasInternasExentas = self::extractDecimal($resumen, [
            ['totalExenta'],
            ['exenta'],
            ['comprasExentas'],
        ], ['totalexenta', 'exenta', 'comprasexentas']);
        $ventasImportacionExentas = self::extractDecimal($resumen, [
            ['totalNoSuj'],
            ['noSujeta'],
            ['importacionExenta'],
        ], ['totalnosuj', 'nosujeta', 'importacionexenta']);
        $ventasExentasDetalle = self::sumCuerpoDocumentoDecimals($payload, [
            ['ventaExenta'],
            ['ventaNoSuj'],
            ['montoExento'],
            ['exenta'],
            ['noSujeta'],
        ], ['ventaexenta', 'ventanosuj', 'montoexento', 'exenta', 'nosujeta']);
        if (($ventasInternasExentas + $ventasImportacionExentas) === 0.0 && $ventasExentasDetalle > 0.0) {
            $ventasInternasExentas = $ventasExentasDetalle;
        }
        $comprasExentas = round($ventasInternasExentas + $ventasImportacionExentas, 2);
        $comprasGravadas = round($ventasInternas + $ventasImportacion, 2);
        $creditoFiscal = self::extractDecimal($resumen, [
            ['creditoFiscal'],
            ['totalIva'],
            ['ivaTotal'],
        ], ['creditofiscal', 'totaliva', 'ivatotal']);
        $impuestosCalculados = self::extractIvaCalculado($payload, $resumen, $creditoFiscal, $comprasGravadas, $tipoDte);
        $ivaPercibido = self::extractDecimal($resumen, [
            ['ivaPercibido1'],
            ['ivaPerci1'],
            ['ivaPercibido'],
        ], ['ivapercibido1', 'ivaperci1', 'ivapercibido']);
        $ivaRetenido = self::extractDecimal($resumen, [
            ['ivaRetenido1'],
            ['ivaRete1'],
            ['ivaRetenido'],
        ], ['ivaretenido1', 'ivarete1', 'ivaretenido']);
        $fovialOtros = self::extractFovialOtros($payload, $resumen);
        $totalDte = self::extractDecimal($resumen, [
            ['totalCompras'],
            ['montoTotalOperacion'],
            ['totalPagar'],
            ['totalOperacion'],
        ], ['totalcompras', 'montototaloperacion', 'totalpagar', 'totaloperacion']);
        $totalCompras = self::calcularTotalCompra([
            'compras_exentas'       => $comprasExentas,
            'compras_gravadas'      => $comprasGravadas,
            'impuestos_calculados'  => $impuestosCalculados,
            'iva_percibido'         => $ivaPercibido,
            'iva_retenido'          => $ivaRetenido,
            'fovial_otros'          => $fovialOtros,
            'total_compras'         => $totalDte,
        ]);

        return [
            'codigo_generacion'          => self::extractFirstText($payload, [
                ['codigoGeneracion'],
                ['identificacion', 'codigoGeneracion'],
                ['respuestaMH', 'codigoGeneracion'],
            ], ['codigogeneracion']),
            'tipo_dte'                   => $tipoDte,
            'tipo_documento_nombre'      => $tipoDocumentoNombre,
            'signo_contable'             => $signoContable,
            'documento_relacionado_tipo' => $documentoRelacionado['tipo'],
            'documento_relacionado_numero' => $documentoRelacionado['numero'],
            'documento_relacionado_codigo' => $documentoRelacionado['codigo'],
            'documento_relacionado_fecha'  => $documentoRelacionado['fecha'],
            'sello_recepcion'            => self::extractLongestText($payload, [
                ['selloRecepcion'],
                ['identificacion', 'selloRecepcion'],
                ['identificacion', 'selloRecibido'],
                ['identificacion', 'sello'],
                ['respuestaMH', 'selloRecepcion'],
                ['respuestaMH', 'selloRecibido'],
                ['respuestaMH', 'sello'],
                ['recepcion', 'selloRecepcion'],
                ['recepcion', 'selloRecibido'],
                ['recepcion', 'sello'],
                ['procesamiento', 'selloRecepcion'],
                ['procesamiento', 'selloRecibido'],
                ['procesamiento', 'sello'],
                ['respuesta', 'selloRecepcion'],
                ['respuesta', 'selloRecibido'],
                ['respuesta', 'sello'],
            ], ['sellorecepcion', 'sellorecibido', 'sellorecepcionmh', 'sellorecibidomh', 'selloderecepcion']),
            'numero_control'             => self::extractFirstText($payload, [
                ['numeroControl'],
                ['identificacion', 'numeroControl'],
            ], ['numerocontrol']),
            'numero_control_completo'    => self::extractLongestText($payload, [
                ['numeroControl'],
                ['identificacion', 'numeroControl'],
            ], ['numerocontrol']),
            'fecha'                      => self::normalizeDate(self::extractFirstText($payload, [
                ['fecEmi'],
                ['identificacion', 'fecEmi'],
                ['fechaEmision'],
                ['identificacion', 'fechaEmision'],
            ], ['fecemi', 'fechaemision'])),
            'nrc'                        => self::nullable($proveedor['nrc'] ?? self::extractFirstText($payload, [], ['nrc'])),
            'nit'                        => self::nullable($proveedor['nit'] ?? self::extractFirstText($payload, [], ['nit'])),
            'nombre_proveedor'           => self::nullable($proveedor['nombre'] ?? self::extractFirstText($payload, [], ['nombreproveedor', 'nombreemisor', 'nombre'])),
            'ventas_internas'            => $ventasInternas,
            'ventas_importacion'         => $ventasImportacion,
            'ventas_internas_exentas'    => $ventasInternasExentas,
            'ventas_importacion_exentas' => $ventasImportacionExentas,
            'compras_exentas'            => $comprasExentas,
            'compras_gravadas'           => $comprasGravadas,
            'credito_fiscal'             => $impuestosCalculados,
            'impuestos_calculados'       => $impuestosCalculados,
            'fovial_otros'               => $fovialOtros,
            'total_compras'              => $totalCompras,
            'iva_percibido'              => $ivaPercibido,
            'iva_retenido'               => $ivaRetenido,
        ];
    }

    public static function hydrateFactura(array $factura): array {
        $payload   = self::decodeRawJson($factura['raw_json'] ?? null);
        $extraido  = is_array($payload) ? self::extractDocumentoData($payload) : [];
        $resuelta  = $factura;

        $resuelta['codigo_generacion'] = self::firstNonEmpty([
            $factura['codigo_generacion'] ?? null,
            $extraido['codigo_generacion'] ?? null,
        ]) ?? '';

        $resuelta['tipo_dte'] = self::firstNonEmpty([
            $factura['tipo_dte'] ?? null,
            $extraido['tipo_dte'] ?? null,
        ]) ?? '';
        $resuelta['tipo_dte'] = self::normalizeTipoDte($resuelta['tipo_dte']) ?? '';
        $resuelta['tipo_documento_nombre'] = self::firstNonEmpty([
            $factura['tipo_documento_nombre'] ?? null,
            $extraido['tipo_documento_nombre'] ?? null,
        ]) ?? self::tipoDocumentoNombre($resuelta['tipo_dte']);
        $resuelta['signo_contable'] = self::signoContable($resuelta['tipo_dte']);
        $resuelta['es_nota_credito'] = self::esNotaCredito($resuelta['tipo_dte']);
        $resuelta['es_repetido'] = (int) ($factura['es_repetido'] ?? 0);
        $resuelta['contabilizable'] = (int) ($factura['contabilizable'] ?? 1);
        $resuelta['motivo_exclusion'] = self::firstNonEmpty([
            $factura['motivo_exclusion'] ?? null,
            $factura['motivo_no_contabilizado'] ?? null,
        ]) ?? '';

        $resuelta['fecha'] = self::normalizeDate(self::firstNonEmpty([
            $factura['fecha'] ?? null,
            $extraido['fecha'] ?? null,
        ]));
        $resuelta['fecha_display'] = self::formatDate($resuelta['fecha']);

        $resuelta['numero_control'] = self::firstNonEmpty([
            $factura['numero_control'] ?? null,
            $extraido['numero_control'] ?? null,
        ]) ?? '';

        $resuelta['numero_control_completo'] = self::longestNonEmpty([
            $factura['numero_control_completo'] ?? null,
            $factura['numero_control'] ?? null,
            $extraido['numero_control_completo'] ?? null,
            $extraido['numero_control'] ?? null,
        ]) ?? '';

        $resuelta['sello_recepcion'] = self::longestNonEmpty([
            $factura['sello_recepcion'] ?? null,
            $extraido['sello_recepcion'] ?? null,
        ]) ?? '';

        if (self::permiteDocumentoRelacionado($resuelta['tipo_dte'])) {
            foreach (['tipo', 'numero', 'codigo', 'fecha'] as $campoRelacionado) {
                $clave = 'documento_relacionado_' . $campoRelacionado;
                $resuelta[$clave] = self::firstNonEmpty([
                    $factura[$clave] ?? null,
                    $extraido[$clave] ?? null,
                ]) ?? '';
            }
        } else {
            foreach (['tipo', 'numero', 'codigo', 'fecha'] as $campoRelacionado) {
                $resuelta['documento_relacionado_' . $campoRelacionado] = '';
            }
        }

        $resuelta['nrc'] = self::firstNonEmpty([
            $factura['nrc'] ?? null,
            $extraido['nrc'] ?? null,
        ]) ?? '';

        $resuelta['nit'] = self::firstNonEmpty([
            $factura['nit'] ?? null,
            $extraido['nit'] ?? null,
        ]) ?? '';

        $resuelta['nombre_proveedor'] = self::firstNonEmpty([
            $factura['nombre_proveedor'] ?? null,
            $extraido['nombre_proveedor'] ?? null,
        ]) ?? '';

        foreach ([
            'ventas_internas',
            'ventas_importacion',
            'ventas_internas_exentas',
            'ventas_importacion_exentas',
            'compras_exentas',
            'compras_gravadas',
            'credito_fiscal',
            'impuestos_calculados',
            'iva_percibido',
            'iva_retenido',
            'fovial_otros',
        ] as $campoNumerico) {
            $valorActual = round((float) ($factura[$campoNumerico] ?? 0), 2);
            $valorExtraido = round((float) ($extraido[$campoNumerico] ?? 0), 2);
            $resuelta[$campoNumerico] = $valorExtraido !== 0.0 || $valorActual === 0.0
                ? $valorExtraido
                : $valorActual;
        }

        $resuelta['compras_exentas'] = round((float) ($resuelta['compras_exentas'] ?? 0), 2);
        if ($resuelta['compras_exentas'] === 0.0) {
            $resuelta['compras_exentas'] = round(
                (float) ($resuelta['ventas_internas_exentas'] ?? 0)
                + (float) ($resuelta['ventas_importacion_exentas'] ?? 0),
                2
            );
        }

        $resuelta['compras_gravadas'] = round((float) ($resuelta['compras_gravadas'] ?? 0), 2);
        if ($resuelta['compras_gravadas'] === 0.0) {
            $resuelta['compras_gravadas'] = round(
                (float) ($resuelta['ventas_internas'] ?? 0)
                + (float) ($resuelta['ventas_importacion'] ?? 0),
                2
            );
        }

        if (round((float) ($resuelta['impuestos_calculados'] ?? 0), 2) === 0.0) {
            $resuelta['impuestos_calculados'] = round((float) ($resuelta['credito_fiscal'] ?? 0), 2);
        }
        $resuelta['credito_fiscal'] = round((float) ($resuelta['impuestos_calculados'] ?? 0), 2);
        $resuelta['total_compras'] = self::calcularTotalCompra($resuelta);
        return $resuelta;
    }

    public static function hydrateFacturas(array $facturas): array {
        return array_map([self::class, 'hydrateFactura'], $facturas);
    }

    public static function resumenLibro(array $facturas): array {
        $totales = [
            'cantidad'                   => count($facturas),
            'ventas_internas'            => 0.0,
            'ventas_importacion'         => 0.0,
            'ventas_internas_exentas'    => 0.0,
            'ventas_importacion_exentas' => 0.0,
            'compras_exentas'            => 0.0,
            'compras_gravadas'           => 0.0,
            'credito_fiscal'             => 0.0,
            'impuestos_calculados'       => 0.0,
            'fovial_otros'               => 0.0,
            'total_compras'              => 0.0,
            'iva_percibido'              => 0.0,
            'iva_retenido'               => 0.0,
        ];

        foreach ($facturas as $factura) {
            if ((int) ($factura['contabilizable'] ?? 1) !== 1 || (int) ($factura['es_repetido'] ?? 0) === 1 || (int) ($factura['fuera_de_periodo'] ?? 0) === 1) {
                continue;
            }
            $totales['ventas_internas'] += self::montoContable($factura, 'ventas_internas');
            $totales['ventas_importacion'] += self::montoContable($factura, 'ventas_importacion');
            $totales['ventas_internas_exentas'] += self::montoContable($factura, 'ventas_internas_exentas');
            $totales['ventas_importacion_exentas'] += self::montoContable($factura, 'ventas_importacion_exentas');
            $totales['compras_exentas'] += self::montoContable($factura, 'compras_exentas');
            $totales['compras_gravadas'] += self::montoContable($factura, 'compras_gravadas');
            $totales['credito_fiscal'] += self::montoContable($factura, 'credito_fiscal');
            $totales['impuestos_calculados'] += self::montoContable($factura, 'impuestos_calculados');
            $totales['fovial_otros'] += self::montoContable($factura, 'fovial_otros');
            $totales['total_compras'] += self::montoContable($factura, 'total_compras');
            $totales['iva_percibido'] += self::montoContable($factura, 'iva_percibido');
            $totales['iva_retenido'] += self::montoContable($factura, 'iva_retenido');
        }

        foreach ($totales as $clave => $valor) {
            if ($clave === 'cantidad') {
                continue;
            }
            $totales[$clave] = round((float) $valor, 2);
        }

        return $totales;
    }

    public static function calcularTotalCompra(array $factura): float {
        $componentes = [
            (float) ($factura['compras_exentas'] ?? 0),
            (float) ($factura['compras_gravadas'] ?? 0),
            (float) ($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? 0),
            (float) ($factura['iva_percibido'] ?? 0),
            (float) ($factura['fovial_otros'] ?? 0),
        ];
        $totalCalculado = round(array_sum($componentes) - (float) ($factura['iva_retenido'] ?? 0), 2);
        $totalDte = round((float) ($factura['total_compras'] ?? 0), 2);
        $baseCompras = round(
            (float) ($factura['compras_exentas'] ?? 0) + (float) ($factura['compras_gravadas'] ?? 0),
            2
        );

        if ($totalDte > 0.0 && $baseCompras === 0.0 && $totalCalculado > 0.0) {
            return $totalDte;
        }

        return $totalCalculado !== 0.0 ? $totalCalculado : $totalDte;
    }

    public static function formatDate(?string $date): string {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : $date;
    }

    public static function formatDecimal($value): string {
        return number_format((float) $value, 2);
    }

    public static function escapeHtml(?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function normalizeDocumentPayload(array $payload): array {
        $normalizado = self::unwrapDocumentPayload($payload);
        return is_array($normalizado) ? $normalizado : $payload;
    }

    public static function expandDocumentPayloads(array $payload): array {
        if (self::looksLikeDocumentList($payload)) {
            return self::normalizeDocumentList($payload);
        }

        $payload = self::normalizeDocumentPayload($payload);

        if (self::looksLikeDocument($payload)) {
            return [$payload];
        }

        if (self::looksLikeDocumentList($payload)) {
            return self::normalizeDocumentList($payload);
        }

        $listKeys = [
            'facturas',
            'documentos',
            'dtes',
            'comprobantes',
            'items',
            'detalle',
            'data',
            'result',
            'resultado',
            'respuesta',
            'lote',
            'loteDte',
        ];

        foreach ($listKeys as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $documentos = self::extractDocumentsFromValue($payload[$key], 1);
            if ($documentos !== []) {
                return $documentos;
            }
        }

        foreach ($payload as $value) {
            $documentos = self::extractDocumentsFromValue($value, 1);
            if ($documentos !== []) {
                return $documentos;
            }
        }

        return [self::normalizeDocumentPayload($payload)];
    }

    private static function extractFirstText(array $payload, array $paths, array $normalizedKeys): ?string {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);
        return self::firstNonEmpty($candidatos);
    }

    private static function normalizeTipoDte(?string $tipoDte): ?string {
        $tipoDte = trim((string) $tipoDte);
        if ($tipoDte === '') {
            return null;
        }

        return ctype_digit($tipoDte) ? str_pad($tipoDte, 2, '0', STR_PAD_LEFT) : $tipoDte;
    }

    private static function aplicarSignoContable(array &$factura, array $campos): void {
        return;
    }

    private static function montoContable(array $factura, string $campo): float {
        return round((float) ($factura[$campo] ?? 0), 2);
    }

    private static function extractDocumentoRelacionado(array $payload): array {
        $relacionado = self::firstRelatedDocument($payload);

        if ($relacionado === []) {
            return self::emptyDocumentoRelacionado();
        }

        $tipo = self::extractFirstText($relacionado, [
            ['tipoDocumento'],
            ['tipoDoc'],
            ['tipoDte'],
        ], ['tipodocumento', 'tipodoc', 'tipodte']);

        return [
            'tipo'   => $tipo ?? '',
            'numero' => self::extractLongestText($relacionado, [
                ['numeroDocumento'],
                ['numeroControl'],
                ['numDocumento'],
            ], ['numerodocumento', 'numerocontrol', 'numdocumento']) ?? '',
            'codigo' => self::extractLongestText($relacionado, [
                ['codigoGeneracion'],
                ['codGeneracion'],
                ['documento'],
            ], ['codigogeneracion', 'codgeneracion']) ?? '',
            'fecha'  => self::normalizeDate(self::extractFirstText($relacionado, [
                ['fechaEmision'],
                ['fecEmi'],
                ['fecha'],
            ], ['fechaemision', 'fecemi', 'fecha'])),
        ];
    }

    private static function emptyDocumentoRelacionado(): array {
        return [
            'tipo'   => '',
            'numero' => '',
            'codigo' => '',
            'fecha'  => '',
        ];
    }

    private static function firstRelatedDocument(array $payload): array {
        $keys = [
            'documentoRelacionado',
            'documentoRelacionado1',
            'documentosRelacionados',
            'docRelacionado',
            'documentoRelacionadoNC',
        ];

        foreach ($keys as $key) {
            $value = self::arrayValue($payload, $key);
            if ($value === []) {
                continue;
            }

            if (self::esLista($value)) {
                foreach ($value as $item) {
                    if (is_array($item) && $item !== []) {
                        return $item;
                    }
                }
                continue;
            }

            return $value;
        }

        return [];
    }

    private static function extractLongestText(array $payload, array $paths, array $normalizedKeys): ?string {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);
        return self::longestNonEmpty($candidatos);
    }

    private static function extractDecimal(array $payload, array $paths, array $normalizedKeys): float {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);

        foreach ($candidatos as $candidato) {
            if ($candidato === null || $candidato === '') {
                continue;
            }

            $normalizado = str_replace([',', ' '], ['', ''], (string) $candidato);
            if (is_numeric($normalizado)) {
                return round((float) $normalizado, 2);
            }
        }

        return 0.0;
    }

    private static function sumCuerpoDocumentoDecimals(array $payload, array $paths, array $normalizedKeys): float {
        $cuerpo = $payload['cuerpoDocumento'] ?? null;
        if (!is_array($cuerpo)) {
            return 0.0;
        }

        if (self::looksLikeLineItem($cuerpo)) {
            $cuerpo = [$cuerpo];
        }

        $total = 0.0;
        foreach ($cuerpo as $item) {
            if (!is_array($item)) {
                continue;
            }

            $total += self::extractDecimal($item, $paths, $normalizedKeys);
        }

        return round($total, 2);
    }

    private static function looksLikeLineItem(array $item): bool {
        foreach (['ventaGravada', 'ventaExenta', 'ventaNoSuj', 'montoGravado', 'montoExento'] as $key) {
            if (array_key_exists($key, $item)) {
                return true;
            }
        }

        return false;
    }

    private static function extractIvaCalculado(array $payload, array $resumen, float $creditoFiscal, float $comprasGravadas, ?string $tipoDte): float {
        if ($tipoDte === '01') {
            return 0.0;
        }

        $tributos = self::extractTributoTotals($payload, $resumen);
        if ($tributos['iva'] > 0) {
            return $tributos['iva'];
        }

        if ($creditoFiscal > 0) {
            return round($creditoFiscal, 2);
        }

        if ((int) ($tributos['cantidad'] ?? 0) > 0) {
            return 0.0;
        }

        return round($comprasGravadas * 0.13, 2);
    }

    private static function extractFovialOtros(array $payload, array $resumen): float {
        $tributos = self::extractTributoTotals($payload, $resumen);
        if ($tributos['otros'] > 0) {
            return $tributos['otros'];
        }

        $explicitos = self::extractDecimal($resumen, [
            ['fovial'],
            ['totalFovial'],
            ['impuestoFovial'],
            ['impuestosCombustibles'],
            ['impuestoCombustible'],
            ['otrosImpuestos'],
            ['totalOtrosImpuestos'],
            ['totalOtrosTributos'],
        ], [
            'fovial',
            'totalfovial',
            'impuestofovial',
            'impuestoscombustibles',
            'impuestocombustible',
            'otrosimpuestos',
            'totalotrosimpuestos',
            'totalotrostributos',
        ]);

        return round($explicitos, 2);
    }

    private static function extractTributoTotals(array $payload, array $resumen): array {
        $items = [];
        $contenedores = [
            $resumen['tributos'] ?? null,
            $resumen['totalTributos'] ?? null,
            $payload['tributos'] ?? null,
            $payload['resumenTributario'] ?? null,
        ];

        foreach ($contenedores as $contenedor) {
            self::collectTributoItems($contenedor, $items);
        }

        $totales = [
            'iva'      => 0.0,
            'otros'    => 0.0,
            'cantidad' => 0,
        ];

        foreach ($items as $item) {
            $valor = self::extractTributoItemValue($item);
            if ($valor === 0.0) {
                continue;
            }

            $totales['cantidad']++;
            $codigo = self::normalizeKey((string) ($item['codigo'] ?? $item['codTributo'] ?? $item['codigoTributo'] ?? ''));
            $descripcion = self::normalizeKey((string) ($item['descripcion'] ?? $item['nombre'] ?? $item['tributo'] ?? ''));

            if (self::isPercepcionRetencionTributo($codigo, $descripcion)) {
                continue;
            }

            if (self::isIvaTributo($codigo, $descripcion)) {
                $totales['iva'] += $valor;
                continue;
            }

            $totales['otros'] += $valor;
        }

        $totales['iva'] = round($totales['iva'], 2);
        $totales['otros'] = round($totales['otros'], 2);

        return $totales;
    }

    private static function collectTributoItems($value, array &$items, int $depth = 0): void {
        if (!is_array($value) || $depth > 4) {
            return;
        }

        if (self::looksLikeTributoItem($value)) {
            $items[] = $value;
            return;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                self::collectTributoItems($item, $items, $depth + 1);
            }
        }
    }

    private static function looksLikeTributoItem(array $value): bool {
        $keys = array_map([self::class, 'normalizeKey'], array_keys($value));
        $hasIdentity = array_intersect($keys, ['codigo', 'codtributo', 'codigotributo', 'descripcion', 'nombre', 'tributo']) !== [];
        $hasValue = array_intersect($keys, ['valor', 'monto', 'valortributo', 'montotributo', 'importe']) !== [];

        return $hasIdentity && $hasValue;
    }

    private static function extractTributoItemValue(array $item): float {
        return self::extractDecimal($item, [
            ['valor'],
            ['monto'],
            ['valorTributo'],
            ['montoTributo'],
            ['importe'],
        ], ['valor', 'monto', 'valortributo', 'montotributo', 'importe']);
    }

    private static function isIvaTributo(string $codigo, string $descripcion): bool {
        if ($codigo === '20') {
            return true;
        }

        return str_contains($descripcion, 'iva')
            || str_contains($descripcion, 'impuestoalvaloragregado');
    }

    private static function isPercepcionRetencionTributo(string $codigo, string $descripcion): bool {
        unset($codigo);

        return str_contains($descripcion, 'percep')
            || str_contains($descripcion, 'perci')
            || str_contains($descripcion, 'retenc')
            || str_contains($descripcion, 'reten');
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
            return $valor !== null && trim((string) $valor) !== '';
        })));
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

    private static function stringifyValue($value): ?string {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return self::nullable($value);
        }

        if (is_array($value)) {
            $leafs = self::collectScalarLeafValues($value);
            if (!empty($leafs)) {
                return self::longestNonEmpty($leafs);
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    private static function unwrapDocumentPayload(array $payload, int $depth = 0): array {
        if ($depth > 8 || $payload === []) {
            return $payload;
        }

        if (self::looksLikeDocumentList($payload)) {
            return $payload;
        }

        if (self::looksLikeDocument($payload)) {
            return $payload;
        }

        $wrapperKeys = [
            'dteJson',
            'dte',
            'documento',
            'documentoFiscal',
            'comprobante',
            'payload',
            'body',
            'data',
            'result',
            'resultado',
            'respuesta',
            'contenido',
            'archivo',
            'item',
        ];

        foreach ($wrapperKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $candidate = self::normalizePossibleDocument($payload[$key], $depth + 1);
            if (is_array($candidate) && (self::looksLikeDocument($candidate) || self::looksLikeDocumentList($candidate))) {
                return $candidate;
            }
        }

        foreach ($payload as $value) {
            $candidate = self::normalizePossibleDocument($value, $depth + 1);
            if (is_array($candidate) && (self::looksLikeDocument($candidate) || self::looksLikeDocumentList($candidate))) {
                return $candidate;
            }
        }

        return $payload;
    }

    private static function normalizePossibleDocument($value, int $depth) {
        if ($depth > 8) {
            return is_array($value) ? $value : null;
        }

        if (is_string($value)) {
            $decoded = self::decodeJsonText($value);
            if (is_array($decoded)) {
                return self::unwrapDocumentPayload($decoded, $depth + 1);
            }
            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        if (self::esLista($value)) {
            $documentos = [];

            foreach ($value as $item) {
                $candidate = self::normalizePossibleDocument($item, $depth + 1);

                if (!is_array($candidate)) {
                    continue;
                }

                if (self::looksLikeDocument($candidate)) {
                    $documentos[] = self::normalizeDocumentPayload($candidate);
                    continue;
                }

                if (self::looksLikeDocumentList($candidate)) {
                    $documentos = array_merge($documentos, self::normalizeDocumentList($candidate));
                }
            }

            if ($documentos !== []) {
                return $documentos;
            }

            return $value;
        }

        return self::unwrapDocumentPayload($value, $depth + 1);
    }

    private static function looksLikeDocument(array $payload): bool {
        if ($payload === []) {
            return false;
        }

        $sectionKeys = [
            'identificacion',
            'resumen',
            'emisor',
            'receptor',
            'sujetoExcluido',
            'cuerpoDocumento',
            'documentoRelacionado',
            'otrosDocumentos',
            'ventaTercero',
            'extension',
            'resumenTributario',
            'respuestaMH',
            'recepcion',
            'procesamiento',
        ];

        foreach ($sectionKeys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        $coreKeys = [
            'codigoGeneracion',
            'tipoDte',
            'numeroControl',
            'fecEmi',
            'fechaEmision',
            'selloRecepcion',
            'selloRecibido',
            'ambiente',
            'tipoMoneda',
        ];
        $partyKeys = [
            'nit',
            'dui',
            'nrc',
            'nombre',
            'nombreComercial',
        ];
        $amountKeys = [
            'totalPagar',
            'totalCompras',
            'montoTotalOperacion',
            'totalExenta',
            'totalGravada',
            'subTotalVentas',
        ];

        $coreCount   = self::countPresentKeys($payload, $coreKeys);
        $partyCount  = self::countPresentKeys($payload, $partyKeys);
        $amountCount = self::countPresentKeys($payload, $amountKeys);

        return $coreCount >= 3 && ($partyCount >= 1 || $amountCount >= 1);
    }

    private static function looksLikeDocumentList(array $payload): bool {
        if (!self::esLista($payload)) {
            return false;
        }

        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalizado = self::normalizeDocumentPayload($item);
            if (self::looksLikeDocument($normalizado) || self::looksLikeDocumentList($normalizado)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeDocumentList(array $payload): array {
        $documentos = [];

        foreach ($payload as $item) {
            if (is_string($item)) {
                $item = self::decodeJsonText($item);
            }

            if (!is_array($item)) {
                continue;
            }

            $normalizado = self::normalizeDocumentPayload($item);

            if (self::looksLikeDocumentList($normalizado)) {
                $documentos = array_merge($documentos, self::normalizeDocumentList($normalizado));
                continue;
            }

            if (self::looksLikeDocument($normalizado)) {
                $documentos[] = $normalizado;
                continue;
            }

            $anidados = self::expandDocumentPayloadsFromNested($normalizado, 1);
            if ($anidados !== []) {
                $documentos = array_merge($documentos, $anidados);
            }
        }

        return $documentos;
    }

    private static function extractDocumentsFromValue($value, int $depth): array {
        if ($depth > 8) {
            return [];
        }

        if (is_string($value)) {
            $value = self::decodeJsonText($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $value = self::normalizeDocumentPayload($value);

        if (self::looksLikeDocument($value)) {
            return [self::normalizeDocumentPayload($value)];
        }

        if (self::looksLikeDocumentList($value)) {
            return self::normalizeDocumentList($value);
        }

        return self::expandDocumentPayloadsFromNested($value, $depth + 1);
    }

    private static function expandDocumentPayloadsFromNested(array $payload, int $depth): array {
        if ($depth > 8) {
            return [];
        }

        $listKeys = [
            'facturas',
            'documentos',
            'dtes',
            'comprobantes',
            'items',
            'detalle',
            'data',
            'result',
            'resultado',
            'respuesta',
            'lote',
            'loteDte',
        ];

        foreach ($listKeys as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $documentos = self::extractDocumentsFromValue($payload[$key], $depth + 1);
            if ($documentos !== []) {
                return $documentos;
            }
        }

        foreach ($payload as $value) {
            $documentos = self::extractDocumentsFromValue($value, $depth + 1);
            if ($documentos !== []) {
                return $documentos;
            }
        }

        return [];
    }

    public static function decodeJsonText(string $text): ?array {
        $text = self::normalizeJsonText($text);
        if ($text === '') {
            return null;
        }

        $firstChar = substr($text, 0, 1);
        if ($firstChar !== '{' && $firstChar !== '[' && $firstChar !== '"') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($decoded)) {
            $decodedNested = json_decode($decoded, true);
            return is_array($decodedNested) ? $decodedNested : null;
        }

        return null;
    }

    private static function normalizeJsonText(string $text): string {
        $text = self::convertJsonTextToUtf8($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        return trim($text);
    }

    private static function convertJsonTextToUtf8(string $text): string {
        if ($text === '') {
            return '';
        }

        $bomEncodings = [
            "\xFF\xFE\x00\x00" => 'UTF-32LE',
            "\x00\x00\xFE\xFF" => 'UTF-32BE',
            "\xFF\xFE"         => 'UTF-16LE',
            "\xFE\xFF"         => 'UTF-16BE',
        ];

        foreach ($bomEncodings as $bom => $encoding) {
            if (strncmp($text, $bom, strlen($bom)) === 0) {
                $converted = @mb_convert_encoding(substr($text, strlen($bom)), 'UTF-8', $encoding);
                return is_string($converted) ? $converted : $text;
            }
        }

        if (!function_exists('mb_detect_encoding') || !function_exists('mb_convert_encoding')) {
            return $text;
        }

        $encoding = @mb_detect_encoding($text, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-32LE', 'UTF-32BE', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding === false || $encoding === 'UTF-8') {
            return $text;
        }

        $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
        return is_string($converted) ? $converted : $text;
    }

    private static function normalizeKey(string $key): string {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
    }

    private static function countPresentKeys(array $payload, array $keys): int {
        $count = 0;

        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_array($value)) {
                if ($value !== []) {
                    $count++;
                }
                continue;
            }

            if (self::nullable($value) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private static function arrayValue(array $payload, string $key): array {
        $value = $payload[$key] ?? [];
        return is_array($value) ? $value : [];
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

    private static function longestNonEmpty(array $values): ?string {
        $mejor = null;

        foreach ($values as $value) {
            $value = self::nullable($value);
            if ($value === null) {
                continue;
            }

            $lengthValue = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            $lengthBest  = $mejor !== null ? (function_exists('mb_strlen') ? mb_strlen($mejor) : strlen($mejor)) : -1;

            if ($mejor === null || $lengthValue > $lengthBest) {
                $mejor = $value;
            }
        }

        return $mejor;
    }

    private static function normalizeDate(?string $date): string {
        $date = trim((string) $date);
        if ($date === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private static function esLista(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private static function collectScalarLeafValues(array $value): array {
        $result = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $result = array_merge($result, self::collectScalarLeafValues($item));
                continue;
            }

            if (is_scalar($item)) {
                $texto = self::nullable($item);
                if ($texto !== null) {
                    $result[] = $texto;
                }
            }
        }

        return array_values(array_unique($result));
    }
}
