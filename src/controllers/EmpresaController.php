<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/EmpresaModel.php';

class EmpresaController {

    public static function index(): void {
        header('Content-Type: application/json');
        requireLogin();

        global $pdo;
        $session  = sessionData();
        $empresas = EmpresaModel::getByUsuario($pdo, (int) $session['id_usuario']);

        echo json_encode([
            'success' => true,
            'data'    => $empresas,
            'message' => '',
        ]);
    }

    public static function store(): void {
        header('Content-Type: application/json');
        requireLogin();

        global $pdo;
        $session    = sessionData();
        $idUsuario  = (int) $session['id_usuario'];
        $nombre     = trim((string) ($_POST['nombre'] ?? ''));
        $iniciales  = trim((string) ($_POST['iniciales'] ?? ''));
        $color      = trim((string) ($_POST['color_emblema'] ?? '#f97316'));
        $dui        = trim((string) ($_POST['dui'] ?? ''));
        $nit        = trim((string) ($_POST['nit'] ?? ''));
        $nrc        = trim((string) ($_POST['nrc'] ?? ''));
        $tipoLegal  = trim((string) ($_POST['tipo_legal'] ?? 'natural'));

        if ($nombre === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'El nombre de la empresa es requerido.']);
            return;
        }

        if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Tipo legal invalido.']);
            return;
        }

        if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Ya tienes una empresa con ese NRC.']);
            return;
        }

        if ($nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Ya tienes una empresa con ese NIT.']);
            return;
        }

        $idEmpresa = EmpresaModel::create($pdo, [
            'id_usuario'    => $idUsuario,
            'nombre'        => $nombre,
            'iniciales'     => $iniciales,
            'color_emblema' => $color,
            'dui'           => $dui,
            'nit'           => $nit,
            'nrc'           => $nrc,
            'tipo_legal'    => $tipoLegal,
        ]);

        $empresa = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data'    => $empresa,
            'message' => 'Empresa creada correctamente.',
        ]);
    }
}
