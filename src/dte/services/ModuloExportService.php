<?php
require_once __DIR__ . '/DteDataService.php';

class ModuloExportService {

    public static function descargarCsv(string $nombreArchivo, array $report): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");

        $columns = $report['columns'] ?? [];
        $numericKeys = $report['numeric_keys'] ?? [];
        $rows = $report['rows'] ?? [];

        fputcsv($output, array_map(static function (array $column): string {
            return (string) ($column['label'] ?? '');
        }, $columns), ';', '"', '');

        foreach ($rows as $row) {
            $line = [];

            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                $value = $row[$key] ?? '';

                if (in_array($key, $numericKeys, true) && $value !== '' && $value !== null) {
                    $value = number_format((float) $value, 2, '.', '');
                }

                $line[] = (string) $value;
            }

            fputcsv($output, $line, ';', '"', '');
        }

        fclose($output);
    }

    public static function descargarExcel(string $nombreArchivo, array $report): void {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xls"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo self::renderExcelHtml($report);
    }

    public static function descargarPdf(string $nombreArchivo, array $report): void {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '_pdf.html"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo self::renderPrintHtml($report);
    }

    private static function renderExcelHtml(array $report): string {
        $accent = self::color($report['accent_color'] ?? '#4b49ac');

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#172033;margin:24px;background:#ffffff;}'
            . '.sheet{border:1px solid #dbe4f0;border-radius:24px;overflow:hidden;}'
            . '.hero{padding:22px 24px;background:linear-gradient(135deg,#ffffff 0%,' . self::rgba($accent, 0.12) . ' 100%);border-bottom:1px solid #e3ebf5;}'
            . '.eyebrow{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin-bottom:10px;}'
            . '.title{margin:0 0 6px;font-size:28px;font-weight:700;color:#111827;}'
            . '.meta{font-size:13px;color:#536176;margin-bottom:0;}'
            . '.summary{width:100%;border-collapse:separate;border-spacing:10px 0;margin:18px -10px 0;}'
            . '.summary td{padding:14px 16px;border:1px solid #dde6f2;border-radius:16px;background:#fff;vertical-align:top;}'
            . '.summary-label{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7a90;margin-bottom:6px;}'
            . '.summary-value{display:block;font-size:20px;font-weight:700;color:#101827;}'
            . '.summary-note{display:block;font-size:12px;color:#6b7280;margin-top:4px;}'
            . '.table-wrap{padding:22px 24px 24px;}'
            . 'table.report{width:100%;border-collapse:collapse;font-size:12px;}'
            . 'table.report thead th{padding:10px 9px;background:#172033;color:#fff;border:1px solid #d4dbe7;text-align:left;}'
            . 'table.report tbody td{padding:8px 9px;border:1px solid #e4eaf2;vertical-align:top;}'
            . 'table.report tbody tr:nth-child(even){background:#f8fbff;}'
            . 'table.report tbody tr.total-row td{font-weight:700;background:' . self::rgba($accent, 0.12) . ';}'
            . 'table.report tbody tr.credit-note-row td{background:#fff1f2;color:#991b1b;}'
            . '.num{text-align:right;}'
            . '.wrap{white-space:normal;word-break:break-word;}'
            . '</style></head><body>'
            . '<section class="sheet">'
            . '<div class="hero">'
            . '<span class="eyebrow">Reporte exportable</span>'
            . '<h1 class="title">' . DteDataService::escapeHtml((string) ($report['titulo'] ?? 'Libro')) . '</h1>'
            . '<p class="meta">' . DteDataService::escapeHtml((string) ($report['meta'] ?? '')) . '</p>'
            . self::renderSummaryTable($report['summary'] ?? [])
            . '</div>'
            . '<div class="table-wrap">'
            . self::renderTable($report)
            . '</div>'
            . '</section>'
            . '</body></html>';
    }

    private static function renderPrintHtml(array $report): string {
        $accent = self::color($report['accent_color'] ?? '#4b49ac');

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . DteDataService::escapeHtml((string) ($report['titulo'] ?? 'Libro')) . '</title><style>'
            . ':root{color-scheme:light;}'
            . 'body{margin:0;font-family:Arial,sans-serif;background:#eef3fa;color:#101827;}'
            . '.page{max-width:1380px;margin:0 auto;padding:32px 24px 40px;}'
            . '.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;}'
            . '.toolbar-note{font-size:13px;color:#5f6f84;}'
            . '.toolbar-actions{display:flex;gap:10px;}'
            . '.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:999px;border:1px solid #d7e0eb;background:#fff;color:#0f172a;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer;}'
            . '.btn-primary{background:' . $accent . ';border-color:' . $accent . ';color:#fff;}'
            . '.sheet{background:#fff;border:1px solid #dae3ee;border-radius:24px;box-shadow:0 24px 70px rgba(15,23,42,.1);overflow:hidden;}'
            . '.hero{padding:24px 28px;background:linear-gradient(135deg,#ffffff 0%,' . self::rgba($accent, 0.12) . ' 100%);border-bottom:1px solid #e3ebf5;}'
            . '.eyebrow{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin-bottom:10px;}'
            . '.title{margin:0 0 6px;font-size:30px;font-weight:700;color:#111827;}'
            . '.meta{margin:0;font-size:14px;color:#4f5d73;}'
            . '.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px;}'
            . '.summary-card{padding:14px 16px;border:1px solid #dde6f2;border-radius:18px;background:#fff;}'
            . '.summary-label{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7a90;margin-bottom:6px;}'
            . '.summary-value{display:block;font-size:22px;font-weight:700;color:#101827;}'
            . '.summary-note{display:block;font-size:12px;color:#6b7280;margin-top:4px;}'
            . '.table-wrap{padding:0 28px 28px;overflow:auto;}'
            . 'table.report{width:100%;border-collapse:collapse;font-size:12px;}'
            . 'table.report thead th{position:sticky;top:0;padding:10px 9px;background:#172033;color:#fff;border:1px solid #d4dbe7;text-align:left;}'
            . 'table.report tbody td{padding:9px;border:1px solid #e4eaf2;vertical-align:top;}'
            . 'table.report tbody tr:nth-child(even){background:#f8fbff;}'
            . 'table.report tbody tr.total-row td{font-weight:700;background:' . self::rgba($accent, 0.12) . ';}'
            . 'table.report tbody tr.credit-note-row td{background:#fff1f2;color:#991b1b;}'
            . '.num{text-align:right;}'
            . '.wrap{white-space:normal;word-break:break-word;}'
            . '@media (max-width:1100px){.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}'
            . '@media print{body{background:#fff;} .page{max-width:none;padding:0;} .toolbar{display:none;} .sheet{border:0;box-shadow:none;border-radius:0;} .hero{padding:16px 0;} .table-wrap{padding:0;} table.report thead th{position:static;}}'
            . '</style></head><body><div class="page">'
            . '<div class="toolbar"><div class="toolbar-note">Vista lista para imprimir o guardar como PDF.</div>'
            . '<div class="toolbar-actions"><button class="btn" onclick="window.close()">Cerrar</button><button class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button></div></div>'
            . '<section class="sheet"><div class="hero">'
            . '<span class="eyebrow">Reporte exportable</span>'
            . '<h1 class="title">' . DteDataService::escapeHtml((string) ($report['titulo'] ?? 'Libro')) . '</h1>'
            . '<p class="meta">' . DteDataService::escapeHtml((string) ($report['meta'] ?? '')) . '</p>'
            . self::renderSummaryGrid($report['summary'] ?? [])
            . '</div><div class="table-wrap">'
            . self::renderTable($report)
            . '</div></section></div></body></html>';
    }

    private static function renderSummaryTable(array $summary): string {
        if (empty($summary)) {
            return '';
        }

        $html = '<table class="summary"><tr>';
        foreach ($summary as $card) {
            $html .= '<td>'
                . '<span class="summary-label">' . DteDataService::escapeHtml((string) ($card['label'] ?? '')) . '</span>'
                . '<span class="summary-value">' . DteDataService::escapeHtml((string) ($card['value'] ?? '')) . '</span>'
                . '<span class="summary-note">' . DteDataService::escapeHtml((string) ($card['note'] ?? '')) . '</span>'
                . '</td>';
        }
        $html .= '</tr></table>';

        return $html;
    }

    private static function renderSummaryGrid(array $summary): string {
        if (empty($summary)) {
            return '';
        }

        $html = '<div class="summary-grid">';
        foreach ($summary as $card) {
            $html .= '<div class="summary-card">'
                . '<span class="summary-label">' . DteDataService::escapeHtml((string) ($card['label'] ?? '')) . '</span>'
                . '<span class="summary-value">' . DteDataService::escapeHtml((string) ($card['value'] ?? '')) . '</span>'
                . '<span class="summary-note">' . DteDataService::escapeHtml((string) ($card['note'] ?? '')) . '</span>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function renderTable(array $report): string {
        $columns        = $report['columns'] ?? [];
        $rows           = $report['rows'] ?? [];
        $numericKeys    = $report['numeric_keys'] ?? [];
        $totalRowIndex  = $report['total_row_index'] ?? null;
        $emptyMessage   = (string) ($report['empty_message'] ?? 'No hay registros.');

        if (empty($rows)) {
            return '<div style="padding:18px 0;color:#5f6f84;">' . DteDataService::escapeHtml($emptyMessage) . '</div>';
        }

        $html = '<table class="report"><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>' . DteDataService::escapeHtml((string) ($column['label'] ?? '')) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $index => $row) {
            $classes = [];
            if ($totalRowIndex !== null && $index === (int) $totalRowIndex) {
                $classes[] = 'total-row';
            } elseif (!empty($row['_highlight_document_type']) || self::isCreditNoteRow($row)) {
                $classes[] = 'credit-note-row';
            }
            $rowClass = $classes !== [] ? ' class="' . implode(' ', $classes) . '"' : '';
            $html .= '<tr' . $rowClass . '>';

            foreach ($columns as $column) {
                $key     = (string) ($column['key'] ?? '');
                $value   = $row[$key] ?? '';
                $isNum   = in_array($key, $numericKeys, true);
                $classes = $isNum ? 'num' : 'wrap';

                if ($isNum && $value !== '' && $value !== null) {
                    $value = number_format((float) $value, 2);
                }

                $html .= '<td class="' . $classes . '">' . DteDataService::escapeHtml((string) $value) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private static function color(string $color): string {
        $color = trim($color);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#4b49ac';
    }

    private static function isCreditNoteRow(array $row): bool {
        $tipo = trim((string) ($row['tipo_documento_nombre'] ?? ''));
        if ($tipo === '') {
            return false;
        }

        return strcasecmp($tipo, 'Nota de Crédito') === 0 || strcasecmp($tipo, 'Nota de Credito') === 0;
    }

    private static function rgba(string $color, float $alpha): string {
        $color = ltrim(self::color($color), '#');
        $red   = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue  = hexdec(substr($color, 4, 2));

        return 'rgba(' . $red . ',' . $green . ',' . $blue . ',' . $alpha . ')';
    }
}
