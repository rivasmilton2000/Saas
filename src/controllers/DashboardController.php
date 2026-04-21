<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';

class DashboardController {

    public static function getData(int $idUsuario): array {
        global $pdo;

        $empresas         = EmpresaModel::getByUsuario($pdo, $idUsuario);
        $librosCompras    = LibroModel::getByUsuarioAndTipo($pdo, $idUsuario, 'compras');
        $cuota            = FacturasCuotaModel::ensure($pdo, $idUsuario);
        $empresaActivaId  = getActiveEmpresaId();
        $libroActivoId    = getActiveLibroId();
        $empresaActiva    = null;
        $libroActivo      = null;

        if ($empresaActivaId !== null) {
            $empresaActiva = EmpresaModel::getById($pdo, $empresaActivaId, $idUsuario);
        }

        if ($libroActivoId !== null) {
            $libroActivo = LibroModel::getById($pdo, $libroActivoId, $idUsuario);
        }

        return [
            'empresas'       => $empresas,
            'libros'         => $librosCompras,
            'libros_count'   => count($librosCompras),
            'cuota'          => $cuota,
            'empresa_activa' => $empresaActiva,
            'libro_activo'   => $libroActivo,
        ];
    }
}
