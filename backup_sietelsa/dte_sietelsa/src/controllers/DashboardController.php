<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class DashboardController {

    public static function getData(int $idUsuario, bool $allowCache = true): array {
        global $pdo;

        $empresaActivaId = getActiveEmpresaId();
        $libroActivoId   = getActiveLibroId();
        $esAdmin = isAdmin();

        $resolver = static function () use ($pdo, $idUsuario, $empresaActivaId, $libroActivoId, $esAdmin): array {
            $empresas = EmpresaModel::getByUsuario($pdo, $idUsuario);
            $libros   = LibroModel::getByUsuario($pdo, $idUsuario);
            $cuota    = FacturasCuotaModel::ensure($pdo, $idUsuario);

            $empresaActiva = null;
            if ($empresaActivaId !== null) {
                foreach ($empresas as $empresa) {
                    if ((int) ($empresa['id'] ?? 0) === $empresaActivaId) {
                        $empresaActiva = $empresa;
                        break;
                    }
                }
            }

            $libroActivo = null;
            if ($libroActivoId !== null) {
                foreach ($libros as $libro) {
                    if ((int) ($libro['id'] ?? 0) === $libroActivoId) {
                        $libroActivo = $libro;
                        break;
                    }
                }
            }

            $usuarios = [];
            $usuariosStats = [
                'total' => 0,
                'admins' => 0,
                'users' => 0,
            ];

            if ($esAdmin) {
                $usuarios = UsuarioModel::getActivos($pdo);
                $usuariosStats = UsuarioModel::getResumen($pdo);
            }

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

            return [
                'empresas' => $empresas,
                'libros' => $libros,
                'libros_count' => count($libros),
                'libros_by_type' => $librosPorTipo,
                'cuota' => $cuota,
                'empresa_activa' => $empresaActiva,
                'libro_activo' => $libroActivo,
                'usuarios' => $usuarios,
                'usuarios_stats' => $usuariosStats,
            ];
        };

        if ($allowCache && function_exists('sietelsa_cache_remember')) {
            $cacheKey = 'dte_dashboard_data_v2'
                . '|u:' . $idUsuario
                . '|a:' . ($esAdmin ? '1' : '0')
                . '|e:' . (string) (int) ($empresaActivaId ?? 0)
                . '|l:' . (string) (int) ($libroActivoId ?? 0);
            $cached = sietelsa_cache_remember('query', $cacheKey, 20, $resolver);
            if (is_array($cached)) {
                return $cached;
            }
        }

        return $resolver();
    }
}
