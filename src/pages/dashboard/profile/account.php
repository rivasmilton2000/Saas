<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/users/bootstrap.php';
require_once SIETELSA_ROOT . '/includes/auth/google.php';
$user = require_authentication();
$destination = dashboard_url_for($user);
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_is_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        throw new DomainException('Solicitud inválida. Actualiza la página.');
    }
    if (login_is_rate_limited((string)$user['correo'])) throw new DomainException('Demasiados intentos. Espera antes de reintentar.');
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['link', 'verify', 'photo'], true)) {
        $url = google_authorization_url();
        $_SESSION['google_oauth']['account_user'] = (int)$user['id'];
        $_SESSION['google_oauth']['account_version'] = (int)$user['version_sesion'];
        $_SESSION['google_oauth']['account_mode'] = $action;
        if ($action==='photo') {
            $linked=db()->prepare('SELECT 1 FROM identidades_autenticacion WHERE usuario_id=? AND proveedor="google"');$linked->execute([$user['id']]);
            $_SESSION['google_oauth']['account_mode']=$linked->fetchColumn()?'photo':'link';
            $_SESSION['google_oauth']['account_photo']=true;
        }
        header('Location: ' . $url);
        exit;
    }
    if ($action !== 'password') throw new DomainException('Acción inválida.');
    record_login_attempt((string)$user['correo'], false, (int)$user['id'], 'account_password');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $errors = PasswordPolicy::validate($password, $confirmation, (string)$user['correo'], (string)$user['nombre_usuario']);
    if (!empty($user['password_hash'])) {
        if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) $errors[] = 'La contraseña actual no es correcta.';
        if (password_verify($password, $user['password_hash'])) $errors[] = 'Elige una contraseña diferente.';
    } else {
        $verified = $_SESSION['account_google_verified'] ?? [];
        if ((int)($verified['id'] ?? 0) !== (int)$user['id'] || (int)($verified['time'] ?? 0) < time() - 300) $errors[] = 'Verifica nuevamente tu identidad con Google antes de establecer una contraseña.';
    }
    if ($errors) throw new DomainException(implode(' ', $errors));
    $pdo = db();
    $pdo->beginTransaction();
    $update = $pdo->prepare('UPDATE usuarios SET password_hash = :hash, debe_cambiar_password = 0, version_sesion = version_sesion + 1 WHERE id = :id AND version_sesion = :version');
    $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id'], 'version' => $user['version_sesion']]);
    if ($update->rowCount() !== 1) throw new DomainException('La cuenta cambió. Inicia sesión nuevamente.');
    $pdo->prepare('DELETE FROM tokens_recordarme WHERE usuario_id = ?')->execute([$user['id']]);
    $pdo->commit();
    unset($_SESSION['account_google_verified']);
    establish_session(find_user_by_id((int)$user['id']), 'local', false);
    auth_audit('password_actualizado', (int)$user['id'], 'local', (string)$user['correo']);
    flash_set('success', 'Contraseña actualizada', 'Tu sesión actual continúa; las demás sesiones y accesos recordados fueron revocados.');
} catch (Throwable $exception) {
    if (db()->inTransaction()) db()->rollBack();
    flash_set('error', 'Configuración de cuenta', user_safe_error_message($exception, 'No fue posible completar la acción. Reintenta más tarde.'));
}
header('Location: ' . $destination);
exit;
