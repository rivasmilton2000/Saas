<?php
require_once __DIR__ . '/DteDataService.php';

class IvaWorkbookExportService {

    private const ANEXO_NUMBERS = [
        'ventas_contribuyente' => '1',
        'ventas_consumidor'    => '2',
        'compras'              => '3',
    ];

    private const DEFAULTS = [
        'ventas_tipo_operacion'  => '01 Gravada',
        'ventas_tipo_ingreso'    => '02 Actividades de Servicios',
        'compras_tipo_operacion' => '1 Gravada',
        'compras_clasificacion'  => '2 Gasto',
        'compras_sector'         => '4 Servicios, Profesiones, Artes y Oficios',
        'compras_tipo_costo'     => '1 Gasto de Venta sin Donacion',
    ];

    public static function soportaPlantilla(array $libro): bool {
        $tipo = (string) ($libro['tipo'] ?? '');
        return array_key_exists($tipo, self::ANEXO_NUMBERS);
    }

    public static function descargarCsvLibro(string $nombreArchivo, array $libro, array $facturas): void {
        $facturas = self::prepareFacturas($facturas);
        [$headers, $rows] = self::buildLibroRows($libro, $facturas);
        self::descargarCsv($nombreArchivo, $headers, $rows);
    }

    public static function descargarExcelLibro(string $nombreArchivo, array $libro, array $facturas): void {
        $facturas = self::prepareFacturas($facturas);
        [$headers, $rows] = self::buildLibroRows($libro, $facturas, true);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xls"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo "\xEF\xBB\xBF";
        echo self::renderExcelHtml($libro, $headers, $rows);
    }

    public static function descargarPdfLibro(string $nombreArchivo, array $libro, array $facturas): void {
        $facturas = self::prepareFacturas($facturas);
        [$headers, $rows] = self::buildLibroRows($libro, $facturas);

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '_pdf.html"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo self::renderPrintHtml($libro, $headers, $rows);
    }

    public static function descargarCsvAnexo(string $nombreArchivo, array $libro, array $facturas): void {
        $tipo = (string) ($libro['tipo'] ?? '');
        $facturas = self::prepareFacturas($facturas);

        if (!self::soportaPlantilla($libro)) {
            throw new InvalidArgumentException('El libro no tiene una plantilla IVA asociada.');
        }

        $headers = [];
        $rows = [];

        if ($tipo === 'compras') {
            [$headers, $rows] = self::buildAnexoComprasRows($facturas);
        } elseif ($tipo === 'ventas_consumidor') {
            [$headers, $rows] = self::buildAnexoConsumidorRows($facturas);
        } elseif ($tipo === 'ventas_contribuyente') {
            [$headers, $rows] = self::buildAnexoContribuyenteRows($facturas);
        }

        self::descargarCsv($nombreArchivo, $headers, $rows);
    }

    private static function buildLibroRows(array $libro, array $facturas, bool $visualExcel = false): array {
        $tipo = (string) ($libro['tipo'] ?? '');

        if (!self::soportaPlantilla($libro)) {
            throw new InvalidArgumentException('El libro no tiene una plantilla IVA asociada.');
        }

        if ($tipo === 'compras') {
            return self::buildLibroComprasRows($libro, $facturas, $visualExcel);
        }

        if ($tipo === 'ventas_consumidor') {
            return self::buildLibroConsumidorRows($facturas);
        }

        if ($tipo === 'ventas_contribuyente') {
            return self::buildLibroContribuyenteRows($facturas);
        }

        return [[], []];
    }

    private static function buildLibroComprasRows(array $libro, array $facturas, bool $visualExcel = false): array {
        $meses = [
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
            9 => 'SEPTIEMBRE',
            10 => 'OCTUBRE',
            11 => 'NOVIEMBRE',
            12 => 'DICIEMBRE',
        ];
        $mes = $meses[(int) ($libro['mes'] ?? 0)] ?? (string) ($libro['mes'] ?? '');

        $headers = [
            ['REGISTRO No', '', (string) ($libro['empresa_nrc'] ?? ''), '', '', '', '', '', '', '', '', (string) ($libro['empresa_nombre'] ?? ''), '', '', '', '', '', '', ''],
            ['MES:', $mes, 'ANO', (string) ($libro['anio'] ?? ''), '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['#', 'FECHA', 'TIPO DE DOCUMENTO', 'Numero de', 'Sello de', 'Codigo de', 'NIT O DUI', 'NOMBRE DEL PROVEEDOR', 'COMPRAS EXENTAS', 'COMPRAS GRAVADAS', 'IVA / IMPUESTOS', 'IVA PERCIBIDO', 'IVA RETENIDO', 'FOVIAL / OTROS', 'TOTAL DE COMPRAS', 'COMPRAS A SUJETOS EXCLUIDOS', 'N CONTROL RELACIONADO', 'CODIGO RELACIONADO', 'FECHA RELACIONADA'],
            ['CORR', 'DE EMISION', '', 'control', 'recepcion', 'generacion', 'DE SUJETOS', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', 'EXCLUIDO(A)', '', '', '', '', '', '', '', '', '', '', '', ''],
        ];

        $rows = [];
        $totales = [
            'compras_exentas'       => 0.0,
            'compras_gravadas'      => 0.0,
            'impuestos_calculados'  => 0.0,
            'iva_percibido'         => 0.0,
            'iva_retenido'          => 0.0,
            'fovial_otros'          => 0.0,
            'total_compras'         => 0.0,
        ];

        foreach ($facturas as $index => $factura) {
            $factura = DteDataService::hydrateFactura($factura);
            $payload = self::decodePayload($factura['raw_json'] ?? null);
            $emisor = self::arrayValue($payload, 'emisor');
            $permiteRelacionado = DteDataService::permiteDocumentoRelacionado($factura['tipo_dte'] ?? '');

            foreach ($totales as $campo => $valor) {
                $totales[$campo] = $valor + (float) ($factura[$campo] ?? 0);
            }

            $rows[] = [
                $index + 1,
                self::formatDate($factura['fecha'] ?? null),
                self::tipoDocumentoVisualCompra($factura),
                (string) ($factura['numero_control'] ?? ''),
                (string) ($factura['sello_recepcion'] ?? ''),
                (string) ($factura['codigo_generacion'] ?? ''),
                self::firstNonEmpty([
                    $factura['nit'] ?? null,
                    $emisor['dui'] ?? null,
                ]),
                (string) ($factura['nombre_proveedor'] ?? ''),
                self::formatNumber($factura['compras_exentas'] ?? 0),
                self::formatNumber($factura['compras_gravadas'] ?? 0),
                self::formatNumber($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? 0),
                self::formatNumber($factura['iva_percibido'] ?? 0),
                self::formatNumber($factura['iva_retenido'] ?? 0),
                self::formatNumber($factura['fovial_otros'] ?? 0),
                self::formatNumber($factura['total_compras'] ?? 0),
                self::formatNumber(0),
                $permiteRelacionado ? (string) ($factura['documento_relacionado_numero'] ?? '') : '',
                $permiteRelacionado ? (string) ($factura['documento_relacionado_codigo'] ?? '') : '',
                $permiteRelacionado ? self::formatDate($factura['documento_relacionado_fecha'] ?? null) : '',
            ];
        }

        if ($rows !== []) {
            $rows[] = [
                '',
                'TOTALES',
                '',
                '',
                '',
                '',
                '',
                '',
                self::formatNumber($totales['compras_exentas']),
                self::formatNumber($totales['compras_gravadas']),
                self::formatNumber($totales['impuestos_calculados']),
                self::formatNumber($totales['iva_percibido']),
                self::formatNumber($totales['iva_retenido']),
                self::formatNumber($totales['fovial_otros']),
                self::formatNumber($totales['total_compras']),
                self::formatNumber(0),
                '',
                '',
                '',
            ];
        }

        if ($visualExcel) {
            return [
                self::quitarColumnasRelacionadasCompra($headers),
                self::quitarColumnasRelacionadasCompra($rows),
            ];
        }

        return [$headers, $rows];
    }

    private static function tipoDocumentoVisualCompra(array $factura): string {
        $tipo = trim((string) ($factura['tipo_dte'] ?? $factura['tipoDte'] ?? ''));
        if (ctype_digit($tipo)) {
            $tipo = str_pad($tipo, 2, '0', STR_PAD_LEFT);
        }

        return $tipo === '05' ? 'Nota de Crédito' : '';
    }

    private static function quitarColumnasRelacionadasCompra(array $rows): array {
        return array_map(static function (array $row): array {
            return array_slice($row, 0, 16);
        }, $rows);
    }

    private static function buildLibroConsumidorRows(array $facturas): array {
        $headers = [
            'DIA',
            'DOCUMENTO EMITIDO (DEL)',
            'DOCUMENTO EMITIDO (AL)',
            'NRO DE CAJA O SISTEMA COMPUTARIZADO',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS GRAVADAS',
            'EXPORTACIONES',
            'TOTAL DE VENTAS DIARIAS PROPIAS',
            'VENTAS A CUENTAS DE TERCEROS',
        ];

        $rows = [];
        foreach ($facturas as $factura) {
            $fecha = self::firstNonEmpty([
                $factura['dia_emision'] ?? null,
                $factura['fecha'] ?? null,
            ]);

            $rows[] = [
                self::formatDayOfMonth($fecha),
                (string) ($factura['del_numero'] ?? ''),
                (string) ($factura['al_numero'] ?? ''),
                '',
                self::formatNumber($factura['ventas_exentas'] ?? 0),
                self::formatNumber($factura['ventas_internas_gravadas'] ?? 0),
                self::formatNumber($factura['exportaciones'] ?? 0),
                self::formatNumber($factura['total_ventas_diarias_propias'] ?? 0),
                self::formatNumber($factura['ventas_cuenta_terceros'] ?? 0),
            ];
        }

        return [$headers, $rows];
    }

    private static function buildLibroContribuyenteRows(array $facturas): array {
        $headers = [
            'NRO',
            'FECHA DE EMISION DEL DOCUMENTO',
            'TIPO DE DOCUMENTO',
            'NUMERO DE CORRELATIVO PREEIMPRESO',
            'NUMERO DE CONTROL',
            'CODIGO DE GENERACION',
            'SELLO DE RECEPCION',
            'NOMBRE DEL CLIENTE MANDANTE O MANDATARIO',
            'NRC DEL CLIENTE',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS GRAVADAS',
            'DEBITO FISCAL',
            'VENTAS EXENTAS A CUENTA DE TERCEROS',
            'VENTAS INTERNAS GRAVADAS A CUENTA DE TERCEROS',
            'DEBITO FISCAL POR CUENTA DE TERCEROS',
            'IVA PERCIBIDO',
            'IVA RETENIDO',
            'TOTAL',
        ];

        $rows = [];
        foreach ($facturas as $index => $factura) {
            $factura = self::hydrateVentaContribuyenteExport($factura);
            $rows[] = [
                $index + 1,
                self::formatDate($factura['fecha'] ?? null),
                (string) ($factura['tipo_documento_nombre'] ?? ''),
                (string) ($factura['numero_control_preimpreso'] ?? ''),
                (string) ($factura['numero_control_interno'] ?? $factura['numero_control'] ?? ''),
                (string) ($factura['codigo_generacion'] ?? ''),
                (string) ($factura['sello_recepcion'] ?? ''),
                (string) ($factura['nombre_cliente'] ?? ''),
                (string) ($factura['nrc_cliente'] ?? ''),
                self::formatNumber($factura['ventas_exentas_contribuyente'] ?? 0),
                self::formatNumber($factura['ventas_internas_gravadas_contribuyente'] ?? 0),
                self::formatNumber($factura['debito_fiscal_contribuyente'] ?? 0),
                self::formatNumber($factura['ventas_exentas'] ?? 0),
                self::formatNumber($factura['ventas_internas_gravadas'] ?? 0),
                self::formatNumber($factura['debito_fiscal'] ?? 0),
                self::formatNumber($factura['iva_percibido'] ?? 0),
                self::formatNumber($factura['iva_retenido'] ?? 0),
                self::formatNumber($factura['ventas_totales'] ?? 0),
            ];
        }

        return [$headers, $rows];
    }

    private static function buildAnexoComprasRows(array $facturas): array {
        $headers = [
            'FECHA DE EMISION DEL DOCUMENTO',
            'CLASE DE DOCUMENTO',
            'TIPO DE DOCUMENTO',
            'NUMERO DE DOCUMENTO',
            'NIT O NRC DEL PROVEEDOR',
            'NOMBRE DEL PROVEEDOR',
            'COMPRAS INTERNAS EXENTAS',
            'INTERNACIONES EXENTAS Y/O NO SUJETAS',
            'IMPORTACIONES EXENTAS Y/O NO SUJETAS',
            'COMPRAS INTERNAS GRAVADAS',
            'INTERNACIONES GRAVADAS DE BIENES',
            'IMPORTACIONES GRAVADAS DE BIENES',
            'IMPORTACIONES GRAVADAS DE SERVICIOS',
            'CREDITO FISCAL',
            'TOTAL DE COMPRAS',
            'DUI DEL PROVEEDOR',
            'TIPO DE OPERACION (Renta)',
            'CLASIFICACION (Renta)',
            'SECTOR (Renta)',
            'TIPO DE COSTO/GASTO (Renta)',
            'NUMERO DEL ANEXO',
        ];

        $rows = [];
        foreach ($facturas as $factura) {
            $payload = self::decodePayload($factura['raw_json'] ?? null);
            $extraido = DteDataService::extractDocumentoData($payload);
            $emisor = self::arrayValue($payload, 'emisor');

            $rows[] = [
                self::formatDate($factura['fecha'] ?? $extraido['fecha'] ?? null),
                self::documentClassText($payload, false),
                self::tipoDocumentoText((string) ($factura['tipo_dte'] ?? $extraido['tipo_dte'] ?? '')),
                (string) self::firstNonEmpty([
                    $factura['codigo_generacion'] ?? null,
                    $extraido['codigo_generacion'] ?? null,
                    $factura['numero_control'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['nit'] ?? null,
                    $emisor['nit'] ?? null,
                    $factura['nrc'] ?? null,
                    $emisor['nrc'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['nombre_proveedor'] ?? null,
                    $emisor['nombre'] ?? null,
                ]),
                self::formatNumber($factura['ventas_internas_exentas'] ?? 0),
                self::formatNumber(0),
                self::formatNumber($factura['ventas_importacion_exentas'] ?? 0),
                self::formatNumber($factura['ventas_internas'] ?? 0),
                self::formatNumber(0),
                self::formatNumber($factura['ventas_importacion'] ?? 0),
                self::formatNumber(0),
                self::formatNumber($factura['impuestos_calculados'] ?? $factura['credito_fiscal'] ?? 0),
                self::formatNumber($factura['total_compras'] ?? 0),
                (string) ($emisor['dui'] ?? ''),
                self::DEFAULTS['compras_tipo_operacion'],
                self::DEFAULTS['compras_clasificacion'],
                self::DEFAULTS['compras_sector'],
                self::DEFAULTS['compras_tipo_costo'],
                self::ANEXO_NUMBERS['compras'],
            ];
        }

        return [$headers, $rows];
    }

    private static function buildAnexoConsumidorRows(array $facturas): array {
        $headers = [
            'FECHA DE EMISION',
            'CLASE DE DOCUMENTO',
            'TIPO DE DOCUMENTO',
            'NUMERO DE RESOLUCION',
            'SERIE DEL DOCUMENTO',
            'NUMERO DE CONTROL INTERNO DEL',
            'NUMERO DE CONTROL INTERNO AL',
            'NUMERO DE DOCUMENTO (DEL)',
            'NUMERO DE DOCUMENTO (AL)',
            'NUMERO DE MAQUINA REGISTRADORA',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS EXENTAS NO SUJETAS A PROPORCIONALIDAD',
            'VENTAS NO SUJETAS',
            'VENTAS GRAVADAS LOCALES',
            'EXPORTACIONES DENTRO DEL AREA DE CENTROAMERICA',
            'EXPORTACIONES FUERA DEL AREA DE CENTROAMERICA',
            'EXPORTACIONES DE SERVICIO',
            'VENTAS A ZONAS FRANCAS Y DPA (TASA CERO)',
            'VENTAS A CUENTA DE TERCEROS NO DOMICILIADOS',
            'TOTAL DE VENTAS',
            'TIPO DE OPERACION (RENTA)',
            'TIPO DE INGRESO (RENTA)',
            'NUMERO DEL ANEXO',
        ];

        $rows = [];
        foreach ($facturas as $index => $factura) {
            $payload = self::decodePayload($factura['raw_json'] ?? null);
            $extraido = DteDataService::extractDocumentoData($payload);
            $controlInterno = $index + 1;
            $exportaciones = (float) ($factura['exportaciones'] ?? 0);
            $ventasTerceros = (float) ($factura['ventas_cuenta_terceros'] ?? 0);
            $totalVentas = round(
                (float) ($factura['ventas_exentas'] ?? 0)
                + (float) ($factura['ventas_internas_gravadas'] ?? 0)
                + $exportaciones
                + $ventasTerceros,
                2
            );

            $rows[] = [
                self::formatDate($factura['fecha'] ?? $extraido['fecha'] ?? null),
                self::documentClassText($payload, true),
                self::tipoDocumentoText((string) ($factura['tipo_dte'] ?? $extraido['tipo_dte'] ?? '')),
                (string) self::firstNonEmpty([
                    $factura['numero_control_completo'] ?? null,
                    $factura['numero_control'] ?? null,
                    $extraido['numero_control_completo'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['sello_recepcion'] ?? null,
                    $extraido['sello_recepcion'] ?? null,
                ]),
                (string) $controlInterno,
                (string) $controlInterno,
                (string) self::firstNonEmpty([
                    $factura['codigo_generacion_desde'] ?? null,
                    $factura['codigo_generacion'] ?? null,
                    $extraido['codigo_generacion'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['codigo_generacion_hasta'] ?? null,
                    $factura['codigo_generacion'] ?? null,
                    $extraido['codigo_generacion'] ?? null,
                ]),
                '',
                self::formatNumber($factura['ventas_exentas'] ?? 0),
                self::formatNumber(0),
                self::formatNumber(0),
                self::formatNumber($factura['ventas_internas_gravadas'] ?? 0),
                self::formatNumber(0),
                self::formatNumber(0),
                self::formatNumber($exportaciones),
                self::formatNumber(0),
                self::formatNumber($ventasTerceros),
                self::formatNumber($totalVentas),
                self::DEFAULTS['ventas_tipo_operacion'],
                self::DEFAULTS['ventas_tipo_ingreso'],
                self::ANEXO_NUMBERS['ventas_consumidor'],
            ];
        }

        return [$headers, $rows];
    }

    private static function buildAnexoContribuyenteRows(array $facturas): array {
        $headers = [
            'FECHA DE EMISION DEL DOCUMENTO',
            'CLASE DE DOCUMENTO',
            'TIPO DE DOCUMENTO',
            'NUMERO DE RESOLUCION',
            'SERIE DEL DOCUMENTO',
            'NUMERO DE DOCUMENTO',
            'NUMERO DE CONTROL INTERNO',
            'NIT O NRC DEL CLIENTE',
            'NOMBRE RAZON SOCIAL O DENOMINACION',
            'VENTAS EXENTAS',
            'VENTAS NO SUJETAS',
            'VENTAS GRAVADAS LOCALES',
            'DEBITO FISCAL',
            'VENTAS A CUENTA DE TERCEROS NO DOMICILIADOS',
            'DEBITO FISCAL POR VENTAS A CUENTA DE TERCEROS',
            'TOTAL DE VENTAS',
            'NUMERO DE DUI DEL CLIENTE',
            'TIPO DE OPERACION (Renta)',
            'TIPO DE INGRESO (Renta)',
            'NUMERO DEL ANEXO',
        ];

        $rows = [];
        foreach ($facturas as $index => $factura) {
            $payload = self::decodePayload($factura['raw_json'] ?? null);
            $extraido = DteDataService::extractDocumentoData($payload);
            $receptor = self::arrayValue($payload, 'receptor');
            $destinatario = self::arrayValue($payload, 'destinatario');

            $rows[] = [
                self::formatDate($factura['fecha'] ?? $extraido['fecha'] ?? null),
                self::documentClassText($payload, false),
                self::tipoDocumentoText((string) ($factura['tipo_dte'] ?? $extraido['tipo_dte'] ?? '')),
                (string) self::firstNonEmpty([
                    $factura['sello_recepcion'] ?? null,
                    $extraido['sello_recepcion'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['numero_control_completo'] ?? null,
                    $factura['numero_control'] ?? null,
                    $extraido['numero_control_completo'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['codigo_generacion'] ?? null,
                    $extraido['codigo_generacion'] ?? null,
                ]),
                (string) ($index + 1),
                (string) self::firstNonEmpty([
                    $receptor['nit'] ?? null,
                    $factura['nrc_cliente'] ?? null,
                    $receptor['nrc'] ?? null,
                    $destinatario['nit'] ?? null,
                    $destinatario['nrc'] ?? null,
                ]),
                (string) self::firstNonEmpty([
                    $factura['nombre_cliente'] ?? null,
                    $receptor['nombre'] ?? null,
                    $destinatario['nombre'] ?? null,
                ]),
                self::formatNumber($factura['ventas_exentas_contribuyente'] ?? 0),
                self::formatNumber(0),
                self::formatNumber($factura['ventas_internas_gravadas_contribuyente'] ?? 0),
                self::formatNumber($factura['debito_fiscal_contribuyente'] ?? 0),
                self::formatNumber($factura['ventas_exentas'] ?? 0),
                self::formatNumber($factura['debito_fiscal'] ?? 0),
                self::formatNumber($factura['ventas_totales'] ?? 0),
                (string) self::firstNonEmpty([
                    $receptor['dui'] ?? null,
                    $destinatario['dui'] ?? null,
                ]),
                self::DEFAULTS['ventas_tipo_operacion'],
                self::DEFAULTS['ventas_tipo_ingreso'],
                self::ANEXO_NUMBERS['ventas_contribuyente'],
            ];
        }

        return [$headers, $rows];
    }

    private static function descargarCsv(string $nombreArchivo, array $headers, array $rows): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $salida = fopen('php://output', 'wb');
        fwrite($salida, "\xEF\xBB\xBF");

        $headerRows = isset($headers[0]) && is_array($headers[0]) ? $headers : [$headers];
        $expectedColumns = 0;

        foreach ($headerRows as $headerRow) {
            $expectedColumns = max($expectedColumns, count($headerRow));
            fputcsv($salida, self::normalizeCsvRow($headerRow, $expectedColumns), ';', '"', '');
        }

        foreach ($rows as $row) {
            $expectedColumns = max($expectedColumns, count($row));
            fputcsv($salida, self::normalizeCsvRow($row, $expectedColumns), ';', '"', '');
        }

        fclose($salida);
    }

    private static function renderExcelHtml(array $libro, array $headers, array $rows): string {
        $headerRows = isset($headers[0]) && is_array($headers[0]) ? $headers : [$headers];
        $expectedColumns = 0;

        foreach ($headerRows as $headerRow) {
            $expectedColumns = max($expectedColumns, count($headerRow));
        }

        foreach ($rows as $row) {
            $expectedColumns = max($expectedColumns, count($row));
        }

        $headerRows = array_map(static function (array $row) use ($expectedColumns): array {
            return self::normalizeCsvRow($row, $expectedColumns);
        }, $headerRows);

        $rows = array_map(static function (array $row) use ($expectedColumns): array {
            return self::normalizeCsvRow($row, $expectedColumns);
        }, $rows);

        $tipo = (string) ($libro['tipo'] ?? '');
        $titulo = self::excelTitleFor($libro);
        $subtitulo = self::excelSubtitleFor($libro);
        $tabla = self::renderExcelTable($headerRows, $rows, $expectedColumns, (string) ($libro['tipo'] ?? ''));
        $encabezado = '';

        if ($tipo !== 'compras') {
            $encabezado = '<div class="sheet-title">' . DteDataService::escapeHtml($titulo) . '</div>'
                . '<div class="sheet-subtitle">' . DteDataService::escapeHtml($subtitulo) . '</div>';
        }

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;background:#fff;color:#0f172a;margin:18px;}'
            . '.sheet-title{font-size:18pt;font-weight:700;margin:0 0 4px;color:#0f172a;}'
            . '.sheet-subtitle{font-size:10pt;color:#475569;margin:0 0 10px;}'
            . 'table.sheet{border-collapse:collapse;font-size:11pt;}'
            . 'table.sheet td,table.sheet th{border:1px solid #1f2937;padding:4px 6px;vertical-align:middle;}'
            . 'table.sheet .header-main{background:#d9e8f6;font-weight:700;text-align:center;}'
            . 'table.sheet .header-sub{background:#ebf5ff;font-weight:700;text-align:center;}'
            . 'table.sheet .header-meta{background:#d9e8f6;font-weight:700;text-align:center;}'
            . 'table.sheet .text{mso-number-format:"\\@";white-space:normal;}'
            . 'table.sheet .num{mso-number-format:"0.00";text-align:right;}'
            . 'table.sheet .date{mso-number-format:"dd\\/mm\\/yyyy";text-align:center;}'
            . 'table.sheet .index{text-align:center;}'
            . 'table.sheet .empty{color:#475569;font-style:italic;text-align:left;}'
            . 'table.sheet .provider{min-width:320px;}'
            . 'table.sheet .code{min-width:170px;}'
            . 'table.sheet .credit-note-row{background:#fee2e2;color:#991b1b;font-weight:700;}'
            . 'table.sheet .credit-note{background:#fee2e2;color:#991b1b;font-weight:700;}'
            . '</style></head><body>'
            . $encabezado
            . $tabla
            . '</body></html>';
    }

    private static function renderPrintHtml(array $libro, array $headers, array $rows): string {
        $headerRows = isset($headers[0]) && is_array($headers[0]) ? $headers : [$headers];
        $expectedColumns = 0;

        foreach ($headerRows as $headerRow) {
            $expectedColumns = max($expectedColumns, count($headerRow));
        }

        foreach ($rows as $row) {
            $expectedColumns = max($expectedColumns, count($row));
        }

        $headerRows = array_map(static function (array $row) use ($expectedColumns): array {
            return self::normalizeCsvRow($row, $expectedColumns);
        }, $headerRows);

        $rows = array_map(static function (array $row) use ($expectedColumns): array {
            return self::normalizeCsvRow($row, $expectedColumns);
        }, $rows);

        $titulo = self::excelTitleFor($libro);
        $subtitulo = self::excelSubtitleFor($libro);
        $tabla = self::renderExcelTable($headerRows, $rows, $expectedColumns, (string) ($libro['tipo'] ?? ''));

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . DteDataService::escapeHtml($titulo) . '</title><style>'
            . ':root{color-scheme:light;}'
            . 'body{margin:0;font-family:Arial,sans-serif;background:#eef3fa;color:#0f172a;}'
            . '.page{max-width:1480px;margin:0 auto;padding:28px 24px 40px;}'
            . '.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;}'
            . '.toolbar-note{font-size:13px;color:#475569;}'
            . '.toolbar-actions{display:flex;gap:10px;}'
            . '.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer;}'
            . '.btn-primary{background:#1f2937;border-color:#1f2937;color:#fff;}'
            . '.sheet{background:#fff;border:1px solid #dbe2ea;border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.12);overflow:auto;padding:22px;}'
            . '.sheet-title{font-size:22px;font-weight:700;margin:0 0 4px;color:#0f172a;}'
            . '.sheet-subtitle{font-size:13px;color:#475569;margin:0 0 14px;}'
            . 'table.sheet{border-collapse:collapse;font-size:11pt;background:#fff;}'
            . 'table.sheet td,table.sheet th{border:1px solid #1f2937;padding:4px 6px;vertical-align:middle;}'
            . 'table.sheet .header-main{background:#d9e8f6;font-weight:700;text-align:center;}'
            . 'table.sheet .header-sub{background:#ebf5ff;font-weight:700;text-align:center;}'
            . 'table.sheet .header-meta{background:#d9e8f6;font-weight:700;text-align:center;}'
            . 'table.sheet .text{white-space:normal;word-break:break-word;}'
            . 'table.sheet .num{text-align:right;}'
            . 'table.sheet .date{text-align:center;}'
            . 'table.sheet .index{text-align:center;}'
            . 'table.sheet .empty{color:#475569;font-style:italic;text-align:left;}'
            . 'table.sheet .provider{min-width:320px;}'
            . 'table.sheet .code{min-width:170px;}'
            . 'table.sheet .credit-note-row{background:#fee2e2;color:#991b1b;font-weight:700;}'
            . 'table.sheet .credit-note{background:#fee2e2;color:#991b1b;font-weight:700;}'
            . '@media print{body{background:#fff;} .page{max-width:none;padding:0;} .toolbar{display:none;} .sheet{border:0;box-shadow:none;border-radius:0;padding:0;}}'
            . '</style></head><body><div class="page">'
            . '<div class="toolbar"><div class="toolbar-note">Vista lista para imprimir o guardar como PDF.</div>'
            . '<div class="toolbar-actions"><button class="btn" onclick="window.close()">Cerrar</button><button class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button></div></div>'
            . '<section class="sheet">'
            . '<div class="sheet-title">' . DteDataService::escapeHtml($titulo) . '</div>'
            . '<div class="sheet-subtitle">' . DteDataService::escapeHtml($subtitulo) . '</div>'
            . $tabla
            . '</section></div></body></html>';
    }

    private static function renderExcelTable(array $headerRows, array $rows, int $expectedColumns, string $tipo): string {
        $html = '<table class="sheet">';

        foreach ($headerRows as $index => $headerRow) {
            $classes = $index < 2 && $tipo === 'compras'
                ? 'header-meta'
                : ($index === count($headerRows) - 1 ? 'header-sub' : 'header-main');

            $html .= '<tr>' . self::renderExcelCells($headerRow, $classes, true, $index, $tipo) . '</tr>';
        }

        if ($rows === []) {
            $html .= '<tr><td class="empty" colspan="' . $expectedColumns . '">No hay registros para exportar en este periodo.</td></tr>';
            $html .= '</table>';
            return $html;
        }

        foreach ($rows as $row) {
            $rowClass = self::isCreditNoteWorkbookRow($row) ? 'credit-note-row' : '';
            $html .= '<tr>' . self::renderExcelCells($row, $rowClass, false, null, $tipo) . '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private static function renderExcelCells(array $row, string $rowClass, bool $isHeader, ?int $headerIndex, string $tipo): string {
        $cells = '';
        $count = count($row);

        for ($index = 0; $index < $count; $index += 1) {
            $value = (string) ($row[$index] ?? '');
            $colspan = 1;

            if ($isHeader && $value !== '' && !($tipo === 'compras' && $headerIndex !== null && $headerIndex < 2)) {
                for ($offset = $index + 1; $offset < $count; $offset += 1) {
                    if ((string) ($row[$offset] ?? '') !== '') {
                        break;
                    }
                    $colspan += 1;
                }
            }

            if ($isHeader && $value === '') {
                $cells .= '<td class="' . $rowClass . ' text"></td>';
                continue;
            }

            if ($isHeader && $colspan > 1) {
                $index += $colspan - 1;
            }

            $cellClass = trim($rowClass . ' ' . self::excelCellClass($value, $index, $isHeader, $headerIndex, $tipo));
            $style = '';
            if (!$isHeader && (strcasecmp($value, 'Nota de Crédito') === 0 || strcasecmp($value, 'Nota de Credito') === 0)) {
                $cellClass = trim($cellClass . ' credit-note');
                $style = ' style="background:#fee2e2;color:#991b1b;font-weight:700;"';
            }
            $attributes = $colspan > 1 ? ' colspan="' . $colspan . '"' : '';

            $cells .= '<td' . $attributes . ' class="' . $cellClass . '"' . $style . '>'
                . DteDataService::escapeHtml($value)
                . '</td>';
        }

        return $cells;
    }

    private static function excelCellClass(string $value, int $index, bool $isHeader, ?int $headerIndex, string $tipo): string {
        if ($isHeader) {
            return 'text';
        }

        if ($tipo === 'compras') {
            if ($index === 0) {
                return 'index';
            }

            if ($index === 1) {
                return 'date';
            }

            if (in_array($index, [8, 9, 10, 11, 12, 13, 14, 15], true) && preg_match('/^-?\d+\.\d{2}$/', $value) === 1) {
                return 'num';
            }

            if ($index === 7) {
                return 'provider text';
            }

            if (in_array($index, [5], true)) {
                return 'code text';
            }

            return 'text';
        }

        if ($index === 0 && preg_match('/^\d+$/', $value) === 1) {
            return 'index';
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            return 'date';
        }

        if (preg_match('/^-?\d+\.\d{2}$/', $value) === 1) {
            return 'num';
        }

        return 'text';
    }

    private static function excelTitleFor(array $libro): string {
        $tipo = (string) ($libro['tipo'] ?? '');

        if ($tipo === 'compras') {
            return 'Libro de Compras';
        }

        if ($tipo === 'ventas_consumidor') {
            return 'Libro de Ventas Consumidor Final';
        }

        if ($tipo === 'ventas_contribuyente') {
            return 'Libro de Ventas a Contribuyentes';
        }

        return 'Libro IVA';
    }

    private static function excelSubtitleFor(array $libro): string {
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

        $empresa = trim((string) ($libro['empresa_nombre'] ?? ''));
        $mes = $meses[(int) ($libro['mes'] ?? 0)] ?? trim((string) ($libro['mes'] ?? ''));
        $anio = trim((string) ($libro['anio'] ?? ''));
        $periodo = trim($mes . ' ' . $anio);

        if ($empresa !== '' && $periodo !== '') {
            return $empresa . ' - ' . $periodo;
        }

        return $empresa !== '' ? $empresa : $periodo;
    }

    private static function decodePayload($raw): array {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return DteDataService::normalizeDocumentPayload($decoded);
    }

    private static function documentClassText(array $payload, bool $accented): string {
        if (isset($payload['identificacion']) || isset($payload['codigoGeneracion']) || isset($payload['tipoDte'])) {
            return $accented
                ? '4. DOCUMENTO TRIBUTARIO ELECTRONICO (DTE)'
                : '4. DOCUMENTO TRIBUTARIO ELECTRONICO (DTE)';
        }

        return '1. IMPRESO POR IMPRENTA O TIQUETES';
    }

    private static function tipoDocumentoText(string $tipo): string {
        $tipo = str_pad(trim($tipo), 2, '0', STR_PAD_LEFT);

        $map = [
            '01' => '01. FACTURA',
            '03' => '03. COMPROBANTE DE CREDITO FISCAL',
            '05' => '05. NOTA DE CREDITO',
            '06' => '06. NOTA DE DEBITO',
            '14' => '14. COMPROBANTE DE RETENCION',
        ];

        return $map[$tipo] ?? ($tipo !== '' ? $tipo : '');
    }

    private static function formatDate(?string $date): string {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : $date;
    }

    private static function formatDayOfMonth(?string $date): string {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('d', $timestamp) : $date;
    }

    private static function formatNumber($value): string {
        return number_format((float) $value, 2, '.', '');
    }

    private static function arrayValue(array $payload, string $key): array {
        $value = $payload[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    private static function firstNonEmpty(array $values): string {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function normalizeCsvRow(array $row, int $expectedColumns): array {
        $normalized = array_map(static function ($value): string {
            return str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], trim((string) $value));
        }, $row);

        if (count($normalized) < $expectedColumns) {
            $normalized = array_pad($normalized, $expectedColumns, '');
        }

        return $normalized;
    }

    private static function isCreditNoteWorkbookRow(array $row): bool {
        foreach ($row as $value) {
            $value = trim((string) $value);
            if (strcasecmp($value, 'Nota de Crédito') === 0 || strcasecmp($value, 'Nota de Credito') === 0) {
                return true;
            }
        }

        return false;
    }

    private static function hydrateVentaContribuyenteExport(array $factura): array {
        $payload = self::decodePayload($factura['raw_json'] ?? null);
        $extraido = $payload !== [] ? DteDataService::extractDocumentoData($payload) : [];
        $receptor = self::arrayValue($payload, 'receptor');
        $destinatario = self::arrayValue($payload, 'destinatario');

        $factura['fecha'] = self::firstNonEmpty([
            $factura['fecha'] ?? '',
            $extraido['fecha'] ?? '',
        ]);
        $factura['tipo_documento_nombre'] = self::firstNonEmpty([
            $factura['tipo_documento_nombre'] ?? '',
            $extraido['tipo_documento_nombre'] ?? '',
            DteDataService::tipoDocumentoNombre($factura['tipo_dte'] ?? $extraido['tipo_dte'] ?? ''),
        ]);
        $factura['codigo_generacion'] = self::firstNonEmpty([
            $factura['codigo_generacion'] ?? '',
            $extraido['codigo_generacion'] ?? '',
        ]);
        $factura['numero_control_interno'] = self::firstNonEmpty([
            $factura['numero_control_interno'] ?? '',
            $factura['numero_control'] ?? '',
            $extraido['numero_control'] ?? '',
        ]);
        $factura['sello_recepcion'] = self::firstNonEmpty([
            $factura['sello_recepcion'] ?? '',
            $extraido['sello_recepcion'] ?? '',
        ]);
        $factura['nombre_cliente'] = self::firstNonEmpty([
            $factura['nombre_cliente'] ?? '',
            $receptor['nombre'] ?? '',
            $destinatario['nombre'] ?? '',
        ]);
        $factura['nrc_cliente'] = self::firstNonEmpty([
            $factura['nrc_cliente'] ?? '',
            $receptor['nrc'] ?? '',
            $destinatario['nrc'] ?? '',
        ]);

        foreach ([
            'ventas_exentas_contribuyente' => 'compras_exentas',
            'ventas_internas_gravadas_contribuyente' => 'compras_gravadas',
            'debito_fiscal_contribuyente' => 'impuestos_calculados',
            'iva_percibido' => 'iva_percibido',
            'iva_retenido' => 'iva_retenido',
        ] as $campoDb => $campoExtraido) {
            $actual = round((float) ($factura[$campoDb] ?? 0), 2);
            $desdeJson = round((float) ($extraido[$campoExtraido] ?? 0), 2);
            if ($actual === 0.0 && $desdeJson !== 0.0) {
                $factura[$campoDb] = $desdeJson;
            }
        }

        $totalJson = round((float) ($extraido['total_compras'] ?? 0), 2);
        if ($totalJson !== 0.0) {
            $factura['ventas_totales'] = $totalJson;
        } elseif (round((float) ($factura['ventas_totales'] ?? 0), 2) === 0.0) {
            $factura['ventas_totales'] = round(
                (float) ($factura['ventas_exentas_contribuyente'] ?? 0)
                + (float) ($factura['ventas_internas_gravadas_contribuyente'] ?? 0)
                + (float) ($factura['debito_fiscal_contribuyente'] ?? 0)
                + (float) ($factura['iva_percibido'] ?? 0)
                - (float) ($factura['iva_retenido'] ?? 0),
                2
            );
        }

        return $factura;
    }

    private static function prepareFacturas(array $facturas): array {
        return DteDataService::hydrateFacturas($facturas);
    }
}
