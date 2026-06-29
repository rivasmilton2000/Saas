<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/google.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

requireGuest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::login();
}

$flash = getFlash('login');
$old = array_merge([
    'username' => '',
], is_array($flash['meta']['old'] ?? null) ? $flash['meta']['old'] : []);
$alertClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'auth-alert--success',
    'danger' => 'auth-alert--danger',
    'warning' => 'auth-alert--warning',
    default => 'auth-alert--info',
};

$publicHomeUrl = saasPublicUrl('index.php');
$forgotPasswordUrl = saasPublicUrl('contact.php?topic=acceso');
$googleReady = googleIsConfigured();
$googleClientId = $googleReady ? googleConfig()['client_id'] : '';
$googleNonce = $googleReady ? googleAuthNonce(true) : '';
$googleCallbackUrl = '/Saas/src/auth/google_callback.php';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Acceso - Zentra</title>
    <link rel="stylesheet" href="../../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/auth-zentra.css">
    <link rel="shortcut icon" href="../../assets/images/favicon.png" />
  </head>
  <body class="zentra-auth-body">
    <div class="zentra-auth-page">
      <main class="auth-shell auth-shell--login">
        <section class="auth-surface">
          <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Inicia sesion</h1>
            <p class="auth-subtitle">Accede a tu cuenta para continuar.</p>
          </header>

          <?php if ($flash): ?>
          <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
          </div>
          <?php endif; ?>

          <div
            class="auth-social-stack"
            data-google-auth-root
            data-google-context="login"
            data-google-client-id="<?php echo htmlspecialchars($googleClientId); ?>"
            data-google-callback="<?php echo htmlspecialchars($googleCallbackUrl); ?>"
            data-google-nonce="<?php echo htmlspecialchars($googleNonce); ?>"
          >
            <?php if ($googleReady): ?>
            <div class="auth-google-slot" data-google-slot></div>
            <p class="auth-social-note">Accede con tu cuenta verificada de Google.</p>
            <?php else: ?>
            <button type="button" class="auth-social-btn" disabled aria-disabled="true">Continuar con Google</button>
            <p class="auth-social-note">Configura GOOGLE_CLIENT_ID para habilitar este acceso.</p>
            <?php endif; ?>
            <p class="auth-feedback-note" data-auth-feedback hidden></p>
          </div>

          <div class="auth-divider"><span>o entra con tu cuenta</span></div>

          <form class="auth-form" method="POST" action="">
            <div class="auth-field">
              <label class="auth-sr-only" for="loginIdentifier">Usuario o correo</label>
              <input
                type="text"
                name="username"
                id="loginIdentifier"
                class="auth-input"
                placeholder="Usuario o correo"
                autocomplete="username"
                value="<?php echo htmlspecialchars((string) ($old['username'] ?? '')); ?>"
                required
              >
            </div>

            <div class="auth-field">
              <label class="auth-sr-only" for="loginPassword">Contrasena</label>
              <input
                type="password"
                name="password"
                id="loginPassword"
                class="auth-input"
                placeholder="Contrasena"
                autocomplete="current-password"
                required
              >
            </div>

            <div class="auth-meta-row">
              <label class="auth-check" for="rememberMe">
                <input type="checkbox" id="rememberMe" name="remember_me" value="1">
                <span>Recordarme</span>
              </label>
              <a href="<?php echo htmlspecialchars($forgotPasswordUrl); ?>" class="auth-link">Olvide mi contrasena</a>
            </div>

            <button type="submit" class="auth-primary-btn">Ingresar</button>
          </form>

          <p class="auth-link-line">
            No tienes cuenta? <a href="register.php">Crear cuenta</a>
          </p>

          <div class="auth-center-links">
            <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-secondary-link">Volver al sitio</a>
          </div>
        </section>
      </main>
    </div>

    <script src="../../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../../assets/js/off-canvas.js"></script>
    <script src="../../assets/js/template.js"></script>
    <script src="../../assets/js/settings.js"></script>
    <script src="../../assets/js/todolist.js"></script>
    <script src="../../assets/js/auth-zentra.js"></script>
    <?php if ($googleReady): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="../../assets/js/auth-google.js"></script>
    <?php endif; ?>
  </body>
</html>
