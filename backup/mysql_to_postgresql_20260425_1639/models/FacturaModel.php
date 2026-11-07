<?php

class FacturaModel {

    private static bool $schemaChecked = false;

    private const EXTRA_COLUMNS = [
        'nombre_cliente'                            => 'VARCHAR(200) NULL',
        'nrc_cliente'                               => 'VARCHAR(20) NULL',
        'numero_control_preimpreso'                 => 'VARCHAR(50) NULL',
        'numero_control_interno'                    => 'VARCHAR(50) NULL',
        'dia_emision'                               => 'DATE NULL',
        'del_numero'                                => 'INT NULL',
        'al_numero'                                 => 'INT NULL',
        'codigo_generacion_desde'                   => 'VARCHAR(50) NULL',
        'codigo_generacion_hasta'                   => 'VARCHAR(50) NULL',
        'ventas_exentas'                            => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'ventas_internas_gravadas'                  => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'exportaciones'                             => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'total_ventas_diarias_propias'              => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'ventas_cuenta_terceros'                    => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'debito_fiscal'                             => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'ventas_exentas_contribuyente'              => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'ventas_internas_gravadas_contribuyente'    => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'debito_fiscal_contribuyente'               => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'ventas_totales'                            => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'nit_agente_retencion'                      => 'VARCHAR(20) NULL',
        'fecha_emision_retencion'                   => 'DATE NULL',
        'tipo_documento_relacionado'                => 'VARCHAR(20) NULL',
        'serie_documento'                           => 'VARCHAR(50) NULL',
        'numero_documento'                          => 'VARCHAR(50) NULL',
        'monto_sujeto_retencion'                    => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'retencion_iva_1'                           => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'dui_agente_retencion'                      => 'VARCHAR(20) NULL',
        'numero_anexo'                              => 'VARCHAR(20) NULL',
    ];

    public static function getByLibro(PDO $pdo, int $idLibro): array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM facturas
             WHERE id_libro = ?
             ORDER BY fecha ASC, id ASC"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetchAll();
    }

    public static function insertarFactura(PDO $pdo, array $data): int {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO facturas (
                id_libro,
                id_usuario,
                codigo_generacion,
                sello_recepcion,
                numero_control,
                tipo_dte,
                fecha,
                nrc,
                nit,
                nombre_proveedor,
                ventas_internas,
                ventas_importacion,
                ventas_internas_exentas,
                ventas_importacion_exentas,
                credito_fiscal,
                total_compras,
                iva_percibido,
                iva_retenido,
                numero_control_completo,
                raw_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['id_libro'],
            (int) $data['id_usuario'],
            $data['codigo_generacion'],
            $data['sello_recepcion'] ?? null,
            $data['numero_control'] ?? null,
            $data['tipo_dte'] ?? null,
            $data['fecha'],
            $data['nrc'] ?? null,
            $data['nit'] ?? null,
            $data['nombre_proveedor'] ?? null,
            $data['ventas_internas'] ?? 0,
            $data['ventas_importacion'] ?? 0,
            $data['ventas_internas_exentas'] ?? 0,
            $data['ventas_importacion_exentas'] ?? 0,
            $data['credito_fiscal'] ?? 0,
            $data['total_compras'] ?? 0,
            $data['iva_percibido'] ?? 0,
            $data['iva_retenido'] ?? 0,
            $data['numero_control_completo'] ?? null,
            $data['raw_json'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function ensureExtendedSchema(PDO $pdo): void {
        self::ensureSchema($pdo);
    }

    public static function existeFactura(PDO $pdo, string $codigoGeneracion, int $idLibro): bool {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM facturas
             WHERE codigo_generacion = ? AND id_libro = ?"
        );
        $stmt->execute([$codigoGeneracion, $idLibro]);

        return $stmt->fetch() !== false;
    }

    public static function countByLibro(PDO $pdo, int $idLibro): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE id_libro = ?");
        $stmt->execute([$idLibro]);

        return (int) $stmt->fetchColumn();
    }

    public static function getTotalesMensualesVentasConsumidor(PDO $pdo, int $idLibro): array {
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(ventas_exentas), 0) AS ventas_exentas,
                COALESCE(SUM(ventas_internas_gravadas), 0) AS ventas_internas_gravadas,
                COALESCE(SUM(exportaciones), 0) AS exportaciones,
                COALESCE(SUM(total_ventas_diarias_propias), 0) AS total_ventas_diarias_propias,
                COALESCE(SUM(ventas_cuenta_terceros), 0) AS ventas_cuenta_terceros
             FROM facturas
             WHERE id_libro = ?"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetch() ?: [
            'ventas_exentas'               => 0,
            'ventas_internas_gravadas'     => 0,
            'exportaciones'                => 0,
            'total_ventas_diarias_propias' => 0,
            'ventas_cuenta_terceros'       => 0,
        ];
    }

    public static function getTotalesMensualesVentasContribuyente(PDO $pdo, int $idLibro): array {
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(ventas_exentas_contribuyente), 0) AS ventas_exentas_contribuyente,
                COALESCE(SUM(ventas_internas_gravadas_contribuyente), 0) AS ventas_internas_gravadas_contribuyente,
                COALESCE(SUM(debito_fiscal_contribuyente), 0) AS debito_fiscal_contribuyente,
                COALESCE(SUM(iva_percibido), 0) AS iva_percibido,
                COALESCE(SUM(iva_retenido), 0) AS iva_retenido,
                COALESCE(SUM(ventas_totales), 0) AS ventas_totales
             FROM facturas
             WHERE id_libro = ?"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetch() ?: [
            'ventas_exentas_contribuyente'           => 0,
            'ventas_internas_gravadas_contribuyente' => 0,
            'debito_fiscal_contribuyente'            => 0,
            'iva_percibido'                          => 0,
            'iva_retenido'                           => 0,
            'ventas_totales'                         => 0,
        ];
    }

    public static function getTotalesMensualesRetencionIva(PDO $pdo, int $idLibro): array {
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(monto_sujeto_retencion), 0) AS monto_sujeto_retencion,
                COALESCE(SUM(retencion_iva_1), 0) AS retencion_iva_1
             FROM facturas
             WHERE id_libro = ?"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetch() ?: [
            'monto_sujeto_retencion' => 0,
            'retencion_iva_1'        => 0,
        ];
    }

    private static function ensureSchema(PDO $pdo): void {
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM facturas");
            $columnas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $existentes = [];

            foreach ($columnas as $columna) {
                $nombre = strtolower((string) ($columna['Field'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                $existentes[$nombre] = strtolower((string) ($columna['Type'] ?? ''));
            }

            $tipoSello = $existentes['sello_recepcion'] ?? '';
            if ($tipoSello !== '' && str_contains($tipoSello, 'varchar(50)')) {
                $pdo->exec("ALTER TABLE facturas MODIFY sello_recepcion TEXT NULL");
            }

            foreach (self::EXTRA_COLUMNS as $columna => $definicion) {
                if (array_key_exists($columna, $existentes)) {
                    continue;
                }

                $pdo->exec("ALTER TABLE facturas ADD COLUMN {$columna} {$definicion}");
            }
        } catch (Throwable $exception) {
            // Si no se puede alterar la tabla, continuamos con el esquema actual.
        }
    }
}
