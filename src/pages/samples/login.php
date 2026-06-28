<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
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
            <h1 class="auth-title">Inicia sesión</h1>
            <p class="auth-subtitle">Accede a tu cuenta para continuar.</p>
          </header>

          <?php if ($flash): ?>
          <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
          </div>
          <?php endif; ?>

          <div class="auth-social-stack">
            <button type="button" class="auth-social-btn" disabled aria-disabled="true">
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.3 0-5.9-2.7-5.9-6s2.6-6 5.9-6c1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 6.8 2.2 2.6 6.4 2.6 11.6S6.8 21 12 21c6.9 0 9.1-4.8 9.1-7.3 0-.5-.1-.9-.1-1.3H12Z"></path>
                <path fill="#34A853" d="M3.6 7.1l3.2 2.4c.9-1.8 2.8-3.1 5.2-3.1 1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 8.3 2.2 5 4.3 3.6 7.1Z"></path>
                <path fill="#FBBC05" d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.5c-.8.6-1.9 1-3.3 1-2.5 0-4.5-1.7-5.3-4l-3.3 2.5C4.8 18.6 8.1 21 12 21Z"></path>
                <path fill="#4285F4" d="M21.1 13.7c0-.5-.1-.9-.1-1.3H12v3.9h5.4c-.3 1.2-1 2.2-2.1 3l3.1 2.5c1.8-1.7 2.7-4.1 2.7-7.1Z"></path>
              </svg>
              Continuar con Google
            </button>
            <p class="auth-social-note">Disponible pronto.</p>
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
              <label class="auth-sr-only" for="loginPassword">Contraseña</label>
              <input
                type="password"
                name="password"
                id="loginPassword"
                class="auth-input"
                placeholder="Contraseña"
                autocomplete="current-password"
                required
              >
            </div>

            <div class="auth-meta-row">
              <label class="auth-check" for="rememberMe">
                <input type="checkbox" id="rememberMe" name="remember_me" value="1">
                <span>Recordarme</span>
              </label>
              <a href="<?php echo htmlspecialchars($forgotPasswordUrl); ?>" class="auth-link">Olvidé mi contraseña</a>
            </div>

            <button type="submit" class="auth-primary-btn">Ingresar</button>
          </form>

          <p class="auth-link-line">
            ¿No tienes cuenta? <a href="register.php">Crear cuenta</a>
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
  </body>
</html>
