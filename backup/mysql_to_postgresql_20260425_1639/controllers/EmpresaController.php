<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/EmpresaModel.php';

class EmpresaController {

    public static function index(): void {
        header('Content-Type: application/json');
        requireLogin();

        global $pdo;
        $session     = sessionData();
        $idUsuario   = (int) $session['id_usuario'];
        $empresas    = EmpresaModel::getByUsuario($pdo, $idUsuario);
        $ultimaUsada = EmpresaModel::getUltimaUsada($pdo, $idUsuario);
        $idUltima    = (int) ($ultimaUsada['id'] ?? 0);

        $empresas = array_map(static function (array $empresa) use ($idUltima): array {
            $empresa['es_ultima_usada'] = $idUltima > 0 && (int) ($empresa['id'] ?? 0) === $idUltima;
            return $empresa;
        }, $empresas);

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
        $dui        = EmpresaModel::normalizeDui($_POST['dui'] ?? '');
        $nit        = EmpresaModel::normalizeNit($_POST['nit'] ?? '');
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

        if (!EmpresaModel::isValidDui($dui)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'El DUI debe tener formato 12345678-9.']);
            return;
        }

        if (!EmpresaModel::isValidNit($nit)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'data' => null, 'message' => 'El NIT debe tener formato 0000-000000-000-0.']);
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
