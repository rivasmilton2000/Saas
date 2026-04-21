<?php

class LibroExportService {

    public static function columnas(): array {
        return [
            'No.',
            'Fecha',
            'N° Control',
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

    public static function filas(array $facturas): array {
        $filas = [];

        foreach ($facturas as $indice => $factura) {
            $filas[] = [
                $indice + 1,
                $factura['fecha'] ?? '',
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

    public static function descargarExcel(string $nombreArchivo, array $facturas): void {
        self::descargarDelimitado(
            $nombreArchivo . '.xls',
            self::columnas(),
            self::filas($facturas),
            "\t",
            'application/vnd.ms-excel; charset=utf-8'
        );
    }

    public static function descargarAnexoA3(string $nombreArchivo, array $facturas): void {
        self::descargarDelimitado(
            $nombreArchivo . '.csv',
            self::columnas(),
            self::filas($facturas),
            ';',
            'text/csv; charset=utf-8'
        );
    }

    private static function descargarDelimitado(
        string $nombreArchivo,
        array $encabezados,
        array $filas,
        string $delimitador,
        string $contentType
    ): void {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $salida = fopen('php://output', 'wb');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezados, $delimitador);

        foreach ($filas as $fila) {
            fputcsv($salida, $fila, $delimitador);
        }

        fclose($salida);
    }

    private static function decimal($value): string {
        return number_format((float) $value, 2, '.', '');
    }
}
