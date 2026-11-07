<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/models/UsuarioModel.php';

$base = rtrim($argv[1] ?? 'http://localhost/saas/Saas', '/');
$prefix = 'ztest_' . bin2hex(random_bytes(5));
$password = '  Integration-' . bin2hex(random_bytes(10)) . '  ';
$cookies = [];
$userIds = [];
$failures = [];
$checks = 0;
$assetUrls = [];
$testPlan = null;
$pendingId = null;
$backupPaths = [];
$original = $pdo->query('SELECT id, username, password, rol, estado FROM usuarios ORDER BY id')->fetchAll();

function check(bool $ok, string $message): void {
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'OK: ' : 'FALLO: ') . $message . "\n";
    if (!$ok) { $failures[] = $message; }
}

function http(string $path, string $role = 'guest', ?array $data = null, bool $json = false): array {
    global $base, $cookies;
    if (!isset($cookies[$role])) { $cookies[$role] = tempnam(sys_get_temp_dir(), 'zentra_http_'); }
    $curl = curl_init(str_starts_with($path, $base . '/') ? $path : $base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $cookies[$role],
        CURLOPT_COOKIEJAR => $cookies[$role], CURLOPT_TIMEOUT => 30]);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($data) : http_build_query($data));
        if ($json) { curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); }
    }
    $raw = curl_exec($curl);
    if ($raw === false) { throw new RuntimeException(curl_error($curl)); }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $match);
    return ['status' => $status, 'headers' => $headers, 'body' => $body,
        'location' => trim($match[1] ?? ''), 'json' => json_decode($body, true)];
}

function page(string $path, string $role = 'guest'): array {
    global $base, $assetUrls;
    $response = http($path, $role);
    $clean = !preg_match('/<b>(?:Fatal error|Warning|Notice|Parse error)<\/b>|SQLSTATE\[|Unknown column|SQL syntax/i', $response['body']);
    check($response['status'] === 200 && $clean, "$role GET $path");
    if (!$clean || $response['status'] >= 500) {
        echo substr(strip_tags($response['body']), 0, 900) . "\n";
    }
    if ($clean && $response['status'] === 200) {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $origin = parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST);
        if (parse_url($base, PHP_URL_PORT)) { $origin .= ':' . parse_url($base, PHP_URL_PORT); }
        foreach ($xpath->query('//script[@src]/@src | //link[@href]/@href | //img[@src]/@src') as $attribute) {
            $reference = $attribute->nodeValue;
            if (preg_match('#^(?:https?:)?//#', $reference) && !str_starts_with($reference, $base . '/')) { continue; }
            if (preg_match('#^(?:data|mailto|javascript):#', $reference)) { continue; }
            $assetPath = parse_url($reference, PHP_URL_PATH);
            if (!$assetPath) { continue; }
            if ($assetPath[0] !== '/') { $assetPath = dirname(parse_url($base . $path, PHP_URL_PATH)) . '/' . $assetPath; }
            $segments = [];
            foreach (explode('/', $assetPath) as $part) {
                if ($part === '..') { array_pop($segments); }
                elseif ($part !== '' && $part !== '.') { $segments[] = $part; }
            }
            $url = $origin . '/' . implode('/', $segments);
            $assetUrls[$url] = $role;
        }
    }
    return $response;
}

function jsonOk(array $response, string $message): array {
    $ok = $response['status'] < 400 && ($response['json']['success'] ?? false);
    check((bool)$ok, $message);
    if (!$ok) { echo substr($response['body'], 0, 1000) . "\n"; }
    return $response['json']['data'] ?? [];
}

try {
    UsuarioModel::ensureSchema($pdo);
    check($pdo->query('SELECT DATABASE()')->fetchColumn() === 'zentra', 'Conexion unica a zentra');
    $free = (int)$pdo->query("SELECT id_plan FROM planes WHERE slug = 'free'")->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO usuarios (username, password, rol, estado, id_plan, email, pais, suscripcion_estado) VALUES (?, ?, ?, 1, ?, ?, ?, ?)');
    foreach (['admin', 'user', 'other'] as $role) {
        $insert->execute([$prefix . '_' . $role, password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'user', $free, $prefix . '_' . $role . '@example.invalid', 'El Salvador', $role === 'admin' ? 'admin' : 'free']);
        $userIds[$role] = (int)$pdo->lastInsertId();
    }
    page('/public/index.php');
    page('/public/pricing.php');
    page('/public/contact.php');
    page('/src/pages/samples/login.php');
    page('/src/pages/samples/register.php');
    check(http('/src/admin/index.php')['status'] === 302, 'Invitado no entra al administrador');
    check(http('/src/app/index.php')['status'] === 302, 'Invitado no entra al panel usuario');
    foreach (['admin', 'user', 'other'] as $role) {
        $r = http('/src/pages/samples/login.php', $role, ['username' => $prefix . '_' . $role, 'password' => $password]);
        $destination = $role === 'admin' ? '/src/admin/index.php' : '/src/app/index.php';
        check($r['status'] === 302 && str_ends_with($r['location'], $destination), 'Login con rol ' . $role);
    }
    foreach (['dashboard', 'users', 'companies', 'plans', 'subscriptions', 'payments', 'dte', 'dte.documents', 'dte.books', 'dte.quotas', 'audit', 'backups', 'settings'] as $route) {
        page('/src/admin/index.php?page=' . $route, 'admin');
    }
    foreach (['dashboard', 'dte.purchases', 'dte.consumer-sales', 'dte.taxpayer-sales', 'dte.vat-withholdings'] as $route) {
        page('/src/app/index.php?page=' . $route, 'user');
    }
    check(http('/src/admin/index.php', 'user')['status'] === 302, 'Usuario comun no entra al administrador');
    check(http('/src/app/index.php', 'admin')['status'] === 302, 'Administrador usa su panel separado');

    foreach ([['username' => '', 'password' => ''], ['username' => $prefix . '_user', 'password' => 'wrong'], ['username' => "' OR 1=1 --", 'password' => 'x'], ['username' => ['x'], 'password' => ['x']]] as $invalid) {
        $r = http('/src/pages/samples/login.php', 'invalid', $invalid);
        check($r['status'] === 302 && str_ends_with($r['location'], '/src/pages/samples/login.php'), 'Credenciales invalidas rechazadas');
        check(http('/src/app/index.php', 'invalid')['status'] === 302, 'No se crea sesion con credenciales invalidas');
    }
    $pdo->prepare('UPDATE usuarios SET estado = 0 WHERE id = ?')->execute([$userIds['other']]);
    check(http('/src/api/facturas/listar.php?id_libro=1', 'other')['status'] === 401, 'Cuenta desactivada tampoco usa la API');
    check(http('/src/app/index.php', 'other')['status'] === 302, 'Una sesion de cuenta desactivada pierde acceso');
    $pdo->prepare('UPDATE usuarios SET estado = 1 WHERE id = ?')->execute([$userIds['other']]);
    http('/src/pages/samples/login.php', 'other', ['username' => $prefix . '_other', 'password' => $password]);

    // Administracion: alta, edicion y desactivacion mediante los formularios reales.
    $managed = $prefix . '_managed';
    http('/src/admin/index.php?page=users', 'admin', ['action' => 'create_user', 'username' => $managed,
        'password' => $password, 'confirm_password' => $password, 'rol' => 'user', 'pais' => 'El Salvador', 'id_plan' => $free]);
    $find = $pdo->prepare('SELECT * FROM usuarios WHERE username = ?');
    $find->execute([$managed]);
    $created = $find->fetch();
    check((bool)$created && password_verify($password, $created['password']), 'Administrador crea cuenta con hash PHP');
    if ($created) {
        $managedId = (int)$created['id'];
        $userIds['managed'] = $managedId;
        http('/src/admin/index.php?page=users', 'admin', ['action' => 'edit_user', 'user_id' => $managedId,
            'username' => $managed . '_edit', 'rol' => 'user', 'pais' => 'Guatemala', 'id_plan' => $free]);
        $find->execute([$managed . '_edit']);
        check(($find->fetch()['pais'] ?? '') === 'Guatemala', 'Administrador edita usuario');
        http('/src/admin/index.php?page=users', 'admin', ['action' => 'delete_user', 'user_id' => $managedId]);
        check((int)$pdo->query('SELECT estado FROM usuarios WHERE id=' . $managedId)->fetchColumn() === 0, 'Administrador desactiva usuario');
    }
    // Registro publico gratuito usa el mecanismo de la rama SaaS, sin servicios de pago.
    $signupName = $prefix . '_signup';
    $r = http('/src/pages/samples/register.php', 'signup', ['username' => $signupName,
        'nombre_completo' => 'Prueba de integracion', 'email' => $signupName . '@example.invalid',
        'password' => $password, 'confirm_password' => $password, 'pais' => 'El Salvador', 'id_plan' => $free,
        'terms' => '1', 'rol' => 'admin']);
    $find->execute([$signupName]);
    $signedUp = $find->fetch();
    check((bool)$signedUp && $signedUp['rol'] === 'user' && (int)$signedUp['id_plan'] === $free,
        'Registro Free crea usuario y no permite elevar rol a admin');
    if ($signedUp) { $userIds['signup'] = (int)$signedUp['id']; }

    // Editar un plan de prueba sin tocar precios ni caracteristicas del catalogo real.
    $pdo->prepare('INSERT INTO planes (slug, nombre, precio) VALUES (?, ?, ?)')->execute([$prefix, 'Plan de prueba', 5]);
    $testPlan = (int)$pdo->lastInsertId();
    http('/src/admin/index.php?page=plans', 'admin', ['action' => 'update_plan', 'id_plan' => $testPlan,
        'nombre' => 'Plan editado', 'precio' => '12.50', 'activo' => '1', 'caracteristicas_text' => "Incluido\n- Excluido"]);
    check($pdo->query('SELECT nombre FROM planes WHERE id_plan=' . $testPlan)->fetchColumn() === 'Plan editado', 'Administrador edita planes');
    check((int)$pdo->query('SELECT COUNT(*) FROM planes_caracteristicas WHERE id_plan=' . $testPlan)->fetchColumn() === 2, 'Se guardan caracteristicas del plan');
    require_once __DIR__ . '/../src/models/SubscriptionModel.php';
    require_once __DIR__ . '/../src/models/PaymentModel.php';
    require_once __DIR__ . '/../src/models/PendingRegistrationModel.php';
    $subscription = ['user_id' => $userIds['user'], 'plan_id' => $testPlan, 'stripe_subscription_id' => $prefix . '_sub',
        'status' => 'active', 'amount' => '12.50', 'currency' => 'USD', 'billing_interval' => 'month'];
    $subFirst = SubscriptionModel::upsert($pdo, $subscription);
    $subscription['status'] = 'canceled';
    $subSecond = SubscriptionModel::upsert($pdo, $subscription);
    check($subFirst['id'] === $subSecond['id'] && $subFirst['id'] > 0, 'Suscripciones: alta y actualizacion idempotente');
    $payment = ['user_id' => $userIds['user'], 'plan_id' => $testPlan, 'subscription_id' => $subFirst['id'],
        'stripe_invoice_id' => $prefix . '_invoice', 'status' => 'pending', 'amount' => '12.50', 'currency' => 'USD'];
    $payFirst = PaymentModel::upsert($pdo, $payment);
    $payment['status'] = 'paid';
    $paySecond = PaymentModel::upsert($pdo, $payment);
    check($payFirst['id'] === $paySecond['id'] && $payFirst['id'] > 0, 'Pagos: alta y actualizacion idempotente');
    $pendingId = PendingRegistrationModel::create($pdo, ['nombre_completo' => 'Prueba', 'email' => $prefix . '@example.invalid',
        'username' => $prefix . '_pending', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id_plan' => $testPlan,
        'plan_slug_snapshot' => $prefix, 'plan_nombre_snapshot' => 'Plan editado', 'plan_precio_snapshot' => '12.50']);
    check($pendingId > 0 && PendingRegistrationModel::getById($pdo, $pendingId) !== null, 'Registro de pago pendiente persiste en MariaDB');
    page('/src/admin/index.php?page=subscriptions', 'admin');
    page('/src/admin/index.php?page=payments', 'admin');

    // API real de empresas y aislamiento de propietarios.
    $company = jsonOk(http('/src/api/empresas.php', 'user', ['nombre' => 'Prueba ñ 😀', 'tipo_legal' => 'natural']), 'API crea empresa Unicode');
    $companyId = (int)($company['id'] ?? 0);
    if ($companyId <= 0) { throw new RuntimeException('No se pueden probar libros sin una empresa valida.'); }
    $companies = jsonOk(http('/src/api/empresas.php', 'user'), 'API consulta empresas');
    check($companyId > 0 && in_array($companyId, array_map('intval', array_column($companies, 'id')), true), 'La empresa pertenece al usuario autenticado');
    check(http('/src/api/libros.php?id_empresa=' . $companyId, 'other')['status'] === 403, 'Otro usuario no puede consultar libros ajenos');
    check(http('/src/api/empresas/eliminar.php', 'other', ['id_empresa' => $companyId])['status'] === 403, 'Otro usuario no puede eliminar empresas ajenas');

    $books = [];
    foreach (['compras' => '03', 'ventas_consumidor' => '01', 'ventas_contribuyente' => '03', 'retencion_iva' => '14'] as $type => $dteType) {
        $book = jsonOk(http('/src/api/libros.php', 'user', ['id_empresa' => $companyId, 'tipo' => $type, 'mes' => 11, 'anio' => 2026]), 'API crea libro ' . $type);
        $bookId = (int)($book['id'] ?? 0);
        $books[$type] = $bookId;
        check($bookId > 0 && ($book['nombre'] ?? '') === $type . '-2026-11', 'Nombre de libro calculado por MariaDB: ' . $type);
        $again = http('/src/api/libros.php', 'user', ['id_empresa' => $companyId, 'tipo' => $type, 'mes' => 11, 'anio' => 2026]);
        check(($again['json']['exists'] ?? false) === true && (int)($again['json']['data']['id'] ?? 0) === $bookId, 'Crear el mismo periodo no duplica el libro');
        check(http('/src/api/facturas/listar.php?id_libro=' . $bookId, 'other')['status'] === 403, 'No se leen documentos de otro usuario');
        $document = [
            'identificacion' => ['tipoDte' => $dteType, 'codigoGeneracion' => strtoupper(bin2hex(random_bytes(16))),
                'numeroControl' => 'DTE-' . $dteType . '-M001P001-000000000000001', 'fecEmi' => '2026-11-05'],
            'emisor' => ['nombre' => 'Proveedor de prueba', 'nit' => '0614-010101-001-1', 'nrc' => '123456-7'],
            'receptor' => ['nombre' => 'Cliente de prueba', 'nit' => '0614-010101-002-2', 'nrc' => '765432-1'],
            'cuerpoDocumento' => [['numItem' => 1, 'descripcion' => 'Prueba', 'cantidad' => 1, 'ventaGravada' => 100,
                'ventaExenta' => 0, 'tipoDte' => '03', 'numDocumento' => 'DTE-03-M001P001-000000000000001',
                'fechaEmision' => '2026-11-05', 'montoSujetoGrav' => 100, 'ivaRetenido' => 1]],
            'resumen' => ['totalGravada' => 100, 'totalExenta' => 0, 'subTotal' => 100, 'montoTotalOperacion' => 113,
                'totalPagar' => 113, 'totalIva' => 13, 'totalIVAretenido' => 1, 'totalSujetoRetencion' => 100,
                'tributos' => [['codigo' => '20', 'valor' => 13]]], 'selloRecibido' => 'SELLO-PRUEBA',
        ];
        $import = http('/src/api/facturas/importar.php', 'user', ['id_libro' => $bookId, 'facturas' => [$document]], true);
        jsonOk($import, 'Importacion DTE ' . $type);
        $count = $pdo->prepare('SELECT COUNT(*) FROM facturas WHERE id_libro = ?');
        $count->execute([$bookId]);
        check((int)$count->fetchColumn() === 1, 'Documento guardado: ' . $type);
        if ((int)($import['json']['data']['importadas'] ?? 0) !== 1) { echo substr($import['body'], 0, 1100) . "\n"; }
        jsonOk(http('/src/api/facturas/listar.php?id_libro=' . $bookId, 'user'), 'Consulta de documentos ' . $type);
        $export = http('/src/api/facturas/exportar.php?id_libro=' . $bookId . '&formato=excel', 'user');
        check($export['status'] === 200 && strlen($export['body']) > 100 && !str_contains($export['body'], 'SQLSTATE'), 'Exportacion Excel ' . $type);
        $route = ['compras' => 'dte.purchases', 'ventas_consumidor' => 'dte.consumer-sales', 'ventas_contribuyente' => 'dte.taxpayer-sales', 'retencion_iva' => 'dte.vat-withholdings'][$type];
        $open = http('/src/app/index.php?page=' . $route . '&open=' . $bookId, 'user');
        check($open['status'] === 302 && str_contains($open['location'], 'page=' . $route), 'Abrir libro conserva la ruta integrada: ' . $type);
        page('/src/app/index.php?page=' . $route, 'user');
    }
    // Centro de mando: los UPSERT se ejercitan dos veces.
    foreach (['Primera nota', 'Nota actualizada'] as $note) {
        $r = http('/src/app/index.php', 'user', ['action' => 'save_command_center_note', 'empresa_id' => $companyId, 'nota_mensual' => $note]);
        check($r['status'] === 302, 'Guardar nota de centro de mando');
    }
    $stmt = $pdo->prepare('SELECT nota FROM centro_mando_notas WHERE id_empresa = ?');
    $stmt->execute([$companyId]);
    check($stmt->fetchColumn() === 'Nota actualizada', 'UPSERT actualiza la nota sin duplicarla');
    require_once __DIR__ . '/../src/models/CentroMandoModel.php';
    foreach ([true, false] as $completed) {
        CentroMandoModel::setProgress($pdo, $companyId, $userIds['user'], 11, 2026, 'pago_confirmado', $completed);
        $stmt = $pdo->prepare('SELECT completado FROM centro_mando_avance WHERE id_empresa = ? AND item_key = ?');
        $stmt->execute([$companyId, 'pago_confirmado']);
        check((int)$stmt->fetchColumn() === ($completed ? 1 : 0), 'Centro de mando persiste booleano ' . (int)$completed);
    }
    CentroMandoModel::setWelcomeHidden($pdo, $userIds['user'], true);
    CentroMandoModel::setWelcomeHidden($pdo, $userIds['user'], false);
    check(!CentroMandoModel::isWelcomeHidden($pdo, $userIds['user']), 'Preferencia booleana se puede activar y desactivar');
    $profile = http('/src/actions/profile.php', 'user', ['username' => $prefix . '_user',
        'nombre_completo' => 'Nombre integrado', 'redirect_to' => $base . '/src/app/index.php']);
    check($profile['status'] === 302 && str_ends_with($profile['location'], '/src/app/index.php'), 'Perfil vuelve a la carpeta real de Zentra');
    check($pdo->query('SELECT nombre_completo FROM usuarios WHERE id=' . $userIds['user'])->fetchColumn() === 'Nombre integrado', 'Perfil actualiza datos del usuario');
    // La pantalla DTE extraida conserva su puente de usuarios en la misma base.
    page('/src/dte/index.php', 'user');
    check((int)$pdo->query('SELECT COUNT(*) FROM dte_user_bridge WHERE main_user_id=' . $userIds['user'])->fetchColumn() === 1, 'Puente DTE se crea en zentra');
    page('/src/dte/pages/compras.php', 'user');
    page('/src/dte/index.php', 'other');
    $pdo->prepare('UPDATE usuarios SET estado = 0 WHERE id = ?')->execute([$userIds['other']]);
    check(http('/src/dte/index.php', 'other')['status'] === 302, 'Sesion DTE respeta la desactivacion del usuario principal');

    // El formulario publico guarda el mensaje aun sin un SMTP configurado.
    http('/src/includes/contactService.php', 'guest', ['submit' => '1', 'name' => 'Prueba', 'email' => $prefix . '@example.invalid',
        'subject' => $prefix, 'message' => 'Consulta de prueba de integracion']);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mensajes_contacto WHERE asunto = ?');
    $stmt->execute([$prefix]);
    check((int)$stmt->fetchColumn() === 1, 'Contacto persiste sin requerir SMTP');

    require_once __DIR__ . '/../src/services/DatabaseBackupService.php';
    $beforeBackups = array_column(DatabaseBackupService::listBackups(500), 'path');
    http('/src/admin/index.php?page=backups', 'admin', ['action' => 'generate_manual_backup']);
    foreach (DatabaseBackupService::listBackups(500) as $backup) {
        if (!in_array($backup['path'], $beforeBackups, true)) { $backupPaths[] = $backup['path']; }
    }
    check(count($backupPaths) === 1 && filesize($backupPaths[0]) > 1000, 'Backup manual usa mysqldump');
    if ($backupPaths) {
        $backupText = file_get_contents($backupPaths[0]);
        check(str_contains($backupText, 'CREATE TABLE `usuarios`') && str_contains($backupText, 'zentra'), 'El backup generado contiene zentra');
    }
    // Revisar recursos del HTML realmente generado, incluidas las rutas movidas.
    $missingAssets = [];
    foreach ($assetUrls as $url => $role) {
        if (http($url, $role)['status'] !== 200) { $missingAssets[] = $url; }
    }
    check(!$missingAssets, 'Recursos estaticos disponibles: ' . count($assetUrls));
    foreach ($missingAssets as $missingAsset) { echo "AUSENTE: $missingAsset\n"; }
    jsonOk(http('/src/api/empresas/eliminar.php', 'user', ['id_empresa' => $companyId]), 'El propietario puede dar de baja su empresa');
    $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute(['user', $userIds['admin']]);
    check(http('/src/admin/index.php', 'admin')['status'] === 302, 'Los cambios de rol se aplican a sesiones existentes');

    foreach (['/zentra.sql', '/.env.example', '/.git/config', '/src/config/db.local.php', '/database/postgresql_seed.sql', '/src/admin/pages/users/index.php', '/src/admin/routes.php'] as $privatePath) {
        check(http($privatePath)['status'] === 403, 'Bloqueo HTTP: ' . $privatePath);
    }
    $r = http('/src/pages/samples/logout.php', 'user');
    check($r['status'] === 302 && http('/src/app/index.php', 'user')['status'] === 302, 'Cerrar sesion revoca acceso');
} catch (Throwable $e) {
    check(false, $e->getMessage());
} finally {
    if ($pendingId) { $pdo->prepare('DELETE FROM registros_pago_pendientes WHERE id = ?')->execute([$pendingId]); }
    $pdo->prepare('DELETE FROM mensajes_contacto WHERE asunto = ?')->execute([$prefix]);
    foreach ($userIds as $id) {
        $bridges = $pdo->prepare('SELECT dte_user_id FROM dte_user_bridge WHERE main_user_id = ?');
        $bridges->execute([$id]);
        foreach ($bridges->fetchAll(PDO::FETCH_COLUMN) as $dteId) {
            $pdo->prepare('DELETE FROM dte_empresas WHERE id_usuario = ?')->execute([$dteId]);
            $pdo->prepare('DELETE FROM dte_usuarios WHERE id = ?')->execute([$dteId]);
        }
        foreach (['empresas' => 'id_usuario', 'email_logs' => 'user_id', 'payments' => 'user_id', 'subscriptions' => 'user_id', 'registros_pago_pendientes' => 'created_user_id'] as $table => $column) {
            $pdo->prepare("DELETE FROM $table WHERE $column = ?")->execute([$id]);
        }
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    }
    if ($testPlan) { $pdo->prepare('DELETE FROM planes WHERE id_plan = ?')->execute([$testPlan]); }
    foreach ($backupPaths as $backupPath) { if (is_file($backupPath)) { unlink($backupPath); } }
    foreach ($cookies as $cookie) { if (is_file($cookie)) { unlink($cookie); } }
    $after = $pdo->query('SELECT id, username, password, rol, estado FROM usuarios ORDER BY id')->fetchAll();
    check($original === $after, 'Usuarios originales intactos y usuarios de prueba eliminados');
}
echo "Verificaciones: $checks; fallos: " . count($failures) . ".\n";
exit($failures ? 1 : 0);
