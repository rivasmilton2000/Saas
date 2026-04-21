<?php

class EmpresaModel {

    public static function getByUsuario(PDO $pdo, int $idUsuario): array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM empresas
             WHERE id_usuario = ? AND estado = 1
             ORDER BY nombre ASC, id DESC"
        );
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $idEmpresa, int $idUsuario): ?array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM empresas
             WHERE id = ? AND id_usuario = ? AND estado = 1"
        );
        $stmt->execute([$idEmpresa, $idUsuario]);

        $empresa = $stmt->fetch();
        return $empresa ?: null;
    }

    public static function create(PDO $pdo, array $data): int {
        $nombre     = trim((string) ($data['nombre'] ?? ''));
        $iniciales  = self::normalizarIniciales($nombre, $data['iniciales'] ?? '');
        $color      = trim((string) ($data['color_emblema'] ?? '#f97316'));
        $dui        = self::normalizeDui($data['dui'] ?? null);
        $nit        = self::normalizeNit($data['nit'] ?? null);
        $nrc        = self::nullable($data['nrc'] ?? null);
        $tipoLegal  = trim((string) ($data['tipo_legal'] ?? 'natural'));

        $stmt = $pdo->prepare(
            "INSERT INTO empresas (
                id_usuario,
                nombre,
                iniciales,
                color_emblema,
                dui,
                nit,
                nrc,
                tipo_legal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['id_usuario'],
            $nombre,
            $iniciales,
            $color,
            $dui,
            $nit,
            $nrc,
            $tipoLegal,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(PDO $pdo, int $idEmpresa, int $idUsuario): bool {
        $stmt = $pdo->prepare(
            "UPDATE empresas
             SET estado = 0
             WHERE id = ? AND id_usuario = ?"
        );
        $stmt->execute([$idEmpresa, $idUsuario]);

        return $stmt->rowCount() > 0;
    }

    public static function existeNrc(PDO $pdo, int $idUsuario, string $nrc): bool {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM empresas
             WHERE id_usuario = ? AND nrc = ? AND estado = 1"
        );
        $stmt->execute([$idUsuario, trim($nrc)]);

        return $stmt->fetch() !== false;
    }

    public static function existeNit(PDO $pdo, int $idUsuario, string $nit): bool {
        $nit = self::normalizeNit($nit);
        if ($nit === null) {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM empresas
             WHERE id_usuario = ? AND nit = ? AND estado = 1"
        );
        $stmt->execute([$idUsuario, $nit]);

        return $stmt->fetch() !== false;
    }

    public static function normalizeDui($dui): ?string {
        $digits = preg_replace('/\D+/', '', (string) $dui);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 8) {
            return $digits;
        }

        return substr($digits, 0, 8) . '-' . substr($digits, 8, 1);
    }

    public static function isValidDui($dui): bool {
        $normalizado = self::normalizeDui($dui);
        if ($normalizado === null) {
            return true;
        }

        return preg_match('/^\d{8}-\d$/', $normalizado) === 1;
    }

    public static function normalizeNit($nit): ?string {
        $digits = preg_replace('/\D+/', '', (string) $nit);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return $digits;
        }

        if (strlen($digits) <= 10) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4);
        }

        if (strlen($digits) <= 13) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4, 6) . '-' . substr($digits, 10);
        }

        return substr($digits, 0, 4) . '-' . substr($digits, 4, 6) . '-' . substr($digits, 10, 3) . '-' . substr($digits, 13, 1);
    }

    public static function isValidNit($nit): bool {
        $normalizado = self::normalizeNit($nit);
        if ($normalizado === null) {
            return true;
        }

        return preg_match('/^\d{4}-\d{6}-\d{3}-\d$/', $normalizado) === 1;
    }

    public static function normalizarIniciales(string $nombre, ?string $iniciales = null): string {
        $iniciales = trim((string) $iniciales);
        if ($iniciales !== '') {
            return mb_strtoupper(mb_substr($iniciales, 0, 4));
        }

        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $valor  = '';

        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }

            $valor .= mb_substr(mb_strtoupper($parte), 0, 1);
            if (mb_strlen($valor) >= 4) {
                break;
            }
        }

        return mb_substr($valor, 0, 4);
    }

    private static function nullable($value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
