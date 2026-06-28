<?php

class LibroModel {

    public static function getByUsuario(PDO $pdo, int $idUsuario): array {
        $stmt = $pdo->prepare(
            "SELECT
                l.*,
                e.nombre AS empresa_nombre,
                e.iniciales AS empresa_iniciales,
                e.color_emblema AS empresa_color,
                e.nit AS empresa_nit,
                e.nrc AS empresa_nrc
             FROM dte_libros l
             INNER JOIN dte_empresas e ON e.id = l.id_empresa
             WHERE l.id_usuario = ? AND l.estado = 1 AND e.estado = 1
             ORDER BY l.anio DESC, l.mes DESC, l.id DESC"
        );
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll();
    }

    public static function getByUsuarioAndTipo(PDO $pdo, int $idUsuario, string $tipo): array {
        $stmt = $pdo->prepare(
            "SELECT
                l.*,
                e.nombre AS empresa_nombre,
                e.iniciales AS empresa_iniciales,
                e.color_emblema AS empresa_color,
                e.nit AS empresa_nit,
                e.nrc AS empresa_nrc
             FROM dte_libros l
             INNER JOIN dte_empresas e ON e.id = l.id_empresa
             WHERE l.id_usuario = ? AND l.tipo = ? AND l.estado = 1 AND e.estado = 1
             ORDER BY l.anio DESC, l.mes DESC, l.id DESC"
        );
        $stmt->execute([$idUsuario, $tipo]);

        return $stmt->fetchAll();
    }

    public static function getByEmpresa(PDO $pdo, int $idEmpresa, ?string $tipo = null): array {
        if ($tipo === null) {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM dte_libros
                 WHERE id_empresa = ? AND estado = 1
                 ORDER BY anio DESC, mes DESC, id DESC"
            );
            $stmt->execute([$idEmpresa]);
            return $stmt->fetchAll();
        }

        return self::getByEmpresaYTipo($pdo, $idEmpresa, $tipo);
    }

    public static function getByEmpresaYTipo(PDO $pdo, int $idEmpresa, string $tipo): array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_libros
             WHERE id_empresa = ? AND tipo = ? AND estado = 1
             ORDER BY anio DESC, mes DESC, id DESC"
        );
        $stmt->execute([$idEmpresa, $tipo]);

        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $idLibro, int $idUsuario): ?array {
        $stmt = $pdo->prepare(
            "SELECT
                l.*,
                e.nombre AS empresa_nombre,
                e.iniciales AS empresa_iniciales,
                e.color_emblema AS empresa_color,
                e.dui AS empresa_dui,
                e.nit AS empresa_nit,
                e.nrc AS empresa_nrc,
                e.tipo_legal AS empresa_tipo_legal
             FROM dte_libros l
             INNER JOIN dte_empresas e ON e.id = l.id_empresa
             WHERE l.id = ? AND l.id_usuario = ? AND l.estado = 1 AND e.estado = 1"
        );
        $stmt->execute([$idLibro, $idUsuario]);

        $libro = $stmt->fetch();
        return $libro ?: null;
    }

    public static function create(PDO $pdo, array $data): int {
        $stmt = $pdo->prepare(
            "INSERT INTO dte_libros (id_empresa, id_usuario, tipo, mes, anio)
             VALUES (?, ?, ?, ?, ?)
             RETURNING id"
        );
        $stmt->execute([
            (int) $data['id_empresa'],
            (int) $data['id_usuario'],
            trim((string) $data['tipo']),
            (int) $data['mes'],
            (int) $data['anio'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function findByEmpresaTipoPeriodo(PDO $pdo, int $idEmpresa, string $tipo, int $mes, int $anio): ?array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM dte_libros
             WHERE id_empresa = ? AND tipo = ? AND mes = ? AND anio = ? AND estado = 1
             LIMIT 1"
        );
        $stmt->execute([$idEmpresa, $tipo, $mes, $anio]);

        $libro = $stmt->fetch();
        return $libro ?: null;
    }

    public static function existeLibro(PDO $pdo, int $idEmpresa, string $tipo, int $mes, int $anio): bool {
        return self::findByEmpresaTipoPeriodo($pdo, $idEmpresa, $tipo, $mes, $anio) !== null;
    }

    public static function delete(PDO $pdo, int $idLibro, int $idUsuario): bool {
        $stmt = $pdo->prepare(
            "UPDATE dte_libros
             SET estado = 0
             WHERE id = ? AND id_usuario = ? AND estado = 1"
        );
        $stmt->execute([$idLibro, $idUsuario]);

        return $stmt->rowCount() > 0;
    }
}
