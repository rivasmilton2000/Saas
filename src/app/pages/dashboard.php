<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/modulos.php';
require_once __DIR__ . '/../../controllers/DashboardController.php';
require_once __DIR__ . '/../../models/EmpresaModel.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';
require_once __DIR__ . '/../../services/AuthSessionService.php';
require_once __DIR__ . '/../../services/BitacoraService.php';
require_once __DIR__ . '/../../services/CentroMandoService.php';
require_once __DIR__ . '/../../services/DatabaseBackupService.php';
require_once __DIR__ . '/../../services/PageVisitService.php';
require_once __DIR__ . '/../../services/StripeBillingService.php';

requireLogin();
requireUser();

if (!defined('SAAS_APP_ENTRY')) {
    header('Location: /Saas/src/app/index.php');
    exit;
}

$session = sessionData();
$idUsuario = (int) $session['id_usuario'];
$usuarioActual = UsuarioModel::getById($pdo, $idUsuario, false);

if ($usuarioActual !== null && AuthSessionService::needsPlanSelection($usuarioActual)) {
    AuthSessionService::refreshFromDatabase($pdo, $idUsuario);
    header('Location: /Saas/src/pages/samples/select-plan.php');
    exit;
}

if (
    $usuarioActual !== null
    && (string) ($usuarioActual['rol'] ?? 'user') === 'user'
    && ($usuarioActual['plan_precio'] ?? null) !== null
    && (float) ($usuarioActual['plan_precio'] ?? 0) > 0
    && !StripeBillingService::subscriptionStatusAllowsAccess($usuarioActual['suscripcion_estado'] ?? null)
) {
    $checkoutSessionId = trim((string) ($usuarioActual['stripe_checkout_session_id'] ?? ''));
    if ($checkoutSessionId !== '') {
        header('Location: /Saas/src/payments/payment_success.php?session_id=' . rawurlencode($checkoutSessionId));
        exit;
    }

    header('Location: /Saas/src/pages/samples/select-plan.php');
    exit;
}

$esAdmin = isAdmin();
$zonaHorariaLocal = new DateTimeZone('America/El_Salvador');
$ahoraLocal = new DateTimeImmutable('now', $zonaHorariaLocal);
$mesActual = (int) $ahoraLocal->format('n');
$anioActual = (int) $ahoraLocal->format('Y');
$registrarBitacora = static function (
    string $modulo,
    string $accion,
    string $descripcion,
    array $opciones = []
) use ($pdo, $idUsuario, $session): void {
    BitacoraService::registrar(
        $pdo,
        $idUsuario,
        $modulo,
        $accion,
        $descripcion,
        array_merge([
            'username' => (string) ($session['username'] ?? ''),
            'rol'      => (string) ($session['rol'] ?? 'user'),
        ], $opciones)
    );
};

if (isset($_GET['activate_company'])) {
    $idEmpresaActiva = (int) ($_GET['activate_company'] ?? 0);
    $redirectTo      = trim((string) ($_GET['redirect_to'] ?? 'index.php'));
    $empresaActiva   = $idEmpresaActiva > 0 ? EmpresaModel::getById($pdo, $idEmpresaActiva, $idUsuario) : null;
    $rutasValidas    = ['index.php'];

    foreach (getLibroModules() as $modulo) {
        $rutaModulo = trim((string) ($modulo['ruta'] ?? ''));
        if ($rutaModulo !== '') {
            $rutasValidas[] = $rutaModulo;
        }
    }

    if ($empresaActiva) {
        setActiveEmpresaId($idEmpresaActiva);
        setActiveLibroId(null);
        EmpresaModel::marcarUltimaUsada($pdo, $idEmpresaActiva, $idUsuario);
        $registrarBitacora(
            'dashboard',
            'cambiar_empresa_activa',
            'Cambio la empresa activa a ' . (string) ($empresaActiva['nombre'] ?? 'Empresa') . '.',
            [
                'entidad_tipo' => 'empresa',
                'entidad_id'   => (int) ($empresaActiva['id'] ?? 0),
                'contexto'     => [
                'empresa' => (string) ($empresaActiva['nombre'] ?? 'Empresa'),
                ],
            ]
        );
    } else {
        setFlash('dashboard', 'No se encontro la empresa que intentas abrir.', 'danger');
        $redirectTo = 'index.php';
    }

    if (!in_array($redirectTo, $rutasValidas, true)) {
        $redirectTo = 'index.php';
    }

    header('Location: /Saas/src/' . ltrim($redirectTo, '/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hide_command_center_welcome') {
    CentroMandoService::hideWelcome($pdo, $idUsuario);
    $registrarBitacora('dashboard', 'ocultar_bienvenida', 'Oculto la bienvenida del centro de mando.');
    setFlash('dashboard', 'La bienvenida inicial ya no se mostrara de nuevo.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_command_center_config') {
    $idEmpresa = (int) ($_POST['empresa_id'] ?? 0);
    $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if (!$empresa) {
        setFlash('dashboard', 'La empresa seleccionada no existe o no te pertenece.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $resultado = CentroMandoService::saveConfig($pdo, $idEmpresa, $idUsuario, $_POST['items'] ?? []);
    if (!($resultado['success'] ?? false)) {
        setFlash('dashboard', (string) ($resultado['message'] ?? 'No se pudo guardar el checklist.'), 'danger', [
            'open_command_config' => [
                'company_id'     => $idEmpresa,
                'company_name'   => (string) ($empresa['nombre'] ?? 'Empresa'),
                'selected_keys'  => $resultado['data']['selected_keys'] ?? CentroMandoService::getDefaultSelectedKeys(),
            ],
        ]);
    } else {
        EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
        $registrarBitacora(
            'dashboard',
            'guardar_checklist',
            'Guardo el checklist mensual de ' . (string) ($empresa['nombre'] ?? 'Empresa') . '.',
            [
                'entidad_tipo' => 'empresa',
                'entidad_id'   => (int) ($empresa['id'] ?? 0),
                'contexto'     => [
                    'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                    'detalle' => count($resultado['data']['selected_keys'] ?? []) . ' controles seleccionados',
                ],
            ]
        );
        setFlash('dashboard', (string) ($resultado['message'] ?? 'Checklist guardado.'), 'success');
    }

    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_command_center_item') {
    $idEmpresa   = (int) ($_POST['empresa_id'] ?? 0);
    $itemKey     = trim((string) ($_POST['item_key'] ?? ''));
    $completado  = (int) ($_POST['completed'] ?? 0) === 1;
    $empresa     = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if (!$empresa) {
        setFlash('dashboard', 'No puedes actualizar una empresa que no te pertenece.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $resultado = CentroMandoService::toggleManualItem(
        $pdo,
        $idEmpresa,
        $idUsuario,
        $itemKey,
        $completado,
        $mesActual,
        $anioActual
    );

    if (($resultado['success'] ?? false) === true) {
        $registrarBitacora(
            'dashboard',
            'actualizar_control',
            'Marco un control del centro de mando para ' . (string) ($empresa['nombre'] ?? 'Empresa') . '.',
            [
                'entidad_tipo' => 'empresa',
                'entidad_id'   => (int) ($empresa['id'] ?? 0),
                'contexto'     => [
                    'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                    'periodo' => str_pad((string) $mesActual, 2, '0', STR_PAD_LEFT) . '/' . $anioActual,
                    'detalle' => $itemKey . ': ' . ($completado ? 'completado' : 'pendiente'),
                ],
            ]
        );
    }

    setFlash(
        'dashboard',
        (string) ($resultado['message'] ?? 'Control actualizado.'),
        ($resultado['success'] ?? false) ? 'success' : 'danger'
    );
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_command_center_note') {
    $idEmpresa = (int) ($_POST['empresa_id'] ?? 0);
    $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if (!$empresa) {
        setFlash('dashboard', 'No puedes guardar notas para una empresa que no te pertenece.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $resultado = CentroMandoService::saveNote(
        $pdo,
        $idEmpresa,
        $idUsuario,
        (string) ($_POST['nota_mensual'] ?? ''),
        $mesActual,
        $anioActual
    );

    $registrarBitacora(
        'dashboard',
        'guardar_nota_mensual',
        'Actualizo la nota mensual de ' . (string) ($empresa['nombre'] ?? 'Empresa') . '.',
        [
            'entidad_tipo' => 'empresa',
            'entidad_id'   => (int) ($empresa['id'] ?? 0),
            'contexto'     => [
                'empresa' => (string) ($empresa['nombre'] ?? 'Empresa'),
                'periodo' => str_pad((string) $mesActual, 2, '0', STR_PAD_LEFT) . '/' . $anioActual,
            ],
        ]
    );

    setFlash('dashboard', (string) ($resultado['message'] ?? 'Nota guardada.'), 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_company') {
    $nombre       = trim((string) ($_POST['nombre'] ?? ''));
    $iniciales    = trim((string) ($_POST['iniciales'] ?? ''));
    $colorEmblema = trim((string) ($_POST['color_emblema'] ?? '#f97316'));
    $dui          = EmpresaModel::normalizeDui($_POST['dui'] ?? '');
    $nit          = EmpresaModel::normalizeNit($_POST['nit'] ?? '');
    $nrc          = trim((string) ($_POST['nrc'] ?? ''));
    $tipoLegal    = trim((string) ($_POST['tipo_legal'] ?? 'natural'));
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
        setFlash('dashboard', 'Debes escribir el nombre de la empresa.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        setFlash('dashboard', 'Tipo legal invalido.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!EmpresaModel::isValidDui($dui)) {
        setFlash('dashboard', 'El DUI debe tener formato 12345678-9.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (!EmpresaModel::isValidNit($nit)) {
        setFlash('dashboard', 'El NIT debe tener formato 0000-000000-000-0.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NRC.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NIT.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    $idEmpresa = EmpresaModel::create($pdo, [
        'id_usuario'    => $idUsuario,
        'nombre'        => $nombre,
        'iniciales'     => $iniciales,
        'color_emblema' => $colorEmblema,
        'dui'           => $dui,
        'nit'           => $nit,
        'nrc'           => $nrc,
        'tipo_legal'    => $tipoLegal,
    ]);

    setActiveEmpresaId($idEmpresa);
    $registrarBitacora(
        'dashboard',
        'crear_empresa',
        'Creo la empresa ' . $nombre . '.',
        [
            'entidad_tipo' => 'empresa',
            'entidad_id'   => $idEmpresa,
            'contexto'     => [
                'empresa' => $nombre,
            ],
        ]
    );
    setFlash('dashboard', 'Empresa creada correctamente. Ya puedes trabajar tus libros.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede agregar perfiles.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $validation = UsuarioModel::validateNewUser($pdo, $_POST, true);

    if (!($validation['ok'] ?? false)) {
        setFlash('dashboard', (string) ($validation['message'] ?? 'No se pudo crear el perfil.'), 'danger', [
            'open_user_modal' => true,
            'user_old'        => $validation['old'] ?? [],
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    $idNuevoUsuario = UsuarioModel::create($pdo, $validation['data']);
    $registrarBitacora(
        'usuarios',
        'crear_usuario',
        'Creo el perfil ' . (string) ($validation['data']['username'] ?? 'usuario') . '.',
        [
            'entidad_tipo' => 'usuario',
            'entidad_id'   => $idNuevoUsuario,
            'contexto'     => [
                'usuario_objetivo' => (string) ($validation['data']['username'] ?? 'usuario'),
            ],
        ]
    );
    setFlash('dashboard', 'Perfil creado correctamente.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede editar perfiles.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $editUserId = (int) ($_POST['user_id'] ?? 0);
    if ($editUserId <= 0) {
        setFlash('dashboard', 'Debes indicar el perfil que quieres editar.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $validation = UsuarioModel::validateUserUpdate($pdo, $editUserId, $_POST, true);

    if (!($validation['ok'] ?? false)) {
        setFlash('dashboard', (string) ($validation['message'] ?? 'No se pudo actualizar el perfil.'), 'danger', [
            'open_edit_user_modal' => true,
            'edit_user_old'        => $validation['old'] ?? ['id' => $editUserId],
        ]);
        header('Location: /Saas/src/index.php');
        exit;
    }

    UsuarioModel::update($pdo, $editUserId, $validation['data']);
    $registrarBitacora(
        'usuarios',
        'editar_usuario',
        'Actualizo el perfil ' . (string) ($validation['data']['username'] ?? 'usuario') . '.',
        [
            'entidad_tipo' => 'usuario',
            'entidad_id'   => $editUserId,
            'contexto'     => [
                'usuario_objetivo' => (string) ($validation['data']['username'] ?? 'usuario'),
            ],
        ]
    );

    if ($editUserId === $idUsuario) {
        $_SESSION['username'] = $validation['data']['username'];
        $_SESSION['rol'] = $validation['data']['rol'];
    }

    setFlash('dashboard', 'Perfil actualizado correctamente.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede eliminar perfiles.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $deleteUserId = (int) ($_POST['user_id'] ?? 0);
    if ($deleteUserId <= 0) {
        setFlash('dashboard', 'Debes indicar el perfil que quieres eliminar.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    $usuarioObjetivo = UsuarioModel::getById($pdo, $deleteUserId);
    if ($usuarioObjetivo === null) {
        setFlash('dashboard', 'El perfil que intentas eliminar ya no existe.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    if ($deleteUserId === $idUsuario) {
        setFlash('dashboard', 'No puedes eliminar la sesion que estas usando ahora mismo.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    if (
        (string) $usuarioObjetivo['rol'] === 'admin' &&
        UsuarioModel::countAdmins($pdo, $deleteUserId) === 0
    ) {
        setFlash('dashboard', 'No puedes eliminar al ultimo administrador activo.', 'danger');
        header('Location: /Saas/src/index.php');
        exit;
    }

    UsuarioModel::deactivate($pdo, $deleteUserId);
    $registrarBitacora(
        'usuarios',
        'desactivar_usuario',
        'Desactivo el perfil ' . (string) ($usuarioObjetivo['username'] ?? 'usuario') . '.',
        [
            'entidad_tipo' => 'usuario',
            'entidad_id'   => $deleteUserId,
            'contexto'     => [
                'usuario_objetivo' => (string) ($usuarioObjetivo['username'] ?? 'usuario'),
            ],
        ]
    );
    setFlash('dashboard', 'Perfil eliminado correctamente.', 'success');
    header('Location: /Saas/src/index.php');
    exit;
}

$data = DashboardController::getData($idUsuario);
PageVisitService::track(
    $pdo,
    $session,
    $esAdmin ? 'dashboard_admin' : 'panel_usuario',
    $esAdmin ? 'Panel admin' : 'Panel de usuario'
);
$modulosLibros = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_dashboard'] ?? false) === true
);
$appModuleRoutes = [
    'compras' => 'dte.purchases',
    'ventas_consumidor' => 'dte.consumer-sales',
    'ventas_contribuyente' => 'dte.taxpayer-sales',
    'retencion_iva' => 'dte.vat-withholdings',
];
$centroMando = is_array($data['centro_mando'] ?? null) ? $data['centro_mando'] : [];
$flash = getFlash('dashboard');
$flashMeta = $flash['meta'] ?? [];
$planOptions = is_array($data['plan_options'] ?? null) ? $data['plan_options'] : [];
$countryOptions = is_array($data['country_options'] ?? null) ? $data['country_options'] : [];
$adminDashboardData = is_array($data['admin_dashboard'] ?? null) ? $data['admin_dashboard'] : [];
$defaultUserPlanId = 0;

foreach ($planOptions as $planOption) {
    if ((string) ($planOption['slug'] ?? '') === 'free') {
        $defaultUserPlanId = (int) ($planOption['id_plan'] ?? 0);
        break;
    }
}

$defaultCountry = array_key_exists('El Salvador', $countryOptions) ? 'El Salvador' : (string) array_key_first($countryOptions);
$companyFormData = array_merge([
    'nombre'        => '',
    'iniciales'     => '',
    'color_emblema' => '#f97316',
    'dui'           => '',
    'nit'           => '',
    'nrc'           => '',
    'tipo_legal'    => 'natural',
], is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : []);
$profileFormData = array_merge([
    'username' => '',
    'rol'      => 'user',
    'pais'     => $defaultCountry,
    'id_plan'  => $defaultUserPlanId,
], is_array($flashMeta['user_old'] ?? null) ? $flashMeta['user_old'] : []);
$editProfileFormData = array_merge([
    'id'       => 0,
    'username' => '',
    'rol'      => 'user',
    'pais'     => $defaultCountry,
    'id_plan'  => $defaultUserPlanId,
], is_array($flashMeta['edit_user_old'] ?? null) ? $flashMeta['edit_user_old'] : []);
$roleLabels = [
    'admin' => 'Administrador',
    'user'  => 'Usuario',
];
$backupDashboard = [
    'overview' => null,
    'auto_result' => null,
    'error' => null,
];

if ($esAdmin) {
    try {
        $backupDashboard['auto_result'] = DatabaseBackupService::ensureDailyBackup(dbConfig());
        if (($backupDashboard['auto_result']['created'] ?? false) === true) {
            $backupCreado = $backupDashboard['auto_result']['backup'] ?? [];
            $registrarBitacora(
                'backups',
                'generar_backup_diario',
                'Genero el backup diario de PostgreSQL desde el dashboard.',
                [
                    'contexto' => [
                        'detalle' => (string) ($backupCreado['name'] ?? 'backup_diario.sql'),
                        'formato' => 'sql',
                    ],
                ]
            );
        }

        $backupDashboard['overview'] = DatabaseBackupService::getOverview(dbConfig(), 8);
    } catch (Throwable $exception) {
        $backupDashboard['error'] = $exception->getMessage();
    }
}
$basePath = '../';
$empresaActivaNavbar = $data['empresa_activa'] ?? null;
$horaActual = (int) $ahoraLocal->format('G');
$saludoTitulo = $horaActual < 12
    ? 'Buenos días'
    : ($horaActual < 19 ? 'Buenas tardes' : 'Buenas noches');
$saludoPersona = !empty($data['empresa_activa']['nombre'])
    ? (string) $data['empresa_activa']['nombre']
    : (string) ($session['username'] ?? 'equipo');
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Panel - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <style>
      .empresa-modal .modal-dialog {
        max-width: 980px;
        margin: 1.25rem auto;
      }

      .empresa-modal .modal-content {
        border: 1px solid rgba(75, 73, 172, 0.12);
        border-radius: 22px;
        background: #ffffff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
        max-height: calc(100vh - 2.5rem);
        overflow: hidden;
      }

      .empresa-modal form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
      }

      .empresa-modal .modal-header {
        align-items: flex-start;
        padding: 1.15rem 1.4rem;
        border-bottom: 1px solid rgba(75, 73, 172, 0.1);
        background: #ffffff;
        flex: 0 0 auto;
      }

      .empresa-modal .modal-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 0.9rem;
      }

      .empresa-modal .modal-badge {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 1.35rem;
        flex: 0 0 auto;
      }

      .empresa-modal .modal-title {
        margin: 0;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .empresa-modal .modal-subtitle {
        margin: 0.25rem 0 0;
        color: #6b7280;
        font-size: 0.92rem;
        max-width: 36rem;
      }

      .empresa-modal .modal-body {
        padding: 0;
        background: #f6f7fb;
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
      }

      .empresa-modal-layout {
        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        min-height: 100%;
      }

      .empresa-modal-sidebar {
        padding: 1.4rem;
        background: linear-gradient(180deg, #f8f9ff 0%, #eef2ff 100%);
        border-right: 1px solid rgba(75, 73, 172, 0.08);
      }

      .empresa-modal-kicker {
        display: inline-block;
        margin-bottom: 0.55rem;
        color: #4b49ac;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .empresa-modal-sidebar h6 {
        margin-bottom: 0.45rem;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .empresa-modal-copy {
        margin: 0;
        color: #6c7383;
        font-size: 0.92rem;
        line-height: 1.6;
      }

      .empresa-preview-panel {
        margin-top: 1.25rem;
        padding: 1rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 14px 35px rgba(30, 41, 59, 0.08);
      }

      .empresa-preview-caption {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.85rem;
        color: #4b49ac;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .empresa-preview-avatar {
        width: 84px;
        height: 84px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
      }

      .empresa-preview-label {
        margin-top: 0.95rem;
        margin-bottom: 0.18rem;
        color: #8b95a7;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .empresa-preview-name {
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
        word-break: break-word;
      }

      .empresa-preview-color {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.7rem;
        color: #6c7383;
        font-size: 0.87rem;
        font-weight: 600;
      }

      .empresa-preview-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.08);
      }

      .empresa-sidebar-list {
        display: grid;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .empresa-sidebar-item {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.9rem 0.95rem;
        border-radius: 16px;
        border: 1px solid rgba(75, 73, 172, 0.08);
        background: rgba(255, 255, 255, 0.78);
      }

      .empresa-sidebar-item i {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 1rem;
        flex: 0 0 auto;
      }

      .empresa-sidebar-item strong {
        display: block;
        color: #111827;
        font-size: 0.9rem;
        font-weight: 700;
      }

      .empresa-sidebar-item span {
        display: block;
        margin-top: 0.15rem;
        color: #6c7383;
        font-size: 0.83rem;
        line-height: 1.45;
      }

      .empresa-main-pane {
        padding: 1.4rem;
        display: grid;
        gap: 1rem;
      }

      .empresa-form-section {
        padding: 1.2rem 1.25rem;
        border: 1px solid rgba(124, 134, 153, 0.16);
        border-radius: 18px;
        background: #ffffff;
      }

      .empresa-form-section-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1rem;
      }

      .empresa-form-section-head h6 {
        margin: 0;
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
      }

      .empresa-form-section-head p {
        margin: 0.2rem 0 0;
        color: #7c8699;
        font-size: 0.86rem;
      }

      .empresa-section-tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 0 0.75rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
      }

      .empresa-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }

      .empresa-form-grid--three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }

      .empresa-modal .form-label {
        margin-bottom: 0.5rem;
        color: #6c7383;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .empresa-modal .form-control {
        min-height: 50px;
        border-radius: 14px;
        border-color: rgba(124, 134, 153, 0.22);
        background: #fcfcfe;
        box-shadow: none;
      }

      .empresa-modal .form-control:focus {
        border-color: rgba(75, 73, 172, 0.52);
        background: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(75, 73, 172, 0.11);
      }

      .empresa-modal-helper {
        display: block;
        margin-top: 0.45rem;
        color: #97a0af;
        font-size: 0.8rem;
        line-height: 1.45;
      }

      .empresa-color-field {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-height: 58px;
        padding: 0.75rem 0.9rem;
        border: 1px solid rgba(124, 134, 153, 0.22);
        border-radius: 14px;
        background: #fcfcfe;
        cursor: pointer;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
      }

      .empresa-color-field:hover {
        border-color: rgba(75, 73, 172, 0.32);
        background: #ffffff;
      }

      .empresa-color-field:focus-within {
        border-color: rgba(75, 73, 172, 0.52);
        box-shadow: 0 0 0 0.2rem rgba(75, 73, 172, 0.11);
        background: #ffffff;
      }

      .empresa-color-field input[type="color"] {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
      }

      .empresa-color-swatch {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        border: 3px solid rgba(255, 255, 255, 0.92);
        box-shadow: 0 0 0 1px rgba(17, 24, 39, 0.08);
        flex: 0 0 auto;
      }

      .empresa-color-copy strong {
        display: block;
        color: #111827;
        font-size: 0.95rem;
      }

      .empresa-color-copy span {
        display: block;
        color: #7c8699;
        font-size: 0.8rem;
      }

      .empresa-legal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
      }

      .empresa-legal-choice {
        position: relative;
      }

      .empresa-legal-choice input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }

      .empresa-legal-choice label {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        min-height: 52px;
        margin: 0;
        padding: 0 1rem;
        border: 1px solid rgba(124, 134, 153, 0.22);
        border-radius: 14px;
        background: #fcfcfe;
        color: #374151;
        font-weight: 700;
        transition: all 0.18s ease;
        cursor: pointer;
      }

      .empresa-legal-choice label::before {
        content: "";
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: rgba(124, 134, 153, 0.34);
        box-shadow: 0 0 0 4px rgba(124, 134, 153, 0.08);
        transition: all 0.18s ease;
      }

      .empresa-legal-choice input:checked + label {
        border-color: rgba(75, 73, 172, 0.35);
        background: rgba(75, 73, 172, 0.07);
        color: #312e81;
        box-shadow: inset 0 0 0 1px rgba(75, 73, 172, 0.12);
      }

      .empresa-legal-choice input:checked + label::before {
        background: #4b49ac;
        box-shadow: 0 0 0 4px rgba(75, 73, 172, 0.14);
      }

      .empresa-modal .modal-footer {
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.4rem 1.25rem;
        border-top: 1px solid rgba(75, 73, 172, 0.08);
        background: #ffffff;
        flex: 0 0 auto;
      }

      .empresa-modal-footer-note {
        color: #8b95a7;
        font-size: 0.85rem;
        line-height: 1.5;
        max-width: 32rem;
      }

      .command-hero-card {
        border: 0;
        border-radius: 28px;
        background:
          radial-gradient(circle at top right, rgba(79, 70, 229, 0.08), transparent 30%),
          linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
        box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
      }

      .command-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.42rem 0.8rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.1);
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .command-hero-title {
        margin: 1rem 0 0.8rem;
        color: #0f172a;
        font-size: clamp(2rem, 4vw, 3.15rem);
        font-weight: 800;
        line-height: 1.05;
      }

      .command-hero-text {
        max-width: 42rem;
        margin: 0;
        color: #5b6474;
        font-size: 1rem;
        line-height: 1.7;
      }

      .command-metrics-grid {
        width: min(100%, 540px);
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
      }

      .command-metric-card {
        padding: 1rem 1.05rem;
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(8px);
      }

      .command-metric-label {
        display: block;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      .command-metric-value {
        display: block;
        margin-top: 0.55rem;
        color: #0f172a;
        font-size: 1.25rem;
        font-weight: 800;
      }

      .command-metric-note {
        display: block;
        margin-top: 0.45rem;
        color: #64748b;
        font-size: 0.85rem;
        line-height: 1.5;
      }

      .command-progress {
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.9);
      }

      .command-empty-state {
        padding: 3rem 1.5rem;
        border: 1px dashed rgba(148, 163, 184, 0.5);
        border-radius: 24px;
        text-align: center;
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.95) 0%, rgba(255, 255, 255, 1) 100%);
      }

      .command-empty-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 1rem;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.1);
        color: #4b49ac;
        font-size: 2rem;
      }

      .command-company-card {
        padding: 1.25rem;
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
      }

      .command-company-card + .command-company-card {
        margin-top: 1rem;
      }

      .command-company-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .command-company-avatar {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.1rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }

      .command-mini-progress {
        width: 180px;
      }

      .command-mini-progress .progress {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
      }

      .command-group-block + .command-group-block {
        margin-top: 1rem;
      }

      .command-group-head {
        margin-bottom: 0.55rem;
      }

      .command-group-title {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #334155;
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .command-group-title small {
        color: #94a3b8;
        font-size: 0.82em;
        letter-spacing: 0;
        text-transform: none;
      }

      .command-chip-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
      }

      .command-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.68rem 0.95rem;
        border: 1px solid rgba(191, 219, 254, 0.9);
        border-radius: 999px;
        background: rgba(239, 246, 255, 0.9);
        color: #2563eb;
        font-size: 0.92rem;
        font-weight: 600;
        text-decoration: none;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
      }

      .command-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12);
        color: #1d4ed8;
        text-decoration: none;
      }

      .command-chip.is-done {
        border-color: rgba(52, 211, 153, 0.7);
        background: rgba(236, 253, 245, 0.96);
        color: #059669;
      }

      .command-chip.is-done:hover {
        box-shadow: 0 10px 24px rgba(5, 150, 105, 0.12);
        color: #047857;
      }

      button.command-chip {
        cursor: pointer;
      }

      .command-company-note {
        margin-top: 1rem;
        padding: 0.95rem 1rem;
        border-radius: 18px;
        background: #f8fafc;
        color: #475569;
        line-height: 1.65;
      }

      .command-modal {
        border: 1px solid rgba(75, 73, 172, 0.12);
        border-radius: 24px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
      }

      .command-modal .modal-header,
      .command-modal .modal-footer {
        border-color: rgba(148, 163, 184, 0.14);
        padding: 1.15rem 1.35rem;
      }

      .command-modal .modal-body {
        padding: 1.35rem;
        max-height: calc(100vh - 260px);
        overflow-y: auto;
      }

      .command-modal .modal-title {
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 800;
      }

      .command-welcome-list {
        display: grid;
        gap: 0.9rem;
      }

      .command-welcome-item {
        display: flex;
        gap: 0.9rem;
        align-items: flex-start;
        padding: 1rem;
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, 0.16);
        background: #f8fafc;
      }

      .command-welcome-item i {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(75, 73, 172, 0.1);
        color: #4b49ac;
        font-size: 1.3rem;
        flex: 0 0 auto;
      }

      .command-welcome-item strong {
        display: block;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 800;
      }

      .command-welcome-item span {
        display: block;
        margin-top: 0.2rem;
        color: #64748b;
        line-height: 1.55;
      }

      .command-config-section + .command-config-section {
        margin-top: 1.2rem;
      }

      .command-config-head {
        margin-bottom: 0.8rem;
      }

      .command-config-head span {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #334155;
        font-size: 0.86rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .command-choice-card {
        position: relative;
        display: block;
        margin: 0;
        cursor: pointer;
      }

      .command-config-checkbox {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }

      .command-choice-body {
        display: block;
        height: 100%;
        min-height: 122px;
        padding: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 18px;
        background: #ffffff;
        transition: all 0.18s ease;
      }

      .command-choice-title {
        display: block;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 800;
      }

      .command-choice-copy {
        display: block;
        margin-top: 0.35rem;
        color: #64748b;
        line-height: 1.55;
      }

      .command-config-checkbox:checked + .command-choice-body {
        border-color: rgba(75, 73, 172, 0.35);
        background: rgba(238, 242, 255, 0.86);
        box-shadow: 0 12px 28px rgba(75, 73, 172, 0.12);
      }

      .profile-role-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 112px;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .profile-role-pill--admin {
        background: rgba(13, 110, 253, 0.12);
        color: #0d6efd;
      }

      .profile-role-pill--user {
        background: rgba(25, 135, 84, 0.12);
        color: #198754;
      }

      .profile-helper-card {
        border: 1px solid rgba(124, 134, 153, 0.16);
        border-radius: 16px;
        background: #f8faff;
        padding: 0.95rem 1rem;
      }

      .profile-helper-card strong {
        display: block;
        color: #111827;
        font-size: 0.92rem;
        margin-bottom: 0.2rem;
      }

      .profile-helper-card span {
        color: #6c7383;
        font-size: 0.84rem;
        line-height: 1.5;
      }

      .profile-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .admin-hub-hero {
        border: 1px solid rgba(75, 73, 172, 0.08);
        background: linear-gradient(135deg, #ffffff 0%, #f5f7ff 58%, #eef2ff 100%);
        box-shadow: 0 20px 44px rgba(15, 23, 42, 0.06);
      }

      .admin-hub-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.85fr);
        gap: 1.25rem;
      }

      .admin-hub-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.8rem;
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .admin-hub-title {
        margin: 0;
        color: #111827;
        font-size: 1.65rem;
        font-weight: 700;
      }

      .admin-hub-copy {
        margin: 0.75rem 0 0;
        max-width: 44rem;
        color: #64748b;
        font-size: 0.95rem;
      }

      .admin-hub-side {
        display: grid;
        gap: 0.85rem;
      }

      .admin-hub-side-card {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.92);
      }

      .admin-hub-side-card span,
      .admin-summary-label {
        display: block;
        margin-bottom: 0.25rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .admin-hub-side-card strong,
      .admin-summary-value {
        display: block;
        color: #111827;
        font-size: 1.2rem;
        font-weight: 700;
      }

      .admin-hub-side-card small,
      .admin-summary-note {
        color: #64748b;
      }

      .admin-summary-card {
        border: 1px solid rgba(75, 73, 172, 0.08);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.05);
      }

      .admin-plan-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem;
      }

      .admin-plan-card {
        padding: 1rem 1.05rem;
        border-radius: 20px;
        border: 1px solid #e6eaf3;
        background: #fbfcff;
      }

      .admin-plan-card.is-featured {
        border-color: rgba(75, 73, 172, 0.28);
        box-shadow: 0 16px 34px rgba(75, 73, 172, 0.12);
      }

      .admin-plan-name {
        display: block;
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
      }

      .admin-plan-price {
        display: block;
        margin-top: 0.15rem;
        color: #4b49ac;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .admin-plan-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 108px;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.08);
        color: #4b49ac;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
      }

      .admin-plan-copy {
        margin: 0.85rem 0 0.7rem;
        color: #64748b;
        font-size: 0.9rem;
      }

      .admin-plan-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        color: #334155;
        font-size: 0.82rem;
        font-weight: 600;
      }

      .admin-plan-limits {
        margin-top: 0.7rem;
        color: #7c8699;
        font-size: 0.78rem;
      }

      .admin-route-card {
        height: 100%;
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid #e5e8f1;
        background: #fbfcff;
      }

      .admin-route-card span {
        display: block;
        margin-bottom: 0.3rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .admin-route-card strong {
        display: block;
        color: #111827;
        font-size: 1.1rem;
        font-weight: 700;
      }

      .admin-route-card small {
        color: #64748b;
      }

      @media (max-width: 991.98px) {
        .command-metrics-grid {
          width: 100%;
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .command-company-head {
          flex-direction: column;
        }

        .empresa-modal-layout {
          grid-template-columns: 1fr;
        }

        .empresa-modal-sidebar {
          border-right: 0;
          border-bottom: 1px solid rgba(75, 73, 172, 0.08);
        }

        .admin-plan-grid,
        .admin-hub-grid {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 767.98px) {
        .command-hero-card .card-body,
        .command-company-card,
        .command-modal .modal-body,
        .command-modal .modal-header,
        .command-modal .modal-footer {
          padding-left: 1rem;
          padding-right: 1rem;
        }

        .command-metrics-grid {
          grid-template-columns: 1fr;
        }

        .command-mini-progress {
          width: 100%;
        }

        .empresa-modal .modal-dialog {
          margin: 0.75rem;
        }

        .empresa-modal .modal-header,
        .empresa-modal .modal-footer,
        .empresa-modal-sidebar,
        .empresa-main-pane {
          padding-left: 1rem;
          padding-right: 1rem;
        }

        .empresa-form-grid,
        .empresa-form-grid--three,
        .empresa-legal-grid {
          grid-template-columns: 1fr;
        }

        .empresa-form-section-head {
          flex-direction: column;
        }

        .empresa-modal .modal-footer {
          flex-direction: column;
          align-items: stretch;
        }

        .empresa-modal-footer-note {
          max-width: none;
          text-align: center;
        }

        .empresa-modal .modal-footer .d-flex {
          width: 100%;
        }

        .empresa-modal .modal-footer .btn {
          flex: 1 1 auto;
        }

        .command-modal .modal-footer .btn,
        .command-hero-copy .btn {
          width: 100%;
        }
      }

      .admin-backup-card {
        border: 1px solid rgba(75, 73, 172, 0.08);
        background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 58%, #eef2ff 100%);
        box-shadow: 0 20px 44px rgba(15, 23, 42, 0.06);
      }

      .admin-backup-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(260px, 0.95fr);
        gap: 1.25rem;
      }

      .admin-backup-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.8rem;
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .admin-backup-title {
        margin: 0;
        color: #111827;
        font-size: 1.4rem;
        font-weight: 700;
      }

      .admin-backup-copy {
        margin: 0.75rem 0 0;
        max-width: 42rem;
        color: #64748b;
        font-size: 0.95rem;
      }

      .admin-backup-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .admin-backup-stat {
        padding: 1rem 1.05rem;
        border-radius: 18px;
        border: 1px solid rgba(75, 73, 172, 0.1);
        background: rgba(255, 255, 255, 0.92);
      }

      .admin-backup-stat span {
        display: block;
        margin-bottom: 0.25rem;
        color: #7c8699;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .admin-backup-stat strong {
        display: block;
        color: #111827;
        font-size: 1.15rem;
        font-weight: 700;
      }

      .admin-backup-status {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .admin-backup-status--ok {
        background: rgba(34, 197, 94, 0.12);
        color: #166534;
      }

      .admin-backup-status--warn {
        background: rgba(249, 115, 22, 0.14);
        color: #9a3412;
      }

      @media (max-width: 1199.98px) {
        .admin-backup-grid,
        .admin-backup-stats {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <div class="container-scroller">
      <?php include __DIR__ . '/../../partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/../../partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <?php if (!$esAdmin): ?>
            <?php include __DIR__ . '/../../partials/_command_center_dashboard.php'; ?>
            <?php endif; ?>
            <?php if (!$esAdmin): ?>
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <h3 class="card-title mb-1">Centro de trabajo</h3>
                    <p class="text-muted mb-0">
                      Gestiona tu empresa activa y entra rapido a los modulos contables que si vas a trabajar.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Empresas</h4>
                    <p class="card-description mb-3">Crea una empresa nueva sin ocupar espacio fijo en el dashboard.</p>
                    <div class="mb-3">
                      <strong>Registradas:</strong> <?php echo count($data['empresas']); ?><br>
                      <strong>Activa:</strong>
                      <?php if (!empty($data['empresa_activa'])): ?>
                        <?php echo htmlspecialchars((string) $data['empresa_activa']['nombre']); ?>
                      <?php else: ?>
                        Sin seleccionar
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($data['empresas'])): ?>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Empresa</th>
                            <th>NIT</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (array_slice($data['empresas'], 0, 3) as $empresa): ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string) $empresa['nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($empresa['nit'] ?: '-')); ?></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Todavia no has creado empresas.</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empresaModal">
                      Agregar empresa
                    </button>
                  </div>
                </div>
              </div>

              <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Ver mis módulos</h4>
                    <p class="card-description">Accede a compras y ventas desde la misma empresa activa, con visibilidad rápida por tipo de libro.</p>
                    <div class="mb-3">
                      <strong>Empresas registradas:</strong> <?php echo count($data['empresas']); ?><br>
                      <strong>Libros totales:</strong> <?php echo (int) $data['libros_count']; ?><br>
                      <strong>Facturas disponibles:</strong> <?php echo (int) ($data['cuota']['disponibles'] ?? 0); ?> de <?php echo (int) ($data['cuota']['total'] ?? 0); ?>
                    </div>

                    <?php if (!empty($data['empresa_activa'])): ?>
                    <div class="alert alert-info">
                      Empresa activa: <strong><?php echo htmlspecialchars((string) $data['empresa_activa']['nombre']); ?></strong>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                      <?php foreach ($modulosLibros as $tipoModulo => $modulo): ?>
                      <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                          <h6 class="mb-2"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></h6>
                          <p class="text-muted mb-2"><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? '')); ?></p>
                          <div class="mb-3">
                            <strong>Libros:</strong> <?php echo (int) ($data['libros_by_type'][$tipoModulo] ?? 0); ?>
                          </div>
                          <?php if (!empty($modulo['ruta'])): ?>
                          <a href="<?php echo htmlspecialchars($appBaseUrl . '?page=' . rawurlencode($appModuleRoutes[$tipoModulo] ?? 'dashboard')); ?>" class="btn btn-outline-primary btn-sm">Abrir módulo</a>
                          <?php endif; ?>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>

                    <?php if (!empty($data['libros'])): ?>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Empresa</th>
                            <th>Periodo</th>
                            <th>Tipo</th>
                            <th>Acceso</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (array_slice($data['libros'], 0, 5) as $libro): ?>
                          <?php $moduloLibro = getLibroModule((string) ($libro['tipo'] ?? '')); ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string) $libro['empresa_nombre']); ?></td>
                            <td><?php echo str_pad((string) $libro['mes'], 2, '0', STR_PAD_LEFT) . '/' . htmlspecialchars((string) $libro['anio']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($moduloLibro['nombre'] ?? $libro['tipo'])); ?></td>
                            <td>
                              <?php if (!empty($moduloLibro['ruta'])): ?>
                              <a href="<?php echo htmlspecialchars($appBaseUrl . '?page=' . rawurlencode($appModuleRoutes[(string) ($libro['tipo'] ?? '')] ?? 'dashboard')); ?>" class="btn btn-outline-secondary btn-sm">Ir</a>
                              <?php else: ?>
                              <span class="text-muted">Sin vista</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Todavía no has creado libros en ninguno de los módulos habilitados.</p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($modulosLibros as $tipoModulo => $modulo): ?>
                      <?php if (!empty($modulo['ruta'])): ?>
                      <a href="<?php echo htmlspecialchars($appBaseUrl . '?page=' . rawurlencode($appModuleRoutes[$tipoModulo] ?? 'dashboard')); ?>" class="btn btn-outline-primary"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></a>
                      <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($esAdmin): ?>
            <?php include __DIR__ . '/../../partials/_admin_dashboard.php'; ?>
            <div class="row">
              <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Gestion de cuentas</h4>
                    <p class="card-description mb-3">Crea perfiles cliente, asigna membresias segun el documento y controla que pais y acceso tiene cada cuenta.</p>
                    <div class="mb-3">
                      <strong>Activos:</strong> <?php echo (int) ($data['usuarios_stats']['total'] ?? 0); ?><br>
                      <strong>Admins:</strong> <?php echo (int) ($data['usuarios_stats']['admins'] ?? 0); ?><br>
                      <strong>Users:</strong> <?php echo (int) ($data['usuarios_stats']['users'] ?? 0); ?>
                    </div>
                    <div class="profile-helper-card mb-3">
                      <strong>Control por rol</strong>
                      <span>Los perfiles admin pueden crear, editar y eliminar cuentas. El borrado se hace como desactivacion para no romper historicos.</span>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#perfilModal">
                      Agregar perfil
                    </button>
                  </div>
                </div>
              </div>

              <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Usuarios registrados</h4>
                    <p class="card-description">Vista rapida de acceso, membresia, pais y actividad de cada cuenta activa.</p>
                    <?php if (!empty($data['usuarios'])): ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Membresia</th>
                            <th>Pais</th>
                            <th>Ultima actividad</th>
                            <th>Acciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($data['usuarios'] as $usuario): ?>
                          <?php
                            $rolUsuario = (string) ($usuario['rol'] ?? 'user');
                            $planNombre = (string) ($usuario['plan_nombre'] ?? ($rolUsuario === 'admin' ? 'Interno' : 'Free'));
                            $paisUsuario = (string) ($usuario['pais'] ?? 'El Salvador');
                            $ultimaActividad = !empty($usuario['ultima_actividad_at']) ? strtotime((string) $usuario['ultima_actividad_at']) : false;
                            $ultimaActividadTexto = $ultimaActividad ? date('d/m/Y h:i A', $ultimaActividad) : 'Sin actividad';
                          ?>
                          <tr>
                            <td>
                              <?php echo htmlspecialchars((string) $usuario['username']); ?>
                              <?php if ((int) $usuario['id'] === $idUsuario): ?>
                              <span class="badge badge-light ms-2">Sesion actual</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="profile-role-pill profile-role-pill--<?php echo $rolUsuario === 'admin' ? 'admin' : 'user'; ?>">
                                <?php echo htmlspecialchars($roleLabels[$rolUsuario] ?? ucfirst($rolUsuario)); ?>
                              </span>
                            </td>
                            <td><?php echo htmlspecialchars($planNombre); ?></td>
                            <td><?php echo htmlspecialchars($paisUsuario); ?></td>
                            <td><?php echo htmlspecialchars($ultimaActividadTexto); ?></td>
                            <td>
                              <div class="profile-actions">
                                <button
                                  type="button"
                                  class="btn btn-outline-primary btn-sm btn-edit-user"
                                  data-user-id="<?php echo (int) $usuario['id']; ?>"
                                  data-user-username="<?php echo htmlspecialchars((string) $usuario['username'], ENT_QUOTES); ?>"
                                  data-user-role="<?php echo htmlspecialchars($rolUsuario, ENT_QUOTES); ?>"
                                  data-user-country="<?php echo htmlspecialchars($paisUsuario, ENT_QUOTES); ?>"
                                  data-user-plan-id="<?php echo (int) ($usuario['id_plan'] ?? 0); ?>"
                                >
                                  Editar
                                </button>
                                <button
                                  type="button"
                                  class="btn btn-outline-danger btn-sm btn-delete-user"
                                  data-user-id="<?php echo (int) $usuario['id']; ?>"
                                  data-user-name="<?php echo htmlspecialchars((string) $usuario['username'], ENT_QUOTES); ?>"
                                  data-user-role="<?php echo htmlspecialchars($rolUsuario, ENT_QUOTES); ?>"
                                  <?php echo (int) $usuario['id'] === $idUsuario ? 'disabled' : ''; ?>
                                >
                                  Eliminar
                                </button>
                              </div>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">Todavia no hay perfiles registrados.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <?php if (!$esAdmin): ?>
          <?php include __DIR__ . '/../../partials/_command_center_modals.php'; ?>
          <?php endif; ?>

          <div class="modal fade empresa-modal" id="empresaModal" tabindex="-1" aria-labelledby="empresaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered empresa-modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <div class="modal-title-wrap">
                    <div class="modal-badge">
                      <i class="mdi mdi-domain"></i>
                    </div>
                    <div>
                      <h5 class="modal-title" id="empresaModalLabel">Agregar empresa</h5>
                      <p class="modal-subtitle">Crea una empresa nueva y dejala lista para trabajar dentro del sistema.</p>
                    </div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="empresaForm">
                  <div class="modal-body">
                    <input type="hidden" name="action" value="create_company">
                    <div class="empresa-modal-layout">
                      <aside class="empresa-modal-sidebar">
                        <span class="empresa-modal-kicker">Nueva empresa</span>
                        <h6>Dejala lista para operar</h6>
                        <p class="empresa-modal-copy">Completa los datos base y el sistema usara esta empresa en la sesion actual y en el encabezado de tus libros.</p>

                        <div class="empresa-preview-panel">
                          <div class="empresa-preview-caption">
                            <i class="mdi mdi-eye-outline"></i>
                            Vista previa
                          </div>
                          <div
                            class="empresa-preview-avatar"
                            id="empresaPreviewAvatar"
                            style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                          >
                            <span id="empresaPreviewInitials"><?php echo htmlspecialchars((string) ($companyFormData['iniciales'] !== '' ? strtoupper((string) $companyFormData['iniciales']) : 'EMP')); ?></span>
                          </div>
                          <div class="empresa-preview-label">Nombre visible</div>
                          <div class="empresa-preview-name" id="empresaPreviewName">
                            <?php echo htmlspecialchars((string) ($companyFormData['nombre'] !== '' ? $companyFormData['nombre'] : 'Nueva empresa')); ?>
                          </div>
                          <div class="empresa-preview-color">
                            <span
                              class="empresa-preview-color-dot"
                              id="empresaPreviewColorDot"
                              style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                            ></span>
                            <span id="empresaPreviewColorHex"><?php echo htmlspecialchars(strtoupper((string) $companyFormData['color_emblema'])); ?></span>
                          </div>
                        </div>

                        <div class="empresa-sidebar-list">
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-check-decagram-outline"></i>
                            <div>
                              <strong>Uso inmediato</strong>
                              <span>Quedara disponible para seleccionarla en esta misma sesion.</span>
                            </div>
                          </div>
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-shield-check-outline"></i>
                            <div>
                              <strong>Validacion base</strong>
                              <span>DUI y NIT se revisan con formato salvadoreno antes de guardar.</span>
                            </div>
                          </div>
                          <div class="empresa-sidebar-item">
                            <i class="mdi mdi-book-open-variant"></i>
                            <div>
                              <strong>Lista para libros</strong>
                              <span>Podras trabajar tus libros con la empresa activa apenas se cree.</span>
                            </div>
                          </div>
                        </div>
                      </aside>

                      <div class="empresa-main-pane">
                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identidad de la empresa</h6>
                              <p>Los datos principales que usaras para reconocerla dentro del sistema.</p>
                            </div>
                            <span class="empresa-section-tag">Base</span>
                          </div>

                          <div class="form-group mb-4">
                            <label class="form-label" for="nombre">Nombre de la empresa</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars((string) $companyFormData['nombre']); ?>" required>
                            <small class="empresa-modal-helper">Se mostrara en el dashboard, la sesion activa y los encabezados del modulo.</small>
                          </div>

                          <div class="empresa-form-grid">
                            <div class="form-group mb-0">
                              <label class="form-label" for="iniciales">Iniciales</label>
                              <input type="text" class="form-control" id="iniciales" name="iniciales" maxlength="4" value="<?php echo htmlspecialchars((string) $companyFormData['iniciales']); ?>">
                              <small class="empresa-modal-helper">Se sugieren automaticamente a partir del nombre, pero puedes afinarlas.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label d-block">Tipo legal</label>
                              <div class="empresa-legal-grid">
                                <div class="empresa-legal-choice">
                                  <input type="radio" id="tipo_legal_natural" name="tipo_legal" value="natural" <?php echo $companyFormData['tipo_legal'] === 'natural' ? 'checked' : ''; ?>>
                                  <label for="tipo_legal_natural">Natural</label>
                                </div>
                                <div class="empresa-legal-choice">
                                  <input type="radio" id="tipo_legal_juridica" name="tipo_legal" value="juridica" <?php echo $companyFormData['tipo_legal'] === 'juridica' ? 'checked' : ''; ?>>
                                  <label for="tipo_legal_juridica">Juridica</label>
                                </div>
                              </div>
                            </div>
                          </div>
                        </section>

                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identidad visual</h6>
                              <p>Un color sobrio ayuda a distinguir la empresa sin romper el estilo del panel.</p>
                            </div>
                            <span class="empresa-section-tag">Visual</span>
                          </div>

                          <div class="form-group mb-0">
                            <label class="form-label" for="color_emblema">Color emblema</label>
                            <label class="empresa-color-field mb-0" for="color_emblema">
                              <span
                                class="empresa-color-swatch"
                                id="empresaColorSwatch"
                                style="background: <?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>;"
                              ></span>
                              <span class="empresa-color-copy">
                                <strong id="empresaColorFieldHex"><?php echo htmlspecialchars(strtoupper((string) $companyFormData['color_emblema'])); ?></strong>
                                <span>Haz clic para elegir el color del distintivo</span>
                              </span>
                              <input type="color" id="color_emblema" name="color_emblema" value="<?php echo htmlspecialchars((string) $companyFormData['color_emblema']); ?>">
                            </label>
                            <small class="empresa-modal-helper">Mantiene la empresa identificable sin chocar con el estilo original del dashboard.</small>
                          </div>
                        </section>

                        <section class="empresa-form-section">
                          <div class="empresa-form-section-head">
                            <div>
                              <h6>Identificacion fiscal</h6>
                              <p>Estos campos son opcionales, pero te dejan la empresa lista para procesos contables.</p>
                            </div>
                            <span class="empresa-section-tag">Fiscal</span>
                          </div>

                          <div class="empresa-form-grid empresa-form-grid--three">
                            <div class="form-group mb-0">
                              <label class="form-label" for="dui">DUI</label>
                              <input
                                type="text"
                                class="form-control"
                                id="dui"
                                name="dui"
                                maxlength="10"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="12345678-9"
                                value="<?php echo htmlspecialchars((string) $companyFormData['dui']); ?>"
                              >
                              <small class="empresa-modal-helper">Formato salvadoreno: 8 digitos y guion.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label" for="nit">NIT</label>
                              <input
                                type="text"
                                class="form-control"
                                id="nit"
                                name="nit"
                                maxlength="17"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0000-000000-000-0"
                                value="<?php echo htmlspecialchars((string) $companyFormData['nit']); ?>"
                              >
                              <small class="empresa-modal-helper">Formato salvadoreno completo de 14 digitos.</small>
                            </div>
                            <div class="form-group mb-0">
                              <label class="form-label" for="nrc">NRC</label>
                              <input type="text" class="form-control" id="nrc" name="nrc" value="<?php echo htmlspecialchars((string) $companyFormData['nrc']); ?>">
                              <small class="empresa-modal-helper">Util para dejar lista la empresa para procesos y reportes.</small>
                            </div>
                          </div>
                        </section>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <div class="empresa-modal-footer-note">Puedes guardar solo lo esencial ahora y completar datos fiscales despues si lo necesitas.</div>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check-circle-outline me-1"></i> Crear empresa
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <?php if ($esAdmin): ?>
          <div class="modal fade" id="perfilModal" tabindex="-1" aria-labelledby="perfilModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <div>
                    <h5 class="modal-title" id="perfilModalLabel">Agregar perfil</h5>
                    <p class="text-muted mb-0">Crea una nueva cuenta y asigna el rol correcto dentro del sistema.</p>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="perfilForm">
                  <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-group mb-3">
                      <label for="profile_username" class="form-label">Nombre de usuario</label>
                      <input
                        type="text"
                        class="form-control"
                        id="profile_username"
                        name="username"
                        autocomplete="username"
                        value="<?php echo htmlspecialchars((string) $profileFormData['username']); ?>"
                        required
                      >
                    </div>
                    <div class="form-group mb-3">
                      <label for="profile_role" class="form-label">Rol</label>
                      <select class="form-select" id="profile_role" name="rol">
                        <option value="user" <?php echo ($profileFormData['rol'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Usuario</option>
                        <option value="admin" <?php echo ($profileFormData['rol'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                      </select>
                    </div>
                    <div class="form-group mb-3">
                      <label for="profile_plan_id" class="form-label">Membresia</label>
                      <select class="form-select" id="profile_plan_id" name="id_plan">
                        <?php foreach ($planOptions as $planOption): ?>
                        <option
                          value="<?php echo (int) ($planOption['id_plan'] ?? 0); ?>"
                          <?php echo (int) ($profileFormData['id_plan'] ?? $defaultUserPlanId) === (int) ($planOption['id_plan'] ?? 0) ? 'selected' : ''; ?>
                        >
                          <?php echo htmlspecialchars((string) ($planOption['nombre'] ?? 'Plan')); ?>
                          · <?php echo htmlspecialchars(PlanModel::formatPriceLabel($planOption)); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted">Solo aplica a usuarios cliente. Si el rol es admin, se guarda como cuenta interna.</small>
                    </div>
                    <div class="form-group mb-3">
                      <label for="profile_country" class="form-label">Pais</label>
                      <select class="form-select" id="profile_country" name="pais">
                        <?php foreach ($countryOptions as $countryValue => $countryLabel): ?>
                        <option value="<?php echo htmlspecialchars((string) $countryValue); ?>" <?php echo ($profileFormData['pais'] ?? $defaultCountry) === $countryValue ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars((string) $countryLabel); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group mb-3">
                      <label for="profile_password" class="form-label">Clave</label>
                      <input
                        type="password"
                        class="form-control"
                        id="profile_password"
                        name="password"
                        autocomplete="new-password"
                        required
                      >
                    </div>
                    <div class="form-group mb-0">
                      <label for="profile_confirm_password" class="form-label">Confirmar clave</label>
                      <input
                        type="password"
                        class="form-control"
                        id="profile_confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                        required
                      >
                    </div>
                  </div>
                  <div class="modal-footer">
                    <span class="text-muted small">Admin puede gestionar perfiles. User entra a trabajar empresas y libros.</span>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Crear perfil</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="modal fade" id="editarPerfilModal" tabindex="-1" aria-labelledby="editarPerfilModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <div>
                    <h5 class="modal-title" id="editarPerfilModalLabel">Editar perfil</h5>
                    <p class="text-muted mb-0">Actualiza usuario, rol o clave. Si dejas la clave vacia, se conserva la actual.</p>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="editarPerfilForm">
                  <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id" value="<?php echo (int) $editProfileFormData['id']; ?>">
                    <div class="form-group mb-3">
                      <label for="edit_profile_username" class="form-label">Nombre de usuario</label>
                      <input
                        type="text"
                        class="form-control"
                        id="edit_profile_username"
                        name="username"
                        autocomplete="username"
                        value="<?php echo htmlspecialchars((string) $editProfileFormData['username']); ?>"
                        required
                      >
                    </div>
                    <div class="form-group mb-3">
                      <label for="edit_profile_role" class="form-label">Rol</label>
                      <select class="form-select" id="edit_profile_role" name="rol">
                        <option value="user" <?php echo ($editProfileFormData['rol'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Usuario</option>
                        <option value="admin" <?php echo ($editProfileFormData['rol'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                      </select>
                    </div>
                    <div class="form-group mb-3">
                      <label for="edit_profile_plan_id" class="form-label">Membresia</label>
                      <select class="form-select" id="edit_profile_plan_id" name="id_plan">
                        <?php foreach ($planOptions as $planOption): ?>
                        <option
                          value="<?php echo (int) ($planOption['id_plan'] ?? 0); ?>"
                          <?php echo (int) ($editProfileFormData['id_plan'] ?? $defaultUserPlanId) === (int) ($planOption['id_plan'] ?? 0) ? 'selected' : ''; ?>
                        >
                          <?php echo htmlspecialchars((string) ($planOption['nombre'] ?? 'Plan')); ?>
                          · <?php echo htmlspecialchars(PlanModel::formatPriceLabel($planOption)); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group mb-3">
                      <label for="edit_profile_country" class="form-label">Pais</label>
                      <select class="form-select" id="edit_profile_country" name="pais">
                        <?php foreach ($countryOptions as $countryValue => $countryLabel): ?>
                        <option value="<?php echo htmlspecialchars((string) $countryValue); ?>" <?php echo ($editProfileFormData['pais'] ?? $defaultCountry) === $countryValue ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars((string) $countryLabel); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group mb-3">
                      <label for="edit_profile_password" class="form-label">Nueva clave</label>
                      <input
                        type="password"
                        class="form-control"
                        id="edit_profile_password"
                        name="password"
                        autocomplete="new-password"
                      >
                    </div>
                    <div class="form-group mb-0">
                      <label for="edit_profile_confirm_password" class="form-label">Confirmar nueva clave</label>
                      <input
                        type="password"
                        class="form-control"
                        id="edit_profile_confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                      >
                    </div>
                  </div>
                  <div class="modal-footer">
                    <span class="text-muted small">No se permite quitar o eliminar al ultimo admin activo, ni borrar tu sesion actual.</span>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <form method="POST" action="" id="deleteUserForm" class="d-none">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="delete_user_id" value="">
          </form>
          <?php endif; ?>

          <?php include __DIR__ . '/../../partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const empresaModalElement = document.getElementById('empresaModal');
        const empresaModal = empresaModalElement && window.bootstrap
          ? new bootstrap.Modal(empresaModalElement)
          : null;
        const commandWelcomeModalElement = document.getElementById('commandWelcomeModal');
        const commandWelcomeModal = commandWelcomeModalElement && window.bootstrap
          ? new bootstrap.Modal(commandWelcomeModalElement)
          : null;
        const commandConfigModalElement = document.getElementById('commandConfigModal');
        const commandConfigModal = commandConfigModalElement && window.bootstrap
          ? new bootstrap.Modal(commandConfigModalElement)
          : null;
        const commandNoteModalElement = document.getElementById('commandNoteModal');
        const commandNoteModal = commandNoteModalElement && window.bootstrap
          ? new bootstrap.Modal(commandNoteModalElement)
          : null;
        const perfilModalElement = document.getElementById('perfilModal');
        const perfilModal = perfilModalElement && window.bootstrap
          ? new bootstrap.Modal(perfilModalElement)
          : null;
        const editarPerfilModalElement = document.getElementById('editarPerfilModal');
        const editarPerfilModal = editarPerfilModalElement && window.bootstrap
          ? new bootstrap.Modal(editarPerfilModalElement)
          : null;
        const empresaForm = document.getElementById('empresaForm');
        const commandConfigForm = document.getElementById('commandConfigForm');
        const commandConfigEmpresaId = document.getElementById('command_config_empresa_id');
        const commandConfigEmpresaNombre = document.getElementById('command_config_empresa_nombre');
        const commandConfigCheckboxes = document.querySelectorAll('.command-config-checkbox');
        const commandNoteForm = document.getElementById('commandNoteForm');
        const commandNoteEmpresaId = document.getElementById('command_note_empresa_id');
        const commandNoteEmpresaNombre = document.getElementById('command_note_empresa_nombre');
        const commandNoteText = document.getElementById('command_note_text');
        const perfilForm = document.getElementById('perfilForm');
        const editarPerfilForm = document.getElementById('editarPerfilForm');
        const profileRoleInput = document.getElementById('profile_role');
        const profilePlanInput = document.getElementById('profile_plan_id');
        const profileCountryInput = document.getElementById('profile_country');
        const deleteUserForm = document.getElementById('deleteUserForm');
        const nombreInput = document.getElementById('nombre');
        const inicialesInput = document.getElementById('iniciales');
        const colorInput = document.getElementById('color_emblema');
        const duiInput = document.getElementById('dui');
        const nitInput = document.getElementById('nit');
        const profilePasswordInput = document.getElementById('profile_password');
        const profileConfirmPasswordInput = document.getElementById('profile_confirm_password');
        const editUserIdInput = document.getElementById('edit_user_id');
        const editProfileUsernameInput = document.getElementById('edit_profile_username');
        const editProfileRoleInput = document.getElementById('edit_profile_role');
        const editProfilePlanInput = document.getElementById('edit_profile_plan_id');
        const editProfileCountryInput = document.getElementById('edit_profile_country');
        const editProfilePasswordInput = document.getElementById('edit_profile_password');
        const editProfileConfirmPasswordInput = document.getElementById('edit_profile_confirm_password');
        const editUserButtons = document.querySelectorAll('.btn-edit-user');
        const deleteUserButtons = document.querySelectorAll('.btn-delete-user');
        const commandConfigButtons = document.querySelectorAll('.btn-command-config, .btn-command-open-config');
        const commandNoteButtons = document.querySelectorAll('.btn-command-note');
        const commandStartButton = document.querySelector('.btn-command-start');
        const deleteUserIdInput = document.getElementById('delete_user_id');
        const previewAvatar = document.getElementById('empresaPreviewAvatar');
        const previewInitials = document.getElementById('empresaPreviewInitials');
        const previewName = document.getElementById('empresaPreviewName');
        const previewColorDot = document.getElementById('empresaPreviewColorDot');
        const previewColorHex = document.getElementById('empresaPreviewColorHex');
        const colorFieldHex = document.getElementById('empresaColorFieldHex');
        const flash = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const shouldShowWelcome = <?php echo !empty($centroMando['bienvenida']['mostrar']) ? 'true' : 'false'; ?>;

        function parseSelectedKeys(value) {
          if (Array.isArray(value)) {
            return value;
          }

          if (typeof value !== 'string' || value.trim() === '') {
            return [];
          }

          try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
          } catch (error) {
            return [];
          }
        }

        function openCommandConfig(options) {
          if (!commandConfigModal || !commandConfigEmpresaId || !commandConfigEmpresaNombre) {
            return false;
          }

          const companyId = Number(options && options.companyId ? options.companyId : 0);
          if (!companyId) {
            return false;
          }

          const selectedKeys = parseSelectedKeys(options && options.selectedKeys ? options.selectedKeys : []);

          commandConfigEmpresaId.value = String(companyId);
          commandConfigEmpresaNombre.textContent = options && options.companyName ? options.companyName : 'Empresa';

          commandConfigCheckboxes.forEach(function (checkbox) {
            checkbox.checked = selectedKeys.includes(checkbox.value);
          });

          commandConfigModal.show();
          return true;
        }

        function openCommandNote(options) {
          if (!commandNoteModal || !commandNoteEmpresaId || !commandNoteEmpresaNombre || !commandNoteText) {
            return false;
          }

          const companyId = Number(options && options.companyId ? options.companyId : 0);
          if (!companyId) {
            return false;
          }

          commandNoteEmpresaId.value = String(companyId);
          commandNoteEmpresaNombre.textContent = options && options.companyName ? options.companyName : 'Empresa';
          commandNoteText.value = options && typeof options.note === 'string' ? options.note : '';
          commandNoteModal.show();
          return true;
        }

        function openFirstCommandConfig() {
          const primaryConfigButton = document.querySelector('.btn-command-open-config[data-company-id]');
          if (primaryConfigButton && Number(primaryConfigButton.dataset.companyId || 0) > 0) {
            return openCommandConfig({
              companyId: primaryConfigButton.dataset.companyId,
              companyName: primaryConfigButton.dataset.companyName,
              selectedKeys: primaryConfigButton.dataset.selectedKeys
            });
          }

          const secondaryConfigButton = document.querySelector('.btn-command-config[data-company-id]');
          if (secondaryConfigButton && Number(secondaryConfigButton.dataset.companyId || 0) > 0) {
            return openCommandConfig({
              companyId: secondaryConfigButton.dataset.companyId,
              companyName: secondaryConfigButton.dataset.companyName,
              selectedKeys: secondaryConfigButton.dataset.selectedKeys
            });
          }

          if (empresaModal) {
            empresaModal.show();
            return true;
          }

          return false;
        }

        function maybeShowWelcome() {
          if (shouldShowWelcome && commandWelcomeModal) {
            commandWelcomeModal.show();
          }
        }

        function formatDui(value) {
          const digits = String(value || '').replace(/\D/g, '').slice(0, 9);
          if (digits.length <= 8) {
            return digits;
          }
          return digits.slice(0, 8) + '-' + digits.slice(8);
        }

        function formatNit(value) {
          const digits = String(value || '').replace(/\D/g, '').slice(0, 14);
          if (digits.length <= 4) {
            return digits;
          }
          if (digits.length <= 10) {
            return digits.slice(0, 4) + '-' + digits.slice(4);
          }
          if (digits.length <= 13) {
            return digits.slice(0, 4) + '-' + digits.slice(4, 10) + '-' + digits.slice(10);
          }
          return digits.slice(0, 4) + '-' + digits.slice(4, 10) + '-' + digits.slice(10, 13) + '-' + digits.slice(13);
        }

        function buildInitials(value) {
          return String(value || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map(function (part) {
              return part.charAt(0).toUpperCase();
            })
            .join('')
            .slice(0, 4);
        }

        function syncPreview() {
          const resolvedInitials = inicialesInput && inicialesInput.value.trim() !== ''
            ? inicialesInput.value.trim().toUpperCase().slice(0, 4)
            : buildInitials(nombreInput ? nombreInput.value : '');
          const resolvedName = nombreInput && nombreInput.value.trim() !== ''
            ? nombreInput.value.trim()
            : 'Nueva empresa';
          const resolvedColor = colorInput && colorInput.value
            ? colorInput.value.toUpperCase()
            : '#F97316';

          if (previewInitials) {
            previewInitials.textContent = resolvedInitials || 'EMP';
          }

          if (previewName) {
            previewName.textContent = resolvedName;
          }

          if (previewAvatar) {
            previewAvatar.style.background = resolvedColor;
          }

          if (previewColorDot) {
            previewColorDot.style.background = resolvedColor;
          }

          if (previewColorHex) {
            previewColorHex.textContent = resolvedColor;
          }

          if (colorFieldHex) {
            colorFieldHex.textContent = resolvedColor;
          }
        }

        function openModalFromFlashMeta(meta) {
          if (!meta) {
            return false;
          }

          if (meta.open_edit_user_modal && editarPerfilModal) {
            if (meta.edit_user_old) {
              fillEditUserForm(meta.edit_user_old);
            }
            editarPerfilModal.show();
            return true;
          }

          if (meta.open_user_modal && perfilModal) {
            perfilModal.show();
            return true;
          }

          if (meta.open_command_config) {
            return openCommandConfig({
              companyId: meta.open_command_config.company_id,
              companyName: meta.open_command_config.company_name,
              selectedKeys: meta.open_command_config.selected_keys
            });
          }

          if (meta.open_modal && empresaModal) {
            empresaModal.show();
            return true;
          }

          return false;
        }

        function fillEditUserForm(userData) {
          if (!userData) {
            return;
          }

          if (editUserIdInput) {
            editUserIdInput.value = userData.id || '';
          }

          if (editProfileUsernameInput) {
            editProfileUsernameInput.value = userData.username || '';
          }

          if (editProfileRoleInput) {
            editProfileRoleInput.value = userData.rol || 'user';
          }

          if (editProfilePlanInput) {
            editProfilePlanInput.value = userData.id_plan || String(editProfilePlanInput.value || '');
          }

          if (editProfileCountryInput) {
            editProfileCountryInput.value = userData.pais || editProfileCountryInput.value;
          }

          if (editProfilePasswordInput) {
            editProfilePasswordInput.value = '';
          }

          if (editProfileConfirmPasswordInput) {
            editProfileConfirmPasswordInput.value = '';
          }

          syncPlanFieldState(editProfileRoleInput, editProfilePlanInput);
        }

        function syncPlanFieldState(roleInput, planInput) {
          if (!roleInput || !planInput) {
            return;
          }

          const isAdminRole = roleInput.value === 'admin';
          planInput.disabled = isAdminRole;
          planInput.classList.toggle('bg-light', isAdminRole);
        }

        function validatePasswordForm(passwordInput, confirmInput, allowEmpty) {
          const passwordValue = passwordInput ? passwordInput.value : '';
          const confirmValue = confirmInput ? confirmInput.value : '';

          if (allowEmpty && passwordValue === '' && confirmValue === '') {
            return null;
          }

          if (passwordValue.length < 6) {
            return 'La clave debe tener al menos 6 caracteres.';
          }

          if (passwordValue !== confirmValue) {
            return 'Las claves no coinciden.';
          }

          return null;
        }

        function showFlashAlert(flashData) {
          if (!flashData || !window.Swal) {
            const handled = openModalFromFlashMeta(flashData ? flashData.meta : null);
            if (!handled) {
              maybeShowWelcome();
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
            text: flashData.message || '',
            confirmButtonText: 'Entendido'
          }).then(function () {
            const handled = openModalFromFlashMeta(flashData.meta);
            if (!handled) {
              maybeShowWelcome();
            }
          });
        }

        if (duiInput) {
          duiInput.addEventListener('input', function () {
            duiInput.value = formatDui(duiInput.value);
          });
          duiInput.value = formatDui(duiInput.value);
        }

        if (nitInput) {
          nitInput.addEventListener('input', function () {
            nitInput.value = formatNit(nitInput.value);
          });
          nitInput.value = formatNit(nitInput.value);
        }

        if (nombreInput && inicialesInput) {
          nombreInput.addEventListener('input', function () {
            if (inicialesInput.dataset.touched === 'true') {
              return;
            }
            inicialesInput.value = buildInitials(nombreInput.value);
          });

          inicialesInput.addEventListener('input', function () {
            inicialesInput.dataset.touched = inicialesInput.value.trim() !== '' ? 'true' : 'false';
            inicialesInput.value = inicialesInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4);
            syncPreview();
          });

          nombreInput.addEventListener('input', syncPreview);
        }

        if (colorInput) {
          colorInput.addEventListener('input', syncPreview);
        }

        if (empresaForm) {
          empresaForm.addEventListener('submit', function (event) {
            if (duiInput && duiInput.value.trim() !== '' && !/^\d{8}-\d$/.test(duiInput.value.trim())) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: 'El DUI debe tener formato 12345678-9.',
                confirmButtonText: 'Corregir'
              });
              duiInput.focus();
              return;
            }

            if (nitInput && nitInput.value.trim() !== '' && !/^\d{4}-\d{6}-\d{3}-\d$/.test(nitInput.value.trim())) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: 'El NIT debe tener formato 0000-000000-000-0.',
                confirmButtonText: 'Corregir'
              });
              nitInput.focus();
            }
          });
        }

        if (perfilForm) {
          perfilForm.addEventListener('submit', function (event) {
            const passwordError = validatePasswordForm(profilePasswordInput, profileConfirmPasswordInput, false);
            if (passwordError) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: passwordError,
                confirmButtonText: 'Corregir'
              });
              profilePasswordInput.focus();
            }
          });
        }

        if (profileRoleInput) {
          profileRoleInput.addEventListener('change', function () {
            syncPlanFieldState(profileRoleInput, profilePlanInput);
          });
        }

        if (editarPerfilForm) {
          editarPerfilForm.addEventListener('submit', function (event) {
            const passwordError = validatePasswordForm(editProfilePasswordInput, editProfileConfirmPasswordInput, true);
            if (passwordError) {
              event.preventDefault();
              Swal.fire({
                icon: 'error',
                text: passwordError,
                confirmButtonText: 'Corregir'
              });
              if (editProfilePasswordInput) {
                editProfilePasswordInput.focus();
              }
            }
          });
        }

        if (editProfileRoleInput) {
          editProfileRoleInput.addEventListener('change', function () {
            syncPlanFieldState(editProfileRoleInput, editProfilePlanInput);
          });
        }

        editUserButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            fillEditUserForm({
              id: button.dataset.userId || '',
              username: button.dataset.userUsername || '',
              rol: button.dataset.userRole || 'user',
              pais: button.dataset.userCountry || '',
              id_plan: button.dataset.userPlanId || ''
            });

            if (editarPerfilModal) {
              editarPerfilModal.show();
            }
          });
        });

        deleteUserButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            if (!deleteUserForm || !deleteUserIdInput) {
              return;
            }

            const userId = button.dataset.userId || '';
            const userName = button.dataset.userName || 'este perfil';

            const submitDelete = function () {
              deleteUserIdInput.value = userId;
              deleteUserForm.submit();
            };

            if (!window.Swal) {
              if (window.confirm('Se eliminara el perfil ' + userName + '.')) {
                submitDelete();
              }
              return;
            }

            Swal.fire({
              icon: 'warning',
              title: 'Eliminar perfil',
              text: 'Se desactivara el acceso de ' + userName + '.',
              showCancelButton: true,
              confirmButtonText: 'Eliminar',
              cancelButtonText: 'Cancelar'
            }).then(function (result) {
              if (result.isConfirmed) {
                submitDelete();
              }
            });
          });
        });

        commandConfigButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            openCommandConfig({
              companyId: button.dataset.companyId,
              companyName: button.dataset.companyName,
              selectedKeys: button.dataset.selectedKeys
            });
          });
        });

        commandNoteButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            openCommandNote({
              companyId: button.dataset.companyId,
              companyName: button.dataset.companyName,
              note: button.dataset.companyNote || ''
            });
          });
        });

        if (commandStartButton) {
          commandStartButton.addEventListener('click', function () {
            if (commandWelcomeModal) {
              commandWelcomeModal.hide();
            }

            openFirstCommandConfig();
          });
        }

        if (commandConfigForm) {
          commandConfigForm.addEventListener('submit', function (event) {
            const selected = Array.from(commandConfigCheckboxes).filter(function (checkbox) {
              return checkbox.checked;
            });

            if (selected.length === 0) {
              event.preventDefault();
              Swal.fire({
                icon: 'warning',
                text: 'Selecciona al menos un control para guardar el checklist.',
                confirmButtonText: 'Entendido'
              });
            }
          });
        }

        syncPreview();
        syncPlanFieldState(profileRoleInput, profilePlanInput);
        syncPlanFieldState(editProfileRoleInput, editProfilePlanInput);
        showFlashAlert(flash);
      });
    </script>
  </body>
</html>
