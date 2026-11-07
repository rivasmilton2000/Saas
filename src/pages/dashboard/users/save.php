<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
users_require_post();

$mode = (string) ($_POST['mode'] ?? '');
$avatarPath = null;
$avatarManager = new AvatarManager();

try {
    if ($mode === 'email') {
        $user = $userService->createInvitation((string) ($_POST['correo'] ?? ''), (int) $currentUser['id']);
        $invitation = $invitationService->resend((int) $user['id']);
        flash_set(
            $invitation['sent'] ? 'success' : 'warning',
            'Invitación creada',
            $invitation['sent']
                ? 'La invitación fue enviada y caduca en 30 minutos.'
                : 'La cuenta quedó pendiente. Configura SMTP para entregar la invitación.'
        );
        users_redirect('view.php?id=' . (int) $user['id']);
    }

    if (
        isset($_FILES['avatar'])
        && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        $avatarPath = $avatarManager->store($_FILES['avatar']);
    }

    if ($mode === 'complete') {
        $user = $userService->createComplete(
            $_POST,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? ''),
            (int) $currentUser['id']
        );
        if ($avatarPath !== null) {
            $userService->setAvatar(
                (int) $user['id'],
                'personalizado',
                $avatarPath,
                null,
                (int) $currentUser['id']
            );
        }
        flash_set('success', 'Usuario creado', 'La contraseña quedó marcada como temporal.');
        users_redirect('view.php?id=' . (int) $user['id']);
    }

    if ($mode === 'edit') {
        $userId = (int) ($_POST['id'] ?? 0);
        $previous = $userService->find($userId);
        $user = $userService->updateProfile($userId, $_POST, (int) $currentUser['id']);
        if ($avatarPath !== null) {
            $userService->setAvatar(
                $userId,
                'personalizado',
                $avatarPath,
                null,
                (int) $currentUser['id']
            );
            if (($previous['avatar_tipo'] ?? '') === 'personalizado') {
                $avatarManager->delete($previous['avatar_ruta'] ?? null);
            }
        }
        flash_set('success', 'Usuario actualizado', 'Los datos fueron guardados.');
        users_redirect('view.php?id=' . (int) $user['id']);
    }

    throw new InvalidArgumentException('La modalidad seleccionada no es válida.');
} catch (Throwable $exception) {
    if ($avatarPath !== null) {
        $avatarManager->delete($avatarPath);
    }
    flash_set('error', 'No fue posible guardar', user_safe_error_message($exception));
    $returnPath = 'form.php?mode=' . rawurlencode($mode);
    if ($mode === 'edit') {
        $returnPath .= '&id=' . (int) ($_POST['id'] ?? 0);
    }
    users_redirect($returnPath);
}
