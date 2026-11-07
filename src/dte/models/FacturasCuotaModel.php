<?php

class FacturasCuotaModel {

    private const QUOTA_DISABLED = true;
    private const UNLIMITED_TOTAL = 999999;

    public static function getByUsuario(PDO $pdo, int $idUsuario): ?array {
        $stmt = $pdo->prepare("SELECT * FROM dte_facturas_disponibles WHERE id_usuario = ?");
        $stmt->execute([$idUsuario]);

        $cuota = $stmt->fetch();
        if (self::quotaDeshabilitada()) {
            if (!$cuota) {
                return self::resumenIlimitado([
                    'id_usuario' => $idUsuario,
                ]);
            }

            return self::resumenIlimitado($cuota);
        }

        if (!$cuota) {
            return null;
        }

        return self::completarResumen($cuota);
    }

    public static function ensure(PDO $pdo, int $idUsuario, int $total = 50): array {
        if (self::quotaDeshabilitada()) {
            return self::getByUsuario($pdo, $idUsuario) ?? self::resumenIlimitado([
                'id_usuario' => $idUsuario,
            ]);
        }

        $actual = self::getByUsuario($pdo, $idUsuario);
        if ($actual !== null) {
            return $actual;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO dte_facturas_disponibles (id_usuario, total, consumidas) VALUES (?, ?, 0)"
        );
        $stmt->execute([$idUsuario, $total]);

        return self::getByUsuario($pdo, $idUsuario) ?? self::resumenVacio();
    }

    public static function tieneCuota(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        if (self::quotaDeshabilitada()) {
            return true;
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM dte_facturas_disponibles
             WHERE id_usuario = ? AND (total - consumidas) >= ?"
        );
        $stmt->execute([$idUsuario, $cantidad]);

        return $stmt->fetch() !== false;
    }

    public static function decrementar(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        if (self::quotaDeshabilitada()) {
            return true;
        }

        if ($cantidad <= 0) {
            return true;
        }

        if (!self::tieneCuota($pdo, $idUsuario, $cantidad)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE dte_facturas_disponibles
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
            'ilimitada'   => false,
        ];
    }

    public static function quotaDeshabilitada(): bool {
        return self::QUOTA_DISABLED;
    }

    public static function formatDisponibleLabel(array $cuota): string {
        if (!empty($cuota['ilimitada'])) {
            return 'Sin limite';
        }

        return (int) ($cuota['disponibles'] ?? 0) . ' / ' . (int) ($cuota['total'] ?? 0);
    }

    public static function formatRestanteLabel(?int $restante, array $cuota): string {
        if (!empty($cuota['ilimitada'])) {
            return 'Sin limite';
        }

        return (string) max(0, (int) $restante);
    }

    private static function resumenIlimitado(array $cuota): array {
        return array_merge($cuota, [
            'total'       => self::UNLIMITED_TOTAL,
            'consumidas'  => 0,
            'disponibles' => self::UNLIMITED_TOTAL,
            'porcentaje'  => 0,
            'ilimitada'   => true,
        ]);
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
            'ilimitada'   => false,
        ]);
    }
}
