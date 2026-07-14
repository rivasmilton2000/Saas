<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/stripe.php';
require_once __DIR__ . '/../../../config/google.php';
require_once __DIR__ . '/../../../config/mail.php';
require_once __DIR__ . '/../../../services/DatabaseBackupService.php';

if (!function_exists('adminSettingsTableCount')) {
    function adminSettingsTableCount(PDO $pdo, string $table): ?int
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            return null;
        }

        try {
            $exists = $pdo->prepare('SELECT to_regclass(?)');
            $exists->execute(['public.' . $table]);

            if ($exists->fetchColumn() === null) {
                return null;
            }

            $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('adminSettingsStatusBadge')) {
    function adminSettingsStatusBadge(bool $ok, string $okText = 'Activo', string $badText = 'Pendiente'): string
    {
        $class = $ok ? 'badge-success' : 'badge-warning';
        $text = $ok ? $okText : $badText;

        return '<span class="badge ' . $class . '">' . htmlspecialchars($text) . '</span>';
    }
}

if (!function_exists('adminSettingsMask')) {
    function adminSettingsMask(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        if (strlen($value) <= 10) {
            return 'Configurado';
        }

        return substr($value, 0, 6) . '...' . substr($value, -4);
    }
}

$dbConfig = dbConfig();
$stripeConfig = stripeConfig();
$googleConfig = googleConfig();
$mailConfig = mailConfig();

$envPath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . '.env';
$appUrl = saasBaseUrl();
$appPath = saasBasePath();
$phpTimezone = date_default_timezone_get();
$dbVersion = '-';

try {
    $dbVersion = (string) $pdo->query('SHOW server_version')->fetchColumn();
} catch (Throwable $exception) {
    $dbVersion = '-';
}

$backupCount = 0;
$latestBackup = null;
$backupError = null;

try {
    $backups = DatabaseBackupService::listBackups(1);
    $latestBackup = $backups[0] ?? null;
    $backupCount = count(DatabaseBackupService::listBackups(500));
} catch (Throwable $exception) {
    $backupError = $exception->getMessage();
}

$counts = [
    'usuarios' => adminSettingsTableCount($pdo, 'usuarios'),
    'empresas' => adminSettingsTableCount($pdo, 'empresas'),
    'planes' => adminSettingsTableCount($pdo, 'planes'),
    'suscripciones' => adminSettingsTableCount($pdo, 'subscriptions'),
    'pagos' => adminSettingsTableCount($pdo, 'payments'),
    'libros' => adminSettingsTableCount($pdo, 'libros'),
    'facturas' => adminSettingsTableCount($pdo, 'facturas'),
    'bitacora' => adminSettingsTableCount($pdo, 'bitacora_movimientos'),
];

$serviceChecks = [
    [
        'label' => 'Base de datos',
        'status' => true,
        'detail' => (string) ($dbConfig['dbname'] ?? '-') . ' en ' . (string) ($dbConfig['host'] ?? '-') . ':' . (string) ($dbConfig['port'] ?? '-'),
    ],
    [
        'label' => 'Stripe',
        'status' => stripeIsConfigured(),
        'detail' => stripeIsConfigured()
            ? (stripeIsTestMode() ? 'Modo prueba' : 'Modo produccion')
            : 'Falta STRIPE_SECRET_KEY',
    ],
    [
        'label' => 'Webhook Stripe',
        'status' => trim((string) ($stripeConfig['webhook_secret'] ?? '')) !== '',
        'detail' => trim((string) ($stripeConfig['webhook_secret'] ?? '')) !== '' ? 'Webhook configurado' : 'Falta STRIPE_WEBHOOK_SECRET',
    ],
    [
        'label' => 'Google Auth',
        'status' => googleIsConfigured(),
        'detail' => googleIsConfigured() ? 'Cliente OAuth configurado' : 'Falta GOOGLE_CLIENT_ID',
    ],
    [
        'label' => 'Correo',
        'status' => mailIsConfigured(),
        'detail' => mailIsConfigured()
            ? (string) ($mailConfig['from'] ?? '') . ' via ' . (string) ($mailConfig['host'] ?? '')
            : 'Faltan MAIL_HOST o MAIL_FROM',
    ],
    [
        'label' => 'Backups',
        'status' => $backupError === null,
        'detail' => $backupError === null ? $backupCount . ' archivos disponibles' : $backupError,
    ],
];

$configItems = [
    'APP_URL' => $appUrl,
    'APP_PATH' => $appPath,
    'STRIPE_SECRET_KEY' => adminSettingsMask((string) ($stripeConfig['secret_key'] ?? '')),
    'STRIPE_PUBLISHABLE_KEY' => adminSettingsMask((string) ($stripeConfig['publishable_key'] ?? '')),
    'STRIPE_WEBHOOK_SECRET' => adminSettingsMask((string) ($stripeConfig['webhook_secret'] ?? '')),
    'GOOGLE_CLIENT_ID' => adminSettingsMask((string) ($googleConfig['client_id'] ?? '')),
    'MAIL_HOST' => (string) ($mailConfig['host'] ?? ''),
    'MAIL_FROM' => (string) ($mailConfig['from'] ?? ''),
    'PG_DUMP_PATH' => adminSettingsMask((string) envValue('PG_DUMP_PATH', '')),
];
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div>
        <h3 class="font-weight-bold mb-1">Configuracion</h3>
        <p class="text-muted mb-0">Estado operativo del SaaS, integraciones y variables sensibles sin exponer secretos.</p>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Entorno</p>
          <h4 class="font-weight-bold mb-2"><?php echo htmlspecialchars(PHP_OS_FAMILY); ?></h4>
          <span class="text-muted">PHP <?php echo htmlspecialchars(PHP_VERSION); ?></span>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">PostgreSQL</p>
          <h4 class="font-weight-bold mb-2"><?php echo htmlspecialchars((string) ($dbConfig['dbname'] ?? '-')); ?></h4>
          <span class="text-muted"><?php echo htmlspecialchars($dbVersion); ?></span>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Archivo .env</p>
          <h4 class="font-weight-bold mb-2"><?php echo is_file($envPath) ? 'Detectado' : 'No detectado'; ?></h4>
          <?php echo adminSettingsStatusBadge(is_file($envPath), 'Disponible', 'Revisar'); ?>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="card-description mb-1">Ultimo backup</p>
          <h4 class="font-weight-bold mb-2" style="font-size: 1rem;">
            <?php echo htmlspecialchars((string) ($latestBackup['created_at_label'] ?? '-')); ?>
          </h4>
          <span class="text-muted"><?php echo htmlspecialchars((string) ($latestBackup['size_label'] ?? 'Sin archivos')); ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Servicios e integraciones</h4>
          <p class="card-description">Checklist de configuracion necesaria para operar registros, pagos, correo y respaldos.</p>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Servicio</th>
                  <th>Estado</th>
                  <th>Detalle</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($serviceChecks as $check): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars((string) $check['label']); ?></strong></td>
                  <td><?php echo adminSettingsStatusBadge((bool) $check['status'], 'Configurado', 'Pendiente'); ?></td>
                  <td><?php echo htmlspecialchars((string) $check['detail']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Variables principales</h4>
          <p class="card-description">Valores visibles y secretos enmascarados para diagnostico.</p>

          <div class="table-responsive">
            <table class="table">
              <tbody>
                <?php foreach ($configItems as $key => $value): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($key); ?></strong></td>
                  <td class="text-right"><code><?php echo htmlspecialchars((string) ($value !== '' ? $value : '-')); ?></code></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Resumen de datos</h4>
          <p class="card-description">Conteo rapido de tablas centrales de la plataforma.</p>

          <div class="row">
            <?php foreach ($counts as $label => $count): ?>
            <div class="col-md-6 col-xl-3 mb-3">
              <div class="border rounded p-3 h-100">
                <small class="text-muted d-block mb-1"><?php echo htmlspecialchars(ucfirst($label)); ?></small>
                <strong style="font-size: 1.35rem;"><?php echo $count === null ? '-' : (int) $count; ?></strong>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Notas profesionales</h4>
          <p class="card-description">Criterios para mantener este modulo seguro.</p>

          <ul class="list-unstyled mb-0">
            <li class="mb-3">
              <i class="mdi mdi-shield-lock-outline text-primary mr-2"></i>
              No se editan claves secretas desde el navegador.
            </li>
            <li class="mb-3">
              <i class="mdi mdi-eye-off-outline text-primary mr-2"></i>
              Los tokens se muestran enmascarados.
            </li>
            <li class="mb-3">
              <i class="mdi mdi-database-check-outline text-primary mr-2"></i>
              Los conteos ayudan a detectar tablas faltantes.
            </li>
            <li>
              <i class="mdi mdi-backup-restore text-primary mr-2"></i>
              Backups y pagos siguen teniendo sus propios modulos.
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
