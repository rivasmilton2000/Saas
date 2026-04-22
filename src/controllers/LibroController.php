<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';

class LibroController {

    public static function index(): void {
        header('Content-Type: application/json');
        requireLogin();

        global $pdo;
        $session   = sessionData();
        $idUsuario = (int) $session['id_usuario'];
        $idEmpresa = isset($_GET['id_empresa']) ? (int) $_GET['id_empresa'] : 0;
        $tipo      = trim((string) ($_GET['tipo'] ?? ''));

        if ($idEmpresa > 0) {
            $empresa = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);
            if (!$empresa) {
                http_response_code(403);
                echo json_encode(['success' => false, 'data' => null, 'message' => 'Empresa no encontrada o sin permiso.']);
                return;
            }

            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);

            $libros = $tipo !== ''
                ? LibroModel::getByEmpresaYTipo($pdo, $idEmpresa, $tipo)
                : LibroModel::getByEmpresa($pdo, $idEmpresa);
        } else {
            $libros = $tipo !== ''
                ? LibroModel::getByUsuarioAndTipo($pdo, $idUsuario, $tipo)
                : LibroModel::getByUsuario($pdo, $idUsuario);
        }

        echo json_encode([
            'success' => true,
            'data'    => $libros,
            'message' => '',
        ]);
    }

    public static function store(): void {
        header('Content-Type: application/json');
        requireLogin();

        global $pdo;
        $session   = sessionData();
        $idUsuario = (int) $session['id_usuario'];
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $tipo      = trim((string) ($_POST['tipo'] ?? ''));
        $mes       = (int) ($_POST['mes'] ?? 0);
        $anio      = (int) ($_POST['anio'] ?? 0);

        if ($idEmpresa <= 0 || $tipo === '' || $mes < 1 || $mes > 12 || $anio < 2000) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'data'    => null,
                'message' => 'Debes enviar id_empresa, tipo, mes y anio validos.',
            ]);
            return;
        }

        if (!in_array($tipo, ['compras', 'ventas_consumidor', 'ventas_contribuyente', 'retencion_iva'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Tipo de libro invalido.']);
            return;
        }

        $empresa = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);
        if (!$empresa) {
            http_response_code(403);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Empresa no encontrada o sin permiso.']);
            return;
        }

        EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);

        $existente = LibroModel::findByEmpresaTipoPeriodo($pdo, $idEmpresa, $tipo, $mes, $anio);
        if ($existente) {
            echo json_encode([
                'success' => true,
                'data'    => $existente,
                'message' => 'El libro ya existia para ese periodo.',
                'exists'  => true,
            ]);
            return;
        }

        $idLibro = LibroModel::create($pdo, [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'tipo'       => $tipo,
            'mes'        => $mes,
            'anio'       => $anio,
        ]);

        $libro = LibroModel::getById($pdo, $idLibro, $idUsuario);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data'    => $libro,
            'message' => 'Libro creado correctamente.',
            'exists'  => false,
        ]);
    }
}
