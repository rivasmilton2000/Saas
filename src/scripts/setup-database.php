<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/config/database.php';

try {
    $pdo = databaseConnection(false);
    // El SQL contiene solo sentencias simples, sin procedimientos ni delimitadores.
    $sql = file_get_contents(__DIR__ . '/../zentra.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
    $pdo->query('SELECT id, username, password, rol, estado FROM usuarios LIMIT 0');
    require_once __DIR__ . '/../src/config/db.php';
    require_once __DIR__ . '/../src/models/UsuarioModel.php';
    UsuarioModel::ensureSchema($pdo);
    echo "Base zentra disponible; registros existentes conservados.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo preparar zentra: " . $e->getMessage() . "\n");
    exit(1);
}
