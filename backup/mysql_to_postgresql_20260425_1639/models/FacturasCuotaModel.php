<?php

class FacturasCuotaModel {

    public static function getByUsuario(PDO $pdo, int $idUsuario): ?array {
        $stmt = $pdo->prepare("SELECT * FROM facturas_disponibles WHERE id_usuario = ?");
        $stmt->execute([$idUsuario]);

        $cuota = $stmt->fetch();
        if (!$cuota) {
            return null;
        }

        return self::completarResumen($cuota);
    }

    public static function ensure(PDO $pdo, int $idUsuario, int $total = 50): array {
        $actual = self::getByUsuario($pdo, $idUsuario);
        if ($actual !== null) {
            return $actual;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO facturas_disponibles (id_usuario, total, consumidas) VALUES (?, ?, 0)"
        );
        $stmt->execute([$idUsuario, $total]);

        return self::getByUsuario($pdo, $idUsuario) ?? self::resumenVacio();
    }

    public static function tieneCuota(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM facturas_disponibles
             WHERE id_usuario = ? AND (total - consumidas) >= ?"
        );
        $stmt->execute([$idUsuario, $cantidad]);

        return $stmt->fetch() !== false;
    }

    public static function decrementar(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        if ($cantidad <= 0) {
            return true;
        }

        if (!self::tieneCuota($pdo, $idUsuario, $cantidad)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE facturas_disponibles
             SET consumidas = consumidas + ?
             WHERE id_usuario = ?"
        );
        $stmt->execute([$cantidad, $idUsuario]);

        return true;
    }

    public static function resumenVacio(): array {
        return [
            'total'       => 0,
            'consumidas'  => 0,
            'disponibles' => 0,
            'porcentaje'  => 0,
        ];
    }

    private static function completarResumen(array $cuota): array {
        $total       = (int) ($cuota['total'] ?? 0);
        $consumidas  = (int) ($cuota['consumidas'] ?? 0);
        $disponibles = max(0, $total - $consumidas);
        $porcentaje  = $total > 0 ? round(($consumidas / $total) * 100, 1) : 0;

        return array_merge($cuota, [
            'total'       => $total,
            'consumidas'  => $consumidas,
            'disponibles' => $disponibles,
            'porcentaje'  => $porcentaje,
        ]);
    }
}
