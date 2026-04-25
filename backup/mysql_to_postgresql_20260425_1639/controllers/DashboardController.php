<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../services/CentroMandoService.php';

class DashboardController {

    public static function getData(int $idUsuario): array {
        global $pdo;

        $empresas        = EmpresaModel::getByUsuario($pdo, $idUsuario);
        $libros          = LibroModel::getByUsuario($pdo, $idUsuario);
        $cuota           = FacturasCuotaModel::ensure($pdo, $idUsuario);
        $empresaActivaId = getActiveEmpresaId();
        $libroActivoId   = getActiveLibroId();
        $empresaActiva   = null;
        $libroActivo     = null;
        $usuarios        = [];
        $usuariosStats   = [
            'total'  => 0,
            'admins' => 0,
            'users'  => 0,
        ];
        $librosPorTipo = [];

        foreach (getLibroModules() as $tipo => $modulo) {
            $librosPorTipo[$tipo] = 0;
        }

        foreach ($libros as $libro) {
            $tipo = (string) ($libro['tipo'] ?? '');
            if (!array_key_exists($tipo, $librosPorTipo)) {
                $librosPorTipo[$tipo] = 0;
            }

            $librosPorTipo[$tipo]++;
        }

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

        $centroMando = CentroMandoService::build($empresas, $libros, $cuota, $pdo, $idUsuario);

        return [
            'empresas'       => $empresas,
            'libros'         => $libros,
            'libros_count'   => count($libros),
            'libros_by_type' => $librosPorTipo,
            'cuota'          => $cuota,
            'empresa_activa' => $empresaActiva,
            'libro_activo'   => $libroActivo,
            'usuarios'       => $usuarios,
            'usuarios_stats' => $usuariosStats,
            'centro_mando'   => $centroMando,
        ];
    }
}
