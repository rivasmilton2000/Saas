<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../services/BitacoraService.php';

requireLogin();

function profileRedirectTarget(string $fallback = '/Saas/src/app/index.php'): string {
    $raw = trim((string) ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? $fallback));
    if ($raw === '') {
        return $fallback;
    }

    $parsed = parse_url($raw);
    $path = (string) ($parsed['path'] ?? '');
    $query = isset($parsed['query']) ? '?' . (string) $parsed['query'] : '';

    if ($path === '' || strpos($path, '/Saas/src/') !== 0) {
        return $fallback;
    }

    return $path . $query;
}

function setProfileFlashAndRedirect(string $message, string $type, array $meta = []): void {
    setFlash('profile', $message, $type, $meta);
    header('Location: ' . profileRedirectTarget());
    exit;
}

function deleteProfilePhotoIfLocal(?string $path): void {
    $path = trim((string) $path);
    if ($path === '' || strpos($path, '/Saas/src/uploads/profiles/') !== 0) {
        return;
    }

    $relative = str_replace('/Saas/src/', '', $path);
    $fullPath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $uploadsRoot = realpath(__DIR__ . '/../uploads/profiles');

    if ($fullPath === false || $uploadsRoot === false) {
        return;
    }

    if (strpos($fullPath, $uploadsRoot) !== 0 || !is_file($fullPath)) {
        return;
    }

    @unlink($fullPath);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . profileRedirectTarget());
    exit;
}

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$usuario = UsuarioModel::getById($pdo, $idUsuario, false);

if (!$usuario) {
    session_destroy();
    header('Location: /Saas/src/pages/samples/login.php');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$removePhoto = (int) ($_POST['remove_photo'] ?? 0) === 1;
$oldInput = [
    'username' => $username,
    'nombre_completo' => $nombreCompleto,
];

if ($username === '') {
    setProfileFlashAndRedirect('Debes escribir un nombre de usuario.', 'danger', [
        'open_modal' => true,
        'old' => $oldInput,
    ]);
}

if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
    setProfileFlashAndRedirect('Usa de 3 a 50 caracteres: letras, numeros, punto, guion o guion bajo.', 'danger', [
        'open_modal' => true,
        'old' => $oldInput,
    ]);
}

$usuarioDuplicado = UsuarioModel::getByUsername($pdo, $username);
if ($usuarioDuplicado && (int) ($usuarioDuplicado['id'] ?? 0) !== $idUsuario) {
    setProfileFlashAndRedirect('Ese nombre de usuario ya existe.', 'danger', [
        'open_modal' => true,
        'old' => $oldInput,
    ]);
}

$wantsPasswordChange = ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '');
if ($wantsPasswordChange) {
    if ($currentPassword === '' || !password_verify($currentPassword, (string) ($usuario['password'] ?? ''))) {
        setProfileFlashAndRedirect('Debes escribir tu clave actual correctamente para cambiarla.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    if ($newPassword === '') {
        setProfileFlashAndRedirect('Debes escribir una nueva clave.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    if (strlen($newPassword) < 6) {
        setProfileFlashAndRedirect('La nueva clave debe tener al menos 6 caracteres.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    if ($newPassword !== $confirmPassword) {
        setProfileFlashAndRedirect('La confirmacion de clave no coincide.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }
}

$nuevoPathFoto = null;
$fotoActual = (string) ($usuario['foto_perfil'] ?? '');
$file = $_FILES['profile_photo'] ?? null;

if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        setProfileFlashAndRedirect('No se pudo subir la foto de perfil.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        setProfileFlashAndRedirect('La foto de perfil no debe superar los 2 MB.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file((string) ($file['tmp_name'] ?? ''));
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        setProfileFlashAndRedirect('La foto debe ser JPG, PNG, WEBP o GIF.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    $uploadDir = __DIR__ . '/../uploads/profiles';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        setProfileFlashAndRedirect('No se pudo preparar la carpeta de fotos de perfil.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    $fileName = 'user_' . $idUsuario . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        setProfileFlashAndRedirect('No se pudo guardar la foto de perfil.', 'danger', [
            'open_modal' => true,
            'old' => $oldInput,
        ]);
    }

    $nuevoPathFoto = '/Saas/src/uploads/profiles/' . $fileName;
}

$payload = [
    'username' => $username,
    'nombre_completo' => $nombreCompleto,
];

if ($wantsPasswordChange) {
    $payload['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
}

if ($nuevoPathFoto !== null) {
    $payload['foto_perfil'] = $nuevoPathFoto;
} elseif ($removePhoto) {
    $payload['foto_perfil'] = null;
}

$actualizado = UsuarioModel::updateOwnProfile($pdo, $idUsuario, $payload);
if (!$actualizado) {
    if ($nuevoPathFoto !== null) {
        deleteProfilePhotoIfLocal($nuevoPathFoto);
    }

    setProfileFlashAndRedirect('No se pudo guardar tu perfil.', 'danger', [
        'open_modal' => true,
        'old' => $oldInput,
    ]);
}

if ($nuevoPathFoto !== null && $fotoActual !== '') {
    deleteProfilePhotoIfLocal($fotoActual);
}

if ($removePhoto && $fotoActual !== '') {
    deleteProfilePhotoIfLocal($fotoActual);
}

$_SESSION['username'] = $username;
$_SESSION['nombre_completo'] = $nombreCompleto !== '' ? $nombreCompleto : null;

$detalle = [];
if ($nombreCompleto !== trim((string) ($usuario['nombre_completo'] ?? ''))) {
    $detalle[] = 'nombre actualizado';
}
if ($username !== (string) ($usuario['username'] ?? '')) {
    $detalle[] = 'usuario actualizado';
}
if ($wantsPasswordChange) {
    $detalle[] = 'clave actualizada';
}
if ($nuevoPathFoto !== null) {
    $detalle[] = 'foto actualizada';
} elseif ($removePhoto) {
    $detalle[] = 'foto removida';
}

BitacoraService::registrar(
    $pdo,
    $idUsuario,
    'perfil',
    'actualizar_perfil',
    'Actualizo sus datos de perfil.',
    [
        'username' => $username,
        'rol'      => (string) ($usuario['rol'] ?? 'user'),
        'contexto' => [
            'detalle' => implode(', ', $detalle),
        ],
    ]
);

setProfileFlashAndRedirect('Perfil actualizado correctamente.', 'success');
