<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../services/BitacoraService.php';
require_once __DIR__ . '/../services/LibroVistaService.php';
require_once __DIR__ . '/../services/PageVisitService.php';

requireLogin();
requireUser();

$tipoLibro = trim((string) ($tipoLibroPagina ?? ''));
$modulo    = getLibroModule($tipoLibro);

if ($modulo === null) {
    http_response_code(404);
    exit('Modulo no disponible.');
}

$session          = sessionData();
$idUsuario        = (int) $session['id_usuario'];
$basePath         = '../';
$flashKey         = 'libro_' . $tipoLibro;
$importSessionKey = '_import_result_' . $tipoLibro;
$rutaModulo       = '/Saas/src/' . ltrim((string) ($modulo['ruta'] ?? ''), '/');
$meses            = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
$anioActual = (int) date('Y');
$anios      = range($anioActual + 1, max($anioActual - 4, 2024));

$toHexColor = static function (?string $color): string {
    $color = trim((string) $color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#4b49ac';
};
$hexToRgba = static function (string $color, float $alpha) use ($toHexColor): string {
    $color = ltrim($toHexColor($color), '#');
    $red   = hexdec(substr($color, 0, 2));
    $green = hexdec(substr($color, 2, 2));
    $blue  = hexdec(substr($color, 4, 2));

    return 'rgba(' . $red . ', ' . $green . ', ' . $blue . ', ' . $alpha . ')';
};
$accentColor     = $toHexColor((string) ($modulo['accent_color'] ?? '#4b49ac'));
$accentSoft      = $hexToRgba($accentColor, 0.12);
$accentSoftStrong = $hexToRgba($accentColor, 0.22);
$registrarBitacora = static function (
    string $accion,
    string $descripcion,
    array $opciones = []
) use ($pdo, $idUsuario, $modulo, $session): void {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        (string) ($modulo['tipo'] ?? 'libros'),
        $accion,
        $descripcion,
        array_merge([
            'username' => (string) ($session['username'] ?? ''),
            'rol'      => (string) ($session['rol'] ?? 'user'),
        ], $opciones)
    );
};
PageVisitService::track(
    $pdo,
    $session,
    'modulo_' . (string) ($modulo['tipo'] ?? 'libro'),
    (string) ($modulo['nombre'] ?? 'Modulo')
);

$leerDocumentosSubidos = static function (array $files): array {
    $documentos = [];
    $nombres    = $files['name'] ?? [];
    $temporales = $files['tmp_name'] ?? [];
    $errores    = $files['error'] ?? [];

    if (!is_array($nombres)) {
        return [];
    }

    foreach ($nombres as $indice => $nombreArchivo) {
        $error   = $errores[$indice] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = $temporales[$indice] ?? '';

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        $contenido = file_get_contents($tmpName);
        if ($contenido === false) {
            continue;
        }

        $documentos[] = [
            'nombre_archivo' => trim((string) $nombreArchivo) !== '' ? trim((string) $nombreArchivo) : ('documento_' . ($indice + 1) . '.json'),
            'payload'        => $contenido,
        ];
    }

    return $documentos;
};

$validarEmpresa = static function (PDO $pdo, int $idUsuario, array $input): array {
    $nombre       = trim((string) ($input['nombre'] ?? ''));
    $iniciales    = trim((string) ($input['iniciales'] ?? ''));
    $colorEmblema = trim((string) ($input['color_emblema'] ?? '#f97316'));
    $dui          = EmpresaModel::normalizeDui($input['dui'] ?? '');
    $nit          = EmpresaModel::normalizeNit($input['nit'] ?? '');
    $nrc          = trim((string) ($input['nrc'] ?? ''));
    $tipoLegal    = trim((string) ($input['tipo_legal'] ?? 'natural'));
    $oldInput     = [
        'nombre'        => $nombre,
        'iniciales'     => strtoupper($iniciales),
        'color_emblema' => $colorEmblema !== '' ? $colorEmblema : '#f97316',
        'dui'           => $dui ?? '',
        'nit'           => $nit ?? '',
        'nrc'           => $nrc,
        'tipo_legal'    => in_array($tipoLegal, ['natural', 'juridica'], true) ? $tipoLegal : 'natural',
    ];

    if ($nombre === '') {
        return ['ok' => false, 'message' => 'Debes escribir el nombre de la empresa.', 'old' => $oldInput];
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        return ['ok' => false, 'message' => 'Tipo legal invalido.', 'old' => $oldInput];
    }

    if (!EmpresaModel::isValidDui($dui)) {
        return ['ok' => false, 'message' => 'El DUI debe tener formato 12345678-9.', 'old' => $oldInput];
    }

    if (!EmpresaModel::isValidNit($nit)) {
        return ['ok' => false, 'message' => 'El NIT debe tener formato 0000-000000-000-0.', 'old' => $oldInput];
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
        return ['ok' => false, 'message' => 'Ya tienes una empresa con ese NRC.', 'old' => $oldInput];
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
        return ['ok' => false, 'message' => 'Ya tienes una empresa con ese NIT.', 'old' => $oldInput];
    }

    return [
        'ok'   => true,
        'data' => [
            'id_usuario'    => $idUsuario,
            'nombre'        => $nombre,
            'iniciales'     => $iniciales,
            'color_emblema' => $colorEmblema,
            'dui'           => $dui,
            'nit'           => $nit,
            'nrc'           => $nrc,
            'tipo_legal'    => $tipoLegal,
        ],
        'old'  => $oldInput,
    ];
};

if (count(EmpresaModel::getByUsuario($pdo, $idUsuario)) === 1 && getActiveEmpresaId() === null) {
    $empresaUnica = EmpresaModel::getByUsuario($pdo, $idUsuario)[0];
    setActiveEmpresaId((int) $empresaUnica['id']);
    EmpresaModel::marcarUltimaUsada($pdo, (int) $empresaUnica['id'], $idUsuario);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'create_company') {
        $validacion = $validarEmpresa($pdo, $idUsuario, $_POST);

        if (!($validacion['ok'] ?? false)) {
            setFlash($flashKey, (string) ($validacion['message'] ?? 'No se pudo crear la empresa.'), 'danger', [
                'open_modal' => 'company-create',
                'old'        => $validacion['old'] ?? [],
            ]);
        } else {
            $idEmpresa = EmpresaModel::create($pdo, $validacion['data']);
            setActiveEmpresaId($idEmpresa);
            setActiveLibroId(null);
            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            $registrarBitacora(
                'crear_empresa',
                'Creo la empresa ' . (string) ($validacion['data']['nombre'] ?? 'Empresa') . ' desde ' . (string) ($modulo['nombre'] ?? 'el modulo') . '.',
                [
                    'entidad_tipo' => 'empresa',
                    'entidad_id'   => $idEmpresa,
                    'contexto'     => [
                        'empresa' => (string) ($validacion['data']['nombre'] ?? 'Empresa'),
                    ],
                ]
            );
            setFlash($flashKey, 'Empresa creada correctamente.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'select_company') {
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $empresa   = EmpresaModel::getById($pdo, $idEmpresa, $idUsuario);

        if (!$empresa) {
            setFlash($flashKey, 'Selecciona una empresa valida.', 'danger', [
                'open_modal' => 'company-select',
            ]);
        } else {
            setActiveEmpresaId($idEmpresa);
            setActiveLibroId(null);
            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            $registrarBitacora(
                'seleccionar_empresa',
                'Selecciono la empresa ' . (string) ($empresa['nombre'] ?? 'Empresa') . ' en ' . (string) ($modulo['nombre'] ?? 'el modulo') . '.',
                [
                    'entidad_tipo' => 'empresa',
                    'entidad_id'   => (int) ($empresa['id'] ?? 0),
                    'contexto'     => [
                        'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                    ],
                ]
            );
            setFlash($flashKey, 'Empresa activa actualizada.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'create_book') {
        $idEmpresa = getActiveEmpresaId();
        $empresa   = $idEmpresa ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $mes       = (int) ($_POST['mes'] ?? 0);
        $anio      = (int) ($_POST['anio'] ?? 0);

        if (!$empresa) {
            setFlash($flashKey, 'Primero debes seleccionar una empresa.', 'danger', [
                'open_modal' => 'company-select',
            ]);
        } elseif ($mes < 1 || $mes > 12 || $anio < 2000) {
            setFlash($flashKey, 'Debes elegir un mes y año válidos.', 'danger', [
                'open_modal' => 'period-create',
            ]);
        } else {
            $existente = LibroModel::findByEmpresaTipoPeriodo($pdo, (int) $empresa['id'], $tipoLibro, $mes, $anio);

            if ($existente) {
                setActiveLibroId((int) $existente['id']);
                EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                $registrarBitacora(
                    'abrir_libro_existente',
                    'Abro un libro existente de ' . (string) ($modulo['nombre'] ?? 'libros') . '.',
                    [
                        'entidad_tipo' => 'libro',
                        'entidad_id'   => (int) ($existente['id'] ?? 0),
                        'contexto'     => [
                            'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                            'periodo' => str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio,
                            'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
                        ],
                    ]
                );
                setFlash($flashKey, 'Ese libro ya existia. Se abrio el periodo guardado.', 'info');
            } else {
                $idLibro = LibroModel::create($pdo, [
                    'id_empresa' => (int) $empresa['id'],
                    'id_usuario' => $idUsuario,
                    'tipo'       => $tipoLibro,
                    'mes'        => $mes,
                    'anio'       => $anio,
                ]);

                setActiveLibroId($idLibro);
                EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                $registrarBitacora(
                    'crear_libro',
                    'Creo un libro de ' . (string) ($modulo['nombre'] ?? 'libros') . '.',
                    [
                        'entidad_tipo' => 'libro',
                        'entidad_id'   => $idLibro,
                        'contexto'     => [
                            'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                            'periodo' => str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio,
                            'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
                        ],
                    ]
                );
                setFlash($flashKey, 'Libro creado correctamente.', 'success');
            }
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'import_json') {
        $idLibro     = getActiveLibroId();
        $libroActual = $idLibro ? LibroModel::getById($pdo, $idLibro, $idUsuario) : null;
        $documentos  = $leerDocumentosSubidos($_FILES['json_files'] ?? []);

        if ($libroActual === null || ($libroActual['tipo'] ?? '') !== $tipoLibro) {
            setFlash($flashKey, 'Debes abrir un libro valido antes de importar.', 'danger', [
                'open_modal' => 'book-choice',
            ]);
        } elseif ($documentos === []) {
            setFlash($flashKey, 'Selecciona al menos un archivo JSON.', 'danger', [
                'open_modal' => 'import-json',
            ]);
        } else {
            $resultado = LibroVistaService::importar($pdo, $libroActual, $idUsuario, $documentos);
            $_SESSION[$importSessionKey] = $resultado;
            if (($resultado['success'] ?? false) === true) {
                $registrarBitacora(
                    'importar_json',
                    'Importo documentos al libro ' . (string) ($modulo['nombre'] ?? 'Libro') . '.',
                    [
                        'entidad_tipo' => 'libro',
                        'entidad_id'   => (int) ($libroActual['id'] ?? 0),
                        'contexto'     => [
                            'empresa'    => (string) ($libroActual['empresa_nombre'] ?? ''),
                            'periodo'    => str_pad((string) ($libroActual['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libroActual['anio'] ?? ''),
                            'libro'      => (string) ($modulo['nombre'] ?? 'Libro'),
                            'documentos' => count($documentos),
                            'detalle'    => 'Importadas: ' . (int) ($resultado['data']['importadas'] ?? 0),
                        ],
                    ]
                );
            }
            setFlash(
                $flashKey,
                (string) ($resultado['message'] ?? 'Importacion procesada.'),
                ($resultado['success'] ?? false) ? 'success' : 'warning'
            );
        }

        header('Location: ' . $rutaModulo);
        exit;
    }
}

if (isset($_GET['open'])) {
    $idLibroAbrir = (int) $_GET['open'];
    $libroAbrir   = LibroModel::getById($pdo, $idLibroAbrir, $idUsuario);

    if (!$libroAbrir || ($libroAbrir['tipo'] ?? '') !== $tipoLibro) {
        setFlash($flashKey, 'No se encontro el libro solicitado.', 'danger', [
            'open_modal' => 'book-choice',
        ]);
    } else {
        setActiveEmpresaId((int) $libroAbrir['id_empresa']);
        setActiveLibroId((int) $libroAbrir['id']);
        EmpresaModel::marcarUltimaUsada($pdo, (int) $libroAbrir['id_empresa'], $idUsuario);
        $registrarBitacora(
            'abrir_libro',
            'Abro un libro de ' . (string) ($modulo['nombre'] ?? 'Libro') . '.',
            [
                'entidad_tipo' => 'libro',
                'entidad_id'   => (int) ($libroAbrir['id'] ?? 0),
                'contexto'     => [
                    'empresa' => (string) ($libroAbrir['empresa_nombre'] ?? ''),
                    'periodo' => str_pad((string) ($libroAbrir['mes'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . (string) ($libroAbrir['anio'] ?? ''),
                    'libro'   => (string) ($modulo['nombre'] ?? 'Libro'),
                ],
            ]
        );
        setFlash($flashKey, 'Libro cargado correctamente.', 'success');
    }

    header('Location: ' . $rutaModulo);
    exit;
}

if (isset($_GET['clear_book'])) {
    setActiveLibroId(null);
    $registrarBitacora('limpiar_libro_activo', 'Limpio el libro activo de ' . (string) ($modulo['nombre'] ?? 'Libro') . '.');
    setFlash($flashKey, 'Libro activo limpiado.', 'info', [
        'open_modal' => 'book-choice',
    ]);
    header('Location: ' . $rutaModulo);
    exit;
}

$empresas         = EmpresaModel::getByUsuario($pdo, $idUsuario);
$empresaUltima    = EmpresaModel::getUltimaUsada($pdo, $idUsuario);
$idEmpresaActiva  = getActiveEmpresaId();
$empresaActiva    = $idEmpresaActiva ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;

if (!$empresaActiva && !empty($empresas)) {
    $empresaDefecto = $empresaUltima ?: $empresas[0];
    setActiveEmpresaId((int) $empresaDefecto['id']);
    $empresaActiva = EmpresaModel::getById($pdo, (int) $empresaDefecto['id'], $idUsuario);
}

$libros        = $empresaActiva ? LibroModel::getByEmpresaYTipo($pdo, (int) $empresaActiva['id'], $tipoLibro) : [];
$idLibroActivo = getActiveLibroId();
$libroActivo   = $idLibroActivo ? LibroModel::getById($pdo, $idLibroActivo, $idUsuario) : null;

if ($libroActivo && (($libroActivo['tipo'] ?? '') !== $tipoLibro || !$empresaActiva || (int) $libroActivo['id_empresa'] !== (int) $empresaActiva['id'])) {
    setActiveLibroId(null);
    $libroActivo = null;
}

$resultadoListado = [
    'success' => true,
    'message' => '',
    'data'    => [
        'columnas'          => array_keys($modulo['columnas'] ?? []),
        'registros'         => [],
        'filas'             => [],
        'totales'           => [],
        'cantidad_facturas' => 0,
    ],
];

if ($libroActivo) {
    $resultadoListado = LibroVistaService::listar($pdo, (int) $libroActivo['id'], $idUsuario, $tipoLibro);
}

$tablaData          = is_array($resultadoListado['data'] ?? null) ? $resultadoListado['data'] : [];
$columnas           = $tablaData['columnas'] ?? array_keys($modulo['columnas'] ?? []);
$filas              = $tablaData['filas'] ?? [];
$registros          = $tablaData['registros'] ?? [];
$cantidadFacturas   = (int) ($tablaData['cantidad_facturas'] ?? 0);
$totales            = is_array($tablaData['totales'] ?? null) ? $tablaData['totales'] : [];
$cuota              = FacturasCuotaModel::ensure($pdo, $idUsuario);
$flash              = getFlash($flashKey);
$flashMeta          = $flash['meta'] ?? [];
$importResult       = $_SESSION[$importSessionKey] ?? null;
$importData         = is_array($importResult['data'] ?? null) ? $importResult['data'] : [];
$empresaActivaNavbar = $empresaActiva;
$periodoLibroActivo = $libroActivo
    ? (($meses[(int) $libroActivo['mes']] ?? (string) $libroActivo['mes']) . ' ' . $libroActivo['anio'])
    : '';
$columnLabels       = $modulo['columnas'] ?? [];
$numericColumns     = $modulo['columnas_numericas'] ?? [];
$summaryCards       = LibroVistaService::construirResumenTarjetas($modulo, $resultadoListado);
$companyFormData    = array_merge([
    'nombre'        => '',
    'iniciales'     => '',
    'color_emblema' => '#f97316',
    'dui'           => '',
    'nit'           => '',
    'nrc'           => '',
    'tipo_legal'    => 'natural',
], is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : []);

unset($_SESSION[$importSessionKey]);

$modalInicial = trim((string) ($flashMeta['open_modal'] ?? ''));
if ($modalInicial === '') {
    if (empty($empresas)) {
        $modalInicial = 'company-create';
    } elseif (!$empresaActiva) {
        $modalInicial = 'company-select';
    } elseif (!$libroActivo) {
        $modalInicial = 'book-choice';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo')); ?> - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <style>
      :root {
        --module-accent: <?php echo htmlspecialchars($accentColor); ?>;
        --module-accent-soft: <?php echo htmlspecialchars($accentSoft); ?>;
        --module-accent-strong: <?php echo htmlspecialchars($accentSoftStrong); ?>;
      }

      .module-shell .card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
      }

      .module-hero {
        border-radius: 24px;
        background: linear-gradient(135deg, #ffffff 0%, var(--module-accent-soft) 100%);
        overflow: hidden;
      }

      .module-hero .card-body {
        padding: 1.75rem;
      }

      .module-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.85rem;
        color: var(--module-accent);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .module-title {
        margin: 0;
        color: #101827;
        font-size: 2rem;
        font-weight: 700;
      }

      .module-copy {
        margin-top: 0.75rem;
        max-width: 48rem;
        color: #5f6b7d;
        font-size: 0.96rem;
      }

      .module-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(15, 23, 42, 0.06);
        font-weight: 600;
        color: #122033;
      }

      .module-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.95rem;
        margin-top: 1.35rem;
      }

      .module-summary-card {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(15, 23, 42, 0.08);
      }

      .module-summary-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #6c7b90;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .module-summary-value {
        display: block;
        color: #111827;
        font-size: 1.35rem;
        font-weight: 700;
      }

      .module-summary-note {
        display: block;
        margin-top: 0.2rem;
        color: #6b7280;
        font-size: 0.78rem;
      }

      .module-actions-card {
        border-radius: 24px;
      }

      .btn-module {
        background: var(--module-accent);
        border-color: var(--module-accent);
        color: #fff;
      }

      .btn-module:hover,
      .btn-module:focus {
        background: var(--module-accent);
        border-color: var(--module-accent);
        color: #fff;
        box-shadow: 0 0 0 0.2rem var(--module-accent-soft);
      }

      .btn-module-soft {
        background: var(--module-accent-soft);
        border-color: transparent;
        color: #122033;
      }

      .btn-module-outline {
        border-color: var(--module-accent-strong);
        color: var(--module-accent);
      }

      .btn-module-outline:hover,
      .btn-module-outline:focus {
        background: var(--module-accent-soft);
        border-color: var(--module-accent-strong);
        color: var(--module-accent);
      }

      .module-search {
        min-width: 240px;
      }

      .module-empty-state {
        padding: 2rem 1.5rem;
        border: 1px dashed rgba(15, 23, 42, 0.14);
        border-radius: 20px;
        background: #f8fbff;
        text-align: center;
      }

      .module-table-wrap {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 22px;
        overflow: hidden;
      }

      .module-table-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 0.9rem 1.1rem;
        background: linear-gradient(135deg, #ffffff 0%, var(--module-accent-soft) 100%);
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      }

      .module-table {
        margin-bottom: 0;
      }

      .module-table th,
      .module-table td {
        white-space: nowrap;
        font-size: 0.84rem;
        vertical-align: middle;
      }

      .module-table thead th {
        background: #172033;
        color: #fff;
        border-color: #243349;
      }

      .module-table tbody tr.total-row td {
        background: var(--module-accent-soft);
        font-weight: 700;
      }

      .module-table tbody td.wrap-cell {
        white-space: normal;
        word-break: break-word;
        min-width: 160px;
      }

      .module-modal .modal-dialog {
        max-width: 760px;
      }

      .module-modal .modal-content {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 30px 70px rgba(15, 23, 42, 0.18);
      }

      .module-modal .modal-header {
        padding: 1.4rem 1.5rem 1rem;
        border-bottom: 0;
      }

      .module-modal .modal-body {
        padding: 0 1.5rem 1.5rem;
      }

      .module-modal .modal-footer {
        padding: 0 1.5rem 1.5rem;
        border-top: 0;
      }

      .module-modal-eyebrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: var(--module-accent-soft);
        color: var(--module-accent);
        font-size: 1.2rem;
      }

      .module-choice {
        display: block;
        padding: 1rem 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 20px;
        background: #fff;
        transition: all 0.2s ease;
      }

      .module-choice:hover {
        border-color: var(--module-accent-strong);
        background: var(--module-accent-soft);
      }

      .module-choice input[type="radio"] {
        display: none;
      }

      .module-choice input[type="radio"]:checked + .module-choice-body {
        border-color: var(--module-accent-strong);
      }

      .module-choice-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.35rem 0;
      }

      .module-company-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: var(--module-accent-soft);
        color: var(--module-accent);
        font-size: 0.76rem;
        font-weight: 700;
      }

      .module-period-preview {
        padding: 1rem 1rem;
        border-radius: 18px;
        background: linear-gradient(135deg, #ffffff 0%, var(--module-accent-soft) 100%);
        border: 1px solid var(--module-accent-strong);
      }

      .module-import-list {
        margin: 0;
        padding-left: 1.1rem;
        color: #556173;
      }

      @media (max-width: 1200px) {
        .module-summary-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 767px) {
        .module-hero .card-body,
        .module-actions-card .card-body {
          padding: 1.25rem;
        }

        .module-title {
          font-size: 1.65rem;
        }

        .module-summary-grid {
          grid-template-columns: 1fr;
        }

        .module-table-meta {
          flex-direction: column;
          align-items: flex-start;
        }
      }
    </style>
  </head>
  <body>
    <div class="container-scroller module-shell">
      <?php include __DIR__ . '/../partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/../partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> d-flex justify-content-between align-items-start" role="alert">
              <div>
                <strong><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo')); ?>:</strong>
                <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
              <div class="col-12">
                <div class="card module-hero">
                  <div class="card-body">
                    <span class="module-eyebrow">
                      <i class="mdi mdi-file-document-multiple-outline"></i>
                      Gestion de libros
                    </span>
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                      <div>
                        <h1 class="module-title"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo')); ?></h1>
                        <p class="module-copy"><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? '')); ?></p>
                        <div class="module-chip">
                          <i class="mdi mdi-office-building-outline"></i>
                          <span><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Empresa no seleccionada')); ?></span>
                          <?php if ($periodoLibroActivo !== ''): ?>
                          <span>&middot;</span>
                          <span><?php echo htmlspecialchars($periodoLibroActivo); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="text-lg-right">
                        <div class="badge badge-light p-2">
                          Tipos validos: <?php echo htmlspecialchars((string) ($modulo['tipos_validos'] ?? '')); ?>
                        </div>
                      </div>
                    </div>

                    <div class="module-summary-grid">
                      <?php foreach ($summaryCards as $card): ?>
                      <div class="module-summary-card">
                        <span class="module-summary-label"><?php echo htmlspecialchars((string) ($card['label'] ?? '')); ?></span>
                        <span class="module-summary-value"><?php echo htmlspecialchars((string) ($card['value'] ?? '')); ?></span>
                        <span class="module-summary-note"><?php echo htmlspecialchars((string) ($card['note'] ?? '')); ?></span>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row mb-4">
              <div class="col-12">
                <div class="card module-actions-card">
                  <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                      <div class="input-group module-search">
                        <span class="input-group-text bg-white"><i class="mdi mdi-magnify"></i></span>
                        <input type="text" class="form-control" id="tableSearchInput" placeholder="Buscar en el libro">
                      </div>

                      <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#companySelectModal">
                          <i class="mdi mdi-office-building-outline me-1"></i> Empresa
                        </button>
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#bookChoiceModal" <?php echo $empresaActiva ? '' : 'disabled'; ?>>
                          <i class="mdi mdi-book-open-page-variant-outline me-1"></i> Abrir libro
                        </button>
                        <button type="button" class="btn btn-light" disabled>
                          <i class="mdi mdi-content-save-outline me-1"></i> Guardado automatico
                        </button>
                        <button type="button" class="btn btn-module" data-bs-toggle="modal" data-bs-target="#importJsonModal" <?php echo $libroActivo ? '' : 'disabled'; ?>>
                          <i class="mdi mdi-plus me-1"></i> Importar JSON
                        </button>
                        <div class="btn-group">
                          <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?php echo $libroActivo ? '' : 'disabled'; ?>>
                            <i class="mdi mdi-download me-1"></i> Exportar
                          </button>
                          <div class="dropdown-menu dropdown-menu-end">
                            <?php if ($libroActivo): ?>
                            <a class="dropdown-item" href="/Saas/src/api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=excel">Excel</a>
                            <a class="dropdown-item" href="/Saas/src/api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=pdf" target="_blank" rel="noopener">PDF</a>
                            <a class="dropdown-item" href="/Saas/src/api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&modo=json&formato=json" target="_blank" rel="noopener">JSON estructurado</a>
                            <?php if ($tipoLibro === 'compras'): ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/Saas/src/api/facturas/exportar.php?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=anexo_mh_a3">Anexo MH A3</a>
                            <?php endif; ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
                      <span class="badge badge-light">
                        Cuota disponible: <strong><?php echo (int) ($cuota['disponibles'] ?? 0); ?></strong> de <?php echo (int) ($cuota['total'] ?? 0); ?>
                      </span>
                      <?php if ($libroActivo): ?>
                      <span class="badge badge-light">
                        Libro activo: <strong><?php echo htmlspecialchars($periodoLibroActivo); ?></strong>
                      </span>
                      <a href="?clear_book=1" class="btn btn-sm btn-outline-secondary">Limpiar libro activo</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($importResult): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                      <div>
                        <h4 class="card-title mb-2">Resultado de importacion</h4>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($importResult['message'] ?? 'Importacion procesada.')); ?></p>
                      </div>
                      <div class="d-flex flex-wrap gap-2">
                        <span class="badge badge-success p-2">Importadas: <?php echo (int) ($importData['importadas'] ?? 0); ?></span>
                        <span class="badge badge-warning p-2">Duplicadas: <?php echo (int) ($importData['duplicadas_total'] ?? 0); ?></span>
                        <span class="badge badge-danger p-2">Invalidas: <?php echo (int) ($importData['invalidas_total'] ?? 0); ?></span>
                        <span class="badge badge-light p-2">Cuota restante: <?php echo (int) ($importData['cuota_restante'] ?? ($cuota['disponibles'] ?? 0)); ?></span>
                      </div>
                    </div>

                    <?php if (!empty($importData['duplicadas'])): ?>
                    <div class="mt-4">
                      <h6 class="mb-3">Documentos duplicados</h6>
                      <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                          <thead>
                            <tr>
                              <th>Archivo</th>
                              <th>Tipo DTE</th>
                              <th>Nombre</th>
                              <th>Codigo de generacion</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($importData['duplicadas'] as $duplicada): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($duplicada['nombre_archivo'] ?? $duplicada['archivo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['tipoDte'] ?? $duplicada['tipo_dte'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['nombreTipo'] ?? $duplicada['tipo_dte_nombre'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['codigoGeneracion'] ?? $duplicada['codigo_generacion'] ?? '')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($importData['invalidas'])): ?>
                    <div class="mt-4">
                      <h6 class="mb-3">Documentos invalidos</h6>
                      <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                          <thead>
                            <tr>
                              <th>Archivo</th>
                              <th>Tipo DTE</th>
                              <th>Detalle</th>
                              <th>Codigo de generacion</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($importData['invalidas'] as $invalida): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombre_archivo'] ?? $invalida['archivo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['tipoDte'] ?? $invalida['tipo_dte'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombreTipo'] ?? $invalida['tipo_dte_nombre'] ?? $invalida['razon'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['codigoGeneracion'] ?? $invalida['codigo_generacion'] ?? '')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <?php if (empty($empresas)): ?>
                    <div class="module-empty-state">
                      <h4 class="mb-2">Todavia no tienes empresas</h4>
                      <p class="text-muted mb-3">Crea una empresa para comenzar a trabajar este modulo.</p>
                      <button type="button" class="btn btn-module" data-bs-toggle="modal" data-bs-target="#companyCreateModal">
                        Crear empresa
                      </button>
                    </div>
                    <?php elseif (!$libroActivo): ?>
                    <div class="module-empty-state">
                      <h4 class="mb-2">Aun no hay un libro abierto</h4>
                      <p class="text-muted mb-3">Selecciona una empresa y abre o crea un periodo para empezar.</p>
                      <div class="d-flex flex-wrap justify-content-center gap-2">
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#companySelectModal">
                          Elegir empresa
                        </button>
                        <button type="button" class="btn btn-module" data-bs-toggle="modal" data-bs-target="#bookChoiceModal" <?php echo $empresaActiva ? '' : 'disabled'; ?>>
                          Abrir libro
                        </button>
                      </div>
                    </div>
                    <?php else: ?>
                    <div class="module-table-wrap">
                      <div class="module-table-meta">
                        <div>
                          <h4 class="card-title mb-1">Registros del libro</h4>
                          <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? '')); ?> · <?php echo htmlspecialchars($periodoLibroActivo); ?></p>
                        </div>
                        <div class="text-muted">
                          <?php echo $cantidadFacturas; ?> registro(s)
                        </div>
                      </div>
                      <?php if (empty($filas)): ?>
                      <div class="p-4 text-muted">Aun no hay facturas cargadas en este libro.</div>
                      <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-bordered table-sm module-table" id="moduleTable">
                          <thead>
                            <tr>
                              <?php foreach ($columnas as $columna): ?>
                              <th><?php echo htmlspecialchars((string) ($columnLabels[$columna] ?? $columna)); ?></th>
                              <?php endforeach; ?>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($filas as $fila): ?>
                            <?php
                              $esFilaTotal = !isset($fila['no']) || $fila['no'] === null;
                              $searchChunks = [];
                              foreach ($columnas as $columna) {
                                  $searchChunks[] = (string) ($fila[$columna] ?? '');
                              }
                            ?>
                            <tr class="<?php echo $esFilaTotal ? 'total-row' : ''; ?>" data-row-type="<?php echo $esFilaTotal ? 'total' : 'data'; ?>" data-search="<?php echo htmlspecialchars(strtolower(implode(' ', $searchChunks))); ?>">
                              <?php foreach ($columnas as $columna): ?>
                              <?php
                                $valor = $fila[$columna] ?? '';
                                $esNumerica = in_array($columna, $numericColumns, true);
                                $esLarga = is_string($valor) && strlen((string) $valor) > 24;
                                if ($valor === null) {
                                    $valor = '';
                                }
                                if ($esNumerica && $valor !== '' && is_numeric($valor)) {
                                    $valorRender = number_format((float) $valor, 2);
                                } else {
                                    $valorRender = (string) $valor;
                                }
                              ?>
                              <td class="<?php echo $esNumerica ? 'text-end' : ''; ?> <?php echo $esLarga ? 'wrap-cell' : ''; ?>">
                                <?php echo htmlspecialchars($valorRender); ?>
                              </td>
                              <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php include __DIR__ . '/../partials/_footer.php'; ?>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="companySelectModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-office-building-outline"></i></span>
              <div>
                <h3 class="mb-1">En que empresa vas a trabajar?</h3>
                <p class="text-muted mb-0">Esta empresa se usara en el encabezado del libro y en esta sesion.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <?php if ($empresaUltima): ?>
            <div class="mb-3">
              <span class="module-company-pill">Ultima usada</span>
              <form method="post" class="mt-2">
                <input type="hidden" name="action" value="select_company">
                <input type="hidden" name="id_empresa" value="<?php echo (int) $empresaUltima['id']; ?>">
                <div class="module-choice">
                  <div class="module-choice-body">
                    <div>
                      <div class="font-weight-bold"><?php echo htmlspecialchars((string) $empresaUltima['nombre']); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars((string) ($empresaUltima['nit'] ?? 'Sin NIT')); ?></small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-module">Seleccionar</button>
                  </div>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <div class="mt-4">
              <div class="text-muted font-weight-bold text-uppercase small mb-3">Tus empresas</div>
              <?php if (!empty($empresas)): ?>
              <form method="post">
                <input type="hidden" name="action" value="select_company">
                <div class="d-grid gap-3">
                  <?php foreach ($empresas as $empresa): ?>
                  <label class="module-choice mb-0">
                    <input type="radio" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>" <?php echo (int) ($empresaActiva['id'] ?? 0) === (int) $empresa['id'] ? 'checked' : ''; ?>>
                    <div class="module-choice-body module-choice-body--stack">
                      <div>
                        <div class="font-weight-bold"><?php echo htmlspecialchars((string) $empresa['nombre']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars((string) ($empresa['nit'] ?? 'Sin NIT')); ?></small>
                      </div>
                      <?php if ((int) ($empresaUltima['id'] ?? 0) === (int) $empresa['id']): ?>
                      <span class="module-company-pill">Ultima</span>
                      <?php endif; ?>
                    </div>
                  </label>
                  <?php endforeach; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-4">
                  <button type="button" class="btn btn-module-outline" data-open-modal="companyCreateModal">Crear empresa</button>
                  <button type="submit" class="btn btn-module">Continuar</button>
                </div>
              </form>
              <?php else: ?>
              <div class="module-empty-state">
                <p class="text-muted mb-3">Todavia no has creado empresas.</p>
                <button type="button" class="btn btn-module" data-open-modal="companyCreateModal">Crear empresa</button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="companyCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-plus"></i></span>
              <div>
                <h3 class="mb-1">Crear empresa</h3>
                <p class="text-muted mb-0">Agrega la empresa que usaras en este modulo sin salir de la pantalla.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="create_company">
            <div class="modal-body">
              <div class="row">
                <div class="col-md-8">
                  <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars((string) ($companyFormData['nombre'] ?? '')); ?>" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Iniciales</label>
                    <input type="text" class="form-control" name="iniciales" value="<?php echo htmlspecialchars((string) ($companyFormData['iniciales'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Tipo legal</label>
                    <select class="form-control" name="tipo_legal">
                      <option value="natural" <?php echo ($companyFormData['tipo_legal'] ?? '') === 'natural' ? 'selected' : ''; ?>>Natural</option>
                      <option value="juridica" <?php echo ($companyFormData['tipo_legal'] ?? '') === 'juridica' ? 'selected' : ''; ?>>Juridica</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>DUI</label>
                    <input type="text" class="form-control" name="dui" value="<?php echo htmlspecialchars((string) ($companyFormData['dui'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>NRC</label>
                    <input type="text" class="form-control" name="nrc" value="<?php echo htmlspecialchars((string) ($companyFormData['nrc'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>NIT</label>
                    <input type="text" class="form-control" name="nit" value="<?php echo htmlspecialchars((string) ($companyFormData['nit'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Color del emblema</label>
                    <input type="color" class="form-control" name="color_emblema" value="<?php echo htmlspecialchars((string) ($companyFormData['color_emblema'] ?? '#f97316')); ?>">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="button" class="btn btn-module-outline" data-open-modal="companySelectModal">Volver</button>
              <button type="submit" class="btn btn-module">Guardar empresa</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="bookChoiceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-book-open-variant"></i></span>
              <div>
                <h3 class="mb-1">Libro nuevo o existente?</h3>
                <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Selecciona una empresa')); ?> · <?php echo htmlspecialchars((string) ($modulo['nombre'] ?? '')); ?></p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <?php if ($empresaActiva): ?>
            <button type="button" class="module-choice text-start w-100 mb-3" data-open-modal="periodCreateModal">
              <div class="module-choice-body">
                <div>
                  <div class="font-weight-bold">Crear libro nuevo</div>
                  <small class="text-muted">Seleccionar periodo y comenzar desde cero.</small>
                </div>
                <span class="btn btn-sm btn-module-soft">Nuevo</span>
              </div>
            </button>

            <?php if (!empty($libros)): ?>
            <div class="text-muted font-weight-bold text-uppercase small mb-3">Libros guardados</div>
            <div class="d-grid gap-3">
              <?php foreach ($libros as $libro): ?>
              <div class="module-choice">
                <div class="module-choice-body">
                  <div>
                    <div class="font-weight-bold"><?php echo htmlspecialchars(($meses[(int) $libro['mes']] ?? (string) $libro['mes']) . ' ' . $libro['anio']); ?></div>
                    <small class="text-muted">Periodo existente</small>
                  </div>
                  <a href="?open=<?php echo (int) $libro['id']; ?>" class="btn btn-sm btn-module-outline">Abrir</a>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-3">
              No hay libros guardados para esta empresa.
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="module-empty-state">
              <p class="text-muted mb-3">Selecciona una empresa para ver sus periodos.</p>
              <button type="button" class="btn btn-module" data-open-modal="companySelectModal">Elegir empresa</button>
            </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer d-flex justify-content-start">
            <button type="button" class="btn btn-module-outline" data-open-modal="companySelectModal">Cambiar empresa</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="periodCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-calendar-month-outline"></i></span>
              <div>
                <h3 class="mb-1">En qué mes y año te gustaría trabajar?</h3>
                <p class="text-muted mb-0">Selecciona el periodo contable para la nueva sesión de trabajo.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="create_book">
            <div class="modal-body">
              <div class="form-group">
                <label>Año</label>
                <select class="form-control" name="anio">
                  <?php foreach ($anios as $anio): ?>
                  <option value="<?php echo (int) $anio; ?>" <?php echo $anio === $anioActual ? 'selected' : ''; ?>><?php echo (int) $anio; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Mes</label>
                <select class="form-control" name="mes">
                  <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                  <option value="<?php echo (int) $numeroMes; ?>" <?php echo $numeroMes === (int) date('n') ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombreMes); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="module-period-preview">
                <div class="text-muted small mb-1">El libro se guardara dentro del modulo:</div>
                <div class="font-weight-bold"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? '')); ?></div>
              </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="button" class="btn btn-module-outline" data-open-modal="bookChoiceModal">Regresar</button>
              <button type="submit" class="btn btn-module">Confirmar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="importJsonModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-file-upload-outline"></i></span>
              <div>
                <h3 class="mb-1">Importar documentos JSON</h3>
                <p class="text-muted mb-0">Carga uno o varios archivos. El backend validara tipos DTE, duplicados y cuota disponible.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_json">
            <div class="modal-body">
              <div class="module-choice mb-3">
                <div class="module-choice-body">
                  <div>
                    <div class="font-weight-bold">Libro activo</div>
                    <small class="text-muted"><?php echo htmlspecialchars($periodoLibroActivo !== '' ? $periodoLibroActivo : 'Sin libro abierto'); ?></small>
                  </div>
                  <span class="badge badge-light">Cuota: <?php echo (int) ($cuota['disponibles'] ?? 0); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label>Archivos JSON</label>
                <input id="jsonFilesInput" type="file" name="json_files[]" class="form-control" accept=".json,application/json" multiple required>
              </div>
              <div class="module-period-preview">
                <div class="font-weight-bold mb-2">Tipos DTE validos para este libro</div>
                <ul class="module-import-list mb-0">
                  <?php foreach (explode(',', (string) ($modulo['tipos_validos'] ?? '')) as $tipoValido): ?>
                  <li><?php echo htmlspecialchars(trim($tipoValido)); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="mt-3 text-muted small" id="selectedFilesHint">No has seleccionado archivos todavia.</div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="button" class="btn btn-module-outline" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-module" <?php echo $libroActivo ? '' : 'disabled'; ?>>Procesar importacion</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modalMap = {
          'company-select': 'companySelectModal',
          'company-create': 'companyCreateModal',
          'book-choice': 'bookChoiceModal',
          'period-create': 'periodCreateModal',
          'import-json': 'importJsonModal'
        };

        document.querySelectorAll('[data-open-modal]').forEach(function (trigger) {
          trigger.addEventListener('click', function () {
            const targetId = trigger.getAttribute('data-open-modal');
            const currentModal = trigger.closest('.modal');
            if (currentModal && window.bootstrap) {
              const currentInstance = bootstrap.Modal.getInstance(currentModal);
              if (currentInstance) {
                currentInstance.hide();
              }
            }

            window.setTimeout(function () {
              const target = document.getElementById(targetId);
              if (target && window.bootstrap) {
                const instance = new bootstrap.Modal(target);
                instance.show();
              }
            }, 180);
          });
        });

        const initialModalKey = <?php echo json_encode($modalInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        if (initialModalKey && modalMap[initialModalKey] && window.bootstrap) {
          const modal = document.getElementById(modalMap[initialModalKey]);
          if (modal) {
            const instance = new bootstrap.Modal(modal);
            instance.show();
          }
        }

        const searchInput = document.getElementById('tableSearchInput');
        const rows = Array.from(document.querySelectorAll('#moduleTable tbody tr[data-row-type]'));
        if (searchInput && rows.length > 0) {
          searchInput.addEventListener('input', function () {
            const query = (searchInput.value || '').toLowerCase().trim();

            rows.forEach(function (row) {
              if (row.dataset.rowType === 'total') {
                row.style.display = '';
                return;
              }

              const haystack = (row.dataset.search || '').toLowerCase();
              row.style.display = query === '' || haystack.indexOf(query) !== -1 ? '' : 'none';
            });
          });
        }

        const fileInput = document.getElementById('jsonFilesInput');
        const hint = document.getElementById('selectedFilesHint');
        if (fileInput && hint) {
          fileInput.addEventListener('change', function () {
            const names = Array.from(fileInput.files || []).map(function (file) {
              return file.name;
            });

            if (names.length === 0) {
              hint.textContent = 'No has seleccionado archivos todavia.';
              return;
            }

            if (names.length === 1) {
              hint.textContent = '1 archivo listo: ' + names[0];
              return;
            }

            hint.textContent = names.length + ' archivos listos: ' + names.slice(0, 3).join(', ') + (names.length > 3 ? '...' : '');
          });
        }
      });
    </script>
  </body>
</html>
