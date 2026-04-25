<?php

class ValidadorDTE {

    private static array $reglas = [
        'compras'              => ['03', '05', '06'],
        'ventas_consumidor'    => ['01'],
        'ventas_contribuyente' => ['03', '05', '06'],
        'retencion_iva'        => ['14'],
    ];

    private static array $nombres = [
        '01' => 'Factura Consumidor Final',
        '03' => 'Comprobante de Credito Fiscal',
        '05' => 'Nota de Credito',
        '06' => 'Nota de Debito',
        '14' => 'Comprobante de Retencion',
    ];

    public static function validarTipo(string $tipoDte, string $tipoLibro): bool {
        return in_array($tipoDte, self::$reglas[$tipoLibro] ?? [], true);
    }

    public static function getTiposValidos(string $tipoLibro): array {
        $tipos = self::$reglas[$tipoLibro] ?? [];
        $resultado = [];

        foreach ($tipos as $tipo) {
            $resultado[] = [
                'codigo' => $tipo,
                'nombre' => self::getNombreTipo($tipo),
            ];
        }

        return $resultado;
    }

    public static function getNombreTipo(string $tipoDte): string {
        return self::$nombres[$tipoDte] ?? 'Desconocido';
    }
}
