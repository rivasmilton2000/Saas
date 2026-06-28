<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../services/PublicRegistrationService.php';

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

$plans = PublicRegistrationService::getPlansForRegister($pdo);
$showcasePlans = array_values(array_filter($plans, static function (array $plan): bool {
    return !dbBoolValue($plan['personalizado'] ?? false);
}));
$showcasePlans = array_slice($showcasePlans, 0, 3);

$publicHomeUrl = saasPublicUrl('index.php');
$pricingUrl = saasPublicUrl('pricing.php');
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
      <div class="zentra-auth-shell zentra-auth-shell--login">
        <aside class="auth-showcase">
          <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <span class="auth-eyebrow">Plataforma contable y DTE</span>
          <h1 class="auth-title">Tu operacion diaria entra mejor cuando todo vive en un mismo panel.</h1>
          <p class="auth-copy">
            Ingresa a Zentra para seguir trabajando tus modulos, revisar tus empresas y mantener tus reportes,
            ventas y compras en una sola ruta.
          </p>

          <div class="auth-chip-row">
            <span class="auth-chip"><i class="mdi mdi-shield-check-outline"></i> Acceso protegido</span>
            <span class="auth-chip"><i class="mdi mdi-credit-card-check-outline"></i> Checkout con Stripe</span>
            <span class="auth-chip"><i class="mdi mdi-view-grid-plus-outline"></i> Modulos segun tu plan</span>
          </div>

          <div class="auth-stat-grid">
            <div class="auth-stat-card">
              <strong>5</strong>
              <span>niveles de membresia</span>
            </div>
            <div class="auth-stat-card">
              <strong>24/7</strong>
              <span>acceso a tu cuenta</span>
            </div>
            <div class="auth-stat-card">
              <strong>DTE</strong>
              <span>modulo listo para crecer</span>
            </div>
          </div>

          <div class="auth-story-grid">
            <div class="auth-story-card">
              <h3>Modulos para usuarios</h3>
              <p>Libro de compras, ventas consumidor final, ventas a contribuyentes y retencion IVA siguen disponibles segun permisos y plan.</p>
            </div>
            <div class="auth-story-card">
              <h3>Admin con control</h3>
              <p>El perfil administrador mantiene estadisticas, usuarios y configuracion. Los usuarios entran directo a sus herramientas de trabajo.</p>
            </div>
          </div>

          <div class="auth-module-list">
            <div class="auth-module-card">
              <div>
                <strong>Libro de Compras</strong>
                <span>Controla documentos y registros contables.</span>
              </div>
              <span class="auth-module-pill">Activo</span>
            </div>
            <div class="auth-module-card">
              <div>
                <strong>Ventas Consumidor Final</strong>
                <span>Emite y consulta ventas segun tu operacion.</span>
              </div>
              <span class="auth-module-pill">Modulo</span>
            </div>
            <div class="auth-module-card">
              <div>
                <strong>Ventas a Contribuyentes</strong>
                <span>Gestiona facturas, exportaciones y reportes.</span>
              </div>
              <span class="auth-module-pill">Modulo</span>
            </div>
            <div class="auth-module-card">
              <div>
                <strong>Retencion IVA 1%</strong>
                <span>Consulta y exporta tu informacion fiscal.</span>
              </div>
              <span class="auth-module-pill">Fiscal</span>
            </div>
          </div>

          <?php if ($showcasePlans !== []): ?>
          <div class="auth-mini-plans">
            <?php foreach ($showcasePlans as $plan): ?>
            <div class="auth-mini-plan">
              <strong><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></strong>
              <span class="auth-mini-plan-price"><?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?></span>
              <p><?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?></p>
              <div class="auth-mini-plan-meta">
                <span><?php echo htmlspecialchars((string) (($plan['limite_empresas'] ?? null) ? $plan['limite_empresas'] . ' empresas' : 'Escalable')); ?></span>
                <span><?php echo htmlspecialchars((string) (($plan['limite_usuarios'] ?? null) ? $plan['limite_usuarios'] . ' usuarios' : 'Escalable')); ?></span>
                <span><?php echo htmlspecialchars((string) (($plan['limite_documentos'] ?? null) ? number_format((int) $plan['limite_documentos']) . ' docs' : 'Sin tope fijo')); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="auth-pricing-link">
            <a href="<?php echo htmlspecialchars($pricingUrl); ?>" class="auth-plan-link">Ver membresias completas</a>
          </div>
        </aside>

        <main class="auth-panel">
          <div class="auth-card">
            <span class="auth-panel-caption"><i class="mdi mdi-login"></i> Accede a tu cuenta</span>
            <h2 class="auth-title auth-title--panel">Ingresa a Zentra</h2>
            <p class="auth-copy auth-copy--panel">
              Entra con tu usuario o correo para continuar justo donde dejaste tu operacion.
            </p>

            <?php if ($flash): ?>
            <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
              <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
            </div>
            <?php endif; ?>

            <div class="auth-social-stack">
              <button type="button" class="auth-social-btn" data-auth-google="login">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.3 0-5.9-2.7-5.9-6s2.6-6 5.9-6c1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 6.8 2.2 2.6 6.4 2.6 11.6S6.8 21 12 21c6.9 0 9.1-4.8 9.1-7.3 0-.5-.1-.9-.1-1.3H12Z"></path>
                  <path fill="#34A853" d="M3.6 7.1l3.2 2.4c.9-1.8 2.8-3.1 5.2-3.1 1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 8.3 2.2 5 4.3 3.6 7.1Z"></path>
                  <path fill="#FBBC05" d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.5c-.8.6-1.9 1-3.3 1-2.5 0-4.5-1.7-5.3-4l-3.3 2.5C4.8 18.6 8.1 21 12 21Z"></path>
                  <path fill="#4285F4" d="M21.1 13.7c0-.5-.1-.9-.1-1.3H12v3.9h5.4c-.3 1.2-1 2.2-2.1 3l3.1 2.5c1.8-1.7 2.7-4.1 2.7-7.1Z"></path>
                </svg>
                Continuar con Google
                <span>Proximamente</span>
              </button>
              <div class="auth-social-note" data-google-note="login">
                Ya dejamos el espacio preparado para Google Sign-In. Solo falta conectar el proveedor OAuth y sus credenciales.
              </div>
              <div class="auth-divider">o entra manualmente</div>
            </div>

            <form class="auth-form" method="POST" action="">
              <div class="auth-field">
                <label for="loginIdentifier">Usuario o correo</label>
                <input
                  type="text"
                  name="username"
                  id="loginIdentifier"
                  class="auth-input"
                  placeholder="tuusuario o correo@empresa.com"
                  autocomplete="username"
                  value="<?php echo htmlspecialchars((string) ($old['username'] ?? '')); ?>"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="loginPassword">Contrasena</label>
                <input
                  type="password"
                  name="password"
                  id="loginPassword"
                  class="auth-input"
                  placeholder="Escribe tu clave"
                  autocomplete="current-password"
                  required
                >
              </div>

              <ul class="auth-feature-list">
                <li>Los usuarios con membresia paga entran solo cuando Stripe confirma el plan activo.</li>
                <li>Si eliges un plan gratis, tu acceso queda disponible apenas terminas el registro.</li>
              </ul>

              <div class="auth-login-actions">
                <button type="submit" class="auth-primary-btn">Entrar al panel</button>
                <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-secondary-btn">Volver al sitio</a>
              </div>
            </form>

            <p class="auth-link-line">
              Aun no tienes una cuenta?
              <a href="register.php">Crear cuenta</a>
            </p>
          </div>
        </main>
      </div>
    </div>

    <script src="../../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../../assets/js/off-canvas.js"></script>
    <script src="../../assets/js/template.js"></script>
    <script src="../../assets/js/settings.js"></script>
    <script src="../../assets/js/todolist.js"></script>
    <script src="../../assets/js/auth-zentra.js"></script>
  </body>
</html>
