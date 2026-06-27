<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/modulos.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/models/EmpresaModel.php';
require_once __DIR__ . '/models/UsuarioModel.php';
require_once __DIR__ . '/services/DatabaseBackupService.php';

requireLogin();

$session = sessionData();
$idUsuario = (int) $session['id_usuario'];
$esAdmin = isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'select_company') {
    $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
    $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if ($empresa === null) {
        setFlash('dashboard', 'Selecciona una empresa valida.', 'danger');
        redirect_to('src/index.php');
    }

    setActiveEmpresaId($idEmpresa);
    setActiveLibroId(null);
    EmpresaModel::marcarUltimaUsada($pdo, $idEmpresa, $idUsuario);
    setFlash('dashboard', 'Empresa activa actualizada.', 'success');
    redirect_to('src/index.php');
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
        redirect_to('src/index.php');
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        setFlash('dashboard', 'Tipo legal invalido.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if (!EmpresaModel::isValidDui($dui)) {
        setFlash('dashboard', 'El DUI debe tener formato 12345678-9.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if (!EmpresaModel::isValidNit($nit)) {
        setFlash('dashboard', 'El NIT debe tener formato 0000-000000-000-0.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NRC.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit)) {
        setFlash('dashboard', 'Ya tienes una empresa con ese NIT.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
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
    setFlash('dashboard', 'Empresa creada correctamente. Ya puedes trabajar tus libros.', 'success');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_company') {
    $idEmpresa    = (int) ($_POST['id_empresa'] ?? 0);
    $empresa      = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;
    $nombre       = trim((string) ($_POST['nombre'] ?? ''));
    $iniciales    = trim((string) ($_POST['iniciales'] ?? ''));
    $colorEmblema = trim((string) ($_POST['color_emblema'] ?? '#f97316'));
    $dui          = EmpresaModel::normalizeDui($_POST['dui'] ?? '');
    $nit          = EmpresaModel::normalizeNit($_POST['nit'] ?? '');
    $nrc          = trim((string) ($_POST['nrc'] ?? ''));
    $tipoLegal    = trim((string) ($_POST['tipo_legal'] ?? 'natural'));
    $oldInput     = [
        'id_empresa'    => $idEmpresa,
        'nombre'        => $nombre,
        'iniciales'     => strtoupper($iniciales),
        'color_emblema' => $colorEmblema !== '' ? $colorEmblema : '#f97316',
        'dui'           => $dui ?? '',
        'nit'           => $nit ?? '',
        'nrc'           => $nrc,
        'tipo_legal'    => in_array($tipoLegal, ['natural', 'juridica'], true) ? $tipoLegal : 'natural',
    ];

    if ($empresa === null) {
        setFlash('dashboard', 'Empresa no encontrada o sin permiso.', 'danger');
        redirect_to('src/index.php');
    }

    if ($nombre === '') {
        setFlash('dashboard', 'Debes escribir el nombre de la empresa.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if (!in_array($tipoLegal, ['natural', 'juridica'], true)) {
        setFlash('dashboard', 'Tipo legal invalido.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if (!EmpresaModel::isValidDui($dui)) {
        setFlash('dashboard', 'El DUI debe tener formato 12345678-9.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if (!EmpresaModel::isValidNit($nit)) {
        setFlash('dashboard', 'El NIT debe tener formato 0000-000000-000-0.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if ($nrc !== '' && EmpresaModel::existeNrc($pdo, $idUsuario, $nrc, $idEmpresa)) {
        setFlash('dashboard', 'Ya tienes otra empresa con ese NRC.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    if ($nit !== null && $nit !== '' && EmpresaModel::existeNit($pdo, $idUsuario, $nit, $idEmpresa)) {
        setFlash('dashboard', 'Ya tienes otra empresa con ese NIT.', 'danger', [
            'open_modal' => true,
            'old'        => $oldInput,
        ]);
        redirect_to('src/index.php');
    }

    EmpresaModel::update($pdo, $idEmpresa, $idUsuario, [
        'nombre'        => $nombre,
        'iniciales'     => $iniciales,
        'color_emblema' => $colorEmblema,
        'dui'           => $dui,
        'nit'           => $nit,
        'nrc'           => $nrc,
        'tipo_legal'    => $tipoLegal,
    ]);

    setFlash('dashboard', 'Empresa actualizada correctamente.', 'success');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_company') {
    $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
    $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if ($empresa === null) {
        setFlash('dashboard', 'Empresa no encontrada o sin permiso.', 'danger');
        redirect_to('src/index.php');
    }

    $eliminada = EmpresaModel::delete($pdo, $idEmpresa, $idUsuario);
    if ($eliminada && (int) (getActiveEmpresaId() ?? 0) === $idEmpresa) {
        setActiveEmpresaId(null);
        setActiveLibroId(null);
    }

    setFlash('dashboard', $eliminada ? 'Empresa desactivada correctamente.' : 'No se pudo desactivar la empresa.', $eliminada ? 'success' : 'warning');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_delete_company') {
    $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
    $empresa   = $idEmpresa > 0 ? EmpresaModel::getById($pdo, $idEmpresa, $idUsuario) : null;

    if ($empresa === null) {
        setFlash('dashboard', 'Empresa no encontrada o sin permiso.', 'danger');
        redirect_to('src/index.php');
    }

    try {
        $resultado = EmpresaModel::deletePermanently($pdo, $idEmpresa, $idUsuario);
    } catch (Throwable $exception) {
        error_log('Permanent company delete error: ' . $exception->getMessage());
        setFlash('dashboard', 'No se pudo eliminar permanentemente la empresa.', 'danger');
        redirect_to('src/index.php');
    }

    if (!empty($resultado['deleted']) && (int) (getActiveEmpresaId() ?? 0) === $idEmpresa) {
        setActiveEmpresaId(null);
        setActiveLibroId(null);
    }

    if (!empty($resultado['deleted'])) {
        setFlash(
            'dashboard',
            'Empresa eliminada permanentemente. Se borraron ' . (int) ($resultado['libros'] ?? 0) . ' libro(s) y ' . (int) ($resultado['facturas'] ?? 0) . ' factura(s).',
            'success'
        );
    } else {
        setFlash('dashboard', 'No se pudo eliminar permanentemente la empresa.', 'warning');
    }

    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede agregar perfiles.', 'danger');
        redirect_to('src/index.php');
    }

    $validation = UsuarioModel::validateNewUser($pdo, $_POST, true);

    if (!($validation['ok'] ?? false)) {
        setFlash('dashboard', (string) ($validation['message'] ?? 'No se pudo crear el perfil.'), 'danger', [
            'open_user_modal' => true,
            'user_old'        => $validation['old'] ?? [],
        ]);
        redirect_to('src/index.php');
    }

    UsuarioModel::create($pdo, $validation['data']);
    setFlash('dashboard', 'Perfil creado correctamente.', 'success');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede editar perfiles.', 'danger');
        redirect_to('src/index.php');
    }

    $editUserId = (int) ($_POST['user_id'] ?? 0);
    if ($editUserId <= 0) {
        setFlash('dashboard', 'Debes indicar el perfil que quieres editar.', 'danger');
        redirect_to('src/index.php');
    }

    $validation = UsuarioModel::validateUserUpdate($pdo, $editUserId, $_POST, true);

    if (!($validation['ok'] ?? false)) {
        setFlash('dashboard', (string) ($validation['message'] ?? 'No se pudo actualizar el perfil.'), 'danger', [
            'open_edit_user_modal' => true,
            'edit_user_old'        => $validation['old'] ?? ['id' => $editUserId],
        ]);
        redirect_to('src/index.php');
    }

    UsuarioModel::update($pdo, $editUserId, $validation['data']);

    if ($editUserId === $idUsuario) {
        $_SESSION['username'] = $validation['data']['username'];
        $_SESSION['rol'] = $validation['data']['rol'];
    }

    setFlash('dashboard', 'Perfil actualizado correctamente.', 'success');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede eliminar perfiles.', 'danger');
        redirect_to('src/index.php');
    }

    $deleteUserId = (int) ($_POST['user_id'] ?? 0);
    if ($deleteUserId <= 0) {
        setFlash('dashboard', 'Debes indicar el perfil que quieres eliminar.', 'danger');
        redirect_to('src/index.php');
    }

    $usuarioObjetivo = UsuarioModel::getById($pdo, $deleteUserId);
    if ($usuarioObjetivo === null) {
        setFlash('dashboard', 'El perfil que intentas eliminar ya no existe.', 'danger');
        redirect_to('src/index.php');
    }

    if ($deleteUserId === $idUsuario) {
        setFlash('dashboard', 'No puedes eliminar la sesion que estas usando ahora mismo.', 'danger');
        redirect_to('src/index.php');
    }

    if (
        (string) $usuarioObjetivo['rol'] === 'admin' &&
        UsuarioModel::countAdmins($pdo, $deleteUserId) === 0
    ) {
        setFlash('dashboard', 'No puedes eliminar al ultimo administrador activo.', 'danger');
        redirect_to('src/index.php');
    }

    UsuarioModel::deactivate($pdo, $deleteUserId);
    setFlash('dashboard', 'Perfil eliminado correctamente.', 'success');
    redirect_to('src/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup_database') {
    if (!$esAdmin) {
        setFlash('dashboard', 'Solo un administrador puede generar backups.', 'danger');
        redirect_to('src/index.php');
    }

    try {
        DatabaseBackupService::download($pdo, isset($dbname) ? (string) $dbname : null);
    } catch (Throwable $exception) {
        error_log('Database backup error: ' . $exception->getMessage());
        setFlash('dashboard', 'No se pudo generar el backup de la base de datos.', 'danger');
        redirect_to('src/index.php');
    }
}

$flash = getFlash('dashboard');
$data = DashboardController::getData($idUsuario, $flash === null);
$modulosLibros = array_filter(
    getLibroModules(),
    static fn(array $modulo): bool => ($modulo['visible_dashboard'] ?? false) === true && dte_modulo_habilitado($modulo)
);
$flashMeta = $flash['meta'] ?? [];
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
], is_array($flashMeta['user_old'] ?? null) ? $flashMeta['user_old'] : []);
$editProfileFormData = array_merge([
    'id'       => 0,
    'username' => '',
    'rol'      => 'user',
], is_array($flashMeta['edit_user_old'] ?? null) ? $flashMeta['edit_user_old'] : []);
$roleLabels = [
    'admin' => 'Administrador',
    'user'  => 'Usuario',
];
$backupDatabaseName = isset($dbname) && trim((string) $dbname) !== '' ? (string) $dbname : 'base_actual';
$basePath = app_url('src/');
$empresaActivaNavbar = $data['empresa_activa'] ?? null;
$empresaActivaNombre = !empty($data['empresa_activa'])
    ? (string) ($data['empresa_activa']['nombre'] ?? '')
    : 'Sin seleccionar';
$cuotaDisponibleLabel = FacturasCuotaModel::formatDisponibleLabel($data['cuota'] ?? []);
$librosRecientes = array_slice(is_array($data['libros'] ?? null) ? $data['libros'] : [], 0, 5);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard - <?php echo htmlspecialchars(app_name()); ?></title>
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20260613e">
    <link rel="icon" type="image/jpeg" href="/assets/img/logos/sietelsaPestana.jpg" />
    <style>
      .dashboard-shell .content-wrapper {
        background: linear-gradient(180deg, #f7f8fd 0%, #f4f7fb 46%, #ffffff 100%);
      }

      .dashboard-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
      }

      .dashboard-hero {
        background:
          radial-gradient(circle at top right, rgba(75, 73, 172, 0.12) 0%, rgba(255, 255, 255, 0) 34%),
          linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
      }

      .dashboard-hero-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1.25rem;
        align-items: center;
      }

      .dashboard-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.7rem;
        color: #4b49ac;
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .dashboard-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1.15;
      }

      .dashboard-copy {
        max-width: 52rem;
        margin: 0.55rem 0 0;
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.55;
      }

      .dashboard-primary-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.65rem;
      }

      .dashboard-primary-actions .btn {
        min-height: 42px;
        border-radius: 12px;
        font-weight: 700;
      }

      .dashboard-metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1.1rem;
      }

      .dashboard-metric {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-height: 82px;
        padding: 0.9rem 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        background: #ffffff;
      }

      .dashboard-metric i {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(75, 73, 172, 0.1);
        color: #4b49ac;
        font-size: 1.1rem;
        flex: 0 0 auto;
      }

      .dashboard-metric span {
        display: block;
        color: #64748b;
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .dashboard-metric strong {
        display: block;
        margin-top: 0.2rem;
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
        overflow-wrap: anywhere;
      }

      .dashboard-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .dashboard-section-head .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        flex: 0 0 auto;
        white-space: nowrap;
      }

      .dashboard-section-head h4 {
        margin: 0;
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
      }

      .dashboard-section-head p {
        margin: 0.3rem 0 0;
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.5;
      }

      .company-list {
        display: grid;
        gap: 0.75rem;
      }

      .company-item {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
        align-items: start;
        padding: 0.9rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        background: #ffffff;
      }

      .company-item-main {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
      }

      .company-avatar {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #4b49ac;
        color: #ffffff;
        font-size: 0.9rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        flex: 0 0 auto;
      }

      .company-name {
        color: #0f172a;
        font-size: 0.96rem;
        font-weight: 800;
        overflow-wrap: anywhere;
      }

      .company-meta {
        margin-top: 0.16rem;
        color: #64748b;
        font-size: 0.82rem;
        line-height: 1.35;
        overflow-wrap: anywhere;
      }

      .company-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 0.45rem;
        padding-left: calc(42px + 0.75rem);
      }

      .company-actions .btn {
        min-height: 34px;
        border-radius: 999px;
        font-weight: 700;
      }

      .company-active-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        min-height: 34px;
        padding: 0 0.7rem;
        border-radius: 999px;
        background: #ecfdf3;
        color: #157347;
        font-size: 0.76rem;
        font-weight: 800;
      }

      .company-danger-menu {
        min-width: 230px;
        padding: 0.45rem;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.1);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
      }

      .company-danger-menu .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 38px;
        border-radius: 9px;
        font-weight: 700;
      }

      .dashboard-empty-state {
        padding: 1.2rem;
        border: 1px dashed rgba(100, 116, 139, 0.28);
        border-radius: 14px;
        background: #f8fafc;
        color: #64748b;
      }

      .module-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .dashboard-module-card {
        display: flex;
        flex-direction: column;
        min-height: 178px;
        padding: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.1);
        border-top: 4px solid var(--module-color, #4b49ac);
        border-radius: 14px;
        background: #ffffff;
      }

      .dashboard-module-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.55rem;
      }

      .dashboard-module-title h5 {
        margin: 0;
        color: #0f172a;
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.3;
      }

      .dashboard-count-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        min-height: 28px;
        padding: 0 0.55rem;
        border-radius: 999px;
        background: rgba(75, 73, 172, 0.09);
        color: #4b49ac;
        font-size: 0.78rem;
        font-weight: 800;
        flex: 0 0 auto;
      }

      .dashboard-module-card p {
        margin: 0;
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.5;
      }

      .dashboard-module-card .btn {
        align-self: flex-start;
        margin-top: auto;
        border-radius: 999px;
        font-weight: 700;
      }

      .active-company-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border: 1px solid rgba(6, 182, 212, 0.22);
        border-radius: 14px;
        background: #ecfeff;
        color: #155e75;
      }

      .active-company-banner strong {
        color: #0f172a;
      }

      .recent-books {
        display: grid;
        gap: 0.55rem;
        margin-top: 1.15rem;
      }

      .recent-book-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: center;
        padding: 0.75rem 0.85rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 12px;
        background: #ffffff;
      }

      .recent-book-title {
        color: #0f172a;
        font-weight: 800;
      }

      .recent-book-meta {
        margin-top: 0.15rem;
        color: #64748b;
        font-size: 0.82rem;
      }

      .dashboard-shortcut-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
      }

      .dashboard-shortcut-row .btn {
        border-radius: 999px;
        font-weight: 700;
      }

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

      .backup-meta-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .backup-meta-item {
        padding: 0.9rem 1rem;
        border: 1px solid rgba(124, 134, 153, 0.16);
        border-radius: 16px;
        background: #f8faff;
      }

      .backup-meta-item span {
        display: block;
        color: #7c8699;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .backup-meta-item strong {
        display: block;
        margin-top: 0.35rem;
        color: #111827;
        font-size: 0.92rem;
      }

      .backup-form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-start;
      }

      .backup-form-note {
        color: #6c7383;
        font-size: 0.84rem;
        line-height: 1.5;
      }

      @media (max-width: 991.98px) {
        .dashboard-hero-body,
        .company-item,
        .recent-book-item {
          grid-template-columns: 1fr;
        }

        .dashboard-primary-actions,
        .company-actions {
          justify-content: flex-start;
        }

        .company-actions {
          padding-left: calc(42px + 0.75rem);
        }

        .dashboard-metric-grid,
        .module-card-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .empresa-modal-layout {
          grid-template-columns: 1fr;
        }

        .empresa-modal-sidebar {
          border-right: 0;
          border-bottom: 1px solid rgba(75, 73, 172, 0.08);
        }

        .backup-meta-grid {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 767.98px) {
        .dashboard-title {
          font-size: 1.45rem;
        }

        .dashboard-primary-actions .btn,
        .dashboard-primary-actions,
        .dashboard-metric-grid,
        .module-card-grid,
        .company-actions .btn,
        .company-actions .btn-group {
          width: 100%;
        }

        .company-actions {
          padding-left: 0;
        }

        .dashboard-metric-grid,
        .module-card-grid {
          grid-template-columns: 1fr;
        }

        .dashboard-section-head,
        .active-company-banner {
          flex-direction: column;
          align-items: flex-start;
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
      }

      html[data-sietelsa-theme=dark] {
        --dte-dark-page: #0a0d12;
        --dte-dark-panel: #111822;
        --dte-dark-panel-soft: #151e2a;
        --dte-dark-border: #263140;
        --dte-dark-text: #e7edf5;
        --dte-dark-muted: #8d9aab;
        --dte-dark-accent: #8b86d9;
        --dte-dark-accent-strong: #a29df0;
      }

      html[data-sietelsa-theme=dark] body,
      html[data-sietelsa-theme=dark] .dashboard-shell,
      html[data-sietelsa-theme=dark] .dashboard-shell .content-wrapper,
      html[data-sietelsa-theme=dark] .page-body-wrapper,
      html[data-sietelsa-theme=dark] .main-panel {
        background: var(--dte-dark-page) !important;
        color: var(--dte-dark-text);
      }

      html[data-sietelsa-theme=dark] .dashboard-card,
      html[data-sietelsa-theme=dark] .dashboard-hero {
        background: var(--dte-dark-panel) !important;
        border-color: var(--dte-dark-border) !important;
        box-shadow: none !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-hero {
        background:
          radial-gradient(circle at top right, rgba(139, 134, 217, 0.1), transparent 36%),
          var(--dte-dark-panel) !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-eyebrow {
        color: var(--dte-dark-accent-strong);
      }

      html[data-sietelsa-theme=dark] .dashboard-title,
      html[data-sietelsa-theme=dark] .dashboard-section-head h4,
      html[data-sietelsa-theme=dark] .dashboard-module-title h5,
      html[data-sietelsa-theme=dark] .company-name,
      html[data-sietelsa-theme=dark] .recent-book-title,
      html[data-sietelsa-theme=dark] .card-title {
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-copy,
      html[data-sietelsa-theme=dark] .dashboard-section-head p,
      html[data-sietelsa-theme=dark] .dashboard-module-card p,
      html[data-sietelsa-theme=dark] .company-meta,
      html[data-sietelsa-theme=dark] .recent-book-meta,
      html[data-sietelsa-theme=dark] .card-description,
      html[data-sietelsa-theme=dark] .text-muted {
        color: var(--dte-dark-muted) !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-metric,
      html[data-sietelsa-theme=dark] .company-item,
      html[data-sietelsa-theme=dark] .dashboard-module-card,
      html[data-sietelsa-theme=dark] .recent-book-item,
      html[data-sietelsa-theme=dark] .profile-helper-card,
      html[data-sietelsa-theme=dark] .backup-meta-item,
      html[data-sietelsa-theme=dark] .dashboard-empty-state {
        background: var(--dte-dark-panel-soft) !important;
        border-color: var(--dte-dark-border) !important;
        color: var(--dte-dark-text) !important;
        box-shadow: none !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-metric i,
      html[data-sietelsa-theme=dark] .dashboard-count-pill,
      html[data-sietelsa-theme=dark] .modal-badge,
      html[data-sietelsa-theme=dark] .empresa-section-tag {
        background: rgba(139, 134, 217, 0.14) !important;
        color: var(--dte-dark-accent-strong) !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-metric span,
      html[data-sietelsa-theme=dark] .backup-meta-item span,
      html[data-sietelsa-theme=dark] .empresa-preview-label,
      html[data-sietelsa-theme=dark] .empresa-modal-helper {
        color: var(--dte-dark-muted) !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-metric strong,
      html[data-sietelsa-theme=dark] .backup-meta-item strong,
      html[data-sietelsa-theme=dark] .active-company-banner strong {
        color: #f2f5f9 !important;
      }

      html[data-sietelsa-theme=dark] .dashboard-module-card {
        border-top-color: #66628f !important;
      }

      html[data-sietelsa-theme=dark] .active-company-banner {
        background: #14262b !important;
        border-color: #294148 !important;
        color: #c9dde0 !important;
      }

      html[data-sietelsa-theme=dark] .company-active-pill {
        background: rgba(123, 200, 155, 0.12) !important;
        color: #b9e8ca !important;
      }

      html[data-sietelsa-theme=dark] .company-avatar {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
      }

      html[data-sietelsa-theme=dark] .btn-primary {
        background: #5752aa !important;
        border-color: #5752aa !important;
        color: #f8fafc !important;
        box-shadow: none !important;
      }

      html[data-sietelsa-theme=dark] .btn-primary:hover,
      html[data-sietelsa-theme=dark] .btn-primary:focus {
        background: #635db8 !important;
        border-color: #635db8 !important;
      }

      html[data-sietelsa-theme=dark] .btn-outline-primary,
      html[data-sietelsa-theme=dark] .dashboard-shortcut-row .btn,
      html[data-sietelsa-theme=dark] .dashboard-module-card .btn {
        background: transparent !important;
        border-color: #506178 !important;
        color: #d7deeb !important;
        box-shadow: none !important;
      }

      html[data-sietelsa-theme=dark] .btn-outline-primary:hover,
      html[data-sietelsa-theme=dark] .btn-outline-primary:focus,
      html[data-sietelsa-theme=dark] .dashboard-shortcut-row .btn:hover,
      html[data-sietelsa-theme=dark] .dashboard-module-card .btn:hover {
        background: #1b2634 !important;
        border-color: #66758c !important;
        color: #fff !important;
      }

      html[data-sietelsa-theme=dark] .btn-outline-secondary,
      html[data-sietelsa-theme=dark] .company-actions .btn-outline-secondary {
        background: #121a25 !important;
        border-color: #39465a !important;
        color: #d8dee8 !important;
      }

      html[data-sietelsa-theme=dark] .btn-outline-success {
        background: transparent !important;
        border-color: #496b57 !important;
        color: #b9e8ca !important;
      }

      html[data-sietelsa-theme=dark] .table,
      html[data-sietelsa-theme=dark] .table td,
      html[data-sietelsa-theme=dark] .table th {
        background: transparent !important;
        border-color: var(--dte-dark-border) !important;
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .badge-light,
      html[data-sietelsa-theme=dark] .profile-role-pill,
      html[data-sietelsa-theme=dark] .profile-role-pill--admin,
      html[data-sietelsa-theme=dark] .profile-role-pill--user {
        background: #202a38 !important;
        color: #d8dee8 !important;
      }

      html[data-sietelsa-theme=dark] .dropdown-menu,
      html[data-sietelsa-theme=dark] .company-danger-menu {
        background: #121923 !important;
        border-color: var(--dte-dark-border) !important;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.26) !important;
      }

      html[data-sietelsa-theme=dark] .dropdown-item {
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .dropdown-item:hover,
      html[data-sietelsa-theme=dark] .dropdown-item:focus {
        background: #1b2634 !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal .modal-content,
      html[data-sietelsa-theme=dark] .empresa-modal .modal-header,
      html[data-sietelsa-theme=dark] .empresa-modal .modal-footer,
      html[data-sietelsa-theme=dark] .empresa-modal .modal-body,
      html[data-sietelsa-theme=dark] .empresa-main-pane,
      html[data-sietelsa-theme=dark] .empresa-form-section,
      html[data-sietelsa-theme=dark] .empresa-preview-panel,
      html[data-sietelsa-theme=dark] .empresa-sidebar-item,
      html[data-sietelsa-theme=dark] .empresa-color-field,
      html[data-sietelsa-theme=dark] .empresa-legal-choice label {
        background: var(--dte-dark-panel-soft) !important;
        border-color: var(--dte-dark-border) !important;
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal-sidebar {
        background: #101721 !important;
        border-color: var(--dte-dark-border) !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal .modal-title,
      html[data-sietelsa-theme=dark] .empresa-form-section-head h6,
      html[data-sietelsa-theme=dark] .empresa-modal-sidebar h6,
      html[data-sietelsa-theme=dark] .empresa-preview-name,
      html[data-sietelsa-theme=dark] .empresa-sidebar-item strong,
      html[data-sietelsa-theme=dark] .empresa-color-copy strong {
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal .modal-subtitle,
      html[data-sietelsa-theme=dark] .empresa-modal-copy,
      html[data-sietelsa-theme=dark] .empresa-sidebar-item span,
      html[data-sietelsa-theme=dark] .empresa-form-section-head p,
      html[data-sietelsa-theme=dark] .empresa-color-copy span {
        color: var(--dte-dark-muted) !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal .form-control,
      html[data-sietelsa-theme=dark] .empresa-modal .form-select,
      html[data-sietelsa-theme=dark] .form-control,
      html[data-sietelsa-theme=dark] .form-select {
        background: #0c121b !important;
        border-color: #334052 !important;
        color: var(--dte-dark-text) !important;
      }

      html[data-sietelsa-theme=dark] .empresa-modal .form-control:focus,
      html[data-sietelsa-theme=dark] .form-control:focus,
      html[data-sietelsa-theme=dark] .form-select:focus {
        background: #0f1620 !important;
        border-color: #6c7890 !important;
        box-shadow: 0 0 0 0.18rem rgba(139, 134, 217, 0.16) !important;
      }

      html[data-sietelsa-theme=dark] .btn-close {
        filter: invert(1) grayscale(1);
        opacity: 0.72;
      }
    </style>
  </head>
  <body>
    <div class="container-scroller dashboard-shell">
      <?php include __DIR__ . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php include __DIR__ . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card dashboard-card dashboard-hero">
                  <div class="card-body">
                    <div class="dashboard-hero-body">
                      <div>
                        <span class="dashboard-eyebrow">
                          <i class="mdi mdi-view-dashboard-outline"></i>
                          Panel de trabajo
                        </span>
                        <h1 class="dashboard-title">Dashboard DTE</h1>
                        <p class="dashboard-copy">
                          Selecciona la empresa activa, abre tus libros y revisa rapidamente el estado general de tus modulos.
                        </p>
                      </div>
                      <div class="dashboard-primary-actions">
                        <button type="button" class="btn btn-primary" data-company-create data-bs-toggle="modal" data-bs-target="#empresaModal">
                          <i class="mdi mdi-domain-plus me-1"></i> Agregar empresa
                        </button>
                        <?php if (!empty($data['empresa_activa'])): ?>
                        <a href="<?php echo htmlspecialchars((string) (($modulosLibros['compras']['ruta'] ?? '') ?: 'pages/compras.php')); ?>" class="btn btn-outline-primary">
                          <i class="mdi mdi-book-open-page-variant-outline me-1"></i> Abrir compras
                        </a>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="dashboard-metric-grid">
                      <div class="dashboard-metric">
                        <i class="mdi mdi-domain"></i>
                        <div>
                          <span>Empresa activa</span>
                          <strong><?php echo htmlspecialchars($empresaActivaNombre); ?></strong>
                        </div>
                      </div>
                      <div class="dashboard-metric">
                        <i class="mdi mdi-office-building-outline"></i>
                        <div>
                          <span>Empresas</span>
                          <strong><?php echo count($data['empresas']); ?> registradas</strong>
                        </div>
                      </div>
                      <div class="dashboard-metric">
                        <i class="mdi mdi-bookshelf"></i>
                        <div>
                          <span>Libros</span>
                          <strong><?php echo (int) $data['libros_count']; ?> en total</strong>
                        </div>
                      </div>
                      <div class="dashboard-metric">
                        <i class="mdi mdi-cloud-upload-outline"></i>
                        <div>
                          <span>Importacion</span>
                          <strong><?php echo htmlspecialchars($cuotaDisponibleLabel); ?></strong>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row align-items-start">
              <div class="col-xl-4 col-lg-5 grid-margin">
                <div class="card dashboard-card">
                  <div class="card-body">
                    <div class="dashboard-section-head">
                      <div>
                        <h4>Empresas</h4>
                        <p>Activa la empresa con la que vas a trabajar o administra sus datos.</p>
                      </div>
                      <button type="button" class="btn btn-primary btn-sm" data-company-create data-bs-toggle="modal" data-bs-target="#empresaModal">
                        <i class="mdi mdi-plus me-1"></i> Nueva
                      </button>
                    </div>
                    <?php if (!empty($data['empresas'])): ?>
                    <div class="company-list">
                      <?php foreach ($data['empresas'] as $empresa): ?>
                      <?php
                        $empresaJson = htmlspecialchars(json_encode($empresa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        $empresaEstaActiva = (int) ($data['empresa_activa']['id'] ?? 0) === (int) $empresa['id'];
                        $empresaIniciales = trim((string) ($empresa['iniciales'] ?? ''));
                        if ($empresaIniciales === '') {
                            $empresaIniciales = substr(trim((string) ($empresa['nombre'] ?? 'E')), 0, 2);
                        }
                        $empresaColor = trim((string) ($empresa['color_emblema'] ?? '#4b49ac'));
                        if ($empresaColor === '') {
                            $empresaColor = '#4b49ac';
                        }
                      ?>
                      <div class="company-item">
                        <div class="company-item-main">
                          <span class="company-avatar" style="background: <?php echo htmlspecialchars($empresaColor); ?>;">
                            <?php echo htmlspecialchars(strtoupper($empresaIniciales)); ?>
                          </span>
                          <div>
                            <div class="company-name"><?php echo htmlspecialchars((string) $empresa['nombre']); ?></div>
                            <div class="company-meta">NIT: <?php echo htmlspecialchars((string) ($empresa['nit'] ?: 'Sin NIT')); ?></div>
                          </div>
                        </div>
                        <div class="company-actions">
                          <?php if ($empresaEstaActiva): ?>
                          <span class="company-active-pill">
                            <i class="mdi mdi-check-circle-outline"></i> Activa
                          </span>
                          <?php else: ?>
                          <form method="POST" class="m-0">
                            <input type="hidden" name="action" value="select_company">
                            <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                            <button type="submit" class="btn btn-outline-success btn-sm">
                              <i class="mdi mdi-check me-1"></i> Activar
                            </button>
                          </form>
                          <?php endif; ?>
                          <button type="button" class="btn btn-outline-primary btn-sm" data-edit-company="<?php echo $empresaJson; ?>">
                            <i class="mdi mdi-pencil-outline me-1"></i> Editar
                          </button>
                          <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                              Mas
                            </button>
                            <div class="dropdown-menu dropdown-menu-end company-danger-menu">
                              <form
                                method="POST"
                                class="m-0"
                                data-confirm="Se desactivara esta empresa. Sus libros historicos no se borran."
                                data-confirm-title="Desactivar empresa"
                                data-confirm-button="Desactivar"
                              >
                                <input type="hidden" name="action" value="delete_company">
                                <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                                <button type="submit" class="dropdown-item text-warning">
                                  <i class="mdi mdi-archive-arrow-down-outline"></i> Desactivar
                                </button>
                              </form>
                              <form
                                method="POST"
                                class="m-0"
                                data-confirm="Esto borrara permanentemente la empresa, sus libros y sus facturas. Esta accion no se puede deshacer."
                                data-confirm-title="Eliminar permanente"
                                data-confirm-button="Eliminar definitivo"
                              >
                                <input type="hidden" name="action" value="force_delete_company">
                                <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                  <i class="mdi mdi-delete-alert-outline"></i> Eliminar definitivo
                                </button>
                              </form>
                            </div>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-empty-state">
                      <strong class="d-block mb-1">Todavia no tienes empresas</strong>
                      Crea una empresa para poder abrir libros y cargar documentos.
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="col-xl-8 col-lg-7 grid-margin">
                <div class="card dashboard-card">
                  <div class="card-body">
                    <div class="dashboard-section-head">
                      <div>
                        <h4>Modulos de libros</h4>
                        <p>Entra al modulo que necesitas y continua trabajando con la empresa activa.</p>
                      </div>
                    </div>

                    <?php if (!empty($data['empresa_activa'])): ?>
                    <div class="active-company-banner">
                      <span><i class="mdi mdi-domain me-1"></i> Empresa activa: <strong><?php echo htmlspecialchars((string) $data['empresa_activa']['nombre']); ?></strong></span>
                      <span><?php echo (int) $data['libros_count']; ?> libro(s) disponibles</span>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-empty-state mb-3">
                      Selecciona o crea una empresa para que los libros queden asociados correctamente.
                    </div>
                    <?php endif; ?>

                    <div class="module-card-grid">
                      <?php foreach ($modulosLibros as $tipoModulo => $modulo): ?>
                      <div class="dashboard-module-card" style="--module-color: <?php echo htmlspecialchars((string) ($modulo['accent_color'] ?? '#4b49ac')); ?>;">
                        <div class="dashboard-module-title">
                          <h5><?php echo htmlspecialchars((string) $modulo['nombre']); ?></h5>
                          <span class="dashboard-count-pill"><?php echo (int) ($data['libros_by_type'][$tipoModulo] ?? 0); ?></span>
                        </div>
                        <p><?php echo htmlspecialchars((string) ($modulo['descripcion'] ?? '')); ?></p>
                          <?php if (!empty($modulo['ruta'])): ?>
                        <a href="<?php echo htmlspecialchars((string) $modulo['ruta']); ?>" class="btn btn-outline-primary btn-sm">
                          Abrir modulo
                        </a>
                          <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>

                    <?php if (!empty($librosRecientes)): ?>
                    <div class="recent-books">
                      <div class="dashboard-section-head mb-1">
                        <div>
                          <h4>Libros recientes</h4>
                          <p>Ultimos periodos creados para acceder sin buscar entre modulos.</p>
                        </div>
                      </div>
                      <?php foreach ($librosRecientes as $libro): ?>
                      <?php $moduloLibro = getLibroModule((string) ($libro['tipo'] ?? '')); ?>
                      <div class="recent-book-item">
                        <div>
                          <div class="recent-book-title"><?php echo htmlspecialchars((string) ($moduloLibro['nombre'] ?? $libro['tipo'])); ?></div>
                          <div class="recent-book-meta">
                            <?php echo htmlspecialchars((string) $libro['empresa_nombre']); ?> ·
                            <?php echo str_pad((string) $libro['mes'], 2, '0', STR_PAD_LEFT) . '/' . htmlspecialchars((string) $libro['anio']); ?>
                          </div>
                        </div>
                        <?php if (!empty($moduloLibro['ruta'])): ?>
                        <a href="<?php echo htmlspecialchars((string) $moduloLibro['ruta']); ?>" class="btn btn-outline-secondary btn-sm">Ir</a>
                        <?php else: ?>
                        <span class="text-muted">Sin vista</span>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-empty-state mt-3">
                      Todavia no has creado libros. Abre un modulo para crear el primer periodo.
                    </div>
                    <?php endif; ?>

                    <div class="dashboard-shortcut-row">
                      <?php foreach ($modulosLibros as $modulo): ?>
                      <?php if (!empty($modulo['ruta'])): ?>
                      <a href="<?php echo htmlspecialchars((string) $modulo['ruta']); ?>" class="btn btn-outline-primary btn-sm"><?php echo htmlspecialchars((string) $modulo['nombre']); ?></a>
                      <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($esAdmin): ?>
            <div class="row align-items-start">
              <div class="col-lg-4 grid-margin">
                <div class="card dashboard-card">
                  <div class="card-body">
                    <h4 class="card-title">Perfiles</h4>
                    <p class="card-description mb-3">Agrega mas accesos y define si cada cuenta sera `user` o `admin`.</p>
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

              <div class="col-lg-8 grid-margin">
                <div class="card dashboard-card">
                  <div class="card-body">
                    <h4 class="card-title">Usuarios registrados</h4>
                    <p class="card-description">Vista rapida de los perfiles activos dentro del sistema.</p>
                    <?php if (!empty($data['usuarios'])): ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead>
                          <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Alta</th>
                            <th>Acciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($data['usuarios'] as $usuario): ?>
                          <?php $rolUsuario = (string) ($usuario['rol'] ?? 'user'); ?>
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
                            <td>
                              <?php
                              $fechaAlta = !empty($usuario['created_at']) ? strtotime((string) $usuario['created_at']) : false;
                              echo $fechaAlta ? htmlspecialchars(date('d/m/Y', $fechaAlta)) : '-';
                              ?>
                            </td>
                            <td>
                              <div class="profile-actions">
                                <button
                                  type="button"
                                  class="btn btn-outline-primary btn-sm btn-edit-user"
                                  data-user-id="<?php echo (int) $usuario['id']; ?>"
                                  data-user-username="<?php echo htmlspecialchars((string) $usuario['username'], ENT_QUOTES); ?>"
                                  data-user-role="<?php echo htmlspecialchars($rolUsuario, ENT_QUOTES); ?>"
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

            <div class="row">
              <div class="col-12 grid-margin">
                <div class="card dashboard-card">
                  <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4">
                      <div>
                        <h4 class="card-title mb-1">Backup de base de datos</h4>
                        <p class="card-description mb-3">Descarga un archivo `.sql` generado en tiempo real con la estructura y el contenido completo de la base activa.</p>
                        <div class="backup-meta-grid">
                          <div class="backup-meta-item">
                            <span>Base actual</span>
                            <strong><?php echo htmlspecialchars($backupDatabaseName); ?></strong>
                          </div>
                          <div class="backup-meta-item">
                            <span>Contenido</span>
                            <strong>Base completa y datos actuales</strong>
                          </div>
                          <div class="backup-meta-item">
                            <span>Acceso</span>
                            <strong>Solo administradores</strong>
                          </div>
                        </div>
                      </div>
                      <form method="POST" action="" id="databaseBackupForm" class="backup-form">
                        <input type="hidden" name="action" value="backup_database">
                        <button type="submit" class="btn btn-primary btn-icon-text" id="backupDatabaseButton">
                          <i class="ti-download btn-icon-prepend"></i> Descargar backup
                        </button>
                        <span class="backup-form-note">El respaldo se genera al momento desde la base activa, con fecha y hora del servidor.</span>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

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
	                      <p class="modal-subtitle" id="empresaModalSubtitle">Crea una empresa nueva y dejala lista para trabajar dentro del sistema.</p>
                    </div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="empresaForm">
                  <div class="modal-body">
	                    <input type="hidden" name="action" id="empresaFormAction" value="create_company">
	                    <input type="hidden" name="id_empresa" id="empresaId" value="<?php echo htmlspecialchars((string) ($companyFormData['id_empresa'] ?? '')); ?>">
                    <div class="empresa-modal-layout">
                      <aside class="empresa-modal-sidebar">
	                        <span class="empresa-modal-kicker" id="empresaModalKicker">Nueva empresa</span>
	                        <h6 id="empresaSidebarTitle">Dejala lista para operar</h6>
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
	                      <button type="submit" class="btn btn-primary" id="empresaSubmitButton">
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

          <?php include __DIR__ . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <script defer src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script defer src="assets/js/off-canvas.js"></script>
    <script defer src="assets/js/template.js?v=20260613e"></script>
    <script defer src="assets/js/settings.js"></script>
	    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const empresaModalElement = document.getElementById('empresaModal');
        const empresaModal = empresaModalElement && window.bootstrap
          ? new bootstrap.Modal(empresaModalElement)
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
        const perfilForm = document.getElementById('perfilForm');
        const editarPerfilForm = document.getElementById('editarPerfilForm');
	        const databaseBackupForm = document.getElementById('databaseBackupForm');
	        const backupDatabaseButton = document.getElementById('backupDatabaseButton');
	        const deleteUserForm = document.getElementById('deleteUserForm');
	        const empresaFormAction = document.getElementById('empresaFormAction');
	        const empresaIdInput = document.getElementById('empresaId');
	        const empresaModalLabel = document.getElementById('empresaModalLabel');
	        const empresaModalSubtitle = document.getElementById('empresaModalSubtitle');
	        const empresaModalKicker = document.getElementById('empresaModalKicker');
	        const empresaSidebarTitle = document.getElementById('empresaSidebarTitle');
	        const empresaSubmitButton = document.getElementById('empresaSubmitButton');
	        const nombreInput = document.getElementById('nombre');
	        const inicialesInput = document.getElementById('iniciales');
	        const colorInput = document.getElementById('color_emblema');
	        const duiInput = document.getElementById('dui');
	        const nitInput = document.getElementById('nit');
	        const nrcInput = document.getElementById('nrc');
	        const tipoLegalNaturalInput = document.getElementById('tipo_legal_natural');
	        const tipoLegalJuridicaInput = document.getElementById('tipo_legal_juridica');
	        const profilePasswordInput = document.getElementById('profile_password');
	        const profileConfirmPasswordInput = document.getElementById('profile_confirm_password');
        const editUserIdInput = document.getElementById('edit_user_id');
        const editProfileUsernameInput = document.getElementById('edit_profile_username');
        const editProfileRoleInput = document.getElementById('edit_profile_role');
        const editProfilePasswordInput = document.getElementById('edit_profile_password');
	        const editProfileConfirmPasswordInput = document.getElementById('edit_profile_confirm_password');
	        const createCompanyButtons = document.querySelectorAll('[data-company-create]');
	        const editCompanyButtons = document.querySelectorAll('[data-edit-company]');
	        const confirmForms = document.querySelectorAll('form[data-confirm]');
	        const editUserButtons = document.querySelectorAll('.btn-edit-user');
        const deleteUserButtons = document.querySelectorAll('.btn-delete-user');
        const deleteUserIdInput = document.getElementById('delete_user_id');
        const previewAvatar = document.getElementById('empresaPreviewAvatar');
        const previewInitials = document.getElementById('empresaPreviewInitials');
        const previewName = document.getElementById('empresaPreviewName');
        const previewColorDot = document.getElementById('empresaPreviewColorDot');
        const previewColorHex = document.getElementById('empresaPreviewColorHex');
        const colorFieldHex = document.getElementById('empresaColorFieldHex');
        const flash = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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

	        function setTipoLegal(value) {
	          const tipoLegal = value === 'juridica' ? 'juridica' : 'natural';
	          if (tipoLegalNaturalInput) {
	            tipoLegalNaturalInput.checked = tipoLegal === 'natural';
	          }
	          if (tipoLegalJuridicaInput) {
	            tipoLegalJuridicaInput.checked = tipoLegal === 'juridica';
	          }
	        }

	        function fillCompanyForm(companyData) {
	          const company = companyData || {};
	          if (empresaIdInput) {
	            empresaIdInput.value = company.id_empresa || company.id || '';
	          }
	          if (nombreInput) {
	            nombreInput.value = company.nombre || '';
	          }
	          if (inicialesInput) {
	            inicialesInput.value = (company.iniciales || '').toString().toUpperCase().slice(0, 4);
	            inicialesInput.dataset.touched = inicialesInput.value.trim() !== '' ? 'true' : 'false';
	          }
	          if (colorInput) {
	            colorInput.value = company.color_emblema || '#f97316';
	          }
	          if (duiInput) {
	            duiInput.value = formatDui(company.dui || '');
	          }
	          if (nitInput) {
	            nitInput.value = formatNit(company.nit || '');
	          }
	          if (nrcInput) {
	            nrcInput.value = company.nrc || '';
	          }
	          setTipoLegal(company.tipo_legal || 'natural');
	          syncPreview();
	        }

	        function setCompanyCreateMode(resetValues) {
	          if (empresaFormAction) {
	            empresaFormAction.value = 'create_company';
	          }
	          if (empresaIdInput) {
	            empresaIdInput.value = '';
	          }
	          if (empresaModalLabel) {
	            empresaModalLabel.textContent = 'Agregar empresa';
	          }
	          if (empresaModalSubtitle) {
	            empresaModalSubtitle.textContent = 'Crea una empresa nueva y dejala lista para trabajar dentro del sistema.';
	          }
	          if (empresaModalKicker) {
	            empresaModalKicker.textContent = 'Nueva empresa';
	          }
	          if (empresaSidebarTitle) {
	            empresaSidebarTitle.textContent = 'Dejala lista para operar';
	          }
	          if (empresaSubmitButton) {
	            empresaSubmitButton.innerHTML = '<i class="mdi mdi-check-circle-outline me-1"></i> Crear empresa';
	          }
	          if (resetValues !== false) {
	            fillCompanyForm({
	              nombre: '',
	              iniciales: '',
	              color_emblema: '#f97316',
	              dui: '',
	              nit: '',
	              nrc: '',
	              tipo_legal: 'natural'
	            });
	          } else {
	            syncPreview();
	          }
	        }

	        function setCompanyEditMode(companyData, showModal) {
	          if (empresaFormAction) {
	            empresaFormAction.value = 'edit_company';
	          }
	          if (empresaModalLabel) {
	            empresaModalLabel.textContent = 'Editar empresa';
	          }
	          if (empresaModalSubtitle) {
	            empresaModalSubtitle.textContent = 'Actualiza los datos de la empresa sin afectar sus libros historicos.';
	          }
	          if (empresaModalKicker) {
	            empresaModalKicker.textContent = 'Edicion';
	          }
	          if (empresaSidebarTitle) {
	            empresaSidebarTitle.textContent = 'Ajusta la empresa';
	          }
	          if (empresaSubmitButton) {
	            empresaSubmitButton.innerHTML = '<i class="ti-save me-1"></i> Guardar cambios';
	          }
	          fillCompanyForm(companyData || {});
	          if (showModal !== false && empresaModal) {
	            empresaModal.show();
	          }
	        }

	        function openModalFromFlashMeta(meta) {
	          if (!meta) {
            return;
          }

          if (meta.open_edit_user_modal && editarPerfilModal) {
            if (meta.edit_user_old) {
              fillEditUserForm(meta.edit_user_old);
            }
            editarPerfilModal.show();
            return;
          }

          if (meta.open_user_modal && perfilModal) {
            perfilModal.show();
            return;
          }

	          if (meta.open_modal && empresaModal) {
	            if (meta.old && (meta.old.id_empresa || meta.old.id)) {
	              setCompanyEditMode(meta.old, false);
	            } else {
	              setCompanyCreateMode(false);
	            }
	            empresaModal.show();
	          }
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

          if (editProfilePasswordInput) {
            editProfilePasswordInput.value = '';
          }

          if (editProfileConfirmPasswordInput) {
            editProfileConfirmPasswordInput.value = '';
          }
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
            openModalFromFlashMeta(flashData ? flashData.meta : null);
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
            openModalFromFlashMeta(flashData.meta);
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

	        createCompanyButtons.forEach(function (button) {
	          button.addEventListener('click', function () {
	            setCompanyCreateMode(true);
	          });
	        });

	        editCompanyButtons.forEach(function (button) {
	          button.addEventListener('click', function () {
	            let companyData = null;
	            try {
	              companyData = JSON.parse(button.getAttribute('data-edit-company') || '{}');
	            } catch (error) {
	              companyData = {};
	            }
	            setCompanyEditMode(companyData, true);
	          });
	        });

	        confirmForms.forEach(function (form) {
	          form.addEventListener('submit', function (event) {
	            const message = form.getAttribute('data-confirm') || 'Confirma esta accion.';
	            const title = form.getAttribute('data-confirm-title') || 'Confirmar accion';
	            const confirmButton = form.getAttribute('data-confirm-button') || 'Confirmar';
	            event.preventDefault();

	            const submitConfirmed = function () {
	              form.submit();
	            };

	            if (!window.Swal) {
	              if (window.confirm(message)) {
	                submitConfirmed();
	              }
	              return;
	            }

	            Swal.fire({
	              icon: 'warning',
	              title: title,
	              text: message,
	              showCancelButton: true,
	              confirmButtonText: confirmButton,
	              cancelButtonText: 'Cancelar'
	            }).then(function (result) {
	              if (result.isConfirmed) {
	                submitConfirmed();
	              }
	            });
	          });
	        });

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

        if (databaseBackupForm && backupDatabaseButton) {
          databaseBackupForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const submitBackup = function () {
              backupDatabaseButton.disabled = true;
              backupDatabaseButton.innerHTML = '<i class="ti-reload btn-icon-prepend"></i> Generando backup...';
              databaseBackupForm.submit();
            };

            if (!window.Swal) {
              if (window.confirm('Se generara un backup completo de la base de datos actual.')) {
                submitBackup();
              }
              return;
            }

            Swal.fire({
              icon: 'question',
              title: 'Generar backup',
              text: 'Se descargara un respaldo .sql con la informacion actual del sistema.',
              showCancelButton: true,
              confirmButtonText: 'Generar',
              cancelButtonText: 'Cancelar'
            }).then(function (result) {
              if (result.isConfirmed) {
                submitBackup();
              }
            });
          });
        }

        editUserButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            fillEditUserForm({
              id: button.dataset.userId || '',
              username: button.dataset.userUsername || '',
              rol: button.dataset.userRole || 'user'
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

        syncPreview();
        showFlashAlert(flash);
      });
    </script>
  </body>
</html>
