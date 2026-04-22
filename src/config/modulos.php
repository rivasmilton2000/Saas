<?php

function getLibroModules(): array {
    return [
        'compras' => [
            'tipo'              => 'compras',
            'nombre'            => 'Libro de Compras',
            'ruta'              => 'pages/compras.php',
            'icono'             => 'icon-layout',
            'descripcion'       => 'Gestiona tus DTE de compras y revisa el libro por período.',
            'visible_sidebar'   => true,
            'visible_dashboard' => true,
        ],
        'ventas_consumidor' => [
            'tipo'              => 'ventas_consumidor',
            'nombre'            => 'Ventas Consumidor Final',
            'ruta'              => 'pages/ventas_consumidor.php',
            'icono'             => 'icon-grid-2',
            'descripcion'       => 'Trabaja facturas FCF (01) y consolida ventas diarias propias.',
            'visible_sidebar'   => true,
            'visible_dashboard' => true,
            'tipos_validos'     => '01',
            'columnas'          => [
                'no'                           => 'No.',
                'dia_emision'                  => 'Día',
                'del_numero'                   => 'Del No.',
                'al_numero'                    => 'Al No.',
                'codigo_generacion_desde'      => 'Código de generación del No.',
                'codigo_generacion_hasta'      => 'Código de generación al No.',
                'sello_recepcion'              => 'Sello de recepción',
                'ventas_exentas'               => 'Ventas exentas',
                'ventas_internas_gravadas'     => 'Ventas internas gravadas',
                'exportaciones'                => 'Exportaciones',
                'total_ventas_diarias_propias' => 'Total ventas diarias propias',
                'ventas_cuenta_terceros'       => 'Ventas a cuenta de terceros',
            ],
            'columnas_numericas' => [
                'ventas_exentas',
                'ventas_internas_gravadas',
                'exportaciones',
                'total_ventas_diarias_propias',
                'ventas_cuenta_terceros',
            ],
        ],
        'ventas_contribuyente' => [
            'tipo'              => 'ventas_contribuyente',
            'nombre'            => 'Ventas a Contribuyentes',
            'ruta'              => 'pages/ventas_contribuyente.php',
            'icono'             => 'icon-briefcase',
            'descripcion'       => 'Administra CCF, NC y ND para ventas a contribuyentes.',
            'visible_sidebar'   => true,
            'visible_dashboard' => true,
            'tipos_validos'     => '03, 05 y 06',
            'columnas'          => [
                'no'                                     => 'No.',
                'fecha'                                  => 'Fecha de emisión',
                'numero_control_preimpreso'              => 'N° control preimpreso',
                'numero_control_interno'                 => 'N° control interno',
                'codigo_generacion'                      => 'Código de generación',
                'nombre_cliente'                         => 'Nombre cliente',
                'nrc_cliente'                            => 'NRC',
                'ventas_exentas_contribuyente'           => 'Exentas',
                'ventas_internas_gravadas_contribuyente' => 'Internas gravadas',
                'debito_fiscal_contribuyente'            => 'Débito fiscal',
                'ventas_exentas_cuenta_terceros'         => 'Exentas cuenta terceros',
                'ventas_internas_gravadas_cuenta_terceros' => 'Internas gravadas cuenta terceros',
                'debito_fiscal_cuenta_terceros'          => 'Débito fiscal cuenta terceros',
                'iva_percibido'                          => 'IVA percibido',
                'iva_retenido'                           => 'IVA retenido 1%',
                'ventas_totales'                         => 'Ventas totales',
            ],
            'columnas_numericas' => [
                'ventas_exentas_contribuyente',
                'ventas_internas_gravadas_contribuyente',
                'debito_fiscal_contribuyente',
                'ventas_exentas_cuenta_terceros',
                'ventas_internas_gravadas_cuenta_terceros',
                'debito_fiscal_cuenta_terceros',
                'iva_percibido',
                'iva_retenido',
                'ventas_totales',
            ],
        ],
        'retencion_iva' => [
            'tipo'              => 'retencion_iva',
            'nombre'            => 'Retención IVA 1%',
            'ruta'              => null,
            'icono'             => 'icon-shield',
            'descripcion'       => 'Módulo pendiente de interfaz visual.',
            'visible_sidebar'   => false,
            'visible_dashboard' => false,
        ],
    ];
}

function getLibroModule(string $tipo): ?array {
    $modulos = getLibroModules();
    return $modulos[$tipo] ?? null;
}
