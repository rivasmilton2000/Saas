<?php

class DteDataService {

    public static function extractDocumentoData(array $payload): array {
        $identificacion = self::arrayValue($payload, 'identificacion');
        $emisor         = self::arrayValue($payload, 'emisor');
        $receptor       = self::arrayValue($payload, 'receptor');
        $sujeto         = self::arrayValue($payload, 'sujetoExcluido');
        $resumen        = self::arrayValue($payload, 'resumen');
        $proveedor      = $emisor !== [] ? $emisor : ($receptor !== [] ? $receptor : $sujeto);

        return [
            'codigo_generacion'          => self::extractFirstText($payload, [
                ['codigoGeneracion'],
                ['identificacion', 'codigoGeneracion'],
                ['respuestaMH', 'codigoGeneracion'],
            ], ['codigogeneracion']),
            'tipo_dte'                   => self::extractFirstText($payload, [
                ['tipoDte'],
                ['identificacion', 'tipoDte'],
            ], ['tipodte']),
            'sello_recepcion'            => self::extractLongestText($payload, [
                ['selloRecepcion'],
                ['identificacion', 'selloRecepcion'],
                ['identificacion', 'selloRecibido'],
                ['respuestaMH', 'selloRecepcion'],
                ['respuestaMH', 'selloRecibido'],
                ['recepcion', 'selloRecepcion'],
                ['recepcion', 'selloRecibido'],
            ], ['sellorecepcion', 'sellorecibido', 'sellorecepcionmh', 'sellorecibidomh']),
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
            'ventas_internas'            => self::extractDecimal($resumen, [
                ['totalGravada'],
                ['subTotalVentas'],
                ['comprasGravadas'],
                ['gravadasLocales'],
            ], ['totalgravada', 'subtotalventas', 'comprasgravadas', 'gravadaslocales']),
            'ventas_importacion'         => self::extractDecimal($resumen, [
                ['importacion'],
                ['totalImportaciones'],
                ['comprasImportacion'],
                ['gravadaImportacion'],
            ], ['importacion', 'totalimportaciones', 'comprasimportacion', 'gravadaimportacion']),
            'ventas_internas_exentas'    => self::extractDecimal($resumen, [
                ['totalExenta'],
                ['exenta'],
                ['comprasExentas'],
            ], ['totalexenta', 'exenta', 'comprasexentas']),
            'ventas_importacion_exentas' => self::extractDecimal($resumen, [
                ['totalNoSuj'],
                ['noSujeta'],
                ['importacionExenta'],
            ], ['totalnosuj', 'nosujeta', 'importacionexenta']),
            'credito_fiscal'             => self::extractDecimal($resumen, [
                ['creditoFiscal'],
                ['totalIva'],
                ['ivaTotal'],
            ], ['creditofiscal', 'totaliva', 'ivatotal']),
            'total_compras'              => self::extractDecimal($resumen, [
                ['totalCompras'],
                ['montoTotalOperacion'],
                ['totalPagar'],
                ['totalOperacion'],
            ], ['totalcompras', 'montototaloperacion', 'totalpagar', 'totaloperacion']),
            'iva_percibido'              => self::extractDecimal($resumen, [
                ['ivaPercibido1'],
                ['ivaPerci1'],
                ['ivaPercibido'],
            ], ['ivapercibido1', 'ivaperci1', 'ivapercibido']),
            'iva_retenido'               => self::extractDecimal($resumen, [
                ['ivaRetenido1'],
                ['ivaRete1'],
                ['ivaRetenido'],
            ], ['ivaretenido1', 'ivarete1', 'ivaretenido']),
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
            'credito_fiscal'             => 0.0,
            'total_compras'              => 0.0,
            'iva_percibido'              => 0.0,
            'iva_retenido'               => 0.0,
        ];

        foreach ($facturas as $factura) {
            $totales['ventas_internas'] += (float) ($factura['ventas_internas'] ?? 0);
            $totales['ventas_importacion'] += (float) ($factura['ventas_importacion'] ?? 0);
            $totales['ventas_internas_exentas'] += (float) ($factura['ventas_internas_exentas'] ?? 0);
            $totales['ventas_importacion_exentas'] += (float) ($factura['ventas_importacion_exentas'] ?? 0);
            $totales['credito_fiscal'] += (float) ($factura['credito_fiscal'] ?? 0);
            $totales['total_compras'] += (float) ($factura['total_compras'] ?? 0);
            $totales['iva_percibido'] += (float) ($factura['iva_percibido'] ?? 0);
            $totales['iva_retenido'] += (float) ($factura['iva_retenido'] ?? 0);
        }

        foreach ($totales as $clave => $valor) {
            if ($clave === 'cantidad') {
                continue;
            }
            $totales[$clave] = round((float) $valor, 2);
        }

        return $totales;
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

    private static function extractFirstText(array $payload, array $paths, array $normalizedKeys): ?string {
        $candidatos = self::collectCandidates($payload, $paths, $normalizedKeys);
        return self::firstNonEmpty($candidatos);
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

    private static function normalizeKey(string $key): string {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
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
}
