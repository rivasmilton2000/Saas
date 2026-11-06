<?php
// Recupera solo los campos usados por master. No ejecuta SQL de PostgreSQL.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/config/database.php';

try {
    $path = $argv[1] ?? '';
    $expectedHash = 'e4c7e92a0cdb7462af137ac18d96564de98625cb8c5f8ff7b531c1a96566d9b6';
    if (!is_file($path) || hash_file('sha256', $path) !== $expectedHash) {
        throw new RuntimeException('Se requiere el respaldo Zentra verificado del 13/07/2026; consulta RECUPERACION.md.');
    }
    $handle = fopen($path, 'rb');
    $columns = null;
    $rows = [];
    while (($line = fgets($handle)) !== false) {
        $line = rtrim($line, "\r\n");
        if (preg_match('/^COPY public\.usuarios \(([^)]+)\) FROM stdin;$/', $line, $match)) {
            $columns = explode(', ', $match[1]);
            continue;
        }
        if ($columns === null) {
            continue;
        }
        if ($line === '\\.') {
            break;
        }
        $values = explode("\t", $line);
        if (count($values) !== count($columns)) {
            throw new RuntimeException('Fila de usuarios incompatible. No se importo nada.');
        }
        foreach ($values as &$value) {
            $value = $value === '\\N' ? null : preg_replace_callback('/\\\\([bfnrtv\\\\])/', static function ($m) {
                return ['b' => "\x08", 'f' => "\f", 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", '\\' => '\\'][$m[1]];
            }, $value);
        }
        unset($value);
        $record = array_combine($columns, $values);
        $row = [];
        foreach (['id', 'username', 'password', 'rol', 'estado'] as $key) {
            if (!isset($record[$key])) {
                throw new RuntimeException('Falta un campo requerido en el respaldo.');
            }
            $row[$key] = $record[$key];
        }
        if (!ctype_digit($row['id']) || (int)$row['id'] < 1 || (int)$row['id'] > 4294967295
            || mb_strlen($row['username']) > 100 || $row['username'] === ''
            || !in_array($row['rol'], ['admin', 'user'], true)
            || !in_array($row['estado'], ['t', 'f'], true)
            || password_get_info($row['password'])['algoName'] === 'unknown') {
            throw new RuntimeException('Usuario incompatible con el esquema o password_verify de PHP.');
        }
        $row['id'] = (int)$row['id'];
        $row['estado'] = $row['estado'] === 't' ? 1 : 0;
        $rows[] = $row;
    }
    fclose($handle);
    if (count($rows) !== 2) {
        throw new RuntimeException('El respaldo verificado debe contener exactamente dos usuarios.');
    }

    $pdo = databaseConnection();
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id, username, password, rol, estado FROM usuarios WHERE id = ? OR username = ?');
    $insert = $pdo->prepare('INSERT INTO usuarios (id, username, password, rol, estado) VALUES (?, ?, ?, ?, ?)');
    $inserted = 0;
    foreach ($rows as $row) {
        $find->execute([$row['id'], $row['username']]);
        $existing = $find->fetchAll();
        if ($existing) {
            if (count($existing) !== 1 || $existing[0] != $row) {
                throw new RuntimeException('Conflicto con un usuario existente. Se cancelo toda la recuperacion sin sobrescribir datos.');
            }
            continue;
        }
        $insert->execute(array_values($row));
        $inserted++;
    }
    $pdo->commit();
    echo "Usuarios historicos recuperados: $inserted; ya identicos: " . (count($rows) - $inserted) . ".\n";
    echo "Conservados IDs, nombres, hashes, roles y estados. Otros campos y tablas permanecen en el respaldo original.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Recuperacion cancelada: " . $e->getMessage() . "\n");
    exit(1);
}
