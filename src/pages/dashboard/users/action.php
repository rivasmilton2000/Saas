<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
users_require_post();

$userId = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'status') {
        $userService->changeStatus($userId, (string) ($_POST['value'] ?? ''), (int) $currentUser['id']);
        flash_set('success', 'Estado actualizado', 'El nuevo estado ya está vigente.');
    } elseif ($action === 'role') {
        $userService->changeRole($userId, (string) ($_POST['value'] ?? ''), (int) $currentUser['id']);
        flash_set('success', 'Rol actualizado', 'El cambio quedó registrado en la bitácora.');
    } elseif ($action === 'resend') {
        $result = $invitationService->resend($userId);
        flash_set(
            $result['sent'] ? 'success' : 'warning',
            'Invitación renovada',
            $result['sent']
                ? 'Se envió un enlace nuevo con vigencia de 30 minutos.'
                : 'Se generó un enlace nuevo, pero SMTP no está disponible.'
        );
    } elseif ($action === 'reset_avatar') {
        $user = $userService->find($userId);
        if ($user === null) {
            throw new DomainException('El usuario no existe.');
        }
        $userService->setAvatar($userId, 'predeterminado', null, null, (int) $currentUser['id']);
        if ($user['avatar_tipo'] === 'personalizado') {
            (new AvatarManager())->delete($user['avatar_ruta'] ?? null);
        }
        flash_set('success', 'Avatar restablecido', 'Se aplicó el avatar predeterminado.');
    } elseif ($action === 'delete_permanently') {
        $deleted = $userService->deletePermanently(
            $userId,
            (int) $currentUser['id'],
            (string) ($_POST['confirmation'] ?? '')
        );
        if (($deleted['avatar_tipo'] ?? '') === 'personalizado') {
            (new AvatarManager())->delete($deleted['avatar_ruta'] ?? null);
        }
        flash_set('success', 'Cuenta eliminada', 'La cuenta fue eliminada definitivamente y la acción quedó registrada.');
        users_redirect();
    } else {
        throw new InvalidArgumentException('La acción solicitada no es válida.');
    }
} catch (Throwable $exception) {
    flash_set('error', 'No fue posible completar la acción', user_safe_error_message($exception));
}

users_redirect('view.php?id=' . $userId);
