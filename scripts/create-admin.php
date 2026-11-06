<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/config/database.php';

try {
    $username = $argv[1] ?? 'zentra_admin';
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D', $username)) {
        throw new RuntimeException('El usuario debe tener entre 3 y 100 caracteres: letras, numeros, punto, guion o guion bajo.');
    }
    $pdo = databaseConnection();
    $check = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
    $check->execute([$username]);
    if ($check->fetch()) {
        echo "El usuario ya existe; se conserva su clave, rol y estado.\n";
        exit;
    }
    $password = getenv('ZENTRA_ADMIN_PASSWORD');
    if ($password === false || $password === '') {
        $password = bin2hex(random_bytes(16));
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new RuntimeException('Usa una clave de entre 12 y 72 bytes.');
    }
    $stmt = $pdo->prepare('INSERT INTO usuarios (username, password, rol, estado) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin', 1]);
    echo "Cuenta inicial NUEVA (no historica).\nUsuario: $username\nContrasena: $password\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear la cuenta inicial: " . $e->getMessage() . "\n");
    exit(1);
}
