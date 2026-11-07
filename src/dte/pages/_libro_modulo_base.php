<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modulos.php';
require_once __DIR__ . '/../models/EmpresaModel.php';
require_once __DIR__ . '/../models/LibroModel.php';
require_once __DIR__ . '/../models/FacturasCuotaModel.php';
require_once __DIR__ . '/../services/LibroVistaService.php';
require_once __DIR__ . '/../services/ModulePreferenceService.php';

requireLogin();

$tipoLibro = trim((string) ($tipoLibroPagina ?? ''));
$modulo    = getLibroModule($tipoLibro);

if ($modulo === null) {
    http_response_code(404);
    exit('Modulo no disponible.');
}

dte_require_permission(dte_modulo_permiso($modulo));

$session          = sessionData();
$idUsuario        = dte_current_user_id();
$flashKey         = 'libro_' . $tipoLibro;
$importSessionKey = '_import_result_' . $tipoLibro;
$rutaModulo       = app_url(ltrim((string) ($modulo['ruta'] ?? ''), '/'));
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
$anioMinimo = $anioActual - 2;
$anioMaximo = $anioActual + 2;
$anios      = range($anioMinimo, $anioMaximo);
$mesActual  = (int) date('n');

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
$maxFileUploads  = max(1, (int) ini_get('max_file_uploads'));

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
            $documentos[] = [
                'nombre_archivo' => trim((string) $nombreArchivo) !== '' ? trim((string) $nombreArchivo) : ('documento_' . ($indice + 1) . '.json'),
                'payload'        => null,
                'razon'          => $error === UPLOAD_ERR_OK
                    ? 'No se recibio un archivo temporal valido.'
                    : 'No se pudo subir el archivo. Codigo de error: ' . (int) $error . '.',
            ];
            continue;
        }

        $contenido = file_get_contents($tmpName);
        if ($contenido === false) {
            $documentos[] = [
                'nombre_archivo' => trim((string) $nombreArchivo) !== '' ? trim((string) $nombreArchivo) : ('documento_' . ($indice + 1) . '.json'),
                'payload'        => null,
                'razon'          => 'No se pudo leer el archivo subido.',
            ];
            continue;
        }

        $documentos[] = [
            'nombre_archivo' => trim((string) $nombreArchivo) !== '' ? trim((string) $nombreArchivo) : ('documento_' . ($indice + 1) . '.json'),
            'payload'        => $contenido,
        ];
    }

    return $documentos;
};

$validarEmpresa = static function (PDO $pdo, int $idUsuario, array $input, ?int $ignorarId = null): array {
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

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc, $ignorarId)) {
        return ['ok' => false, 'message' => 'Ya tienes una empresa con ese NRC.', 'old' => $oldInput];
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit, $ignorarId)) {
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

$tipoDteNombres = [
    '01' => 'Factura',
    '03' => 'Crédito Fiscal',
    '05' => 'Nota de Crédito',
    '06' => 'Nota de Débito',
    '14' => 'Comprobante de Retención',
];
$tipoDteBadgeClases = [
    '01' => 'is-invoice',
    '03' => 'is-tax-credit',
    '05' => 'is-credit-note',
    '06' => 'is-debit-note',
    '14' => 'is-retention',
];
$tiposDtePorModulo = [
    'compras' => ['01', '03', '05', '06'],
    'ventas_consumidor' => ['01', '05', '06'],
    'ventas_contribuyente' => ['03', '05', '06'],
    'retencion_iva' => ['14'],
];
$tiposDteValidos = [];
if (preg_match_all('/\d+/', (string) ($modulo['tipos_validos'] ?? ''), $coincidenciasTipos) > 0) {
    foreach ($coincidenciasTipos[0] as $tipoValido) {
        $codigoTipo = trim((string) $tipoValido);
        if (strlen($codigoTipo) === 1) {
            $codigoTipo = '0' . $codigoTipo;
        }
        if ($codigoTipo !== '' && !in_array($codigoTipo, $tiposDteValidos, true)) {
            $tiposDteValidos[] = $codigoTipo;
        }
    }
}
if (empty($tiposDteValidos) && isset($tiposDtePorModulo[$tipoLibro])) {
    $tiposDteValidos = $tiposDtePorModulo[$tipoLibro];
}
$tiposDteValidosInfo = [];
foreach ($tiposDteValidos as $tipoValido) {
    $tiposDteValidosInfo[] = [
        'codigo' => $tipoValido,
        'nombre' => $tipoDteNombres[$tipoValido] ?? ('Tipo DTE ' . $tipoValido),
        'clase'  => $tipoDteBadgeClases[$tipoValido] ?? 'is-default',
    ];
}
$tiposDteValidosResumenPartes = [];
foreach ($tiposDteValidosInfo as $tipoValidoInfo) {
    $nombreTipoValido = trim((string) ($tipoValidoInfo['nombre'] ?? ''));
    if ($nombreTipoValido !== '' && !in_array($nombreTipoValido, $tiposDteValidosResumenPartes, true)) {
        $tiposDteValidosResumenPartes[] = $nombreTipoValido;
    }
}
$tiposDteValidosResumen = implode(' · ', $tiposDteValidosResumenPartes);
$tipoDteDefecto = $tiposDteValidos[0] ?? '';

$campoManualDb = [
    'fecha'                                    => 'fecha',
    'dia_emision'                             => 'dia_emision',
    'fecha_emision'                           => 'fecha_emision_retencion',
    'tipo_documento'                          => 'tipo_documento_relacionado',
    'ventas_exentas_cuenta_terceros'          => 'ventas_exentas',
    'ventas_internas_gravadas_cuenta_terceros'=> 'ventas_internas_gravadas',
    'debito_fiscal_cuenta_terceros'           => 'debito_fiscal',
];

$prepararRegistroManual = static function (array $input, array $libro) use ($modulo, $campoManualDb, $tipoDteDefecto, $idUsuario): array {
    $data = [
        'id_libro' => (int) ($libro['id'] ?? 0),
        'id_usuario' => $idUsuario,
        'tipo_dte' => trim((string) ($input['tipo_dte'] ?? $tipoDteDefecto)),
    ];

    foreach (($modulo['columnas'] ?? []) as $campo => $label) {
        if ($campo === 'no') {
            continue;
        }

        $campoDb = $campoManualDb[$campo] ?? $campo;
        $data[$campoDb] = $input[$campo] ?? '';
    }

    foreach (['codigo_generacion', 'sello_recepcion', 'numero_control', 'numero_control_completo'] as $campoExtra) {
        if (array_key_exists($campoExtra, $input)) {
            $data[$campoExtra] = $input[$campoExtra];
        }
    }

    if (isset($input['fecha_emision']) && !isset($data['fecha'])) {
        $data['fecha'] = $input['fecha_emision'];
    }
    if (isset($input['dia_emision']) && !isset($data['fecha'])) {
        $data['fecha'] = $input['dia_emision'];
    }

    return $data;
};

if (count(EmpresaModel::getByUsuario($pdo, $idUsuario)) === 1 && getActiveEmpresaId() === null) {
    $empresaUnica = EmpresaModel::getByUsuario($pdo, $idUsuario)[0];
    setActiveEmpresaId((int) $empresaUnica['id']);
    EmpresaModel::marcarUltimaUsada($pdo, (int) $empresaUnica['id'], $idUsuario);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if (in_array($action, ['save_manual_record', 'delete_record', 'clear_book_records'], true)) {
        $idLibroActivoPost = getActiveLibroId($tipoLibro);
        $libroActualPost = $idLibroActivoPost ? LibroModel::getById($pdo, $idLibroActivoPost, $idUsuario) : null;

        if ($libroActualPost === null || ($libroActualPost['tipo'] ?? '') !== $tipoLibro) {
            setFlash($flashKey, 'Debes abrir un libro valido antes de modificar registros.', 'danger', [
                'open_modal' => 'book-choice',
            ]);
            header('Location: ' . $rutaModulo);
            exit;
        }

        if ($action === 'save_manual_record') {
            $idFactura = max(0, (int) ($_POST['id_factura'] ?? 0));
            $dataManual = $prepararRegistroManual($_POST, $libroActualPost);

            try {
                if ($idFactura > 0) {
                    $registroExistente = FacturaModel::getByIdForLibro($pdo, $idFactura, (int) $libroActualPost['id'], $idUsuario);
                    if ($registroExistente === null) {
                        throw new RuntimeException('No se encontro el registro que quieres editar.');
                    }
                    $dataManual['raw_json'] = $registroExistente['raw_json'] ?? null;
                }

                FacturaModel::guardarManual($pdo, $dataManual, $idFactura > 0 ? $idFactura : null);
                setFlash(
                    $flashKey,
                    $idFactura > 0 ? 'Registro actualizado correctamente.' : 'Registro manual creado correctamente.',
                    'success'
                );
            } catch (Throwable $exception) {
                setFlash($flashKey, 'No se pudo guardar el registro. Revisa que el codigo de generacion no este repetido.', 'danger', [
                    'open_modal' => 'manual-record',
                    'old'        => $_POST,
                ]);
            }

            header('Location: ' . $rutaModulo);
            exit;
        }

        if ($action === 'delete_record') {
            $idFactura = max(0, (int) ($_POST['id_factura'] ?? 0));
            $eliminada = $idFactura > 0 && FacturaModel::eliminarFactura($pdo, $idFactura, (int) $libroActualPost['id'], $idUsuario);
            setFlash(
                $flashKey,
                $eliminada ? 'Registro eliminado correctamente.' : 'No se pudo eliminar el registro seleccionado.',
                $eliminada ? 'success' : 'warning'
            );

            header('Location: ' . $rutaModulo);
            exit;
        }

        if ($action === 'clear_book_records') {
            $eliminadas = FacturaModel::limpiarLibro($pdo, (int) $libroActualPost['id'], $idUsuario);
            setFlash($flashKey, "Libro vaciado correctamente. Se eliminaron {$eliminadas} registro(s).", 'success');

            header('Location: ' . $rutaModulo);
            exit;
        }
    }

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
            setActiveLibroId(null, $tipoLibro);
            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            setFlash($flashKey, 'Empresa creada correctamente.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'update_company') {
        $idEmpresa = max(0, (int) ($_POST['id_empresa'] ?? 0));
        $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $validacion = $validarEmpresa($pdo, $idUsuario, $_POST, $idEmpresa);

        if (!$empresa) {
            setFlash($flashKey, 'Selecciona una empresa valida para editar.', 'danger', [
                'open_modal' => 'company-select',
            ]);
        } elseif (!($validacion['ok'] ?? false)) {
            $old = $validacion['old'] ?? [];
            $old['id_empresa'] = $idEmpresa;
            setFlash($flashKey, (string) ($validacion['message'] ?? 'No se pudo actualizar la empresa.'), 'danger', [
                'open_modal' => 'company-edit',
                'old'        => $old,
            ]);
        } else {
            EmpresaModel::update($pdo, $idEmpresa, $idUsuario, $validacion['data']);
            if ((int) (getActiveEmpresaId() ?? 0) === $idEmpresa) {
                EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            }
            setFlash($flashKey, 'Empresa actualizada correctamente.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'deactivate_company') {
        $idEmpresa = max(0, (int) ($_POST['id_empresa'] ?? 0));
        $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $desactivada = $empresa && EmpresaModel::delete($pdo, $idEmpresa, $idUsuario);

        if ($desactivada && (int) (getActiveEmpresaId() ?? 0) === $idEmpresa) {
            setActiveEmpresaId(null);
            setActiveLibroId(null, $tipoLibro);
        }

        setFlash(
            $flashKey,
            $desactivada ? 'Empresa desactivada correctamente.' : 'No se pudo desactivar la empresa seleccionada.',
            $desactivada ? 'success' : 'warning',
            [
                'open_modal' => $desactivada ? null : 'company-select',
            ]
        );

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
            setActiveLibroId(null, $tipoLibro);
            EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
            setFlash($flashKey, 'Empresa activa actualizada.', 'success');
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'delete_book') {
        $idLibro = max(0, (int) ($_POST['id_libro'] ?? 0));
        $libro   = $idLibro > 0 ? LibroModel::getById($pdo, $idLibro, $idUsuario) : null;

        if (!$libro || ($libro['tipo'] ?? '') !== $tipoLibro) {
            setFlash($flashKey, 'Selecciona un libro valido para eliminar.', 'danger', [
                'open_modal' => 'book-choice',
            ]);
        } else {
            $eliminado = LibroModel::delete($pdo, $idLibro, $idUsuario);
            if ($eliminado && (int) (getActiveLibroId($tipoLibro) ?? 0) === $idLibro) {
                setActiveLibroId(null, $tipoLibro);
            }

            setFlash(
                $flashKey,
                $eliminado ? 'Libro eliminado correctamente.' : 'No se pudo eliminar el libro seleccionado.',
                $eliminado ? 'success' : 'warning',
                [
                    'open_modal' => $eliminado ? null : 'book-choice',
                ]
            );
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'create_book') {
        $idEmpresa = getActiveEmpresaId();
        $empresa   = $idEmpresa ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
        $mesInput  = trim((string) ($_POST['mes'] ?? ''));
        $anioInput = trim((string) ($_POST['anio'] ?? ''));
        $mes       = ctype_digit($mesInput) ? (int) $mesInput : 0;
        $anio      = ctype_digit($anioInput) ? (int) $anioInput : 0;

        if (!$empresa) {
            setFlash($flashKey, 'Primero debes seleccionar una empresa.', 'danger', [
                'open_modal' => 'company-select',
            ]);
        } elseif ($anioInput === '' || !ctype_digit($anioInput) || $anio < $anioMinimo || $anio > $anioMaximo) {
            setFlash($flashKey, 'El año seleccionado no está permitido.', 'danger', [
                'open_modal' => 'period-create',
            ]);
        } elseif ($mesInput === '' || !ctype_digit($mesInput) || $mes < 1 || $mes > 12) {
            setFlash($flashKey, 'El mes seleccionado no es válido.', 'danger', [
                'open_modal' => 'period-create',
            ]);
        } else {
            $existente = LibroModel::findByEmpresaTipoPeriodo($pdo, (int) $empresa['id'], $tipoLibro, $mes, $anio);

            if ($existente) {
                setActiveLibroId((int) $existente['id'], $tipoLibro);
                EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                setFlash($flashKey, 'Ese libro ya existia. Se abrio el periodo guardado.', 'info');
            } else {
                try {
                    $idLibro = LibroModel::create($pdo, [
                        'id_empresa' => (int) $empresa['id'],
                        'id_usuario' => $idUsuario,
                        'tipo'       => $tipoLibro,
                        'mes'        => $mes,
                        'anio'       => $anio,
                    ]);

                    setActiveLibroId($idLibro, $tipoLibro);
                    EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                    setFlash($flashKey, 'Libro creado correctamente.', 'success');
                } catch (PDOException $exception) {
                    $stmtLibroInactivo = $pdo->prepare(
                        "SELECT id
                         FROM dte_libros
                         WHERE id_empresa = ? AND tipo = ? AND mes = ? AND anio = ?
                         LIMIT 1"
                    );
                    $stmtLibroInactivo->execute([(int) $empresa['id'], $tipoLibro, $mes, $anio]);
                    $idLibroInactivo = (int) ($stmtLibroInactivo->fetchColumn() ?: 0);

                    if ($idLibroInactivo > 0) {
                        $stmtReactivarLibro = $pdo->prepare(
                            "UPDATE dte_libros
                             SET estado = 1, id_usuario = ?
                             WHERE id = ?
                             LIMIT 1"
                        );
                        $stmtReactivarLibro->execute([$idUsuario, $idLibroInactivo]);

                        setActiveLibroId($idLibroInactivo, $tipoLibro);
                        EmpresaModel::marcarUltimaUsada($pdo, (int) $empresa['id'], $idUsuario);
                        setFlash($flashKey, 'Ese libro ya existia. Se reactivo y se abrio el periodo guardado.', 'info');
                    } else {
                        setFlash($flashKey, 'No se pudo crear el libro. Intenta nuevamente.', 'danger', [
                            'open_modal' => 'period-create',
                        ]);
                    }
                }
            }
        }

        header('Location: ' . $rutaModulo);
        exit;
    }

    if ($action === 'import_json') {
        $esEnvioAjax        = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $idLibro            = getActiveLibroId($tipoLibro);
        $libroActual        = $idLibro ? LibroModel::getById($pdo, $idLibro, $idUsuario) : null;
        $documentos         = $leerDocumentosSubidos($_FILES['json_files'] ?? []);
        $archivosEsperados  = max(0, (int) ($_POST['selected_file_count'] ?? 0));
        $archivosRecibidos  = count($documentos);
        $cargaIncompleta    = $archivosEsperados > 0 && $archivosRecibidos > 0 && $archivosRecibidos < $archivosEsperados;
        $mensajeCargaCortada = $cargaIncompleta
            ? " El servidor solo recibio {$archivosRecibidos} de {$archivosEsperados} archivos seleccionados."
            : '';

        $respuestaImportacion = null;
        $tipoFlash            = 'warning';

        if ($libroActual === null || ($libroActual['tipo'] ?? '') !== $tipoLibro) {
            $respuestaImportacion = [
                'success' => false,
                'message' => 'Debes abrir un libro valido antes de importar.',
            ];
            setFlash($flashKey, 'Debes abrir un libro valido antes de importar.', 'danger', [
                'open_modal' => 'book-choice',
            ]);
            $tipoFlash = 'danger';
        } elseif ($documentos === []) {
            $mensaje = $archivosEsperados > 0
                ? 'No se pudieron recibir los archivos seleccionados.'
                : 'Selecciona al menos un archivo JSON.';

            $respuestaImportacion = [
                'success' => false,
                'message' => $mensaje . $mensajeCargaCortada,
            ];
            setFlash($flashKey, $mensaje . $mensajeCargaCortada, 'danger', [
                'open_modal' => 'import-json',
            ]);
            $tipoFlash = 'danger';
        } else {
            $resultado = LibroVistaService::importar($pdo, $libroActual, $idUsuario, $documentos);

            if ($cargaIncompleta) {
                $resultado['success'] = false;
                $resultado['message'] = trim((string) ($resultado['message'] ?? 'Importacion procesada.') . $mensajeCargaCortada . ' Sube los restantes en otro lote o aumenta el limite del servidor.');
            }

            if (!$esEnvioAjax) {
                $_SESSION[$importSessionKey] = $resultado;
            }
            setFlash(
                $flashKey,
                (string) ($resultado['message'] ?? 'Importacion procesada.'),
                ($resultado['success'] ?? false) ? 'success' : 'warning',
                [
                    'suppress_alert' => true,
                ]
            );
            $respuestaImportacion = $resultado;
            $tipoFlash = ($resultado['success'] ?? false) ? 'success' : 'warning';
        }

        if ($esEnvioAjax) {
            $batchIndex = max(1, (int) ($_POST['batch_index'] ?? 1));
            $batchTotal = max(1, (int) ($_POST['batch_total'] ?? 1));
            $totalSeleccionado = max($archivosEsperados, (int) ($_POST['selected_total_count'] ?? 0));
            $batchSessionKey = $importSessionKey . '_batch';

            if ($batchIndex === 1 || !is_array($_SESSION[$batchSessionKey] ?? null)) {
                $_SESSION[$batchSessionKey] = [
                    'success' => true,
                    'message' => '',
                    'data'    => [
                        'recibidas'        => 0,
                        'esperadas'        => $totalSeleccionado,
	                        'importadas'       => 0,
	                        'reparadas'        => 0,
		                        'incluidas_libro'   => 0,
		                        'fuera_periodo'     => 0,
                            'documentos_unicos_incluidos' => 0,
                            'copias_duplicadas_apartadas' => 0,
		                        'duplicadas'       => [],
                        'duplicadas_total' => 0,
                        'invalidas'        => [],
                        'invalidas_total'  => 0,
                        'cuota_restante'   => null,
                    ],
                ];
            }

            $dataLote = is_array($respuestaImportacion['data'] ?? null) ? $respuestaImportacion['data'] : [];
            $_SESSION[$batchSessionKey]['success'] = (bool) ($_SESSION[$batchSessionKey]['success'] ?? true)
                && (bool) ($respuestaImportacion['success'] ?? false);
            $_SESSION[$batchSessionKey]['data']['recibidas'] += $archivosRecibidos;
            $_SESSION[$batchSessionKey]['data']['esperadas'] = max(
                (int) ($_SESSION[$batchSessionKey]['data']['esperadas'] ?? 0),
                $totalSeleccionado
            );
	            $_SESSION[$batchSessionKey]['data']['importadas'] += (int) ($dataLote['importadas'] ?? 0);
		            $_SESSION[$batchSessionKey]['data']['reparadas'] += (int) ($dataLote['reparadas'] ?? 0);
		            $_SESSION[$batchSessionKey]['data']['incluidas_libro'] += (int) ($dataLote['incluidas_libro'] ?? 0);
		            $_SESSION[$batchSessionKey]['data']['fuera_periodo'] += (int) ($dataLote['fuera_periodo'] ?? 0);
                $_SESSION[$batchSessionKey]['data']['documentos_unicos_incluidos'] += (int) ($dataLote['documentos_unicos_incluidos'] ?? $dataLote['incluidas_libro'] ?? 0);
                $_SESSION[$batchSessionKey]['data']['copias_duplicadas_apartadas'] += (int) ($dataLote['copias_duplicadas_apartadas'] ?? $dataLote['duplicadas_total'] ?? 0);
            $_SESSION[$batchSessionKey]['data']['duplicadas'] = array_merge(
                $_SESSION[$batchSessionKey]['data']['duplicadas'] ?? [],
                is_array($dataLote['duplicadas'] ?? null) ? $dataLote['duplicadas'] : []
            );
            $_SESSION[$batchSessionKey]['data']['invalidas'] = array_merge(
                $_SESSION[$batchSessionKey]['data']['invalidas'] ?? [],
                is_array($dataLote['invalidas'] ?? null) ? $dataLote['invalidas'] : []
            );
            $_SESSION[$batchSessionKey]['data']['duplicadas_total'] = count($_SESSION[$batchSessionKey]['data']['duplicadas']);
            $_SESSION[$batchSessionKey]['data']['invalidas_total'] = count($_SESSION[$batchSessionKey]['data']['invalidas']);
            $_SESSION[$batchSessionKey]['data']['cuota_restante'] = $dataLote['cuota_restante']
                ?? ($_SESSION[$batchSessionKey]['data']['cuota_restante'] ?? null);

            if ($batchIndex >= $batchTotal) {
                $dataAcumulada = $_SESSION[$batchSessionKey]['data'];
                $rechazadas = (int) ($dataAcumulada['invalidas_total'] ?? 0);
                $_SESSION[$batchSessionKey]['message'] =
		                    "Se recibieron {$dataAcumulada['recibidas']} de {$dataAcumulada['esperadas']} archivo(s). "
		                    . "Se importaron {$dataAcumulada['importadas']} registro(s). "
		                    . "Incluidos en libro: {$dataAcumulada['incluidas_libro']}. "
                            . "Documentos unicos incluidos: {$dataAcumulada['documentos_unicos_incluidos']}. "
		                    . "Fuera de periodo: {$dataAcumulada['fuera_periodo']}. "
		                    . "Copias duplicadas apartadas: {$dataAcumulada['copias_duplicadas_apartadas']}. "
	                    . "Invalidos: {$dataAcumulada['invalidas_total']}.";
                $_SESSION[$batchSessionKey]['success'] = (int) ($dataAcumulada['recibidas'] ?? 0) >= (int) ($dataAcumulada['esperadas'] ?? 0)
                    && $rechazadas === 0;
                $_SESSION[$importSessionKey] = $_SESSION[$batchSessionKey];
                unset($_SESSION[$batchSessionKey]);
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'  => true,
                'message'  => (string) ($respuestaImportacion['message'] ?? 'Importacion procesada.'),
                'result'   => $respuestaImportacion,
                'type'     => $tipoFlash,
                'redirect' => $rutaModulo,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
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
        setActiveLibroId((int) $libroAbrir['id'], $tipoLibro);
        EmpresaModel::marcarUltimaUsada($pdo, (int) $libroAbrir['id_empresa'], $idUsuario);
        setFlash($flashKey, 'Libro cargado correctamente.', 'success');
    }

    header('Location: ' . $rutaModulo);
    exit;
}

if (isset($_GET['clear_book'])) {
    setActiveLibroId(null, $tipoLibro);
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
$idLibroActivo = getActiveLibroId($tipoLibro);
$libroActivo   = $idLibroActivo ? LibroModel::getById($pdo, $idLibroActivo, $idUsuario) : null;

if ($libroActivo && (($libroActivo['tipo'] ?? '') !== $tipoLibro || !$empresaActiva || (int) $libroActivo['id_empresa'] !== (int) $empresaActiva['id'])) {
    setActiveLibroId(null, $tipoLibro);
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
	        'fuera_periodo'     => [],
	        'repetidas'         => [],
	        'totales_fuera_periodo' => [],
	        'cantidad_facturas' => 0,
	        'cantidad_fuera_periodo' => 0,
	        'cantidad_repetidas' => 0,
	    ],
	];

if ($libroActivo) {
    $resultadoListado = LibroVistaService::listar($pdo, (int) $libroActivo['id'], $idUsuario, $tipoLibro);
}

$tablaData          = is_array($resultadoListado['data'] ?? null) ? $resultadoListado['data'] : [];
$columnas           = $tablaData['columnas'] ?? array_keys($modulo['columnas'] ?? []);
$columnasTablaPrincipal = $columnas;
if ($tipoLibro === 'compras') {
    $columnasTablaPrincipal = array_values(array_filter(
        $columnasTablaPrincipal,
        static fn($columna) => !in_array($columna, [
            'documento_relacionado_numero',
            'documento_relacionado_codigo',
            'documento_relacionado_fecha',
        ], true)
    ));
}
$filas              = $tablaData['filas'] ?? [];
$registros          = $tablaData['registros'] ?? [];
$cantidadFacturas   = (int) ($tablaData['cantidad_facturas'] ?? 0);
$fueraPeriodoFilas  = is_array($tablaData['fuera_periodo'] ?? null) ? $tablaData['fuera_periodo'] : [];
$cantidadFueraPeriodo = (int) ($tablaData['cantidad_fuera_periodo'] ?? count($fueraPeriodoFilas));
$repetidasFilas     = is_array($tablaData['repetidas'] ?? null) ? $tablaData['repetidas'] : [];
$cantidadRepetidas  = (int) ($tablaData['cantidad_repetidas'] ?? count($repetidasFilas));
$totales            = is_array($tablaData['totales'] ?? null) ? $tablaData['totales'] : [];
$fueraPeriodoColumns = [
    'fecha'                => 'Fecha de emision',
    'tipo_dte'             => 'Tipo DTE',
    'numero_control'       => 'Numero de control',
    'codigo_generacion'    => 'Codigo de generacion',
    'nombre_proveedor'     => 'Emisor/proveedor',
    'nit'                  => 'NIT',
    'nrc'                  => 'NRC',
    'compras_exentas'      => 'Total exento',
    'compras_gravadas'     => 'Total gravado',
    'impuestos_calculados' => 'IVA',
    'fovial'               => 'FOVIAL',
    'cotrans'              => 'COTRANS',
    'total_compras'        => 'Total pagar',
    'motivo'               => 'Motivo',
];
$fueraPeriodoNumeric = ['compras_exentas', 'compras_gravadas', 'impuestos_calculados', 'fovial', 'cotrans', 'total_compras'];
$repetidasColumns = [
    'fecha'                 => 'Fecha de emision',
    'tipo_documento_nombre' => 'Tipo de documento',
    'numero_control'        => 'Numero de control',
    'codigo_generacion'     => 'Codigo generacion apartado',
    'codigo_generacion_original' => 'Codigo generacion original',
    'nombre_proveedor'      => 'Emisor/proveedor',
    'nit'                   => 'NIT',
    'nrc'                   => 'NRC',
    'total_compras'         => 'Total',
    'motivo'                => 'Motivo de exclusion',
    'documento_original'    => 'Documento original encontrado',
    'estado'                => 'Estado',
];
$repetidasNumeric = ['total_compras'];
$cuota              = FacturasCuotaModel::ensure($pdo, $idUsuario);
$cuotaLabel         = FacturasCuotaModel::formatDisponibleLabel($cuota);
$flash              = getFlash($flashKey);
$flashMeta          = $flash['meta'] ?? [];
$importResult       = $_SESSION[$importSessionKey] ?? null;
$importData         = is_array($importResult['data'] ?? null) ? $importResult['data'] : [];
$cuotaRestanteLabel = FacturasCuotaModel::formatRestanteLabel(
    isset($importData['cuota_restante']) ? (int) $importData['cuota_restante'] : (int) ($cuota['disponibles'] ?? 0),
    $cuota
);
$empresaActivaNavbar = $empresaActiva;
$periodoLibroActivo = $libroActivo
    ? (($meses[(int) $libroActivo['mes']] ?? (string) $libroActivo['mes']) . ' ' . $libroActivo['anio'])
    : '';
$columnLabels       = $modulo['columnas'] ?? [];
$numericColumns     = $modulo['columnas_numericas'] ?? [];
$modulePreferences  = getModuleColumnConfig($pdo, $tipoLibro, $idUsuario, $modulo);
$columnsPreferencePayload = ModulePreferenceService::clientPayload($tipoLibro, $modulo, $modulePreferences);
$columnasTablaPrincipal = ModulePreferenceService::filterColumns($columnasTablaPrincipal, $modulePreferences, 'table');
$manualFormData     = is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : [];
$manualFormFields   = [
    'codigo_generacion' => 'Codigo de generacion',
    'tipo_dte'          => 'Tipo DTE',
];
foreach ($columnLabels as $campo => $label) {
    if ($campo === 'no' || array_key_exists($campo, $manualFormFields)) {
        continue;
    }
    $manualFormFields[$campo] = (string) $label;
}
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
$companyEditFormData = array_merge([
    'id_empresa'    => '',
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
    <title><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo')); ?> - <?php echo htmlspecialchars(app_name()); ?></title>
    <link rel="stylesheet" href="../../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=20260613e">
    <link rel="icon" href="<?php echo htmlspecialchars(parent_app_url('assets/images/favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>" />
    <style>
      :root {
        --module-accent: <?php echo htmlspecialchars($accentColor); ?>;
        --module-accent-soft: <?php echo htmlspecialchars($accentSoft); ?>;
        --module-accent-strong: <?php echo htmlspecialchars($accentSoftStrong); ?>;
      }

      .module-shell .card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
      }

      .module-shell .content-wrapper {
        background:
          linear-gradient(180deg, #f7f8fd 0%, #f4f7fb 42%, #ffffff 100%);
      }

      .module-hero {
        border-radius: 18px;
        background:
          radial-gradient(circle at top right, var(--module-accent-soft) 0%, rgba(255, 255, 255, 0) 34%),
          linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
        overflow: hidden;
      }

      .module-hero .card-body {
        padding: 1.5rem;
      }

      .module-hero-main {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
        gap: 1.25rem;
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
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.15;
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
        margin-top: 0.95rem;
        padding: 0.62rem 0.9rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.06);
        font-weight: 600;
        color: #122033;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
      }

      .module-context-separator {
        color: #94a3b8;
      }

      .module-types-summary {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        max-width: 420px;
        padding: 0.8rem 0.9rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.78);
        color: #475569;
        white-space: normal;
        line-height: 1.35;
        text-align: left;
      }

      .module-types-summary i {
        color: var(--module-accent);
        font-size: 1.1rem;
      }

      .module-types-summary strong {
        display: block;
        margin-bottom: 0.1rem;
        color: #172033;
        font-size: 0.78rem;
      }

      .module-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1.25rem;
        align-items: stretch;
      }

      .module-summary-card {
        display: flex;
        flex-direction: column;
        min-height: 106px;
        padding: 0.95rem 1rem;
        border-radius: 12px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.04);
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
        line-height: 1.2;
        overflow-wrap: anywhere;
      }

      .module-summary-note {
        display: block;
        margin-top: auto;
        padding-top: 0.55rem;
        color: #6b7280;
        font-size: 0.78rem;
      }

      .module-actions-card {
        border-radius: 18px;
      }

      .module-actions-card .card-body {
        padding: 1.25rem;
      }

      .module-command-center {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) minmax(420px, auto);
        gap: 1.25rem;
        align-items: start;
      }

      .module-search {
        width: 100%;
        min-width: 0;
      }

      .module-search .input-group-text,
      .module-search .form-control {
        min-height: 48px;
        border-color: rgba(15, 23, 42, 0.14);
      }

      .module-search .form-control {
        font-size: 0.92rem;
      }

      .module-action-zone {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.65rem;
      }

      .module-action-zone .btn,
      .module-action-zone .btn-group > .btn {
        min-height: 42px;
        border-radius: 12px;
        font-weight: 600;
      }

      .module-status-strip {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.9rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
      }

      .module-status-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        min-width: 0;
      }

      .module-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        min-height: 34px;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid rgba(100, 116, 139, 0.18);
        color: #475569;
        font-size: 0.8rem;
        font-weight: 600;
      }

      .module-status-pill strong {
        color: #172033;
      }

      .module-status-pill.is-ok {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
      }

      .module-status-pill.is-ok strong,
      .module-status-pill.is-ok i {
        color: #166534;
      }

      .module-book-controls {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.55rem;
      }

      .module-book-controls .btn {
        min-height: 34px;
        border-radius: 999px;
        font-weight: 600;
      }

      .module-export-menu {
        min-width: 310px;
        padding: 0.55rem;
        border: 1px solid rgba(15, 23, 42, 0.1);
        border-radius: 14px;
        box-shadow: 0 18px 38px rgba(15, 23, 42, 0.14);
      }

      .module-export-heading {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.55rem 0.35rem;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .module-export-item {
        display: flex;
        align-items: center;
        gap: 0.62rem;
        min-height: 40px;
        padding: 0.55rem 0.65rem;
        border-radius: 10px;
        color: #1f2937;
        font-size: 0.92rem;
        font-weight: 600;
      }

      .module-export-item i {
        width: 20px;
        color: var(--module-accent);
        font-size: 1rem;
        text-align: center;
      }

      .module-export-item:hover,
      .module-export-item:focus {
        background: var(--module-accent-soft);
        color: #111827;
      }

      .module-export-menu .dropdown-divider {
        margin: 0.45rem 0;
        border-top-color: rgba(100, 116, 139, 0.18);
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

      .module-table tbody tr.credit-note-row td {
        background: #fff1f2;
      }

      .document-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #172033;
        font-weight: 700;
        font-size: 0.76rem;
        white-space: nowrap;
      }

      .document-type-badge.is-credit-note {
        background: #fee2e2;
        color: #b91c1c;
      }

      .document-type-badge.is-tax-credit {
        background: #ccfbf1;
        color: #0f766e;
      }

      .document-type-badge.is-invoice {
        background: #e0f2fe;
        color: #075985;
      }

      .document-type-badge.is-debit-note {
        background: #fef3c7;
        color: #92400e;
      }

      .module-preference-table {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        overflow: hidden;
      }

      .module-preference-row {
        display: grid;
        grid-template-columns: minmax(170px, 1fr) 118px 118px;
        gap: 0.75rem;
        align-items: center;
        padding: 0.7rem 0.85rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      }

      .module-preference-row:last-child {
        border-bottom: 0;
      }

      .module-preference-head {
        background: #f8fafc;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .module-preference-types {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }

      .module-preference-type {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 1rem;
        background: #fff;
      }

      .module-preference-type-title {
        display: block;
        margin-bottom: 0.8rem;
        color: #111827;
        font-weight: 700;
      }

      .module-preference-type-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.65rem;
        color: #374151;
      }

      .module-preference-type-option:last-child {
        margin-bottom: 0;
      }

      .module-preference-type-option label {
        margin: 0;
        line-height: 1.3;
      }

      .module-preference-type-option .form-check-input {
        flex-shrink: 0;
        margin: 0;
      }

      @media (max-width: 768px) {
        .module-preference-types {
          grid-template-columns: 1fr;
        }
      }

      .module-secondary-section {
        margin-top: 1.25rem;
      }

      .module-secondary-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.85rem;
      }

      .module-secondary-heading h4 {
        margin-bottom: 0.25rem;
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
      }

      .module-secondary-heading p {
        margin-bottom: 0;
        color: #6b7280;
      }

      .module-secondary-block {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
      }

      .module-secondary-block + .module-secondary-block {
        margin-top: 0.8rem;
      }

      .module-secondary-trigger {
        width: 100%;
        border: 0;
        background: #fff;
        color: #111827;
        padding: 1rem 1.15rem;
        text-align: left;
      }

      .module-secondary-trigger:hover,
      .module-secondary-trigger:focus {
        background: #f8fafc;
      }

      .module-secondary-trigger .badge {
        flex-shrink: 0;
      }

      .module-secondary-body {
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        padding: 1rem;
        background: #fcfdff;
      }

	      .module-table tbody td.wrap-cell {
	        white-space: normal;
	        word-break: break-word;
	        min-width: 160px;
	      }

	      .module-row-actions {
	        min-width: 96px;
	        white-space: nowrap;
	        background: #fff;
	        position: sticky;
	        left: 0;
	        z-index: 2;
	      }

	      .module-table thead th:first-child {
	        position: sticky;
	        left: 0;
	        z-index: 3;
	      }

	      .module-row-actions .btn {
	        width: 32px;
	        height: 32px;
	        padding: 0;
	        display: inline-flex;
	        align-items: center;
	        justify-content: center;
	      }

	      .module-table-shell {
        position: relative;
      }

      .module-table-shell.is-pending {
        opacity: 0.68;
        transition: opacity 0.2s ease;
      }

      .module-table-shell.is-ready {
        opacity: 1;
      }

      .module-table-pager {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.85rem;
        padding: 0.9rem 1rem 1rem;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
      }

      .module-table-pager[hidden] {
        display: none !important;
      }

      .module-table-pager-status {
        color: #5f6f84;
        font-size: 0.84rem;
        font-weight: 600;
      }

      .module-table-pager-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
      }

      .module-page-btn {
        min-width: 38px;
        min-height: 38px;
        padding: 0 0.8rem;
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 12px;
        background: #fff;
        color: #122033;
        font-size: 0.82rem;
        font-weight: 700;
        transition: all 0.2s ease;
      }

      .module-page-btn:hover,
      .module-page-btn:focus {
        border-color: var(--module-accent-strong);
        background: var(--module-accent-soft);
        color: var(--module-accent);
      }

      .module-page-btn.is-active {
        border-color: var(--module-accent);
        background: var(--module-accent);
        color: #fff;
        box-shadow: 0 12px 24px var(--module-accent-soft);
      }

      .module-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        box-shadow: none;
      }

      .module-empty-row td {
        padding: 1rem !important;
        text-align: center;
        color: #66768b;
        white-space: normal !important;
      }

      .module-loading-overlay {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.18);
        backdrop-filter: blur(6px);
        z-index: 2000;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.25s ease, visibility 0.25s ease;
      }

      .module-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
      }

      .module-loading-card {
        min-width: min(380px, 100%);
        max-width: 420px;
        padding: 1.25rem 1.25rem 1.1rem;
        border-radius: 22px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 30px 70px rgba(15, 23, 42, 0.18);
      }

      .module-loading-title {
        display: block;
        margin-bottom: 0.65rem;
        color: #101827;
        font-size: 0.98rem;
        font-weight: 700;
      }

      .module-loading-copy {
        display: block;
        margin-top: 0.55rem;
        color: #607086;
        font-size: 0.84rem;
      }

      .module-loading-bar {
        position: relative;
        height: 8px;
        border-radius: 999px;
        overflow: hidden;
        background: rgba(15, 23, 42, 0.08);
      }

      .module-loading-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: -35%;
        width: 35%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--module-accent) 0%, #8bb4ff 100%);
        animation: moduleLoadingBar 1.15s ease-in-out infinite;
      }

      @keyframes moduleLoadingBar {
        0% {
          left: -35%;
        }

        100% {
          left: 100%;
        }
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

      .period-work-modal .modal-content {
        border-radius: 22px;
      }

      .period-work-modal .modal-header {
        align-items: flex-start;
        padding: 1.55rem 1.55rem 1.15rem;
      }

      .period-work-modal .modal-body {
        padding: 0.25rem 1.55rem 1.35rem;
      }

      .period-work-modal .modal-footer {
        align-items: center;
        padding: 0 1.55rem 1.55rem;
        gap: 0.75rem;
      }

      .period-work-heading {
        display: flex;
        align-items: flex-start;
        gap: 0.95rem;
        min-width: 0;
      }

      .period-work-title {
        margin: 0 0 0.28rem;
        color: #111827;
        font-size: 1.32rem;
        font-weight: 800;
        line-height: 1.2;
      }

      .period-work-subtitle {
        margin: 0;
        color: #667085;
        font-size: 0.92rem;
        line-height: 1.45;
      }

      .period-work-modal .btn-close {
        width: 38px;
        height: 38px;
        margin: -0.15rem -0.15rem 0 0;
        border-radius: 12px;
        background-color: #f8fafc;
        opacity: 0.72;
      }

      .period-work-modal .btn-close:hover,
      .period-work-modal .btn-close:focus {
        background-color: #eef2f7;
        opacity: 1;
      }

      .period-field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .period-field {
        margin-bottom: 0;
      }

      .period-field label {
        display: block;
        margin-bottom: 0.45rem;
        color: #344054;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .period-field .form-control {
        min-height: 48px;
        padding: 0.66rem 0.85rem;
        border-color: rgba(15, 23, 42, 0.14);
        border-radius: 12px;
        color: #111827;
        font-weight: 600;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
      }

      .period-field .form-control:focus {
        border-color: var(--module-accent);
        box-shadow: 0 0 0 0.2rem var(--module-accent-soft);
      }

      .period-module-card {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 1rem;
        border-radius: 16px;
        background: linear-gradient(135deg, #ffffff 0%, var(--module-accent-soft) 100%);
        border: 1px solid var(--module-accent-strong);
      }

      .period-module-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: #fff;
        color: var(--module-accent);
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06);
      }

      .period-module-kicker {
        display: block;
        margin-bottom: 0.12rem;
        color: #667085;
        font-size: 0.76rem;
        font-weight: 700;
      }

      .period-module-name {
        display: block;
        color: #111827;
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.25;
      }

      @media (max-width: 576px) {
        .period-field-grid {
          grid-template-columns: 1fr;
        }

        .period-work-modal .modal-footer {
          flex-direction: column-reverse;
          align-items: stretch;
        }

        .period-work-modal .modal-footer .btn {
          width: 100%;
        }
      }

      .module-dte-preview {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.6rem;
        padding: 0.75rem 0.85rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid rgba(100, 116, 139, 0.24);
      }

      .module-dte-title {
        margin: 0;
        color: #1f2937;
        font-size: 0.84rem;
        font-weight: 700;
        line-height: 1.2;
      }

      .module-dte-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        min-width: 0;
      }

      .module-dte-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.28rem;
        max-width: 100%;
        min-height: 28px;
        padding: 0.32rem 0.58rem;
        border-radius: 999px;
        background: #eff6ff;
        border: 1px solid rgba(59, 130, 246, 0.22);
        color: #1e3a8a;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.15;
        overflow-wrap: anywhere;
        text-align: center;
      }

      .module-dte-badge.is-tax-credit {
        background: #eef2ff;
        border-color: rgba(99, 102, 241, 0.22);
        color: #3730a3;
      }

      .module-dte-badge.is-credit-note {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
      }

      .module-dte-badge.is-debit-note {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #9a3412;
      }

      .module-dte-badge.is-retention {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #334155;
      }

      .module-dte-fallback {
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 600;
      }

      .module-dte-code {
        font-weight: 800;
      }

      .module-dte-name {
        font-weight: 600;
      }

      .module-file-hint {
        margin-top: 0.85rem;
        padding-top: 0.7rem;
        border-top: 1px solid rgba(100, 116, 139, 0.16);
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

        .module-command-center,
        .module-hero-main {
          grid-template-columns: 1fr;
        }

        .module-action-zone {
          justify-content: flex-start;
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

        .module-types-summary {
          max-width: none;
        }

        .module-summary-grid {
          grid-template-columns: 1fr;
        }

        .module-action-zone .btn,
        .module-action-zone .btn-group {
          width: 100%;
        }

        .module-action-zone .btn-group > .btn {
          width: 100%;
        }

        .module-status-strip,
        .module-book-controls {
          align-items: stretch;
          flex-direction: column;
        }

        .module-book-controls .btn,
        .module-book-controls form,
        .module-book-controls form .btn {
          width: 100%;
        }

        .module-table-meta {
          flex-direction: column;
          align-items: flex-start;
        }
      }

      html[data-dte-theme=dark] .module-shell {
        --module-dark-page: #0a0d12;
        --module-dark-panel: #111822;
        --module-dark-panel-soft: #151e2a;
        --module-dark-panel-deep: #0d131c;
        --module-dark-border: #263140;
        --module-dark-text: #e7edf5;
        --module-dark-muted: #8d9aab;
        --module-dark-accent: #8b86d9;
      }

      html[data-dte-theme=dark] .module-shell,
      html[data-dte-theme=dark] .module-shell .content-wrapper,
      html[data-dte-theme=dark] .module-shell .main-panel,
      html[data-dte-theme=dark] .module-shell .page-body-wrapper {
        background: var(--module-dark-page) !important;
        color: var(--module-dark-text);
      }

      html[data-dte-theme=dark] .module-shell .card,
      html[data-dte-theme=dark] .module-hero,
      html[data-dte-theme=dark] .module-actions-card,
      html[data-dte-theme=dark] .module-secondary-block,
      html[data-dte-theme=dark] .module-table-wrap {
        background: var(--module-dark-panel) !important;
        border-color: var(--module-dark-border) !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .module-hero {
        background:
          radial-gradient(circle at top right, rgba(139, 134, 217, 0.1), transparent 36%),
          var(--module-dark-panel) !important;
      }

      html[data-dte-theme=dark] .module-title,
      html[data-dte-theme=dark] .module-summary-value,
      html[data-dte-theme=dark] .module-status-pill strong,
      html[data-dte-theme=dark] .module-secondary-heading h4,
      html[data-dte-theme=dark] .module-secondary-trigger,
      html[data-dte-theme=dark] .module-modal h3,
      html[data-dte-theme=dark] .module-choice .font-weight-bold,
      html[data-dte-theme=dark] .module-dte-title,
      html[data-dte-theme=dark] .module-loading-title {
        color: var(--module-dark-text) !important;
      }

      html[data-dte-theme=dark] .module-copy,
      html[data-dte-theme=dark] .module-summary-label,
      html[data-dte-theme=dark] .module-summary-note,
      html[data-dte-theme=dark] .module-secondary-heading p,
      html[data-dte-theme=dark] .module-empty-row td,
      html[data-dte-theme=dark] .module-loading-copy,
      html[data-dte-theme=dark] .module-import-list,
      html[data-dte-theme=dark] .module-dte-fallback,
      html[data-dte-theme=dark] .module-modal .text-muted,
      html[data-dte-theme=dark] .module-shell .text-muted {
        color: var(--module-dark-muted) !important;
      }

      html[data-dte-theme=dark] .module-chip,
      html[data-dte-theme=dark] .module-types-summary,
      html[data-dte-theme=dark] .module-summary-card,
      html[data-dte-theme=dark] .module-status-pill,
      html[data-dte-theme=dark] .module-empty-state,
      html[data-dte-theme=dark] .module-table-meta,
      html[data-dte-theme=dark] .module-table-pager,
      html[data-dte-theme=dark] .module-secondary-body,
      html[data-dte-theme=dark] .module-secondary-trigger,
      html[data-dte-theme=dark] .module-loading-card {
        background: var(--module-dark-panel-soft) !important;
        border-color: var(--module-dark-border) !important;
        color: var(--module-dark-text) !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .module-types-summary strong,
      html[data-dte-theme=dark] .module-types-summary i,
      html[data-dte-theme=dark] .module-eyebrow {
        color: #a29df0 !important;
      }

      html[data-dte-theme=dark] .module-search .input-group-text,
      html[data-dte-theme=dark] .module-search .form-control,
      html[data-dte-theme=dark] .module-shell .form-control,
      html[data-dte-theme=dark] .module-shell .form-select,
      html[data-dte-theme=dark] .module-shell select,
      html[data-dte-theme=dark] .module-shell textarea,
      html[data-dte-theme=dark] .module-shell input[type="text"],
      html[data-dte-theme=dark] .module-shell input[type="number"],
      html[data-dte-theme=dark] .module-shell input[type="date"],
      html[data-dte-theme=dark] .module-shell input[type="file"] {
        background: #0c121b !important;
        border-color: #334052 !important;
        color: var(--module-dark-text) !important;
      }

      html[data-dte-theme=dark] .module-search .input-group-text.bg-white {
        background: #0c121b !important;
      }

      html[data-dte-theme=dark] .module-shell .form-control:focus,
      html[data-dte-theme=dark] .module-shell .form-select:focus,
      html[data-dte-theme=dark] .module-shell select:focus,
      html[data-dte-theme=dark] .module-shell textarea:focus,
      html[data-dte-theme=dark] .module-shell input:focus {
        background: #0f1620 !important;
        border-color: #6c7890 !important;
        box-shadow: 0 0 0 0.18rem rgba(139, 134, 217, 0.16) !important;
        color: #f8fafc !important;
      }

      html[data-dte-theme=dark] .module-shell .form-control::placeholder {
        color: #687589;
      }

      html[data-dte-theme=dark] .module-status-strip,
      html[data-dte-theme=dark] .module-file-hint,
      html[data-dte-theme=dark] .module-secondary-body,
      html[data-dte-theme=dark] .module-table-pager {
        border-color: var(--module-dark-border) !important;
      }

      html[data-dte-theme=dark] .module-status-pill.is-ok {
        background: rgba(123, 200, 155, 0.12) !important;
        border-color: rgba(123, 200, 155, 0.28) !important;
        color: #b9e8ca !important;
      }

      html[data-dte-theme=dark] .module-status-pill.is-ok strong,
      html[data-dte-theme=dark] .module-status-pill.is-ok i {
        color: #b9e8ca !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-content {
        background: var(--module-dark-panel) !important;
        border-color: var(--module-dark-border) !important;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.34) !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-header,
      html[data-dte-theme=dark] .module-modal .modal-body,
      html[data-dte-theme=dark] .module-modal .modal-footer {
        background: var(--module-dark-panel) !important;
        border-color: var(--module-dark-border) !important;
      }

      html[data-dte-theme=dark] .module-modal-eyebrow,
      html[data-dte-theme=dark] .module-company-pill,
      html[data-dte-theme=dark] .btn-module-soft {
        background: rgba(139, 134, 217, 0.14) !important;
        border-color: rgba(139, 134, 217, 0.22) !important;
        color: #c8c3ff !important;
      }

      html[data-dte-theme=dark] .module-choice,
      html[data-dte-theme=dark] .module-period-preview,
      html[data-dte-theme=dark] .module-dte-preview {
        background: var(--module-dark-panel-soft) !important;
        border-color: var(--module-dark-border) !important;
        color: var(--module-dark-text) !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .module-choice:hover {
        background: #1a2432 !important;
        border-color: #566176 !important;
      }

      html[data-dte-theme=dark] .module-dte-badge,
      html[data-dte-theme=dark] .module-dte-badge.is-tax-credit,
      html[data-dte-theme=dark] .module-dte-badge.is-retention {
        background: #202a38 !important;
        border-color: #39465a !important;
        color: #d8dee8 !important;
      }

      html[data-dte-theme=dark] .module-dte-badge.is-credit-note {
        background: rgba(185, 28, 28, 0.14) !important;
        border-color: rgba(248, 113, 113, 0.32) !important;
        color: #fecaca !important;
      }

      html[data-dte-theme=dark] .module-dte-badge.is-debit-note {
        background: rgba(154, 52, 18, 0.16) !important;
        border-color: rgba(251, 146, 60, 0.3) !important;
        color: #fed7aa !important;
      }

      html[data-dte-theme=dark] .btn-module,
      html[data-dte-theme=dark] .module-page-btn.is-active {
        background: #5752aa !important;
        border-color: #5752aa !important;
        color: #f8fafc !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .btn-module:hover,
      html[data-dte-theme=dark] .btn-module:focus {
        background: #635db8 !important;
        border-color: #635db8 !important;
      }

      html[data-dte-theme=dark] .btn-module-outline,
      html[data-dte-theme=dark] .module-page-btn,
      html[data-dte-theme=dark] .module-action-zone .btn-outline-secondary {
        background: transparent !important;
        border-color: #506178 !important;
        color: #d7deeb !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .btn-module-outline:hover,
      html[data-dte-theme=dark] .btn-module-outline:focus,
      html[data-dte-theme=dark] .module-page-btn:hover,
      html[data-dte-theme=dark] .module-page-btn:focus,
      html[data-dte-theme=dark] .module-action-zone .btn-outline-secondary:hover {
        background: #1b2634 !important;
        border-color: #66758c !important;
        color: #fff !important;
      }

      html[data-dte-theme=dark] .btn-outline-danger {
        background: transparent !important;
        border-color: rgba(248, 113, 113, 0.48) !important;
        color: #fca5a5 !important;
      }

      html[data-dte-theme=dark] .btn-outline-danger:hover,
      html[data-dte-theme=dark] .btn-outline-danger:focus {
        background: rgba(185, 28, 28, 0.16) !important;
        color: #fecaca !important;
      }

      html[data-dte-theme=dark] .module-shell .btn:disabled,
      html[data-dte-theme=dark] .module-shell .btn.disabled {
        background: #0f151e !important;
        border-color: #242d3a !important;
        color: #667386 !important;
        opacity: 1;
      }

      html[data-dte-theme=dark] .module-export-menu {
        background: #121923 !important;
        border-color: var(--module-dark-border) !important;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.26) !important;
      }

      html[data-dte-theme=dark] .module-export-heading {
        color: var(--module-dark-muted) !important;
      }

      html[data-dte-theme=dark] .module-export-item {
        color: var(--module-dark-text) !important;
      }

      html[data-dte-theme=dark] .module-export-item:hover,
      html[data-dte-theme=dark] .module-export-item:focus {
        background: #1b2634 !important;
        color: #fff !important;
      }

      html[data-dte-theme=dark] .module-table,
      html[data-dte-theme=dark] .module-table td,
      html[data-dte-theme=dark] .module-table th {
        background: transparent !important;
        border-color: var(--module-dark-border) !important;
        color: var(--module-dark-text) !important;
      }

      html[data-dte-theme=dark] .module-table thead th {
        background: #151e2a !important;
        color: #eef2f7 !important;
      }

      html[data-dte-theme=dark] .module-table tbody tr.total-row td,
      html[data-dte-theme=dark] .module-table tbody tr.credit-note-row td,
      html[data-dte-theme=dark] .module-row-actions {
        background: #151e2a !important;
      }

      html[data-dte-theme=dark] .document-type-badge {
        background: #202a38 !important;
        color: #d8dee8 !important;
      }

      html[data-dte-theme=dark] .modal-backdrop.show {
        opacity: 0.72;
      }

      html[data-dte-theme=dark] .module-loading-overlay {
        background: rgba(4, 6, 10, 0.5);
      }

      html[data-dte-theme=dark] .module-loading-bar {
        background: #253143;
      }

      html[data-dte-theme=dark] .module-loading-bar::after {
        background: linear-gradient(90deg, #5752aa 0%, #7a75c7 100%);
      }

      html[data-dte-theme=dark] .btn-close {
        filter: invert(1) grayscale(1);
        opacity: 0.72;
      }

      html[data-dte-theme=dark] .module-shell .card,
      html[data-dte-theme=dark] .module-hero,
      html[data-dte-theme=dark] .module-actions-card,
      html[data-dte-theme=dark] .module-summary-card,
      html[data-dte-theme=dark] .module-chip,
      html[data-dte-theme=dark] .module-types-summary,
      html[data-dte-theme=dark] .module-status-pill,
      html[data-dte-theme=dark] .module-empty-state,
      html[data-dte-theme=dark] .module-table-wrap,
      html[data-dte-theme=dark] .module-table-meta,
      html[data-dte-theme=dark] .module-table-pager,
      html[data-dte-theme=dark] .module-secondary-block,
      html[data-dte-theme=dark] .module-secondary-body,
      html[data-dte-theme=dark] .module-secondary-trigger,
      html[data-dte-theme=dark] .module-loading-card,
      html[data-dte-theme=dark] .module-choice,
      html[data-dte-theme=dark] .module-period-preview,
      html[data-dte-theme=dark] .module-dte-preview {
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
      }

      html[data-dte-theme=dark] .module-hero,
      html[data-dte-theme=dark] .module-shell .card,
      html[data-dte-theme=dark] .module-modal .modal-content,
      html[data-dte-theme=dark] .module-modal .modal-header,
      html[data-dte-theme=dark] .module-modal .modal-body,
      html[data-dte-theme=dark] .module-modal .modal-footer {
        background-image: none !important;
        background-color: var(--module-dark-panel) !important;
      }

      html[data-dte-theme=dark] .module-summary-card,
      html[data-dte-theme=dark] .module-chip,
      html[data-dte-theme=dark] .module-types-summary,
      html[data-dte-theme=dark] .module-status-pill,
      html[data-dte-theme=dark] .module-empty-state,
      html[data-dte-theme=dark] .module-table-meta,
      html[data-dte-theme=dark] .module-table-pager,
      html[data-dte-theme=dark] .module-secondary-body,
      html[data-dte-theme=dark] .module-secondary-trigger,
      html[data-dte-theme=dark] .module-loading-card,
      html[data-dte-theme=dark] .module-choice,
      html[data-dte-theme=dark] .module-period-preview,
      html[data-dte-theme=dark] .module-dte-preview {
        background-image: none !important;
        background-color: var(--module-dark-panel-soft) !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-backdrop,
      html[data-dte-theme=dark] .modal-backdrop {
        background-color: #05070b !important;
      }

      html[data-dte-theme=dark] .modal-backdrop.show {
        opacity: 0.88 !important;
      }

      html[data-dte-theme=dark] .btn-module-outline,
      html[data-dte-theme=dark] .module-page-btn,
      html[data-dte-theme=dark] .module-action-zone .btn-outline-secondary,
      html[data-dte-theme=dark] .btn-outline-danger {
        background-color: #151e2a !important;
      }

      html[data-dte-theme=dark] .btn-module-outline:hover,
      html[data-dte-theme=dark] .btn-module-outline:focus,
      html[data-dte-theme=dark] .module-page-btn:hover,
      html[data-dte-theme=dark] .module-page-btn:focus,
      html[data-dte-theme=dark] .module-action-zone .btn-outline-secondary:hover,
      html[data-dte-theme=dark] .btn-outline-danger:hover,
      html[data-dte-theme=dark] .btn-outline-danger:focus {
        background-color: #1b2634 !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-dialog {
        max-width: min(780px, calc(100vw - 2rem));
      }

      html[data-dte-theme=dark] .module-modal .modal-content {
        background: #111722 !important;
        border: 1px solid #303a4b !important;
        border-radius: 22px !important;
        box-shadow: 0 28px 90px rgba(0, 0, 0, 0.58) !important;
        overflow: hidden;
      }

      html[data-dte-theme=dark] .module-modal .modal-header {
        align-items: center;
        padding: 1.45rem 1.65rem 1.15rem;
        background: #141b27 !important;
        border-bottom: 1px solid #263140 !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-header .d-flex {
        align-items: center !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-header h3 {
        margin: 0;
        color: #f3f6fb !important;
        font-size: 1.45rem;
        font-weight: 750;
        letter-spacing: 0;
      }

      html[data-dte-theme=dark] .module-modal .modal-body {
        padding: 1.35rem 1.65rem 1.2rem;
        background: #111722 !important;
      }

      html[data-dte-theme=dark] .module-modal .modal-footer {
        padding: 0 1.65rem 1.55rem;
        background: #111722 !important;
        border-top: 0 !important;
      }

      html[data-dte-theme=dark] .module-modal-eyebrow {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: #232747 !important;
        color: #c8c3ff !important;
        box-shadow: inset 0 0 0 1px rgba(200, 195, 255, 0.1);
      }

      html[data-dte-theme=dark] .module-modal .btn-close {
        width: 38px;
        height: 38px;
        margin: 0;
        border-radius: 12px;
        background-color: #1a2230;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23d8dee8'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293A1 1 0 0 1 .293 14.293L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e");
        background-size: 0.9rem;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 1;
        filter: none;
      }

      html[data-dte-theme=dark] .module-modal .btn-close:hover,
      html[data-dte-theme=dark] .module-modal .btn-close:focus {
        background-color: #242d3d;
      }

      html[data-dte-theme=dark] .module-choice {
        position: relative;
        padding: 1.05rem 1.15rem;
        border: 1px solid #2b3545 !important;
        border-radius: 16px !important;
        background: #151d29 !important;
        color: #edf2f8 !important;
        box-shadow: none !important;
      }

      html[data-dte-theme=dark] .module-choice + .module-choice,
      html[data-dte-theme=dark] .module-modal .d-grid .module-choice {
        margin-top: 0;
      }

      html[data-dte-theme=dark] .module-choice:hover,
      html[data-dte-theme=dark] .module-choice:focus {
        background: #182231 !important;
        border-color: #435068 !important;
      }

      html[data-dte-theme=dark] .module-choice-body {
        gap: 1rem;
      }

      html[data-dte-theme=dark] .module-choice .font-weight-bold {
        color: #f2f5f9 !important;
        font-size: 1rem;
        line-height: 1.25;
      }

      html[data-dte-theme=dark] .period-work-title,
      html[data-dte-theme=dark] .period-module-name {
        color: #f2f5f9 !important;
      }

      html[data-dte-theme=dark] .period-work-subtitle,
      html[data-dte-theme=dark] .period-module-kicker {
        color: #a8b2c1 !important;
      }

      html[data-dte-theme=dark] .period-field label {
        color: #c8d1de !important;
      }

      html[data-dte-theme=dark] .period-field .form-control {
        background: #151d29 !important;
        border-color: #2b3545 !important;
        color: #edf2f8 !important;
      }

      html[data-dte-theme=dark] .period-field .form-control:focus {
        border-color: #6f68d8 !important;
        box-shadow: 0 0 0 0.2rem rgba(111, 104, 216, 0.24) !important;
      }

      html[data-dte-theme=dark] .period-module-icon {
        background: #111722 !important;
        color: #c8c3ff !important;
        box-shadow: inset 0 0 0 1px #2b3545;
      }

      html[data-dte-theme=dark] .module-choice small,
      html[data-dte-theme=dark] .module-modal .small,
      html[data-dte-theme=dark] .module-modal .text-uppercase.small {
        color: #a8b2c1 !important;
      }

      html[data-dte-theme=dark] .module-modal .text-uppercase.small {
        margin-top: 1.35rem;
        margin-bottom: 0.75rem !important;
        letter-spacing: 0.08em;
        color: #c0c8d6 !important;
      }

      html[data-dte-theme=dark] .module-modal .d-grid {
        gap: 0.8rem !important;
      }

      html[data-dte-theme=dark] .module-modal .btn-module-soft {
        background: #282849 !important;
        border: 1px solid #403f68 !important;
        color: #ddd9ff !important;
      }

      html[data-dte-theme=dark] .module-modal .btn-module-outline {
        background: #1a2432 !important;
        border-color: #46546c !important;
        color: #dce3ee !important;
      }

      html[data-dte-theme=dark] .module-modal .btn-module-outline:hover,
      html[data-dte-theme=dark] .module-modal .btn-module-outline:focus {
        background: #223044 !important;
        border-color: #5a6982 !important;
        color: #fff !important;
      }

      html[data-dte-theme=dark] .module-modal .btn-outline-danger {
        background: #281a20 !important;
        border-color: #6e3b45 !important;
        color: #f6b4bd !important;
      }

      html[data-dte-theme=dark] .module-modal .btn-outline-danger:hover,
      html[data-dte-theme=dark] .module-modal .btn-outline-danger:focus {
        background: #3a2028 !important;
        border-color: #8a4653 !important;
        color: #ffd2d8 !important;
      }

      html[data-dte-theme=dark] #bookChoiceModal .modal-body {
        display: block;
      }

      html[data-dte-theme=dark] #bookChoiceModal .module-choice:first-of-type {
        margin-bottom: 1.25rem !important;
      }

      html[data-dte-theme=dark] #bookChoiceModal .modal-footer {
        padding-top: 0.15rem;
      }

      html[data-dte-theme=dark] #bookChoiceModal .modal-footer .btn {
        min-height: 42px;
        padding-inline: 1.1rem;
        border-radius: 12px;
      }

      html[data-dte-theme=dark] .modal-backdrop {
        background: #03060b !important;
      }

      html[data-dte-theme=dark] .modal-backdrop.show {
        opacity: 0.9 !important;
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
            <div class="row mb-4">
              <div class="col-12">
                <div class="card module-hero">
                  <div class="card-body">
                    <div class="module-hero-main">
                      <div>
                        <span class="module-eyebrow">
                          <i class="mdi mdi-file-document-multiple-outline"></i>
                          Gestion de libros
                        </span>
                        <h1 class="module-title"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Modulo')); ?></h1>
                        <p class="module-copy"><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? '')); ?></p>
                        <div class="module-chip">
                          <i class="mdi mdi-office-building-outline"></i>
                          <span><?php echo htmlspecialchars((string) ($empresaActiva['nombre'] ?? 'Empresa no seleccionada')); ?></span>
                          <?php if ($periodoLibroActivo !== ''): ?>
                          <span class="module-context-separator">&middot;</span>
                          <span><?php echo htmlspecialchars($periodoLibroActivo); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="module-types-summary">
                        <i class="mdi mdi-file-check-outline"></i>
                        <span>
                          <strong>Tipos DTE aceptados</strong>
                          <?php echo htmlspecialchars($tiposDteValidosResumen !== '' ? $tiposDteValidosResumen : 'Sin tipos configurados'); ?>
                        </span>
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
                    <div class="module-command-center">
                      <div class="input-group module-search">
                        <span class="input-group-text bg-white"><i class="mdi mdi-magnify"></i></span>
                        <input type="text" class="form-control" id="tableSearchInput" placeholder="Buscar en el libro">
                      </div>

                      <div class="module-action-zone">
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#companySelectModal">
                          <i class="mdi mdi-office-building-outline me-1"></i> Empresa
                        </button>
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#bookChoiceModal" <?php echo $empresaActiva ? '' : 'disabled'; ?>>
                          <i class="mdi mdi-book-open-page-variant-outline me-1"></i> Abrir libro
                        </button>
                        <button type="button" class="btn btn-module" data-bs-toggle="modal" data-bs-target="#importJsonModal" <?php echo $libroActivo ? '' : 'disabled'; ?>>
                          <i class="mdi mdi-plus me-1"></i> Importar JSON
                        </button>
                        <button type="button" class="btn btn-module-outline" data-open-manual-record <?php echo $libroActivo ? '' : 'disabled'; ?>>
                          <i class="mdi mdi-pencil-plus-outline me-1"></i> Crear manual
                        </button>
                        <button type="button" class="btn btn-module-outline" data-bs-toggle="modal" data-bs-target="#columnPreferencesModal">
                          <i class="mdi mdi-table-column-width me-1"></i> Columnas
                        </button>
                        <div class="btn-group">
                          <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?php echo $libroActivo ? '' : 'disabled'; ?>>
                            <i class="mdi mdi-download me-1"></i> Exportar
                          </button>
                          <div class="dropdown-menu dropdown-menu-end module-export-menu">
                            <?php if ($libroActivo): ?>
                              <?php if ($tipoLibro === 'compras'): ?>
                              <div class="module-export-heading">
                                <i class="mdi mdi-bank-outline"></i>
                                Hacienda
                              </div>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_compras">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV Anexo de Compras
                              </a>
                              <?php elseif ($tipoLibro === 'retencion_iva'): ?>
                              <div class="module-export-heading">
                                <i class="mdi mdi-bank-outline"></i>
                                Hacienda
                              </div>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_f14">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV Anexo F14
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_casilla_66">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV Casilla 66
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_casilla_162">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV Casilla 162
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=hacienda_casilla_163">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV Casilla 163
                              </a>
                              <?php endif; ?>
                              <?php if (in_array($tipoLibro, ['compras', 'retencion_iva'], true)): ?>
                              <div class="dropdown-divider"></div>
                              <?php endif; ?>
                              <div class="module-export-heading">
                                <i class="mdi mdi-book-open-page-variant-outline"></i>
                                Libro interno
                              </div>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=excel">
                                <i class="mdi mdi-file-excel-outline"></i>
                                Excel interno
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=csv">
                                <i class="mdi mdi-file-delimited-outline"></i>
                                CSV interno
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&formato=pdf" target="_blank" rel="noopener">
                                <i class="mdi mdi-file-pdf-box"></i>
                                PDF
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&modo=json&formato=json" target="_blank" rel="noopener">
                                <i class="mdi mdi-code-json"></i>
                                JSON estructurado
                              </a>
                              <div class="dropdown-divider"></div>
                              <div class="module-export-heading">
                                <i class="mdi mdi-alert-circle-outline"></i>
                                Revision
                              </div>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=fuera_periodo&formato=excel">
                                <i class="mdi mdi-file-excel-outline"></i>
                                Fuera de periodo en Excel
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=fuera_periodo&formato=pdf" target="_blank" rel="noopener">
                                <i class="mdi mdi-file-pdf-box"></i>
                                Fuera de periodo en PDF
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=repetidas&formato=excel">
                                <i class="mdi mdi-file-excel-outline"></i>
                                Repetidas en Excel
                              </a>
                              <a class="dropdown-item module-export-item" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=repetidas&formato=pdf" target="_blank" rel="noopener">
                                <i class="mdi mdi-file-pdf-box"></i>
                                Repetidas en PDF
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="module-status-strip">
                      <div class="module-status-list">
                        <span class="module-status-pill">
                          <i class="mdi mdi-cloud-upload-outline"></i>
                          Importacion disponible: <strong><?php echo htmlspecialchars($cuotaLabel); ?></strong>
                        </span>
                        <span class="module-status-pill is-ok">
                          <i class="mdi mdi-content-save-check-outline"></i>
                          Guardado automatico
                        </span>
                      <?php if ($libroActivo): ?>
                        <span class="module-status-pill">
                          <i class="mdi mdi-bookmark-check-outline"></i>
                          Libro activo: <strong><?php echo htmlspecialchars($periodoLibroActivo); ?></strong>
                        </span>
                      <?php endif; ?>
                      </div>
                      <?php if ($libroActivo): ?>
                      <div class="module-book-controls">
                        <a href="?clear_book=1" class="btn btn-sm btn-outline-secondary">
                          <i class="mdi mdi-close-circle-outline me-1"></i> Cerrar libro activo
                        </a>
                        <form method="post" class="m-0" data-confirm="Esto eliminara todos los registros del libro abierto.">
                          <input type="hidden" name="action" value="clear_book_records">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="mdi mdi-delete-alert-outline me-1"></i> Vaciar libro completo
                          </button>
                        </form>
                      </div>
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
	                        <?php if (isset($importData['recibidas'])): ?>
	                        <span class="badge badge-primary p-2">Recibidas: <?php echo (int) ($importData['recibidas'] ?? 0); ?><?php echo isset($importData['esperadas']) ? ' / ' . (int) $importData['esperadas'] : ''; ?></span>
	                        <?php endif; ?>
		                        <span class="badge badge-success p-2">Importadas: <?php echo (int) ($importData['importadas'] ?? 0); ?></span>
		                        <span class="badge badge-info p-2">Incluidas en libro: <?php echo (int) ($importData['incluidas_libro'] ?? $importData['importadas'] ?? 0); ?></span>
		                        <span class="badge badge-secondary p-2">Fuera de periodo: <?php echo (int) ($importData['fuera_periodo'] ?? 0); ?></span>
	                        <?php if (!empty($importData['reparadas'])): ?>
                        <span class="badge badge-info p-2">Reparadas: <?php echo (int) ($importData['reparadas'] ?? 0); ?></span>
                        <?php endif; ?>
                        <span class="badge badge-warning p-2">Duplicadas: <?php echo (int) ($importData['duplicadas_total'] ?? 0); ?></span>
                        <span class="badge badge-danger p-2">Invalidas: <?php echo (int) ($importData['invalidas_total'] ?? 0); ?></span>
                        <span class="badge badge-light p-2">Documentos unicos incluidos: <?php echo (int) ($importData['documentos_unicos_incluidos'] ?? $importData['incluidas_libro'] ?? 0); ?></span>
                        <span class="badge badge-light p-2">Copias duplicadas apartadas: <?php echo (int) ($importData['copias_duplicadas_apartadas'] ?? $importData['duplicadas_total'] ?? 0); ?></span>
                        <span class="badge badge-light p-2">Disponibilidad restante: <?php echo htmlspecialchars($cuotaRestanteLabel); ?></span>
                      </div>
                    </div>

                    <?php
                      $duplicadasImportacion = array_values(array_filter(
                          is_array($importData['duplicadas'] ?? null) ? $importData['duplicadas'] : [],
                          static function ($duplicada): bool {
                              if (!is_array($duplicada)) {
                                  return false;
                              }

                              $idOriginal = (int) ($duplicada['id_original'] ?? 0);
                              $codigoOriginal = trim((string) ($duplicada['codigo_generacion_original'] ?? ''));
                              $documentoOriginal = trim((string) ($duplicada['documento_original'] ?? ''));

                              return $idOriginal > 0 || ($codigoOriginal !== '' && $documentoOriginal !== '');
                          }
                      ));
                    ?>
                    <?php if (!empty($duplicadasImportacion)): ?>
                    <div class="mt-4">
                      <h6 class="mb-3">Documentos duplicados</h6>
                      <div class="table-responsive module-table-shell is-pending">
                        <table class="table table-sm table-bordered module-table module-paginated-table" data-page-size="10" data-empty-message="No hay documentos duplicados en esta vista.">
                          <thead>
                            <tr>
                              <th>Archivo duplicado apartado</th>
                              <th>Codigo de generacion del documento apartado</th>
                              <th>Numero de control</th>
                              <th>Proveedor</th>
                              <th>Documento original encontrado</th>
                              <th>Motivo</th>
                              <th>Estado</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($duplicadasImportacion as $duplicada): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($duplicada['nombre_archivo'] ?? $duplicada['archivo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['codigoGeneracion'] ?? $duplicada['codigo_generacion'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['numeroControl'] ?? $duplicada['numero_control'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['nombre_proveedor'] ?? $duplicada['proveedor'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['documento_original'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['razon'] ?? $duplicada['motivo_exclusion'] ?? 'Codigo de generacion repetido. Este archivo fue apartado porque ya existe un documento original contabilizado.')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($duplicada['estado'] ?? $duplicada['estado_duplicado'] ?? 'Apartado, no contabilizado')); ?></td>
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
                      <div class="table-responsive module-table-shell is-pending">
                        <table class="table table-sm table-bordered module-table module-paginated-table" data-page-size="10" data-empty-message="No hay documentos invalidos en esta vista.">
                          <thead>
                            <tr>
                              <th>Archivo</th>
                              <th>Tipo DTE</th>
                              <th>Numero de control</th>
                              <th>Codigo de generacion</th>
                              <th>Motivo</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($importData['invalidas'] as $invalida): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string) ($invalida['nombre_archivo'] ?? $invalida['archivo'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['tipoDte'] ?? $invalida['tipo_dte'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['numeroControl'] ?? $invalida['numero_control'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['codigoGeneracion'] ?? $invalida['codigo_generacion'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string) ($invalida['razon'] ?? $invalida['nombreTipo'] ?? $invalida['tipo_dte_nombre'] ?? '')); ?></td>
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
	                      <div class="p-4 text-muted">
	                        <div class="mb-3">Aun no hay facturas cargadas en este libro.</div>
	                        <button type="button" class="btn btn-module" data-open-manual-record>
	                          <i class="mdi mdi-pencil-plus-outline me-1"></i> Crear registro manual
	                        </button>
	                      </div>
	                      <?php else: ?>
	                      <div class="table-responsive module-table-shell is-pending">
	                        <table class="table table-bordered table-sm module-table module-paginated-table" id="moduleTable" data-page-size="10" data-empty-message="No hay registros que coincidan con la busqueda.">
                          <thead>
	                            <tr>
	                              <th>Acciones</th>
	                              <?php foreach ($columnasTablaPrincipal as $columna): ?>
	                              <th><?php echo htmlspecialchars((string) ($columnLabels[$columna] ?? $columna)); ?></th>
	                              <?php endforeach; ?>
	                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($filas as $fila): ?>
                            <?php
                              $esFilaTotal = !isset($fila['no']) || $fila['no'] === null;
                              $searchChunks = [];
                              foreach ($columnasTablaPrincipal as $columna) {
                                  $searchChunks[] = (string) ($fila[$columna] ?? '');
                              }
                            ?>
                            <?php
                              $rowClass = $esFilaTotal ? 'total-row' : '';
                              if (!$esFilaTotal && shouldHighlightDocumentType($tipoLibro, (string) ($fila['tipo_dte'] ?? ''), $modulePreferences)) {
                                  $rowClass = trim($rowClass . ' credit-note-row');
                              }
                            ?>
                            <tr class="<?php echo $rowClass; ?>" data-row-type="<?php echo $esFilaTotal ? 'total' : 'data'; ?>" data-search="<?php echo htmlspecialchars(strtolower(implode(' ', $searchChunks))); ?>">
	                              <td class="module-row-actions">
	                                <?php if (!$esFilaTotal && (int) ($fila['_id'] ?? 0) > 0): ?>
	                                <?php $registroJson = htmlspecialchars(json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>
	                                <button type="button" class="btn btn-sm btn-outline-primary" title="Editar registro" data-edit-record="<?php echo $registroJson; ?>">
	                                  <i class="mdi mdi-pencil"></i>
	                                </button>
	                                <form method="post" class="d-inline" data-confirm="Seguro que quieres eliminar este registro?">
	                                  <input type="hidden" name="action" value="delete_record">
	                                  <input type="hidden" name="id_factura" value="<?php echo (int) ($fila['_id'] ?? 0); ?>">
	                                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar registro">
	                                    <i class="mdi mdi-delete-outline"></i>
	                                  </button>
	                                </form>
	                                <?php endif; ?>
	                              </td>
	                              <?php foreach ($columnasTablaPrincipal as $columna): ?>
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
	                                <?php if ($columna === 'tipo_documento_nombre' && !$esFilaTotal): ?>
	                                  <?php
                                      $tipoDteFila = (string) ($fila['tipo_dte'] ?? '');
                                      $labelTipoDocumento = getDocumentTypeLabelForModule($tipoLibro, $tipoDteFila, $modulePreferences, $valorRender);
                                    ?>
	                                  <?php if ($labelTipoDocumento !== ''): ?>
	                                  <span class="document-type-badge <?php echo htmlspecialchars(ModulePreferenceService::documentTypeClass($tipoDteFila)); ?>"><?php echo htmlspecialchars($labelTipoDocumento); ?></span>
	                                  <?php else: ?>
	                                  <?php echo htmlspecialchars($labelTipoDocumento); ?>
	                                  <?php endif; ?>
	                                <?php else: ?>
                                  <?php echo htmlspecialchars($valorRender); ?>
                                <?php endif; ?>
                              </td>
                              <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
	                        </table>
	                      </div>
	                      <div class="d-flex justify-content-end mt-3">
	                        <button type="button" class="btn btn-module" data-open-manual-record>
	                          <i class="mdi mdi-pencil-plus-outline me-1"></i> Crear registro manual
	                        </button>
	                      </div>
	                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($libroActivo && ($cantidadRepetidas > 0 || $cantidadFueraPeriodo > 0)): ?>
            <div class="row module-secondary-section">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <div class="module-secondary-heading">
                      <div>
                        <h4>Apartados secundarios</h4>
                        <p>Documentos guardados para revision que no forman parte de la tabla principal del periodo.</p>
                      </div>
                    </div>

                    <?php if ($cantidadRepetidas > 0): ?>
                    <div class="module-secondary-block">
                      <button class="module-secondary-trigger" type="button" data-bs-toggle="collapse" data-bs-target="#secondaryRepeatedDocs" aria-expanded="false" aria-controls="secondaryRepeatedDocs">
                        <span class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                          <span>
                            <strong>Documentos detectados como repetidos</strong>
                            <span class="d-block text-muted small">Registros apartados porque coinciden con documentos ya cargados. No afectan la tabla principal ni las sumatorias.</span>
                          </span>
                          <span class="badge badge-warning p-2"><?php echo (int) $cantidadRepetidas; ?> documento(s)</span>
                        </span>
                      </button>
                      <div class="collapse" id="secondaryRepeatedDocs">
                        <div class="module-secondary-body">
                          <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=repetidas&formato=excel">
                              <i class="mdi mdi-file-excel-outline me-1"></i> Excel
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=repetidas&formato=pdf" target="_blank" rel="noopener">
                              <i class="mdi mdi-file-pdf-box me-1"></i> PDF
                            </a>
                          </div>
                          <div class="table-responsive module-table-shell is-pending">
                            <table class="table table-bordered table-sm module-table module-paginated-table" data-page-size="10" data-empty-message="No hay facturas repetidas apartadas.">
                              <thead>
                                <tr>
                                  <?php foreach ($repetidasColumns as $label): ?>
                                  <th><?php echo htmlspecialchars((string) $label); ?></th>
                                  <?php endforeach; ?>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($repetidasFilas as $filaRepetida): ?>
                                <tr>
                                  <?php foreach ($repetidasColumns as $campoRepetido => $labelRepetido): ?>
                                  <?php
                                    $valorRepetido = $filaRepetida[$campoRepetido] ?? '';
                                    if (in_array($campoRepetido, $repetidasNumeric, true) && $valorRepetido !== '' && is_numeric($valorRepetido)) {
                                        $valorRepetido = number_format((float) $valorRepetido, 2);
                                    }
                                  ?>
                                  <td class="<?php echo in_array($campoRepetido, $repetidasNumeric, true) ? 'text-end' : ''; ?>">
                                    <?php echo htmlspecialchars((string) $valorRepetido); ?>
                                  </td>
                                  <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($cantidadFueraPeriodo > 0): ?>
                    <div class="module-secondary-block">
                      <button class="module-secondary-trigger" type="button" data-bs-toggle="collapse" data-bs-target="#secondaryOutOfPeriodDocs" aria-expanded="false" aria-controls="secondaryOutOfPeriodDocs">
                        <span class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                          <span>
                            <strong>Documentos fuera del periodo principal</strong>
                            <span class="d-block text-muted small">DTE mayores a 3 meses respecto al periodo del libro. Se conservan para revision sin afectar las sumatorias principales.</span>
                          </span>
                          <span class="badge badge-secondary p-2"><?php echo (int) $cantidadFueraPeriodo; ?> documento(s)</span>
                        </span>
                      </button>
                      <div class="collapse" id="secondaryOutOfPeriodDocs">
                        <div class="module-secondary-body">
                          <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=fuera_periodo&formato=excel">
                              <i class="mdi mdi-file-excel-outline me-1"></i> Excel
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('api/facturas/exportar.php')); ?>?id_libro=<?php echo (int) $libroActivo['id']; ?>&reporte=fuera_periodo&formato=pdf" target="_blank" rel="noopener">
                              <i class="mdi mdi-file-pdf-box me-1"></i> PDF
                            </a>
                          </div>
                          <div class="table-responsive module-table-shell is-pending">
                            <table class="table table-bordered table-sm module-table module-paginated-table" data-page-size="10" data-empty-message="No hay DTE fuera de periodo.">
                              <thead>
                                <tr>
                                  <?php foreach ($fueraPeriodoColumns as $label): ?>
                                  <th><?php echo htmlspecialchars((string) $label); ?></th>
                                  <?php endforeach; ?>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($fueraPeriodoFilas as $filaFuera): ?>
                                <tr>
                                  <?php foreach ($fueraPeriodoColumns as $campoFuera => $labelFuera): ?>
                                  <?php
                                    $valorFuera = $filaFuera[$campoFuera] ?? '';
                                    if (in_array($campoFuera, $fueraPeriodoNumeric, true) && $valorFuera !== '' && is_numeric($valorFuera)) {
                                        $valorFuera = number_format((float) $valorFuera, 2);
                                    }
                                  ?>
                                  <td class="<?php echo in_array($campoFuera, $fueraPeriodoNumeric, true) ? 'text-end' : ''; ?>">
                                    <?php echo htmlspecialchars((string) $valorFuera); ?>
                                  </td>
                                  <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php include __DIR__ . '/../partials/_footer.php'; ?>
        </div>
      </div>
    </div>

    <div class="modal fade module-modal" id="columnPreferencesModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <span class="module-modal-eyebrow"><i class="mdi mdi-table-column-width"></i></span>
              <h3 class="mb-1">Personalizar columnas</h3>
              <p class="text-muted mb-0">Ajusta solo la vista en pantalla y el Excel interno de este modulo.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="columnPreferencesForm">
              <div class="mb-4">
                <div class="module-preference-table">
                  <div class="module-preference-row module-preference-head">
                    <span>Columna</span>
                    <span>Tabla</span>
                    <span>Excel</span>
                  </div>
                  <?php foreach (($columnsPreferencePayload['columns'] ?? []) as $columnPreference): ?>
                  <?php
                    $preferenceColumnKey = (string) ($columnPreference['key'] ?? '');
                    $preferenceColumnId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $preferenceColumnKey);
                  ?>
                  <div class="module-preference-row">
                    <strong><?php echo htmlspecialchars((string) ($columnPreference['label'] ?? $preferenceColumnKey)); ?></strong>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="prefTable_<?php echo htmlspecialchars($preferenceColumnId); ?>" data-pref-column-table="<?php echo htmlspecialchars($preferenceColumnKey); ?>" <?php echo !empty($columnPreference['table']) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="prefTable_<?php echo htmlspecialchars($preferenceColumnId); ?>">Mostrar</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="prefExcel_<?php echo htmlspecialchars($preferenceColumnId); ?>" data-pref-column-excel="<?php echo htmlspecialchars($preferenceColumnKey); ?>" <?php echo !empty($columnPreference['excel']) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="prefExcel_<?php echo htmlspecialchars($preferenceColumnId); ?>">Incluir</label>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <?php if (!empty($columnsPreferencePayload['document_types'])): ?>
              <div>
                <h5 class="mb-3">Tipos de documento</h5>
                <div class="module-preference-types">
                  <?php foreach ($columnsPreferencePayload['document_types'] as $typePreference): ?>
                  <?php
                    $typeCode = (string) ($typePreference['code'] ?? '');
                    $typeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $typeCode);
                  ?>
                  <div class="module-preference-type">
                    <strong class="module-preference-type-title"><?php echo htmlspecialchars((string) ($typePreference['label'] ?? ('Tipo DTE ' . $typeCode))); ?></strong>
                    <div class="module-preference-type-option">
                      <label class="form-check-label" for="prefTypeShow_<?php echo htmlspecialchars($typeId); ?>">Mostrar nombre</label>
                      <input class="form-check-input" type="checkbox" role="switch" id="prefTypeShow_<?php echo htmlspecialchars($typeId); ?>" data-pref-type-show="<?php echo htmlspecialchars($typeCode); ?>" <?php echo !empty($typePreference['show']) ? 'checked' : ''; ?>>
                    </div>
                    <div class="module-preference-type-option">
                      <label class="form-check-label" for="prefTypeHighlight_<?php echo htmlspecialchars($typeId); ?>">Marcar visualmente</label>
                      <input class="form-check-input" type="checkbox" role="switch" id="prefTypeHighlight_<?php echo htmlspecialchars($typeId); ?>" data-pref-type-highlight="<?php echo htmlspecialchars($typeCode); ?>" <?php echo !empty($typePreference['highlight']) ? 'checked' : ''; ?>>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </form>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <button type="button" class="btn btn-module-outline" id="restoreColumnPreferences">
              Restaurar por defecto
            </button>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-module" id="saveColumnPreferences">Guardar</button>
            </div>
          </div>
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
            <div>
              <div class="text-muted font-weight-bold text-uppercase small mb-3">Tus empresas</div>
              <?php if (!empty($empresas)): ?>
              <div class="d-grid gap-3">
                <?php foreach ($empresas as $empresa): ?>
                <?php
                  $empresaEditPayload = [
                      'id_empresa'    => (int) $empresa['id'],
                      'nombre'        => (string) ($empresa['nombre'] ?? ''),
                      'iniciales'     => (string) ($empresa['iniciales'] ?? ''),
                      'color_emblema' => (string) ($empresa['color_emblema'] ?? '#f97316'),
                      'dui'           => (string) ($empresa['dui'] ?? ''),
                      'nit'           => (string) ($empresa['nit'] ?? ''),
                      'nrc'           => (string) ($empresa['nrc'] ?? ''),
                      'tipo_legal'    => (string) ($empresa['tipo_legal'] ?? 'natural'),
                  ];
                ?>
                <div class="module-choice mb-0">
                  <div class="module-choice-body module-choice-body--stack">
                    <div>
                      <div class="font-weight-bold"><?php echo htmlspecialchars((string) $empresa['nombre']); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars((string) ($empresa['nit'] ?? 'Sin NIT')); ?></small>
                      <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if ((int) ($empresaActiva['id'] ?? 0) === (int) $empresa['id']): ?>
                        <span class="module-company-pill">Activa</span>
                        <?php endif; ?>
                        <?php if ((int) ($empresaUltima['id'] ?? 0) === (int) $empresa['id']): ?>
                        <span class="module-company-pill">Ultima</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                      <form method="post" class="m-0">
                        <input type="hidden" name="action" value="select_company">
                        <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-module">Seleccionar</button>
                      </form>
                      <button type="button"
                              class="btn btn-sm btn-module-outline"
                              data-edit-company="<?php echo htmlspecialchars(json_encode($empresaEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
                        Editar
                      </button>
                      <form method="post"
                            class="m-0"
                            data-confirm="Esto desactivara la empresa seleccionada. Sus libros dejaran de aparecer hasta que se reactive desde base de datos."
                            data-confirm-title="Desactivar empresa"
                            data-confirm-button="Desactivar">
                        <input type="hidden" name="action" value="deactivate_company">
                        <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
                      </form>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                <button type="button" class="btn btn-module-outline" data-open-modal="companyCreateModal">Agregar empresa</button>
              </div>
              <?php else: ?>
              <div class="module-empty-state">
                <p class="text-muted mb-3">Todavia no has creado empresas.</p>
                <button type="button" class="btn btn-module" data-open-modal="companyCreateModal">Agregar empresa</button>
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

    <div class="modal fade module-modal" id="companyEditModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-3">
              <span class="module-modal-eyebrow"><i class="mdi mdi-pencil-outline"></i></span>
              <div>
                <h3 class="mb-1">Editar empresa</h3>
                <p class="text-muted mb-0">Actualiza los datos que se usaran en el encabezado del libro.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <form method="post" id="companyEditForm">
            <input type="hidden" name="action" value="update_company">
            <input type="hidden" name="id_empresa" value="<?php echo htmlspecialchars((string) ($companyEditFormData['id_empresa'] ?? '')); ?>">
            <div class="modal-body">
              <div class="row">
                <div class="col-md-8">
                  <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars((string) ($companyEditFormData['nombre'] ?? '')); ?>" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Iniciales</label>
                    <input type="text" class="form-control" name="iniciales" value="<?php echo htmlspecialchars((string) ($companyEditFormData['iniciales'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Tipo legal</label>
                    <select class="form-control" name="tipo_legal">
                      <option value="natural" <?php echo ($companyEditFormData['tipo_legal'] ?? '') === 'natural' ? 'selected' : ''; ?>>Natural</option>
                      <option value="juridica" <?php echo ($companyEditFormData['tipo_legal'] ?? '') === 'juridica' ? 'selected' : ''; ?>>Juridica</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>DUI</label>
                    <input type="text" class="form-control" name="dui" value="<?php echo htmlspecialchars((string) ($companyEditFormData['dui'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>NRC</label>
                    <input type="text" class="form-control" name="nrc" value="<?php echo htmlspecialchars((string) ($companyEditFormData['nrc'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>NIT</label>
                    <input type="text" class="form-control" name="nit" value="<?php echo htmlspecialchars((string) ($companyEditFormData['nit'] ?? '')); ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Color del emblema</label>
                    <input type="color" class="form-control" name="color_emblema" value="<?php echo htmlspecialchars((string) ($companyEditFormData['color_emblema'] ?? '#f97316')); ?>">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="button" class="btn btn-module-outline" data-open-modal="companySelectModal">Volver</button>
              <button type="submit" class="btn btn-module">Guardar cambios</button>
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
                  <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="?open=<?php echo (int) $libro['id']; ?>" class="btn btn-sm btn-module-outline">Abrir</a>
                    <form method="post"
                          class="m-0"
                          data-confirm="Esto eliminara este libro de la lista de periodos. Los registros no se borraran fisicamente."
                          data-confirm-title="Eliminar libro"
                          data-confirm-button="Eliminar">
                      <input type="hidden" name="action" value="delete_book">
                      <input type="hidden" name="id_libro" value="<?php echo (int) $libro['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                  </div>
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

    <div class="modal fade module-modal period-work-modal" id="periodCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="period-work-heading">
              <span class="module-modal-eyebrow"><i class="mdi mdi-calendar-month-outline"></i></span>
              <div>
                <h3 class="period-work-title">¿En qué mes y año te gustaría trabajar?</h3>
                <p class="period-work-subtitle">Selecciona el periodo contable para la nueva sesión de trabajo.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <form method="post" id="periodCreateForm" data-year-min="<?php echo (int) $anioMinimo; ?>" data-year-max="<?php echo (int) $anioMaximo; ?>">
            <input type="hidden" name="action" value="create_book">
            <div class="modal-body">
              <div class="period-field-grid">
                <div class="form-group period-field">
                  <label>Año</label>
                  <select class="form-control" name="anio" required>
                    <?php foreach ($anios as $anio): ?>
                    <option value="<?php echo (int) $anio; ?>" <?php echo $anio === $anioActual ? 'selected' : ''; ?>><?php echo (int) $anio; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group period-field">
                  <label>Mes</label>
                  <select class="form-control" name="mes" required>
                    <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                    <option value="<?php echo (int) $numeroMes; ?>" <?php echo $numeroMes === $mesActual ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombreMes); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="module-period-preview period-module-card">
                <span class="period-module-icon"><i class="mdi mdi-book-open-page-variant-outline"></i></span>
                <div>
                  <span class="period-module-kicker">El libro se guardara dentro del modulo</span>
                  <span class="period-module-name"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? '')); ?></span>
                </div>
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
                <p class="text-muted mb-0">Carga uno o varios archivos. El backend procesara cada DTE y reportara duplicados reales o errores sin detener todo el lote.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
	          <form method="post" enctype="multipart/form-data" id="importJsonForm" data-batch-import="1">
            <input type="hidden" name="action" value="import_json">
            <input type="hidden" name="selected_file_count" id="selectedFileCountInput" value="0">
            <div class="modal-body">
              <div class="module-choice mb-3">
                <div class="module-choice-body">
                  <div>
                    <div class="font-weight-bold">Libro activo</div>
                    <small class="text-muted"><?php echo htmlspecialchars($periodoLibroActivo !== '' ? $periodoLibroActivo : 'Sin libro abierto'); ?></small>
                  </div>
                  <span class="badge badge-light">Importacion: <?php echo htmlspecialchars($cuotaLabel); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label>Archivos JSON</label>
                <input id="jsonFilesInput" type="file" name="json_files[]" class="form-control" accept=".json,application/json" multiple required>
              </div>
              <div class="module-period-preview module-dte-preview">
                <div class="module-dte-title">Tipos DTE válidos:</div>
                <?php if (empty($tiposDteValidosInfo)): ?>
                <div class="module-dte-fallback">Tipos DTE permitidos según configuración del libro</div>
                <?php else: ?>
                <div class="module-dte-badges" aria-label="Tipos DTE válidos para este libro">
                  <?php foreach ($tiposDteValidosInfo as $tipoValidoInfo): ?>
                  <span class="module-dte-badge <?php echo htmlspecialchars((string) $tipoValidoInfo['clase']); ?>">
                    <span class="module-dte-name"><?php echo htmlspecialchars((string) $tipoValidoInfo['nombre']); ?></span>
                  </span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="module-file-hint text-muted small" id="selectedFilesHint">No has seleccionado archivos todavía.</div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="button" class="btn btn-module-outline" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-module" <?php echo $libroActivo ? '' : 'disabled'; ?>>Procesar importacion</button>
            </div>
          </form>
        </div>
      </div>
	    </div>

	    <div class="modal fade module-modal" id="manualRecordModal" tabindex="-1" aria-hidden="true">
	      <div class="modal-dialog modal-dialog-centered modal-xl">
	        <div class="modal-content">
	          <div class="modal-header">
	            <div class="d-flex align-items-start gap-3">
	              <span class="module-modal-eyebrow"><i class="mdi mdi-pencil-plus-outline"></i></span>
	              <div>
	                <h3 class="mb-1" id="manualRecordTitle">Crear registro manual</h3>
	                <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($modulo['nombre'] ?? 'Libro')); ?> · <?php echo htmlspecialchars($periodoLibroActivo !== '' ? $periodoLibroActivo : 'Sin libro abierto'); ?></p>
	              </div>
	            </div>
	            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
	          </div>
	          <form method="post" id="manualRecordForm">
	            <input type="hidden" name="action" value="save_manual_record">
	            <input type="hidden" name="id_factura" id="manualRecordId" value="<?php echo htmlspecialchars((string) ($manualFormData['id_factura'] ?? '')); ?>">
	            <div class="modal-body">
	              <div class="row">
	                <?php foreach ($manualFormFields as $campo => $label): ?>
	                <?php
	                  $valorCampo = (string) ($manualFormData[$campo] ?? '');
	                  $esFecha = in_array($campo, ['fecha', 'dia_emision', 'fecha_emision'], true);
	                  $esNumero = in_array($campo, $numericColumns, true) || in_array($campo, ['del_numero', 'al_numero'], true);
	                  $esTextoLargo = in_array($campo, ['sello_recepcion', 'numero_control_completo', 'codigo_generacion_desde', 'codigo_generacion_hasta'], true);
	                  $colClass = $esTextoLargo ? 'col-md-12' : 'col-md-4';
	                ?>
	                <div class="<?php echo $colClass; ?>">
	                  <div class="form-group">
	                    <label><?php echo htmlspecialchars((string) $label); ?></label>
	                    <?php if ($campo === 'tipo_dte' && !empty($tiposDteValidos)): ?>
	                    <select class="form-control" name="<?php echo htmlspecialchars($campo); ?>" data-manual-field="<?php echo htmlspecialchars($campo); ?>">
	                      <?php foreach ($tiposDteValidos as $tipoValido): ?>
	                      <option value="<?php echo htmlspecialchars($tipoValido); ?>" <?php echo ($valorCampo !== '' ? $valorCampo : $tipoDteDefecto) === $tipoValido ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipoValido); ?></option>
	                      <?php endforeach; ?>
	                    </select>
	                    <?php elseif ($esTextoLargo): ?>
	                    <textarea class="form-control" rows="2" name="<?php echo htmlspecialchars($campo); ?>" data-manual-field="<?php echo htmlspecialchars($campo); ?>"><?php echo htmlspecialchars($valorCampo); ?></textarea>
	                    <?php else: ?>
	                    <input
	                      type="<?php echo $esFecha ? 'date' : ($esNumero ? 'number' : 'text'); ?>"
	                      class="form-control"
	                      name="<?php echo htmlspecialchars($campo); ?>"
	                      data-manual-field="<?php echo htmlspecialchars($campo); ?>"
	                      value="<?php echo htmlspecialchars($valorCampo); ?>"
	                      <?php echo $esNumero ? 'step="0.01"' : ''; ?>
	                    >
	                    <?php endif; ?>
	                  </div>
	                </div>
	                <?php endforeach; ?>
	              </div>
	            </div>
	            <div class="modal-footer d-flex justify-content-between">
	              <button type="button" class="btn btn-module-outline" data-bs-dismiss="modal">Cancelar</button>
	              <button type="submit" class="btn btn-module" <?php echo $libroActivo ? '' : 'disabled'; ?>>Guardar registro</button>
	            </div>
	          </form>
	        </div>
	      </div>
	    </div>

	    <div class="module-loading-overlay" id="moduleLoadingOverlay">
      <div class="module-loading-card">
        <span class="module-loading-title" id="moduleLoadingTitle">Preparando modulo</span>
        <div class="module-loading-bar"></div>
        <span class="module-loading-copy" id="moduleLoadingCopy">Organizando tablas, filtros y accesos para que el modulo responda mejor.</span>
      </div>
    </div>

    <script src="../../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../../assets/js/off-canvas.js"></script>
    <script src="../../assets/js/template.js?v=20260613e"></script>
    <script src="../../assets/js/settings.js"></script>
    <script src="../../assets/js/todolist.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
	        const maxFileUploads = <?php echo (int) $maxFileUploads; ?>;
	        const flash = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	        const moduleName = <?php echo json_encode((string) ($modulo['nombre'] ?? 'Modulo'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	        const moduleKey = <?php echo json_encode($tipoLibro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	        const defaultTipoDte = <?php echo json_encode($tipoDteDefecto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	        const importEndpoint = window.location.href.split('#')[0];
	        const preferencesEndpoint = <?php echo json_encode(app_url('api/preferencias/libro_columnas.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const modalMap = {
          'company-select': 'companySelectModal',
          'company-create': 'companyCreateModal',
          'company-edit': 'companyEditModal',
	          'book-choice': 'bookChoiceModal',
	          'period-create': 'periodCreateModal',
	          'import-json': 'importJsonModal',
	          'manual-record': 'manualRecordModal'
	        };
        const loadingOverlay = document.getElementById('moduleLoadingOverlay');
        const loadingTitle = document.getElementById('moduleLoadingTitle');
        const loadingCopy = document.getElementById('moduleLoadingCopy');
        const tableControllers = new Map();

        function wait(ms) {
          return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
          });
        }

        function setLoadingState(title, copy) {
          if (!loadingOverlay) {
            return;
          }

          if (loadingTitle) {
            loadingTitle.textContent = title || 'Procesando';
          }

          if (loadingCopy) {
            loadingCopy.textContent = copy || 'Espera un momento.';
          }

          loadingOverlay.classList.remove('is-hidden');
        }

        function hideLoadingOverlay(delay) {
          if (!loadingOverlay) {
            return;
          }

          window.setTimeout(function () {
            loadingOverlay.classList.add('is-hidden');
          }, Math.max(0, delay || 0));
        }

        function openModalByKey(modalKey) {
          if (!modalKey || !modalMap[modalKey] || !window.bootstrap) {
            return;
          }

          const modal = document.getElementById(modalMap[modalKey]);
          if (!modal) {
            return;
          }

          const instance = bootstrap.Modal.getOrCreateInstance(modal);
          instance.show();
        }

        function showFlashAlert(flashData, callback) {
          if (!flashData || !flashData.message) {
            if (typeof callback === 'function') {
              callback();
            }
            return;
          }

          if (flashData.meta && flashData.meta.suppress_alert) {
            if (typeof callback === 'function') {
              callback();
            }
            return;
          }

          if (!window.Swal) {
            if (typeof callback === 'function') {
              callback();
            }
            return;
          }

          const iconMap = {
            success: 'success',
            danger: 'error',
            warning: 'warning',
            info: 'info'
          };

          Swal.fire({
            icon: iconMap[flashData.type] || 'info',
            title: moduleName,
            text: flashData.message || '',
            confirmButtonText: 'Entendido',
            timer: flashData.type === 'success' ? 2600 : undefined,
            timerProgressBar: flashData.type === 'success',
            customClass: {
              confirmButton: 'btn btn-module'
            },
            buttonsStyling: false
          }).then(function () {
            if (typeof callback === 'function') {
              callback();
            }
          });
        }

        function showRuntimeAlert(type, message) {
          if (!message) {
            return Promise.resolve();
          }

          if (!window.Swal) {
            window.alert(message);
            return Promise.resolve();
          }

          const iconMap = {
            success: 'success',
            danger: 'error',
            warning: 'warning',
            info: 'info'
          };

          return Swal.fire({
            icon: iconMap[type] || 'info',
            title: moduleName,
            text: message,
            confirmButtonText: 'Entendido',
            customClass: {
              confirmButton: 'btn btn-module'
            },
            buttonsStyling: false
          });
        }

        function showConfirmAlert(message, form) {
          if (!message) {
            return Promise.resolve(true);
          }

          if (!window.Swal) {
            return Promise.resolve(window.confirm(message));
          }

          return Swal.fire({
            icon: 'warning',
            title: form.getAttribute('data-confirm-title') || 'Confirmar accion',
            text: message,
            showCancelButton: true,
            confirmButtonText: form.getAttribute('data-confirm-button') || 'Aceptar',
            cancelButtonText: form.getAttribute('data-cancel-button') || 'Cancelar',
            reverseButtons: true,
            customClass: {
              confirmButton: 'btn btn-danger',
              cancelButton: 'btn btn-outline-secondary me-2'
            },
            buttonsStyling: false
          }).then(function (result) {
            return !!result.isConfirmed;
          });
        }

        function parseDownloadFilename(disposition, fallbackName) {
          if (!disposition) {
            return fallbackName;
          }

          const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
          if (utfMatch && utfMatch[1]) {
            try {
              return decodeURIComponent(utfMatch[1]).replace(/[\r\n]/g, '').trim();
            } catch (error) {
              return utfMatch[1].replace(/[\r\n]/g, '').trim() || fallbackName;
            }
          }

          const asciiMatch = disposition.match(/filename="?([^";]+)"?/i);
          if (asciiMatch && asciiMatch[1]) {
            return asciiMatch[1].replace(/[\r\n]/g, '').trim() || fallbackName;
          }

          return fallbackName;
        }

        function buildFallbackDownloadName(link, contentType) {
          let format = 'archivo';
          let libroId = 'exportacion';

          try {
            const url = new URL(link.href, window.location.href);
            format = (url.searchParams.get('formato') || 'archivo').toLowerCase();
            libroId = url.searchParams.get('id_libro') || 'exportacion';
          } catch (error) {
          }

          const extensionMap = {
            csv: 'csv',
            csv_libro_iva: 'csv',
            csv_anexo_iva: 'csv',
            excel: 'xls',
            anexo_mh_a3: 'csv',
            anexo: 'csv',
            json: 'json',
            pdf: 'html'
          };

          let extension = extensionMap[format] || 'bin';

          if (contentType.indexOf('text/html') !== -1) {
            extension = 'html';
          } else if (contentType.indexOf('json') !== -1) {
            extension = 'json';
          }

          return 'libro_' + libroId + '_' + format + '.' + extension;
        }

        function triggerBlobDownload(blob, fileName) {
          const downloadUrl = window.URL.createObjectURL(blob);
          const anchor = document.createElement('a');
          anchor.href = downloadUrl;
          anchor.download = fileName || 'exportacion';
          anchor.style.display = 'none';
          document.body.appendChild(anchor);
          anchor.click();
          anchor.remove();

          window.setTimeout(function () {
            window.URL.revokeObjectURL(downloadUrl);
          }, 1200);
        }

        async function handleExportDownload(link) {
          if (!link || link.dataset.exportBusy === '1') {
            return;
          }

          let abortController = null;
          let abortTimer = null;

          link.dataset.exportBusy = '1';
          link.classList.add('disabled');
          link.setAttribute('aria-disabled', 'true');

          setLoadingState(
            'Preparando exportacion',
            'Estamos construyendo el archivo para que salga con el formato correcto y sin dejar la pantalla bloqueada.'
          );

          try {
            if (typeof window.AbortController === 'function') {
              abortController = new AbortController();
              abortTimer = window.setTimeout(function () {
                abortController.abort();
              }, 60000);
            }

            const response = await fetch(link.href, {
              method: 'GET',
              credentials: 'same-origin',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              },
              signal: abortController ? abortController.signal : undefined
            });

            const contentType = String(response.headers.get('Content-Type') || '').toLowerCase();
            const isJsonResponse = contentType.indexOf('application/json') !== -1;

            if (!response.ok || isJsonResponse) {
              const responseText = await response.text();
              let message = 'No se pudo generar la exportacion solicitada.';

              if (responseText) {
                try {
                  const payload = JSON.parse(responseText);
                  if (payload && payload.message) {
                    message = String(payload.message);
                  }
                } catch (error) {
                  message = responseText.trim() || message;
                }
              }

              throw new Error(message);
            }

            const blob = await response.blob();
            const fileName = parseDownloadFilename(
              String(response.headers.get('Content-Disposition') || ''),
              buildFallbackDownloadName(link, contentType)
            );

            triggerBlobDownload(blob, fileName);
          } catch (error) {
            const wasAborted = error && error.name === 'AbortError';
            await showRuntimeAlert(
              'danger',
              wasAborted
                ? 'La exportacion tardo demasiado en responder. Intenta nuevamente en un momento.'
                : (error && error.message ? error.message : 'No se pudo generar la exportacion solicitada.')
            );
          } finally {
            if (abortTimer !== null) {
              window.clearTimeout(abortTimer);
            }

            delete link.dataset.exportBusy;
            link.classList.remove('disabled');
            link.removeAttribute('aria-disabled');
            hideLoadingOverlay(220);
          }
        }

        const initialModalKey = <?php echo json_encode($modalInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function initModalTriggers() {
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
                  const instance = bootstrap.Modal.getOrCreateInstance(target);
                  instance.show();
                }
              }, 180);
            });
          });
        }

        function initCompanyEditModal() {
          const modal = document.getElementById('companyEditModal');
          const form = document.getElementById('companyEditForm');
          if (!modal || !form || !window.bootstrap) {
            return;
          }

          function setField(name, value) {
            const field = form.querySelector('[name="' + name + '"]');
            if (field) {
              field.value = value !== undefined && value !== null ? String(value) : '';
            }
          }

          document.querySelectorAll('[data-edit-company]').forEach(function (button) {
            button.addEventListener('click', function () {
              let company = {};
              try {
                company = JSON.parse(button.getAttribute('data-edit-company') || '{}');
              } catch (error) {
                company = {};
              }

              setField('id_empresa', company.id_empresa || '');
              setField('nombre', company.nombre || '');
              setField('iniciales', company.iniciales || '');
              setField('tipo_legal', company.tipo_legal || 'natural');
              setField('dui', company.dui || '');
              setField('nrc', company.nrc || '');
              setField('nit', company.nit || '');
              setField('color_emblema', company.color_emblema || '#f97316');

              const currentModal = button.closest('.modal');
              if (currentModal) {
                const currentInstance = bootstrap.Modal.getInstance(currentModal);
                if (currentInstance) {
                  currentInstance.hide();
                }
              }

              window.setTimeout(function () {
                bootstrap.Modal.getOrCreateInstance(modal).show();
              }, 180);
            });
          });
        }

        function buildPagerButtons(totalPages, currentPage) {
          if (totalPages <= 1) {
            return [1];
          }

          const pages = [];
          const start = Math.max(1, currentPage - 2);
          const end = Math.min(totalPages, start + 4);

          for (let page = start; page <= end; page += 1) {
            pages.push(page);
          }

          if (pages[0] !== 1) {
            pages.unshift(1);
          }

          if (pages[pages.length - 1] !== totalPages) {
            pages.push(totalPages);
          }

          return Array.from(new Set(pages));
        }

        function createPaginationController(table) {
          const tbody = table.tBodies[0];
          if (!tbody) {
            return null;
          }

          const shell = table.closest('.module-table-shell') || table.parentElement;
          const allRows = Array.from(tbody.querySelectorAll('tr'));
          const totalRows = allRows.filter(function (row) {
            return row.dataset.rowType === 'total';
          });
          const dataRows = allRows.filter(function (row) {
            return row.dataset.rowType !== 'total';
          });
          const pageSize = Math.max(1, parseInt(table.dataset.pageSize || '10', 10));
          const emptyMessage = table.dataset.emptyMessage || 'No hay registros en esta vista.';
          const pager = document.createElement('div');
          const status = document.createElement('div');
          const controls = document.createElement('div');
          let emptyRow = null;

          pager.className = 'module-table-pager';
          status.className = 'module-table-pager-status';
          controls.className = 'module-table-pager-controls';
          pager.appendChild(status);
          pager.appendChild(controls);

          if (shell) {
            shell.insertAdjacentElement('afterend', pager);
          }

          const state = {
            currentPage: 1,
            filteredRows: dataRows.slice()
          };

          function toggleEmptyRow(show) {
            if (!show) {
              if (emptyRow && emptyRow.parentNode) {
                emptyRow.parentNode.removeChild(emptyRow);
              }
              emptyRow = null;
              return;
            }

            if (!emptyRow) {
              emptyRow = document.createElement('tr');
              emptyRow.className = 'module-empty-row';
              const cell = document.createElement('td');
              cell.colSpan = Math.max(1, table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells.length : 1);
              cell.textContent = emptyMessage;
              emptyRow.appendChild(cell);
              tbody.appendChild(emptyRow);
            }
          }

          function render() {
            const totalItems = state.filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
            state.currentPage = Math.min(state.currentPage, totalPages);

            dataRows.forEach(function (row) {
              row.style.display = 'none';
            });

            totalRows.forEach(function (row) {
              row.style.display = totalItems > 0 ? '' : 'none';
            });

            if (totalItems === 0) {
              toggleEmptyRow(true);
              pager.hidden = false;
              status.textContent = 'Mostrando 0 de 0 registros.';
              controls.innerHTML = '';
              if (shell) {
                shell.classList.remove('is-pending');
                shell.classList.add('is-ready');
              }
              return;
            }

            toggleEmptyRow(false);

            const startIndex = (state.currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalItems);
            state.filteredRows.slice(startIndex, endIndex).forEach(function (row) {
              row.style.display = '';
            });

            pager.hidden = false;
            status.textContent = 'Mostrando ' + (startIndex + 1) + ' a ' + endIndex + ' de ' + totalItems + ' registros.';
            controls.innerHTML = '';

            const prevButton = document.createElement('button');
            prevButton.type = 'button';
            prevButton.className = 'module-page-btn';
            prevButton.textContent = 'Anterior';
            prevButton.disabled = state.currentPage === 1;
            prevButton.addEventListener('click', function () {
              if (state.currentPage > 1) {
                state.currentPage -= 1;
                render();
              }
            });
            controls.appendChild(prevButton);

            buildPagerButtons(totalPages, state.currentPage).forEach(function (page) {
              const button = document.createElement('button');
              button.type = 'button';
              button.className = 'module-page-btn' + (page === state.currentPage ? ' is-active' : '');
              button.textContent = String(page);
              button.addEventListener('click', function () {
                state.currentPage = page;
                render();
              });
              controls.appendChild(button);
            });

            const nextButton = document.createElement('button');
            nextButton.type = 'button';
            nextButton.className = 'module-page-btn';
            nextButton.textContent = 'Siguiente';
            nextButton.disabled = state.currentPage >= totalPages;
            nextButton.addEventListener('click', function () {
              if (state.currentPage < totalPages) {
                state.currentPage += 1;
                render();
              }
            });
            controls.appendChild(nextButton);

            if (shell) {
              shell.classList.remove('is-pending');
              shell.classList.add('is-ready');
            }
          }

          function applyFilter(query) {
            const normalizedQuery = String(query || '').toLowerCase().trim();
            state.filteredRows = dataRows.filter(function (row) {
              const haystack = String(row.dataset.search || row.textContent || '').toLowerCase();
              return normalizedQuery === '' || haystack.indexOf(normalizedQuery) !== -1;
            });
            state.currentPage = 1;
            render();
          }

          render();

          return {
            applyFilter: applyFilter,
            render: render
          };
        }

        function initPaginatedTables() {
          document.querySelectorAll('.module-paginated-table').forEach(function (table) {
            const controller = createPaginationController(table);
            if (controller) {
              tableControllers.set(table, controller);
            }
          });
        }

        function initSearch() {
          const searchInput = document.getElementById('tableSearchInput');
          const mainTable = document.getElementById('moduleTable');
          const controller = mainTable ? tableControllers.get(mainTable) : null;
          let debounceTimer = null;

          if (!searchInput || !controller) {
            return;
          }

          searchInput.addEventListener('input', function () {
            const query = searchInput.value || '';
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function () {
              controller.applyFilter(query);
            }, 140);
          });
        }

	        function initFileInput() {
          const form = document.getElementById('importJsonForm');
          const fileInput = document.getElementById('jsonFilesInput');
          const hint = document.getElementById('selectedFilesHint');
          const selectedFileCountInput = document.getElementById('selectedFileCountInput');
          const chunkSize = Math.max(1, Math.min(maxFileUploads || 1, 100));

          function updateSelectedFilesHint() {
            if (!fileInput || !hint) {
              return;
            }

            const names = Array.from(fileInput.files || []).map(function (file) {
              return file.name;
            });
            const total = names.length;

            if (selectedFileCountInput) {
              selectedFileCountInput.value = String(total);
            }

            if (total === 0) {
              hint.textContent = 'No has seleccionado archivos todavía.';
              return;
            }

            if (total === 1) {
              hint.textContent = '1 archivo listo: ' + names[0];
              return;
            }

            const resumen = total + ' archivos listos: ' + names.slice(0, 3).join(', ') + (total > 3 ? '...' : '');
            const lotes = Math.ceil(total / chunkSize);
            hint.textContent = lotes > 1
              ? resumen + ' Se procesaran automaticamente en ' + lotes + ' lotes.'
              : resumen;
          }

          function getResultNumber(result, key, nestedKey) {
            if (!result || typeof result !== 'object') {
              return 0;
            }

            if (typeof result[key] === 'number') {
              return result[key];
            }

            if (result.data && typeof result.data[nestedKey || key] === 'number') {
              return result.data[nestedKey || key];
            }

            if (Array.isArray(result[key])) {
              return result[key].length;
            }

            if (result.data && Array.isArray(result.data[key])) {
              return result.data[key].length;
            }

            return 0;
          }

          function appendFormFields(formData) {
            if (!form) {
              return;
            }

            form.querySelectorAll('input, select, textarea').forEach(function (field) {
              if (!field.name || field === fileInput || field.type === 'file') {
                return;
              }

              if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                return;
              }

              formData.append(field.name, field.value);
            });
          }

	          async function uploadJsonChunk(files, chunkIndex, totalChunks, selectedTotal) {
	            const formData = new FormData();
	            appendFormFields(formData);
	            formData.set('selected_file_count', String(files.length));
	            formData.set('selected_total_count', String(selectedTotal));
	            formData.set('batch_index', String(chunkIndex + 1));
	            formData.set('batch_total', String(totalChunks));

            files.forEach(function (file) {
              formData.append('json_files[]', file, file.name);
            });

	            const response = await fetch(importEndpoint, {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              }
            });

	            const contentType = response.headers.get('content-type') || '';
	            const isJson = contentType.indexOf('application/json') !== -1;
	            const payload = isJson
	              ? await response.json()
	              : {
	                  success: false,
	                  message: response.status === 404
	                    ? 'La URL de importacion no fue encontrada. Recarga la pagina e intenta de nuevo.'
	                    : 'El servidor respondio con un formato inesperado al importar el lote.'
	                };

            if (!response.ok || !payload.success) {
              throw new Error(payload.message || 'No se pudo importar este lote.');
            }

            return payload;
          }

          async function handleBatchedImport(event) {
            if (!form || !fileInput) {
              return;
            }

            const files = Array.from(fileInput.files || []);
            if (files.length === 0) {
              return;
            }

            event.preventDefault();

            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(function (button) {
              button.disabled = true;
            });

            const chunks = [];
            for (let index = 0; index < files.length; index += chunkSize) {
              chunks.push(files.slice(index, index + chunkSize));
            }

            const totals = {
	              importadas: 0,
	              reparadas: 0,
		              incluidasLibro: 0,
		              fueraPeriodo: 0,
                  documentosUnicos: 0,
                  copiasDuplicadas: 0,
		              duplicadas: 0,
	              invalidas: 0
            };

            try {
              for (let index = 0; index < chunks.length; index += 1) {
                setLoadingState(
                  'Importando JSON',
                  'Procesando lote ' + (index + 1) + ' de ' + chunks.length + ' (' + chunks[index].length + ' archivos).'
                );

	                const payload = await uploadJsonChunk(chunks[index], index, chunks.length, files.length);
                const result = payload.result || {};
	                totals.importadas += getResultNumber(result, 'importadas');
	                totals.reparadas += getResultNumber(result, 'reparadas');
		                totals.incluidasLibro += getResultNumber(result, 'incluidas_libro');
		                totals.fueraPeriodo += getResultNumber(result, 'fuera_periodo');
                    totals.documentosUnicos += getResultNumber(result, 'documentos_unicos_incluidos') || getResultNumber(result, 'incluidas_libro');
                    totals.copiasDuplicadas += getResultNumber(result, 'copias_duplicadas_apartadas') || getResultNumber(result, 'duplicadas_total');
		                totals.duplicadas += getResultNumber(result, 'duplicadas_total', 'duplicadas_total') || getResultNumber(result, 'duplicadas');
	                totals.invalidas += getResultNumber(result, 'invalidas_total', 'invalidas_total') || getResultNumber(result, 'invalidas');
              }

	              const message = 'Se revisaron ' + files.length + ' archivo(s). '
			                + 'Importadas: ' + totals.importadas + '. '
			                + 'Incluidas en libro: ' + totals.incluidasLibro + '. '
                        + 'Documentos unicos incluidos: ' + totals.documentosUnicos + '. '
			                + 'Fuera de periodo: ' + totals.fueraPeriodo + '. '
			                + 'Reparadas: ' + totals.reparadas + '. '
		                + 'Copias duplicadas apartadas: ' + totals.copiasDuplicadas + '. '
		                + 'Invalidas: ' + totals.invalidas + '.';

	              hideLoadingOverlay(0);
	              await wait(120);

	              if (window.Swal) {
	                await Swal.fire({
	                  icon: totals.importadas > 0 || totals.reparadas > 0 ? 'success' : 'info',
	                  title: 'Importacion completada',
	                  text: message,
                  confirmButtonColor: '#4b49ac'
	                });
	              }

	              window.location.href = importEndpoint;
            } catch (error) {
              hideLoadingOverlay(0);
              submitButtons.forEach(function (button) {
                button.disabled = false;
              });

              if (window.Swal) {
                Swal.fire({
                  icon: 'error',
                  title: 'No se completo la importacion',
                  text: error.message || 'Ocurrio un error al procesar los archivos.',
                  confirmButtonColor: '#4b49ac'
                });
              } else {
                window.alert(error.message || 'Ocurrio un error al procesar los archivos.');
              }
            }
          }

          if (fileInput) {
            fileInput.addEventListener('change', updateSelectedFilesHint);
          }

	          if (form) {
	            form.addEventListener('submit', handleBatchedImport);
	          }
	        }

	        function normalizeDateForInput(value) {
	          value = String(value || '').trim();
	          if (!value || value === 'TOTALES DEL MES') {
	            return '';
	          }

	          const slashMatch = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
	          if (slashMatch) {
	            return slashMatch[3] + '-' + slashMatch[2] + '-' + slashMatch[1];
	          }

	          return value.match(/^\d{4}-\d{2}-\d{2}$/) ? value : '';
	        }

	        function openManualRecordModal(record) {
	          const modal = document.getElementById('manualRecordModal');
	          const form = document.getElementById('manualRecordForm');
	          const title = document.getElementById('manualRecordTitle');
	          const idInput = document.getElementById('manualRecordId');
	          if (!modal || !form || !window.bootstrap) {
	            return;
	          }

	          form.reset();
	          const data = record && typeof record === 'object' ? record : {};
	          if (idInput) {
	            idInput.value = data._id ? String(data._id) : '';
	          }
	          if (title) {
	            title.textContent = data._id ? 'Editar registro' : 'Crear registro manual';
	          }

	          form.querySelectorAll('[data-manual-field]').forEach(function (field) {
	            const key = field.getAttribute('data-manual-field');
	            let value = data[key] !== undefined && data[key] !== null ? data[key] : '';
	            if (key === 'tipo_dte' && value === '') {
	              value = defaultTipoDte;
	            }
	            if (field.type === 'date') {
	              value = normalizeDateForInput(value);
	            }
	            field.value = String(value);
	          });

	          bootstrap.Modal.getOrCreateInstance(modal).show();
	        }

	        function initManualRecordModal() {
	          document.querySelectorAll('[data-open-manual-record]').forEach(function (button) {
	            button.addEventListener('click', function () {
	              openManualRecordModal(null);
	            });
	          });

	          document.querySelectorAll('[data-edit-record]').forEach(function (button) {
	            button.addEventListener('click', function () {
	              let record = {};
	              try {
	                record = JSON.parse(button.getAttribute('data-edit-record') || '{}');
	              } catch (error) {
	                record = {};
	              }
	              openManualRecordModal(record);
	            });
	          });
	        }

	        function collectColumnPreferences() {
	          const tableColumns = {};
	          const excelColumns = {};
	          const documentTypes = {};

	          document.querySelectorAll('[data-pref-column-table]').forEach(function (input) {
	            tableColumns[input.getAttribute('data-pref-column-table')] = !!input.checked;
	          });

	          document.querySelectorAll('[data-pref-column-excel]').forEach(function (input) {
	            excelColumns[input.getAttribute('data-pref-column-excel')] = !!input.checked;
	          });

	          document.querySelectorAll('[data-pref-type-show]').forEach(function (input) {
	            const code = input.getAttribute('data-pref-type-show');
	            documentTypes[code] = documentTypes[code] || {};
	            documentTypes[code].show = !!input.checked;
	          });

	          document.querySelectorAll('[data-pref-type-highlight]').forEach(function (input) {
	            const code = input.getAttribute('data-pref-type-highlight');
	            documentTypes[code] = documentTypes[code] || {};
	            documentTypes[code].highlight = !!input.checked;
	          });

	          return {
	            table_columns: tableColumns,
	            excel_columns: excelColumns,
	            document_types: documentTypes
	          };
	        }

	        async function postColumnPreferences(action) {
	          const response = await fetch(preferencesEndpoint, {
	            method: 'POST',
	            credentials: 'same-origin',
	            headers: {
	              'Content-Type': 'application/json',
	              'Accept': 'application/json',
	              'X-Requested-With': 'XMLHttpRequest'
	            },
	            body: JSON.stringify({
	              action: action,
	              module_key: moduleKey,
	              preferences: action === 'restore' ? {} : collectColumnPreferences()
	            })
	          });
	          const payload = await response.json().catch(function () {
	            return { success: false, message: 'El servidor respondio con un formato inesperado.' };
	          });

	          if (!response.ok || !payload.success) {
	            throw new Error(payload.message || 'No se pudo guardar la configuracion.');
	          }

	          return payload;
	        }

	        function initColumnPreferences() {
	          const saveButton = document.getElementById('saveColumnPreferences');
	          const restoreButton = document.getElementById('restoreColumnPreferences');

	          if (saveButton) {
	            saveButton.addEventListener('click', async function () {
	              saveButton.disabled = true;
	              setLoadingState('Guardando columnas', 'Aplicando tu configuracion visual para este modulo.');
	              try {
	                const payload = await postColumnPreferences('save');
	                hideLoadingOverlay(0);
	                await showRuntimeAlert('success', payload.message || 'Configuracion guardada.');
	                window.location.reload();
	              } catch (error) {
	                hideLoadingOverlay(0);
	                await showRuntimeAlert('danger', error.message || 'No se pudo guardar la configuracion.');
	              } finally {
	                saveButton.disabled = false;
	              }
	            });
	          }

	          if (restoreButton) {
	            restoreButton.addEventListener('click', async function () {
	              restoreButton.disabled = true;
	              setLoadingState('Restaurando columnas', 'Volviendo al comportamiento original del modulo.');
	              try {
	                const payload = await postColumnPreferences('restore');
	                hideLoadingOverlay(0);
	                await showRuntimeAlert('success', payload.message || 'Configuracion restaurada.');
	                window.location.reload();
	              } catch (error) {
	                hideLoadingOverlay(0);
	                await showRuntimeAlert('danger', error.message || 'No se pudo restaurar la configuracion.');
	              } finally {
	                restoreButton.disabled = false;
	              }
	            });
	          }
	        }

	        function initPeriodCreateValidation() {
	          const form = document.getElementById('periodCreateForm');
	          if (!form) {
	            return;
	          }

	          const yearField = form.querySelector('[name="anio"]');
	          const monthField = form.querySelector('[name="mes"]');
	          const minYear = parseInt(form.getAttribute('data-year-min') || '', 10);
	          const maxYear = parseInt(form.getAttribute('data-year-max') || '', 10);

	          function clearValidity() {
	            if (yearField) {
	              yearField.setCustomValidity('');
	            }
	            if (monthField) {
	              monthField.setCustomValidity('');
	            }
	          }

	          if (yearField) {
	            yearField.addEventListener('change', clearValidity);
	          }
	          if (monthField) {
	            monthField.addEventListener('change', clearValidity);
	          }

	          form.addEventListener('submit', function (event) {
	            clearValidity();

	            const yearValue = yearField ? String(yearField.value || '').trim() : '';
	            const monthValue = monthField ? String(monthField.value || '').trim() : '';
	            const year = /^\d+$/.test(yearValue) ? parseInt(yearValue, 10) : NaN;
	            const month = /^\d+$/.test(monthValue) ? parseInt(monthValue, 10) : NaN;
	            let invalidField = null;
	            let message = '';

	            if (!Number.isInteger(year) || !Number.isInteger(minYear) || !Number.isInteger(maxYear) || year < minYear || year > maxYear) {
	              invalidField = yearField;
	              message = 'El año seleccionado no está permitido.';
	            } else if (!Number.isInteger(month) || month < 1 || month > 12) {
	              invalidField = monthField;
	              message = 'El mes seleccionado no es válido.';
	            }

	            if (invalidField) {
	              event.preventDefault();
	              event.stopImmediatePropagation();
	              invalidField.setCustomValidity(message);
	              form.reportValidity();
	            }
	          });
	        }

	        function initLoadingTriggers() {
	          document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
	            form.addEventListener('submit', function (event) {
	              if (event.defaultPrevented) {
	                return;
	              }

	              const confirmMessage = form.getAttribute('data-confirm');
	              if (confirmMessage && form.dataset.confirmAccepted !== '1') {
	                event.preventDefault();
	                showConfirmAlert(confirmMessage, form).then(function (confirmed) {
	                  if (!confirmed) {
	                    return;
	                  }

	                  form.dataset.confirmAccepted = '1';
	                  form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
	                    button.disabled = true;
	                  });
	                  setLoadingState('Procesando solicitud', 'Estamos guardando datos y preparando la siguiente vista del modulo.');
	                  HTMLFormElement.prototype.submit.call(form);
	                });
	                return;
	              }

	              form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                button.disabled = true;
              });
              window.setTimeout(function () {
                setLoadingState('Procesando solicitud', 'Estamos guardando datos y preparando la siguiente vista del modulo.');
              }, 90);
            });
          });

          document.querySelectorAll('a[href*="?open="], a[href*="?clear_book="]').forEach(function (link) {
            link.addEventListener('click', function () {
              setLoadingState(
                'Abriendo libro',
                'Espera un momento mientras cargamos el periodo seleccionado.'
              );
            });
          });

          document.querySelectorAll('a[href*="exportar.php"]:not([target="_blank"])').forEach(function (link) {
            link.addEventListener('click', function (event) {
              if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
              }

              event.preventDefault();
              handleExportDownload(link);
            });
          });

          document.querySelectorAll('a[href*="exportar.php"][target="_blank"]').forEach(function (link) {
            link.addEventListener('click', function () {
              setLoadingState(
                'Abriendo vista exportable',
                'Estamos preparando la vista externa para que se abra completa y sin bloquear este modulo.'
              );
              hideLoadingOverlay(900);
            });
          });
        }

        async function bootModule() {
          setLoadingState('Preparando modulo', 'Organizando tablas, filtros y accesos para una carga mas estable.');
          initModalTriggers();
          await wait(90);

          showFlashAlert(flash, function () {
            if (initialModalKey && flash && flash.meta && flash.meta.open_modal) {
              openModalByKey(initialModalKey);
            }
          });

          setLoadingState('Ordenando tablas', 'Aplicando paginacion de 10 registros por pagina para que la vista quede mas limpia.');
          await wait(110);
          initPaginatedTables();

          setLoadingState('Activando herramientas', 'Conectando busqueda, carga de archivos y protecciones contra dobles clics.');
          await wait(120);
	          initSearch();
	          initFileInput();
	          initCompanyEditModal();
	          initManualRecordModal();
	          initColumnPreferences();
	          initPeriodCreateValidation();
	          initLoadingTriggers();

          if (initialModalKey && !flash) {
            openModalByKey(initialModalKey);
          }

          hideLoadingOverlay(220);
        }

        bootModule();
      });
    </script>
  </body>
</html>
