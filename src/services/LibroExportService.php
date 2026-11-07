<?php

require_once __DIR__ . '/DteDataService.php';

class LibroExportService {

    public static function columnas(): array {
        return [
            'No.',
            'Fecha',
            'N Control',
            'NRC',
            'NIT',
            'Nombre del proveedor',
            'Internas',
            'Import.',
            'Internas exentas',
            'Import. exentas',
            'Credito fiscal',
            'Total compras',
            'IVA percibido 1%',
            'IVA retenido 1%',
            'Codigo de generacion',
            'Sello de recepcion',
            'Numero de control completo',
        ];
    }

    public static function prepararFacturas(array $facturas): array {
        return DteDataService::hydrateFacturas($facturas);
    }

    public static function filas(array $facturas): array {
        $filas = [];

        foreach (self::prepararFacturas($facturas) as $indice => $factura) {
            $filas[] = [
                $indice + 1,
                $factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? null),
                $factura['numero_control'] ?? '',
                $factura['nrc'] ?? '',
                $factura['nit'] ?? '',
                $factura['nombre_proveedor'] ?? '',
                self::decimal($factura['ventas_internas'] ?? 0),
                self::decimal($factura['ventas_importacion'] ?? 0),
                self::decimal($factura['ventas_internas_exentas'] ?? 0),
                self::decimal($factura['ventas_importacion_exentas'] ?? 0),
                self::decimal($factura['credito_fiscal'] ?? 0),
                self::decimal($factura['total_compras'] ?? 0),
                self::decimal($factura['iva_percibido'] ?? 0),
                self::decimal($factura['iva_retenido'] ?? 0),
                $factura['codigo_generacion'] ?? '',
                $factura['sello_recepcion'] ?? '',
                $factura['numero_control_completo'] ?? '',
            ];
        }

        return $filas;
    }

    public static function descargarExcel(string $nombreArchivo, array $libro, array $facturas): void {
        $preparadas = self::prepararFacturas($facturas);
        $resumen    = DteDataService::resumenLibro($preparadas);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xls"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo self::renderExcelHtml($libro, $preparadas, $resumen);
    }

    public static function descargarPdf(string $nombreArchivo, array $libro, array $facturas): void {
        $preparadas = self::prepararFacturas($facturas);
        $resumen    = DteDataService::resumenLibro($preparadas);

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '_pdf.html"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo self::renderPrintHtml($libro, $preparadas, $resumen);
    }

    public static function descargarAnexoA3(string $nombreArchivo, array $facturas): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $salida = fopen('php://output', 'wb');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, self::columnas(), ';');

        foreach (self::filas($facturas) as $fila) {
            fputcsv($salida, $fila, ';');
        }

        fclose($salida);
    }

    private static function renderExcelHtml(array $libro, array $facturas, array $resumen): string {
        $titulo = 'Libro de Compras';
        $meta   = self::bookMeta($libro);
        $rows   = self::renderTableRows($facturas, true);

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#1f2937;margin:24px;}'
            . '.sheet-header{margin-bottom:24px;}'
            . '.eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4b49ac;}'
            . 'h1{margin:8px 0 6px;font-size:28px;color:#111827;}'
            . '.meta{font-size:13px;color:#4b5563;margin-bottom:16px;}'
            . '.summary{width:100%;border-collapse:separate;border-spacing:10px 0;margin:18px -10px 24px;}'
            . '.summary td{padding:14px 16px;border:1px solid #dde2f1;border-radius:14px;background:#f8f9ff;vertical-align:top;}'
            . '.summary-label{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7c8699;margin-bottom:6px;}'
            . '.summary-value{display:block;font-size:20px;font-weight:700;color:#111827;}'
            . '.summary-note{display:block;font-size:12px;color:#6b7280;margin-top:2px;}'
            . 'table.report{width:100%;border-collapse:collapse;font-size:12px;}'
            . 'table.report thead th{background:#1f2a44;color:#fff;padding:10px 8px;border:1px solid #d8dbe8;text-align:left;}'
            . 'table.report tbody td{padding:8px;border:1px solid #e5e7ef;vertical-align:top;}'
            . 'table.report tbody tr:nth-child(even){background:#f9fafc;}'
            . '.num{text-align:right;}'
            . '.wrap{white-space:normal;word-break:break-word;}'
            . '.wide{min-width:190px;}'
            . '</style></head><body>'
            . '<div class="sheet-header">'
            . '<span class="eyebrow">Reporte exportado</span>'
            . '<h1>' . DteDataService::escapeHtml($titulo) . '</h1>'
            . '<div class="meta">' . DteDataService::escapeHtml($meta) . '</div>'
            . self::renderSummaryCards($resumen)
            . '</div>'
            . '<table class="report"><thead>' . self::renderHeaderRow() . '</thead><tbody>' . $rows . '</tbody></table>'
            . '</body></html>';
    }

    private static function renderPrintHtml(array $libro, array $facturas, array $resumen): string {
        $titulo = 'Libro de Compras';
        $meta   = self::bookMeta($libro);
        $rows   = self::renderTableRows($facturas, false);

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . DteDataService::escapeHtml($titulo) . '</title><style>'
            . ':root{color-scheme:light;}'
            . 'body{margin:0;font-family:Arial,sans-serif;background:#eef1f7;color:#111827;}'
            . '.page{max-width:1360px;margin:0 auto;padding:32px 24px 40px;}'
            . '.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;}'
            . '.toolbar-note{font-size:13px;color:#6b7280;}'
            . '.toolbar-actions{display:flex;gap:10px;}'
            . '.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:999px;border:1px solid #d7dcee;background:#fff;color:#111827;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer;}'
            . '.btn-primary{background:#4b49ac;border-color:#4b49ac;color:#fff;}'
            . '.sheet{background:#fff;border:1px solid #dce1ee;border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.12);overflow:hidden;}'
            . '.sheet-head{padding:28px 28px 18px;border-bottom:1px solid #e9edf5;}'
            . '.eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4b49ac;}'
            . 'h1{margin:8px 0 6px;font-size:30px;color:#111827;}'
            . '.meta{font-size:14px;color:#4b5563;margin-bottom:18px;}'
            . '.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}'
            . '.summary-card{padding:14px 16px;border:1px solid #e1e5f0;border-radius:18px;background:#f8f9ff;}'
            . '.summary-card span{display:block;}'
            . '.summary-label{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7c8699;margin-bottom:6px;}'
            . '.summary-value{font-size:22px;font-weight:700;color:#111827;}'
            . '.summary-note{font-size:12px;color:#6b7280;margin-top:2px;}'
            . '.table-wrap{padding:0 28px 28px;overflow:auto;}'
            . 'table.report{width:100%;border-collapse:collapse;font-size:12px;}'
            . 'table.report thead th{position:sticky;top:0;background:#1f2a44;color:#fff;padding:10px 8px;border:1px solid #d8dbe8;text-align:left;}'
            . 'table.report tbody td{padding:9px 8px;border:1px solid #e5e7ef;vertical-align:top;}'
            . 'table.report tbody tr:nth-child(even){background:#fafbfe;}'
            . '.num{text-align:right;}'
            . '.wrap{white-space:normal;word-break:break-word;}'
            . '.wide{min-width:200px;}'
            . '@media (max-width:1024px){.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}'
            . '@media print{body{background:#fff;} .page{max-width:none;padding:0;} .toolbar{display:none;} .sheet{border:0;box-shadow:none;border-radius:0;} .sheet-head{padding:18px 0 16px;} .table-wrap{padding:0;} table.report thead th{position:static;}}'
            . '</style></head><body><div class="page">'
            . '<div class="toolbar"><div class="toolbar-note">Vista lista para imprimir o guardar como PDF.</div>'
            . '<div class="toolbar-actions"><button class="btn" onclick="window.close()">Cerrar</button><button class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button></div></div>'
            . '<section class="sheet"><div class="sheet-head">'
            . '<span class="eyebrow">Reporte exportable</span><h1>' . DteDataService::escapeHtml($titulo) . '</h1>'
            . '<div class="meta">' . DteDataService::escapeHtml($meta) . '</div>'
            . self::renderSummaryCards($resumen, true)
            . '</div><div class="table-wrap"><table class="report"><thead>' . self::renderHeaderRow() . '</thead><tbody>' . $rows . '</tbody></table></div></section></div></body></html>';
    }

    private static function renderSummaryCards(array $resumen, bool $grid = false): string {
        $cards = [
            ['Documentos', (string) ($resumen['cantidad'] ?? 0), 'Facturas dentro del libro'],
            ['Total compras', self::decimal($resumen['total_compras'] ?? 0), 'Monto acumulado'],
            ['Credito fiscal', self::decimal($resumen['credito_fiscal'] ?? 0), 'IVA acreditable'],
            ['IVA retenido', self::decimal($resumen['iva_retenido'] ?? 0), 'Retencion del periodo'],
        ];

        if ($grid) {
            $html = '<div class="summary-grid">';
            foreach ($cards as $card) {
                $html .= '<div class="summary-card">'
                    . '<span class="summary-label">' . DteDataService::escapeHtml($card[0]) . '</span>'
                    . '<span class="summary-value">' . DteDataService::escapeHtml($card[1]) . '</span>'
                    . '<span class="summary-note">' . DteDataService::escapeHtml($card[2]) . '</span>'
                    . '</div>';
            }
            $html .= '</div>';
            return $html;
        }

        $html = '<table class="summary"><tr>';
        foreach ($cards as $card) {
            $html .= '<td>'
                . '<span class="summary-label">' . DteDataService::escapeHtml($card[0]) . '</span>'
                . '<span class="summary-value">' . DteDataService::escapeHtml($card[1]) . '</span>'
                . '<span class="summary-note">' . DteDataService::escapeHtml($card[2]) . '</span>'
                . '</td>';
        }
        $html .= '</tr></table>';

        return $html;
    }

    private static function renderHeaderRow(): string {
        $cells = '';

        foreach (self::columnas() as $columna) {
            $cells .= '<th>' . DteDataService::escapeHtml($columna) . '</th>';
        }

        return '<tr>' . $cells . '</tr>';
    }

    private static function renderTableRows(array $facturas, bool $forExcel): string {
        $rows = '';

        foreach ($facturas as $indice => $factura) {
            $cells = [
                (string) ($indice + 1),
                $factura['fecha_display'] ?? DteDataService::formatDate($factura['fecha'] ?? null),
                $factura['numero_control'] ?? '',
                $factura['nrc'] ?? '',
                $factura['nit'] ?? '',
                $factura['nombre_proveedor'] ?? '',
                self::decimal($factura['ventas_internas'] ?? 0),
                self::decimal($factura['ventas_importacion'] ?? 0),
                self::decimal($factura['ventas_internas_exentas'] ?? 0),
                self::decimal($factura['ventas_importacion_exentas'] ?? 0),
                self::decimal($factura['credito_fiscal'] ?? 0),
                self::decimal($factura['total_compras'] ?? 0),
                self::decimal($factura['iva_percibido'] ?? 0),
                self::decimal($factura['iva_retenido'] ?? 0),
                $factura['codigo_generacion'] ?? '',
                $factura['sello_recepcion'] ?? '',
                $factura['numero_control_completo'] ?? '',
            ];

            $rows .= '<tr>';
            foreach ($cells as $cellIndex => $cell) {
                $class = '';
                if (in_array($cellIndex, [6, 7, 8, 9, 10, 11, 12, 13], true)) {
                    $class = ' class="num"';
                } elseif (in_array($cellIndex, [5, 14, 15, 16], true)) {
                    $class = ' class="wrap wide"';
                } else {
                    $class = ' class="wrap"';
                }

                $value = DteDataService::escapeHtml((string) $cell);
                if ($forExcel && in_array($cellIndex, [6, 7, 8, 9, 10, 11, 12, 13], true)) {
                    $value = str_replace(',', '', $value);
                }

                $rows .= '<td' . $class . '>' . nl2br($value) . '</td>';
            }
            $rows .= '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="' . count(self::columnas()) . '" class="wrap">No hay facturas registradas en este libro.</td></tr>';
        }

        return $rows;
    }

    private static function bookMeta(array $libro): string {
        $empresa = trim((string) ($libro['empresa_nombre'] ?? 'Empresa sin nombre'));
        $periodo = str_pad((string) ($libro['mes'] ?? '0'), 2, '0', STR_PAD_LEFT) . '/' . ($libro['anio'] ?? '');
        $nit     = trim((string) ($libro['empresa_nit'] ?? ''));
        $nrc     = trim((string) ($libro['empresa_nrc'] ?? ''));

        $parts = [
            'Empresa: ' . $empresa,
            'Periodo: ' . $periodo,
        ];

        if ($nit !== '') {
            $parts[] = 'NIT: ' . $nit;
        }

        if ($nrc !== '') {
            $parts[] = 'NRC: ' . $nrc;
        }

        return implode(' | ', $parts);
    }

    private static function decimal($value): string {
        return number_format((float) $value, 2, '.', ',');
    }
}
