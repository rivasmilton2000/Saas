<?php

class FacturaModel {

    private static bool $schemaChecked = false;

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

    private static function ensureSchema(PDO $pdo): void {
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'sello_recepcion'");
            $columna = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $tipoActual = strtolower((string) ($columna['Type'] ?? ''));

            if ($tipoActual !== '' && str_contains($tipoActual, 'varchar(50)')) {
                $pdo->exec("ALTER TABLE facturas MODIFY sello_recepcion TEXT NULL");
            }
        } catch (Throwable $exception) {
            // Si no se puede alterar la tabla, continuamos con el esquema actual.
        }
    }
}
