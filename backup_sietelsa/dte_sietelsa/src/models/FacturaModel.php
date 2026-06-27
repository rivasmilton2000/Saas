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
        'compras_exentas'                           => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'compras_gravadas'                          => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'impuestos_calculados'                      => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'fovial_otros'                              => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'import_batch_id'                           => 'VARCHAR(64) NULL',
        'import_index'                              => 'INT NULL',
        'source_filename'                           => 'VARCHAR(255) NULL',
        'raw_json_hash'                             => 'CHAR(64) NULL',
        'posible_duplicado'                         => 'TINYINT(1) NOT NULL DEFAULT 0',
        'fuera_de_periodo'                          => 'TINYINT(1) NOT NULL DEFAULT 0',
        'motivo_no_contabilizado'                   => 'VARCHAR(255) NULL',
        'tipo_documento_nombre'                     => 'VARCHAR(80) NULL',
        'signo_contable'                            => 'TINYINT NOT NULL DEFAULT 1',
        'es_repetido'                               => 'TINYINT(1) NOT NULL DEFAULT 0',
        'motivo_exclusion'                          => 'VARCHAR(255) NULL',
        'contabilizable'                            => 'TINYINT(1) NOT NULL DEFAULT 1',
        'documento_relacionado_tipo'                => 'VARCHAR(20) NULL',
        'documento_relacionado_numero'              => 'VARCHAR(60) NULL',
        'documento_relacionado_codigo'              => 'VARCHAR(60) NULL',
        'documento_relacionado_fecha'               => 'DATE NULL',
    ];

    public static function getByLibro(PDO $pdo, int $idLibro, bool $incluirFueraPeriodo = false): array {
        self::ensureSchema($pdo);

        $where = $incluirFueraPeriodo
            ? "id_libro = ?"
            : "id_libro = ? AND COALESCE(fuera_de_periodo, 0) = 0 AND COALESCE(es_repetido, 0) = 0 AND COALESCE(contabilizable, 1) = 1";
        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE {$where}
             ORDER BY fecha ASC, id ASC"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetchAll();
    }

    public static function getFueraPeriodoByLibro(PDO $pdo, int $idLibro): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id_libro = ? AND COALESCE(fuera_de_periodo, 0) = 1 AND COALESCE(es_repetido, 0) = 0
             ORDER BY fecha ASC, id ASC"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetchAll();
    }

    public static function getRepetidasByLibro(PDO $pdo, int $idLibro): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id_libro = ? AND COALESCE(es_repetido, 0) = 1
             ORDER BY fecha ASC, id ASC"
        );
        $stmt->execute([$idLibro]);

        return $stmt->fetchAll();
    }

    public static function getPrincipalContabilizableByCodigoGeneracion(PDO $pdo, string $codigoGeneracion, int $idLibro): ?array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE codigo_generacion = ?
               AND id_libro = ?
               AND COALESCE(es_repetido, 0) = 0
               AND COALESCE(contabilizable, 1) = 1
               AND COALESCE(fuera_de_periodo, 0) = 0
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute([$codigoGeneracion, $idLibro]);

        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : null;
    }

    public static function getOriginalContabilizableForFactura(PDO $pdo, array $factura): ?array {
        self::ensureSchema($pdo);

        $idLibro = (int) ($factura['id_libro'] ?? 0);
        $idActual = (int) ($factura['id'] ?? 0);
        if ($idLibro <= 0) {
            return null;
        }

        $codigoGeneracion = trim((string) ($factura['codigo_generacion'] ?? ''));
        if ($codigoGeneracion !== '') {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM dte_facturas
                 WHERE id_libro = ?
                   AND codigo_generacion = ?
                   AND id <> ?
                   AND COALESCE(es_repetido, 0) = 0
                   AND COALESCE(contabilizable, 1) = 1
                   AND COALESCE(fuera_de_periodo, 0) = 0
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([$idLibro, $codigoGeneracion, $idActual]);
            $original = $stmt->fetch();
            if (is_array($original)) {
                return $original;
            }
        }

        $tipoDte = trim((string) ($factura['tipo_dte'] ?? ''));
        $numeroControl = trim((string) ($factura['numero_control'] ?? ''));
        $nit = trim((string) ($factura['nit'] ?? ''));
        $selloRecepcion = trim((string) ($factura['sello_recepcion'] ?? ''));

        if ($tipoDte === '' || $numeroControl === '' || $nit === '' || $selloRecepcion === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id_libro = ?
               AND id <> ?
               AND tipo_dte = ?
               AND numero_control = ?
               AND nit = ?
               AND sello_recepcion = ?
               AND COALESCE(es_repetido, 0) = 0
               AND COALESCE(contabilizable, 1) = 1
               AND COALESCE(fuera_de_periodo, 0) = 0
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute([$idLibro, $idActual, $tipoDte, $numeroControl, $nit, $selloRecepcion]);

        $original = $stmt->fetch();
        return is_array($original) ? $original : null;
    }

    public static function getRepetidasByIds(PDO $pdo, array $ids): array {
        self::ensureSchema($pdo);

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id IN ({$placeholders})
               AND COALESCE(es_repetido, 0) = 1
             ORDER BY id ASC"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public static function restaurarRepetidosSinOriginal(PDO $pdo, int $idLibro): int {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT repetido.id
             FROM dte_facturas repetido
             LEFT JOIN dte_facturas original
               ON original.id_libro = repetido.id_libro
              AND original.id <> repetido.id
              AND COALESCE(original.es_repetido, 0) = 0
              AND COALESCE(original.contabilizable, 1) = 1
              AND COALESCE(original.fuera_de_periodo, 0) = 0
              AND (
                    original.codigo_generacion = repetido.codigo_generacion
                    OR (
                        COALESCE(original.tipo_dte, '') <> ''
                        AND COALESCE(original.numero_control, '') <> ''
                        AND COALESCE(original.nit, '') <> ''
                        AND COALESCE(original.sello_recepcion, '') <> ''
                        AND original.tipo_dte = repetido.tipo_dte
                        AND original.numero_control = repetido.numero_control
                        AND original.nit = repetido.nit
                        AND original.sello_recepcion = repetido.sello_recepcion
                    )
                  )
             WHERE repetido.id_libro = ?
               AND COALESCE(repetido.es_repetido, 0) = 1
               AND repetido.codigo_generacion IS NOT NULL
               AND repetido.codigo_generacion <> ''
               AND original.id IS NULL"
        );
        $stmt->execute([$idLibro]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare(
            "UPDATE dte_facturas
             SET es_repetido = 0,
                 posible_duplicado = 0,
                 motivo_exclusion = NULL,
                 contabilizable = CASE WHEN COALESCE(fuera_de_periodo, 0) = 1 THEN 0 ELSE 1 END
             WHERE id IN ({$placeholders})"
        );
        $update->execute($ids);

        return count($ids);
    }

    public static function insertarFactura(PDO $pdo, array $data): int {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas (
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
                compras_exentas,
                compras_gravadas,
                credito_fiscal,
                impuestos_calculados,
                fovial_otros,
                total_compras,
                iva_percibido,
                iva_retenido,
                numero_control_completo,
                import_batch_id,
                import_index,
                source_filename,
                raw_json_hash,
                posible_duplicado,
                fuera_de_periodo,
                motivo_no_contabilizado,
                tipo_documento_nombre,
                signo_contable,
                es_repetido,
                motivo_exclusion,
                contabilizable,
                documento_relacionado_tipo,
                documento_relacionado_numero,
                documento_relacionado_codigo,
                documento_relacionado_fecha,
                raw_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            $data['compras_exentas'] ?? 0,
            $data['compras_gravadas'] ?? 0,
            $data['credito_fiscal'] ?? 0,
            $data['impuestos_calculados'] ?? $data['credito_fiscal'] ?? 0,
            $data['fovial_otros'] ?? 0,
            $data['total_compras'] ?? 0,
            $data['iva_percibido'] ?? 0,
            $data['iva_retenido'] ?? 0,
            $data['numero_control_completo'] ?? null,
            $data['import_batch_id'] ?? null,
            $data['import_index'] ?? null,
            $data['source_filename'] ?? $data['nombre_archivo'] ?? $data['archivo'] ?? null,
            $data['raw_json_hash'] ?? null,
            (int) ($data['posible_duplicado'] ?? 0),
            (int) ($data['fuera_de_periodo'] ?? 0),
            $data['motivo_no_contabilizado'] ?? null,
            $data['tipo_documento_nombre'] ?? null,
            self::signoContable($data['tipo_dte'] ?? null),
            (int) ($data['es_repetido'] ?? 0),
            $data['motivo_exclusion'] ?? null,
            (int) ($data['contabilizable'] ?? 1),
            $data['documento_relacionado_tipo'] ?? null,
            $data['documento_relacionado_numero'] ?? null,
            $data['documento_relacionado_codigo'] ?? null,
            self::normalizarFechaManual($data['documento_relacionado_fecha'] ?? null),
            $data['raw_json'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function ensureExtendedSchema(PDO $pdo): void {
        self::ensureSchema($pdo);
    }

    public static function prepararCargaMasivaFlexible(PDO $pdo): void {
        self::ensureSchema($pdo);
        self::dropRestrictiveUniqueIndexes($pdo);
    }

    public static function existeFactura(PDO $pdo, string $codigoGeneracion, int $idLibro): bool {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM dte_facturas
             WHERE codigo_generacion = ? AND id_libro = ?"
        );
        $stmt->execute([$codigoGeneracion, $idLibro]);

        return $stmt->fetch() !== false;
    }

    public static function getByDuplicateFingerprint(PDO $pdo, string $tipoDte, string $numeroControl, string $nit, string $selloRecepcion, int $idLibro): ?array {
        self::ensureSchema($pdo);

        $tipoDte = trim($tipoDte);
        $numeroControl = trim($numeroControl);
        $nit = trim($nit);
        $selloRecepcion = trim($selloRecepcion);

        if ($tipoDte === '' || $numeroControl === '' || $nit === '' || $selloRecepcion === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id_libro = ?
               AND tipo_dte = ?
               AND numero_control = ?
               AND nit = ?
               AND sello_recepcion = ?
               AND COALESCE(es_repetido, 0) = 0
               AND COALESCE(contabilizable, 1) = 1
               AND COALESCE(fuera_de_periodo, 0) = 0
             LIMIT 1"
        );
        $stmt->execute([$idLibro, $tipoDte, $numeroControl, $nit, $selloRecepcion]);

        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : null;
    }

    public static function getByCodigoGeneracion(PDO $pdo, string $codigoGeneracion, int $idLibro): ?array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE codigo_generacion = ? AND id_libro = ?
             ORDER BY COALESCE(contabilizable, 1) DESC,
                      COALESCE(es_repetido, 0) ASC,
                      COALESCE(fuera_de_periodo, 0) ASC,
                      id ASC
             LIMIT 1"
        );
        $stmt->execute([$codigoGeneracion, $idLibro]);

        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : null;
    }

    public static function getPrincipalByCodigoGeneracion(PDO $pdo, string $codigoGeneracion, int $idLibro): ?array {
        return self::getByCodigoGeneracion($pdo, $codigoGeneracion, $idLibro);
    }

    public static function normalizarDuplicadosPorCodigo(PDO $pdo, int $idLibro): array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT codigo_generacion
             FROM dte_facturas
             WHERE id_libro = ?
               AND codigo_generacion IS NOT NULL
               AND codigo_generacion <> ''
             GROUP BY codigo_generacion
             HAVING COUNT(*) > 1"
        );
        $stmt->execute([$idLibro]);
        $codigos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $principales = 0;
        $copias = 0;
        $motivoDuplicado = 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.';

        $stmtRegistros = $pdo->prepare(
            "SELECT id, fuera_de_periodo
             FROM dte_facturas
             WHERE id_libro = ? AND codigo_generacion = ?
             ORDER BY COALESCE(fuera_de_periodo, 0) ASC, id ASC"
        );
        $stmtPrincipal = $pdo->prepare(
            "UPDATE dte_facturas
             SET es_repetido = 0,
                 posible_duplicado = 0,
                 motivo_exclusion = NULL,
                 contabilizable = CASE WHEN COALESCE(fuera_de_periodo, 0) = 1 THEN 0 ELSE 1 END
             WHERE id = ?"
        );
        $stmtCopia = $pdo->prepare(
            "UPDATE dte_facturas
             SET es_repetido = 1,
                 posible_duplicado = 1,
                 motivo_exclusion = ?,
                 contabilizable = 0
             WHERE id = ?"
        );

        foreach ($codigos as $codigo) {
            $stmtRegistros->execute([$idLibro, (string) $codigo]);
            $registros = $stmtRegistros->fetchAll(PDO::FETCH_ASSOC);
            if (count($registros) < 2) {
                continue;
            }

            $principal = array_shift($registros);
            if ((int) ($principal['fuera_de_periodo'] ?? 0) === 1) {
                continue;
            }

            $stmtPrincipal->execute([(int) $principal['id']]);
            $principales++;

            foreach ($registros as $registro) {
                $stmtCopia->execute([$motivoDuplicado, (int) $registro['id']]);
                $copias++;
            }
        }

        return [
            'documentos_unicos_incluidos' => $principales,
            'copias_duplicadas_apartadas' => $copias,
        ];
    }

    public static function actualizarFactura(PDO $pdo, int $idFactura, array $data): void {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "UPDATE dte_facturas
             SET sello_recepcion = ?,
                 numero_control = ?,
                 tipo_dte = ?,
                 fecha = ?,
                 nrc = ?,
                 nit = ?,
                 nombre_proveedor = ?,
                 ventas_internas = ?,
                 ventas_importacion = ?,
                 ventas_internas_exentas = ?,
                 ventas_importacion_exentas = ?,
                 compras_exentas = ?,
                 compras_gravadas = ?,
                 credito_fiscal = ?,
                 impuestos_calculados = ?,
                 fovial_otros = ?,
                 total_compras = ?,
                 iva_percibido = ?,
                 iva_retenido = ?,
                 numero_control_completo = ?,
                 fuera_de_periodo = ?,
                 motivo_no_contabilizado = ?,
                 tipo_documento_nombre = ?,
                 signo_contable = ?,
                 es_repetido = ?,
                 motivo_exclusion = ?,
                 contabilizable = ?,
                 documento_relacionado_tipo = ?,
                 documento_relacionado_numero = ?,
                 documento_relacionado_codigo = ?,
                 documento_relacionado_fecha = ?,
                 raw_json = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $data['sello_recepcion'] ?? null,
            $data['numero_control'] ?? null,
            $data['tipo_dte'] ?? null,
            $data['fecha'] ?? null,
            $data['nrc'] ?? null,
            $data['nit'] ?? null,
            $data['nombre_proveedor'] ?? null,
            $data['ventas_internas'] ?? 0,
            $data['ventas_importacion'] ?? 0,
            $data['ventas_internas_exentas'] ?? 0,
            $data['ventas_importacion_exentas'] ?? 0,
            $data['compras_exentas'] ?? 0,
            $data['compras_gravadas'] ?? 0,
            $data['credito_fiscal'] ?? 0,
            $data['impuestos_calculados'] ?? $data['credito_fiscal'] ?? 0,
            $data['fovial_otros'] ?? 0,
            $data['total_compras'] ?? 0,
            $data['iva_percibido'] ?? 0,
            $data['iva_retenido'] ?? 0,
            $data['numero_control_completo'] ?? null,
            (int) ($data['fuera_de_periodo'] ?? 0),
            $data['motivo_no_contabilizado'] ?? null,
            $data['tipo_documento_nombre'] ?? null,
            self::signoContable($data['tipo_dte'] ?? null),
            (int) ($data['es_repetido'] ?? 0),
            $data['motivo_exclusion'] ?? null,
            (int) ($data['contabilizable'] ?? 1),
            $data['documento_relacionado_tipo'] ?? null,
            $data['documento_relacionado_numero'] ?? null,
            $data['documento_relacionado_codigo'] ?? null,
            self::normalizarFechaManual($data['documento_relacionado_fecha'] ?? null),
            $data['raw_json'] ?? null,
            $idFactura,
        ]);
    }

    public static function getByIdForLibro(PDO $pdo, int $idFactura, int $idLibro, int $idUsuario): ?array {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_facturas
             WHERE id = ? AND id_libro = ? AND id_usuario = ?
             LIMIT 1"
        );
        $stmt->execute([$idFactura, $idLibro, $idUsuario]);

        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : null;
    }

    public static function guardarManual(PDO $pdo, array $data, ?int $idFactura = null): int {
        self::ensureSchema($pdo);

        $columnas = self::manualColumns();
        $fila = self::normalizarManual($data);

        if ($idFactura !== null && $idFactura > 0) {
            $sets = array_map(static fn (string $columna): string => "{$columna} = ?", $columnas);
            $valores = [];
            foreach ($columnas as $columna) {
                $valores[] = $fila[$columna] ?? null;
            }
            $valores[] = $idFactura;
            $valores[] = (int) $fila['id_libro'];
            $valores[] = (int) $fila['id_usuario'];

            $stmt = $pdo->prepare(
                "UPDATE dte_facturas
                 SET " . implode(', ', $sets) . "
                 WHERE id = ? AND id_libro = ? AND id_usuario = ?"
            );
            $stmt->execute($valores);

            return $idFactura;
        }

        $insertColumns = array_merge(['id_libro', 'id_usuario'], $columnas);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $valores = [];
        foreach ($insertColumns as $columna) {
            $valores[] = $fila[$columna] ?? null;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas (" . implode(', ', $insertColumns) . ")
             VALUES ({$placeholders})"
        );
        $stmt->execute($valores);

        return (int) $pdo->lastInsertId();
    }

    public static function eliminarFactura(PDO $pdo, int $idFactura, int $idLibro, int $idUsuario): bool {
        $stmt = $pdo->prepare(
            "DELETE FROM dte_facturas
             WHERE id = ? AND id_libro = ? AND id_usuario = ?"
        );
        $stmt->execute([$idFactura, $idLibro, $idUsuario]);

        return $stmt->rowCount() > 0;
    }

    public static function limpiarLibro(PDO $pdo, int $idLibro, int $idUsuario): int {
        $stmt = $pdo->prepare(
            "DELETE FROM dte_facturas
             WHERE id_libro = ? AND id_usuario = ?"
        );
        $stmt->execute([$idLibro, $idUsuario]);

        return $stmt->rowCount();
    }

    public static function countByLibro(PDO $pdo, int $idLibro): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dte_facturas WHERE id_libro = ?");
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
             FROM dte_facturas
             WHERE id_libro = ? AND COALESCE(fuera_de_periodo, 0) = 0 AND COALESCE(es_repetido, 0) = 0 AND COALESCE(contabilizable, 1) = 1"
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
             FROM dte_facturas
             WHERE id_libro = ? AND COALESCE(fuera_de_periodo, 0) = 0 AND COALESCE(es_repetido, 0) = 0 AND COALESCE(contabilizable, 1) = 1"
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
             FROM dte_facturas
             WHERE id_libro = ? AND COALESCE(fuera_de_periodo, 0) = 0 AND COALESCE(es_repetido, 0) = 0 AND COALESCE(contabilizable, 1) = 1"
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
            $stmt = $pdo->query("SHOW COLUMNS FROM dte_facturas");
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
                $pdo->exec("ALTER TABLE dte_facturas MODIFY sello_recepcion TEXT NULL");
            }

            foreach (self::EXTRA_COLUMNS as $columna => $definicion) {
                if (array_key_exists($columna, $existentes)) {
                    continue;
                }

                $pdo->exec("ALTER TABLE dte_facturas ADD COLUMN {$columna} {$definicion}");
            }

            self::dropRestrictiveUniqueIndexes($pdo);
        } catch (Throwable $exception) {
            // Si no se puede alterar la tabla, continuamos con el esquema actual.
        }
    }

    private static function dropRestrictiveUniqueIndexes(PDO $pdo): void {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM dte_facturas");
            $indices = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return;
        }

        $unicos = [];
        foreach ($indices as $indice) {
            $nombre = (string) ($indice['Key_name'] ?? '');
            if ($nombre === '' || $nombre === 'PRIMARY' || (int) ($indice['Non_unique'] ?? 1) !== 0) {
                continue;
            }

            $unicos[$nombre][] = strtolower((string) ($indice['Column_name'] ?? ''));
        }

        $columnasBloqueantes = [
            'codigo_generacion',
            'numero_control',
            'sello_recepcion',
            'sello_recibido',
            'identificador',
            'numero_documento',
            'hash',
            'raw_json_hash',
            'uuid',
        ];

        foreach ($unicos as $nombre => $columnas) {
            if (array_intersect($columnasBloqueantes, $columnas) === []) {
                continue;
            }

            if (in_array('id_libro', $columnas, true)) {
                self::ensureIndex($pdo, 'idx_dte_facturas_libro', 'id_libro');
            }

            $pdo->exec("ALTER TABLE dte_facturas DROP INDEX `{$nombre}`");
        }
    }

    private static function ensureIndex(PDO $pdo, string $indexName, string $columns): void {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'dte_facturas'
                   AND index_name = ?
                 LIMIT 1"
            );
            $stmt->execute([$indexName]);
            if ($stmt->fetchColumn()) {
                return;
            }

            $pdo->exec("ALTER TABLE dte_facturas ADD INDEX `{$indexName}` ({$columns})");
        } catch (Throwable $exception) {
            // Si no se puede crear, dejamos que MySQL reporte el error real al quitar UNIQUE.
        }
    }

    private static function manualColumns(): array {
        return [
            'codigo_generacion',
            'sello_recepcion',
            'numero_control',
            'tipo_dte',
            'fecha',
            'nrc',
            'nit',
            'nombre_proveedor',
            'ventas_internas',
            'ventas_importacion',
            'ventas_internas_exentas',
            'ventas_importacion_exentas',
            'compras_exentas',
            'compras_gravadas',
            'credito_fiscal',
            'impuestos_calculados',
            'fovial_otros',
            'total_compras',
            'iva_percibido',
            'iva_retenido',
            'numero_control_completo',
            'nombre_cliente',
            'nrc_cliente',
            'numero_control_preimpreso',
            'numero_control_interno',
            'dia_emision',
            'del_numero',
            'al_numero',
            'codigo_generacion_desde',
            'codigo_generacion_hasta',
            'ventas_exentas',
            'ventas_internas_gravadas',
            'exportaciones',
            'total_ventas_diarias_propias',
            'ventas_cuenta_terceros',
            'debito_fiscal',
            'ventas_exentas_contribuyente',
            'ventas_internas_gravadas_contribuyente',
            'debito_fiscal_contribuyente',
            'ventas_totales',
            'nit_agente_retencion',
            'fecha_emision_retencion',
            'tipo_documento_relacionado',
            'serie_documento',
            'numero_documento',
            'monto_sujeto_retencion',
            'retencion_iva_1',
            'dui_agente_retencion',
            'numero_anexo',
            'raw_json',
            'fuera_de_periodo',
            'motivo_no_contabilizado',
            'tipo_documento_nombre',
            'signo_contable',
            'es_repetido',
            'motivo_exclusion',
            'contabilizable',
            'documento_relacionado_tipo',
            'documento_relacionado_numero',
            'documento_relacionado_codigo',
            'documento_relacionado_fecha',
        ];
    }

    private static function normalizarManual(array $data): array {
        $idLibro = (int) ($data['id_libro'] ?? 0);
        $codigo = trim((string) ($data['codigo_generacion'] ?? ''));
        if ($codigo === '') {
            $codigo = trim((string) ($data['codigo_generacion_desde'] ?? ''));
        }
        if ($codigo === '') {
            $codigo = trim((string) ($data['codigo_generacion_hasta'] ?? ''));
        }
        if ($codigo === '') {
            $codigo = 'MANUAL-' . $idLibro . '-' . strtoupper(substr(str_replace('.', '', uniqid('', true)), -16));
        }

        $fecha = self::normalizarFechaManual($data['fecha'] ?? $data['dia_emision'] ?? $data['fecha_emision_retencion'] ?? null);
        $diaEmision = self::normalizarFechaManual($data['dia_emision'] ?? $fecha);
        $fechaRetencion = self::normalizarFechaManual($data['fecha_emision_retencion'] ?? $fecha);
        $tipoDte = trim((string) ($data['tipo_dte'] ?? ''));
        $signoContable = self::signoContable($tipoDte);
        $tipoDocumentoNombre = self::tipoDocumentoNombre($tipoDte);
        $permiteRelacionado = in_array(self::normalizeTipoDte($tipoDte) ?? '', ['05', '06'], true);
        $rawJson = $data['raw_json'] ?? null;
        if ($rawJson === null || trim((string) $rawJson) === '') {
            $rawJson = json_encode([
                'origen' => 'manual',
                'codigoGeneracion' => $codigo,
                'tipoDte' => $tipoDte,
                'fecha' => $fecha,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ventasInternas = self::decimal($data['ventas_internas'] ?? 0);
        $ventasImportacion = self::decimal($data['ventas_importacion'] ?? 0);
        $ventasInternasExentas = self::decimal($data['ventas_internas_exentas'] ?? 0);
        $ventasImportacionExentas = self::decimal($data['ventas_importacion_exentas'] ?? 0);
        $comprasExentas = self::decimal($data['compras_exentas'] ?? ($ventasInternasExentas + $ventasImportacionExentas));
        $comprasGravadas = self::decimal($data['compras_gravadas'] ?? ($ventasInternas + $ventasImportacion));
        $impuestosCalculados = self::decimal($data['impuestos_calculados'] ?? $data['credito_fiscal'] ?? 0);
        $fovialOtros = self::decimal($data['fovial_otros'] ?? 0);
        $ivaPercibido = self::decimal($data['iva_percibido'] ?? 0);
        $ivaRetenido = self::decimal($data['iva_retenido'] ?? 0);
        $totalComprasRaw = trim((string) ($data['total_compras'] ?? ''));
        $totalCompras = $totalComprasRaw !== '' ? self::decimal($totalComprasRaw) : self::decimal(
            $comprasExentas
            + $comprasGravadas
            + $impuestosCalculados
            + $ivaPercibido
            + $fovialOtros
            - $ivaRetenido
        );

        $fila = [
            'id_libro' => $idLibro,
            'id_usuario' => (int) ($data['id_usuario'] ?? 0),
            'codigo_generacion' => $codigo,
            'sello_recepcion' => self::nullableText($data['sello_recepcion'] ?? null),
            'numero_control' => self::nullableText($data['numero_control'] ?? $data['numero_control_interno'] ?? null),
            'tipo_dte' => self::nullableText($tipoDte),
            'fecha' => $fecha,
            'nrc' => self::nullableText($data['nrc'] ?? $data['nrc_cliente'] ?? null),
            'nit' => self::nullableText($data['nit'] ?? $data['nit_agente_retencion'] ?? null),
            'nombre_proveedor' => self::nullableText($data['nombre_proveedor'] ?? $data['nombre_cliente'] ?? null),
            'ventas_internas' => $ventasInternas,
            'ventas_importacion' => $ventasImportacion,
            'ventas_internas_exentas' => $ventasInternasExentas,
            'ventas_importacion_exentas' => $ventasImportacionExentas,
            'compras_exentas' => $comprasExentas,
            'compras_gravadas' => $comprasGravadas,
            'credito_fiscal' => $impuestosCalculados,
            'impuestos_calculados' => $impuestosCalculados,
            'fovial_otros' => $fovialOtros,
            'total_compras' => $totalCompras,
            'iva_percibido' => $ivaPercibido,
            'iva_retenido' => $ivaRetenido,
            'numero_control_completo' => self::nullableText($data['numero_control_completo'] ?? $data['numero_control'] ?? null),
            'nombre_cliente' => self::nullableText($data['nombre_cliente'] ?? $data['nombre_proveedor'] ?? null),
            'nrc_cliente' => self::nullableText($data['nrc_cliente'] ?? $data['nrc'] ?? null),
            'numero_control_preimpreso' => self::nullableText($data['numero_control_preimpreso'] ?? null),
            'numero_control_interno' => self::nullableText($data['numero_control_interno'] ?? $data['numero_control'] ?? null),
            'dia_emision' => $diaEmision,
            'del_numero' => self::nullableInt($data['del_numero'] ?? null),
            'al_numero' => self::nullableInt($data['al_numero'] ?? null),
            'codigo_generacion_desde' => self::nullableText($data['codigo_generacion_desde'] ?? $codigo),
            'codigo_generacion_hasta' => self::nullableText($data['codigo_generacion_hasta'] ?? $codigo),
            'ventas_exentas' => self::decimal($data['ventas_exentas'] ?? 0),
            'ventas_internas_gravadas' => self::decimal($data['ventas_internas_gravadas'] ?? 0),
            'exportaciones' => self::decimal($data['exportaciones'] ?? 0),
            'total_ventas_diarias_propias' => self::decimal($data['total_ventas_diarias_propias'] ?? 0),
            'ventas_cuenta_terceros' => self::decimal($data['ventas_cuenta_terceros'] ?? 0),
            'debito_fiscal' => self::decimal($data['debito_fiscal'] ?? 0),
            'ventas_exentas_contribuyente' => self::decimal($data['ventas_exentas_contribuyente'] ?? 0),
            'ventas_internas_gravadas_contribuyente' => self::decimal($data['ventas_internas_gravadas_contribuyente'] ?? 0),
            'debito_fiscal_contribuyente' => self::decimal($data['debito_fiscal_contribuyente'] ?? 0),
            'ventas_totales' => self::decimal($data['ventas_totales'] ?? 0),
            'nit_agente_retencion' => self::nullableText($data['nit_agente_retencion'] ?? $data['nit'] ?? null),
            'fecha_emision_retencion' => $fechaRetencion,
            'tipo_documento_relacionado' => self::nullableText($data['tipo_documento_relacionado'] ?? null),
            'serie_documento' => self::nullableText($data['serie_documento'] ?? null),
            'numero_documento' => self::nullableText($data['numero_documento'] ?? null),
            'monto_sujeto_retencion' => self::decimal($data['monto_sujeto_retencion'] ?? 0),
            'retencion_iva_1' => self::decimal($data['retencion_iva_1'] ?? 0),
            'dui_agente_retencion' => self::nullableText($data['dui_agente_retencion'] ?? null),
            'numero_anexo' => self::nullableText($data['numero_anexo'] ?? null),
            'fuera_de_periodo' => (int) ($data['fuera_de_periodo'] ?? 0),
            'motivo_no_contabilizado' => self::nullableText($data['motivo_no_contabilizado'] ?? null),
            'tipo_documento_nombre' => self::nullableText($data['tipo_documento_nombre'] ?? $tipoDocumentoNombre),
            'signo_contable' => $signoContable,
            'es_repetido' => (int) ($data['es_repetido'] ?? 0),
            'motivo_exclusion' => self::nullableText($data['motivo_exclusion'] ?? null),
            'contabilizable' => (int) ($data['contabilizable'] ?? 1),
            'documento_relacionado_tipo' => $permiteRelacionado ? self::nullableText($data['documento_relacionado_tipo'] ?? null) : null,
            'documento_relacionado_numero' => $permiteRelacionado ? self::nullableText($data['documento_relacionado_numero'] ?? null) : null,
            'documento_relacionado_codigo' => $permiteRelacionado ? self::nullableText($data['documento_relacionado_codigo'] ?? null) : null,
            'documento_relacionado_fecha' => $permiteRelacionado ? self::normalizarFechaManual($data['documento_relacionado_fecha'] ?? null) : null,
            'raw_json' => $rawJson,
        ];

        if ($fila['fecha'] === null) {
            $fila['fecha'] = date('Y-m-d');
        }

        return $fila;
    }

    private static function nullableText($value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function decimal($value): float {
        $value = str_replace(',', '', trim((string) $value));
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private static function normalizeTipoDte($tipoDte): string {
        $tipoDte = trim((string) $tipoDte);
        return ctype_digit($tipoDte) ? str_pad($tipoDte, 2, '0', STR_PAD_LEFT) : $tipoDte;
    }

    private static function tipoDocumentoNombre($tipoDte): string {
        $tipoDte = self::normalizeTipoDte($tipoDte);
        $map = [
            '01' => 'Factura',
            '03' => 'Crédito Fiscal',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
        ];

        return $map[$tipoDte] ?? ($tipoDte !== '' ? 'Otro / tipoDte ' . $tipoDte : 'Otro / tipoDte');
    }

    private static function signoContable($tipoDte): int {
        return 1;
    }

    private static function nullableInt($value): ?int {
        $value = trim((string) $value);
        return $value === '' ? null : (int) $value;
    }

    private static function normalizarFechaManual($value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            [$dia, $mes, $anio] = explode('/', $value);
            return sprintf('%04d-%02d-%02d', (int) $anio, (int) $mes, (int) $dia);
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
