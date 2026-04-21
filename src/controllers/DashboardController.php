<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

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
        $usuarios         = [];
        $usuariosStats    = [
            'total'  => 0,
            'admins' => 0,
            'users'  => 0,
        ];

        if ($empresaActivaId !== null) {
            $empresaActiva = EmpresaModel::getById($pdo, $empresaActivaId, $idUsuario);
        }

        if ($libroActivoId !== null) {
            $libroActivo = LibroModel::getById($pdo, $libroActivoId, $idUsuario);
        }

        if (isAdmin()) {
            $usuarios = UsuarioModel::getActivos($pdo);
            $usuariosStats = UsuarioModel::getResumen($pdo);
        }

        return [
            'empresas'       => $empresas,
            'libros'         => $librosCompras,
            'libros_count'   => count($librosCompras),
            'cuota'          => $cuota,
            'empresa_activa' => $empresaActiva,
            'libro_activo'   => $libroActivo,
            'usuarios'       => $usuarios,
            'usuarios_stats' => $usuariosStats,
        ];
    }
}
