<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/PublicRegistrationService.php';

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$status = PublicRegistrationService::getCheckoutStatus($pdo, $sessionId);
$publicHomeUrl = saasPublicUrl('index.php');
$loginUrl = $status['login_url'] ?? '/Saas/src/pages/samples/login.php';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Confirmando pago - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth-zentra.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <?php if ($sessionId !== '' && ($status['status'] ?? '') === 'processing'): ?>
    <meta http-equiv="refresh" content="7">
    <?php endif; ?>
  </head>
  <body class="zentra-auth-body">
    <div class="zentra-auth-page">
      <main class="auth-shell auth-shell--login">
        <section class="auth-surface">
          <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Estamos confirmando tu pago</h1>
            <p class="auth-subtitle"><?php echo htmlspecialchars((string) ($status['message'] ?? 'Revisamos tu pago con Stripe antes de activar la cuenta.')); ?></p>
          </header>

          <div class="auth-mode-note">
            <h2>Estado del checkout</h2>
            <p>
              <?php
              $state = (string) ($status['status'] ?? 'processing');
              echo htmlspecialchars(match ($state) {
                  'completed' => 'La suscripcion ya quedo activa.',
                  'not_found' => 'No encontramos una referencia valida para este pago.',
                  'missing' => 'No recibimos el identificador del checkout.',
                  default => 'Stripe aun esta terminando la confirmacion.',
              });
              ?>
            </p>
          </div>

          <div class="auth-submit-wrap">
            <a href="<?php echo htmlspecialchars((string) $loginUrl); ?>" class="auth-primary-btn">
              <?php echo (($status['status'] ?? '') === 'completed') ? 'Iniciar sesion' : 'Ir al acceso'; ?>
            </a>
            <a href="/Saas/src/pages/samples/register.php" class="auth-social-btn">Volver al registro</a>
          </div>
        </section>
      </main>
    </div>
  </body>
</html>
