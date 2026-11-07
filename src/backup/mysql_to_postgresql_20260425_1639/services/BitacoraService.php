<?php
require_once __DIR__ . '/../models/BitacoraModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class BitacoraService {

    public static function registrar(
        PDO $pdo,
        int $idUsuario,
        string $modulo,
        string $accion,
        string $descripcion,
        array $opciones = []
    ): void {
        if ($idUsuario <= 0) {
            return;
        }

        try {
            $usuario = UsuarioModel::getById($pdo, $idUsuario, false);
            $username = trim((string) ($opciones['username'] ?? ($usuario['username'] ?? 'usuario')));
            $rol = trim((string) ($opciones['rol'] ?? ($usuario['rol'] ?? 'user')));
            $contexto = $opciones['contexto'] ?? null;

            BitacoraModel::registrar($pdo, [
                'id_usuario'         => $idUsuario,
                'username_snapshot'  => $username !== '' ? $username : 'usuario',
                'rol_snapshot'       => $rol !== '' ? $rol : 'user',
                'modulo'             => trim($modulo) !== '' ? trim($modulo) : 'general',
                'accion'             => trim($accion) !== '' ? trim($accion) : 'accion',
                'descripcion'        => trim($descripcion) !== '' ? trim($descripcion) : 'Movimiento registrado.',
                'entidad_tipo'       => $opciones['entidad_tipo'] ?? null,
                'entidad_id'         => $opciones['entidad_id'] ?? null,
                'contexto_json'      => is_array($contexto) ? json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip_address'         => self::resolveIp(),
                'user_agent'         => self::resolveUserAgent(),
            ]);
        } catch (Throwable $exception) {
            return;
        }
    }

    public static function obtenerVista(PDO $pdo, int $idUsuario, bool $esAdmin, ?int $filtroUsuario = null): array {
        $idConsulta = $esAdmin
            ? (($filtroUsuario !== null && $filtroUsuario > 0) ? $filtroUsuario : null)
            : $idUsuario;

        $movimientos = BitacoraModel::getMovimientos($pdo, $idConsulta, $esAdmin ? 500 : 250);
        $usuarios = $esAdmin ? BitacoraModel::getUsuariosConActividad($pdo) : [];
        $grupos = [];

        foreach ($movimientos as $movimiento) {
            $grupoId = (int) ($movimiento['id_usuario'] ?? 0);
            $username = (string) ($movimiento['username_actual'] ?? $movimiento['username_snapshot'] ?? 'usuario');
            $rol = (string) ($movimiento['rol_actual'] ?? $movimiento['rol_snapshot'] ?? 'user');

            if (!isset($grupos[$grupoId])) {
                $grupos[$grupoId] = [
                    'id_usuario'        => $grupoId,
                    'username'          => $username,
                    'rol'               => $rol,
                    'total_movimientos' => 0,
                    'ultima_actividad'  => (string) ($movimiento['created_at'] ?? ''),
                    'movimientos'       => [],
                ];
            }

            $grupos[$grupoId]['total_movimientos']++;
            $grupos[$grupoId]['movimientos'][] = self::mapMovimiento($movimiento);
        }

        usort($usuarios, static function (array $a, array $b): int {
            return strcmp((string) ($b['ultima_actividad'] ?? ''), (string) ($a['ultima_actividad'] ?? ''));
        });

        $gruposOrdenados = array_values($grupos);
        usort($gruposOrdenados, static function (array $a, array $b): int {
            return strcmp((string) ($b['ultima_actividad'] ?? ''), (string) ($a['ultima_actividad'] ?? ''));
        });

        return [
            'resumen' => [
                'total_movimientos' => count($movimientos),
                'usuarios_activos'  => $esAdmin ? count($usuarios) : 1,
                'ultima_actividad'  => !empty($movimientos[0]['created_at']) ? (string) $movimientos[0]['created_at'] : null,
            ],
            'usuarios' => $usuarios,
            'grupos'   => $gruposOrdenados,
        ];
    }

    private static function mapMovimiento(array $movimiento): array {
        $contexto = [];
        $rawContexto = $movimiento['contexto_json'] ?? null;
        if (is_string($rawContexto) && trim($rawContexto) !== '') {
            $decodificado = json_decode($rawContexto, true);
            if (is_array($decodificado)) {
                $contexto = $decodificado;
            }
        }

        return [
            'id'              => (int) ($movimiento['id'] ?? 0),
            'modulo'          => (string) ($movimiento['modulo'] ?? 'general'),
            'accion'          => (string) ($movimiento['accion'] ?? 'accion'),
            'descripcion'     => (string) ($movimiento['descripcion'] ?? ''),
            'fecha_hora'      => (string) ($movimiento['created_at'] ?? ''),
            'fecha_hora_texto'=> self::formatFechaHora((string) ($movimiento['created_at'] ?? '')),
            'ip_address'      => (string) ($movimiento['ip_address'] ?? ''),
            'contexto'        => $contexto,
            'contexto_texto'  => self::formatContexto($contexto),
            'entidad_tipo'    => (string) ($movimiento['entidad_tipo'] ?? ''),
            'entidad_id'      => isset($movimiento['entidad_id']) ? (int) $movimiento['entidad_id'] : null,
        ];
    }

    private static function formatFechaHora(string $fechaHora): string {
        if (trim($fechaHora) === '') {
            return '-';
        }

        try {
            $fecha = new DateTimeImmutable($fechaHora, new DateTimeZone('America/El_Salvador'));
            return $fecha->format('d/m/Y h:i A');
        } catch (Throwable $exception) {
            return $fechaHora;
        }
    }

    private static function formatContexto(array $contexto): string {
        $partes = [];

        if (!empty($contexto['empresa'])) {
            $partes[] = 'Empresa: ' . (string) $contexto['empresa'];
        }

        if (!empty($contexto['libro'])) {
            $partes[] = 'Libro: ' . (string) $contexto['libro'];
        }

        if (!empty($contexto['periodo'])) {
            $partes[] = 'Periodo: ' . (string) $contexto['periodo'];
        }

        if (!empty($contexto['documentos'])) {
            $partes[] = 'Documentos: ' . (string) $contexto['documentos'];
        }

        if (!empty($contexto['formato'])) {
            $partes[] = 'Formato: ' . strtoupper((string) $contexto['formato']);
        }

        if (!empty($contexto['usuario_objetivo'])) {
            $partes[] = 'Usuario: ' . (string) $contexto['usuario_objetivo'];
        }

        if (!empty($contexto['detalle'])) {
            $partes[] = (string) $contexto['detalle'];
        }

        return implode(' | ', $partes);
    }

    private static function resolveIp(): ?string {
        $keys = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $value));
                return $parts[0] !== '' ? $parts[0] : null;
            }

            return $value;
        }

        return null;
    }

    private static function resolveUserAgent(): ?string {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $userAgent !== '' ? substr($userAgent, 0, 255) : null;
    }
}
