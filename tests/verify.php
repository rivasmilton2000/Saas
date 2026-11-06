<?php
// Pruebas de integracion contra XAMPP. Crea y elimina solo sus propios usuarios.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/config/database.php';

function verify(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: $message\n";
}

function request(string $path, ?array $post = null): array {
    global $baseUrl, $cookieFile;
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($curl);
    if ($raw === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    $headers = substr($raw, 0, $size);
    $body = substr($raw, $size);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $match);
    if (stripos($headers, 'Content-Type: text/html') !== false
        && preg_match('/(?:<b>(?:Fatal error|Warning|Notice)<\/b>|SQLSTATE\[)/i', $body)) {
        throw new RuntimeException('La respuesta contiene un error PHP o SQL: ' . $path);
    }
    return ['status' => $status, 'location' => trim($match[1] ?? ''), 'body' => $body, 'headers' => $headers];
}

$baseUrl = rtrim($argv[1] ?? 'http://localhost/saas/Saas', '/');
$basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '';
$login = '/src/pages/samples/login.php';
$cookieFile = tempnam(sys_get_temp_dir(), 'zentra_test_');
$ids = [];
$exitCode = 0;
try {
    $pdo = databaseConnection();
    verify($pdo->query('SELECT DATABASE()')->fetchColumn() === 'zentra', 'Conexion a zentra');
    verify($pdo->query('SELECT @@character_set_connection')->fetchColumn() === 'utf8mb4', 'Conexion utf8mb4');
    $initialCount = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $username = 'test_' . bin2hex(random_bytes(8));
    $password = '  Test-' . bin2hex(random_bytes(8)) . '  ';
    $insert = $pdo->prepare('INSERT INTO usuarios (username, password) VALUES (?, ?)');
    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int)$pdo->lastInsertId();
    $ids[] = $id;
    $find = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $find->execute([$id]);
    $user = $find->fetch();
    verify($user['rol'] === 'user' && (int)$user['estado'] === 1, 'Alta y consulta con valores predeterminados');
    $update = $pdo->prepare('UPDATE usuarios SET username = ?, rol = ? WHERE id = ?');
    $update->execute([$username . '_ñ😀', 'admin', $id]);
    $find->execute([$id]);
    verify($find->fetch()['username'] === $username . '_ñ😀', 'Edicion y lectura Unicode de cuatro bytes');
    $update->execute([$username, 'admin', $id]);
    try {
        $insert->execute([$username, password_hash('unused', PASSWORD_DEFAULT)]);
        throw new RuntimeException('Se permitio un username duplicado.');
    } catch (PDOException $e) {
        verify($e->getCode() === '23000', 'Indice unico rechaza usuarios duplicados');
    }
    foreach (['rol' => "'invalid'", 'estado' => '2'] as $column => $value) {
        try {
            $pdo->exec("UPDATE usuarios SET $column = $value WHERE id = $id");
            throw new RuntimeException('No se aplico la restriccion de ' . $column);
        } catch (PDOException $e) {
            verify($e->getCode() === '23000', 'Restriccion valida ' . $column);
        }
    }

    verify(request('/')['status'] === 302, 'Entrada del proyecto redirige a PHP');
    foreach (['/src/index.php', '/src/pages/samples/dashboard.php'] as $page) {
        $response = request($page);
        verify($response['status'] === 302 && $response['location'] === $basePath . $login, 'Proteccion de invitado: ' . $page);
    }
    verify(request($login)['status'] === 200, 'Formulario de acceso disponible');
    foreach ([
        ['username' => '', 'password' => ''],
        ['username' => $username, 'password' => 'incorrecta'],
        ['username' => 'inexistente_' . $username, 'password' => 'incorrecta'],
        ['username' => "' OR 1=1 --", 'password' => 'incorrecta'],
        ['username' => ['invalid'], 'password' => ['invalid']],
    ] as $index => $post) {
        $response = request($login, $post);
        verify($response['status'] === 302 && str_starts_with($response['location'], $basePath . $login . '?error='), 'Rechazo de acceso invalido ' . ($index + 1));
        verify(request('/src/index.php')['location'] === $basePath . $login, 'El rechazo no inicia sesion ' . ($index + 1));
    }
    $state = $pdo->prepare('UPDATE usuarios SET estado = ? WHERE id = ?');
    $state->execute([0, $id]);
    $response = request($login, ['username' => $username, 'password' => $password]);
    verify(str_contains(urldecode($response['location']), 'Usuario inactivo'), 'Cuenta inactiva rechazada');
    $state->execute([1, $id]);
    $cookieBefore = file_get_contents($cookieFile);
    $response = request($login, ['username' => $username, 'password' => $password]);
    verify($response['status'] === 302 && $response['location'] === $basePath . '/src/index.php', 'Acceso valido con password_verify y espacios en la clave');
    verify(file_get_contents($cookieFile) !== $cookieBefore, 'Sesion regenerada al iniciar sesion');
    $response = request('/src/index.php');
    verify($response['status'] === 200 && str_contains($response['body'], 'Welcome ' . $username), 'Panel principal muestra el usuario autenticado');
    $renderedPages = ['/src/index.php' => $response['body']];
    $response = request('/src/pages/samples/dashboard.php');
    $renderedPages['/src/pages/samples/dashboard.php'] = $response['body'];
    verify($response['status'] === 200 && str_contains($response['body'], '<strong>admin</strong>'), 'Panel secundario muestra el rol');
    verify(str_contains($response['body'], 'href="' . $basePath . '/src/pages/forms/basic_elements.php"'), 'Menu del panel usa rutas reales');
    verify(request($login)['location'] === $basePath . '/src/index.php', 'Un usuario autenticado no vuelve al login');

    $assets = [];
    foreach ($renderedPages as $page => $html) {
        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script[@src]/@src | //link[@href]/@href | //img[@src]/@src') as $attribute) {
            $reference = parse_url($attribute->nodeValue, PHP_URL_PATH);
            if (!$reference || preg_match('#^(?:https?:)?//#', $attribute->nodeValue)) {
                continue;
            }
            $segments = [];
            foreach (explode('/', dirname($page) . '/' . $reference) as $segment) {
                if ($segment === '..') {
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }
            $assets['/' . implode('/', $segments)] = true;
        }
    }
    foreach (array_keys($assets) as $asset) {
        if (request($asset)['status'] !== 200) {
            throw new RuntimeException('Recurso de panel ausente: ' . $asset);
        }
    }
    verify(true, 'Todos los recursos HTML de ambos paneles disponibles (' . count($assets) . ')');

    foreach (['/src/assets/css/style.css', '/src/assets/vendors/js/vendor.bundle.base.js', '/src/assets/images/logo.svg', '/src/pages/forms/basic_elements.php', '/src/pages/tables/basic-table.php'] as $path) {
        verify(request($path)['status'] === 200, 'Recurso disponible: ' . $path);
    }
    foreach (['/zentra.sql', '/src/config/db.local.php', '/src/config/db.local.php.example', '/.git/config', '/scripts/create-admin.php', '/tests/verify.php'] as $path) {
        verify(request($path)['status'] === 403, 'Archivo privado bloqueado: ' . $path);
    }
    $response = request('/src/pages/samples/logout.php');
    verify($response['status'] === 302 && $response['location'] === $basePath . $login, 'Cerrar sesion redirige correctamente');
    verify(request('/src/index.php')['location'] === $basePath . $login, 'La sesion deja de permitir acceso');
    $registration = request('/src/pages/samples/register.php');
    verify($registration['status'] === 200 && !str_contains($registration['body'], '<form'), 'Registro informa que requiere una cuenta del administrador');

    // Opcional: comprueba la cuenta de arranque sin incluir su clave en el codigo.
    $credentialsPath = getenv('ZENTRA_TEST_CREDENTIALS');
    if ($credentialsPath) {
        $credentials = file_get_contents($credentialsPath);
        if (!preg_match('/^Usuario: (.+)$/m', $credentials, $userMatch) || !preg_match('/^Contrasena: (.+)$/m', $credentials, $passwordMatch)) {
            throw new RuntimeException('Formato de credenciales iniciales no reconocido.');
        }
        $response = request($login, ['username' => trim($userMatch[1]), 'password' => trim($passwordMatch[1])]);
        verify($response['location'] === $basePath . '/src/index.php', 'Cuenta inicial real inicia sesion');
        request('/src/pages/samples/logout.php');
    }
} catch (Throwable $e) {
    fwrite(STDERR, "FALLO: " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (isset($pdo)) {
        $delete = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        foreach ($ids as $testId) {
            $delete->execute([$testId]);
        }
        if (isset($initialCount)) {
            $remaining = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
            if ($remaining !== $initialCount) {
                fwrite(STDERR, "FALLO: el conteo final difiere del inicial.\n");
                $exitCode = 1;
            } else {
                echo "OK: Baja de los datos de prueba; registros originales conservados.\n";
            }
        }
    }
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}
exit($exitCode);
