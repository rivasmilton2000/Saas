<?php
require_once __DIR__ . '/FacturasCuotaModel.php';

class FacturaDisponibleModel {

    public static function getByUsuario(PDO $pdo, int $idUsuario): ?array {
        return FacturasCuotaModel::getByUsuario($pdo, $idUsuario);
    }

    public static function decrementar(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        return FacturasCuotaModel::decrementar($pdo, $idUsuario, $cantidad);
    }

    public static function tieneCuota(PDO $pdo, int $idUsuario, int $cantidad = 1): bool {
        return FacturasCuotaModel::tieneCuota($pdo, $idUsuario, $cantidad);
    }
}
