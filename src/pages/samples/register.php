<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/stripe.php';
require_once __DIR__ . '/../../config/google.php';
require_once __DIR__ . '/../../config/countries.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';
require_once __DIR__ . '/../../services/PublicRegistrationService.php';

requireGuest();

$checkoutAction = strtolower(trim((string) ($_GET['checkout'] ?? '')));
if ($checkoutAction === 'success') {
    $sessionId = trim((string) ($_GET['session_id'] ?? ''));
    $target = 'payment_success.php';
    if ($sessionId !== '') {
        $target .= '?session_id=' . rawurlencode($sessionId);
    }

    header('Location: ' . $target);
    exit;
}

if ($checkoutAction === 'cancel') {
    header('Location: payment_cancel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::register();
}

$flash = getFlash('register');
$alertClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'auth-alert--success',
    'danger' => 'auth-alert--danger',
    'warning' => 'auth-alert--warning',
    default => 'auth-alert--info',
};

$allowAdminSelection = UsuarioModel::canSelectAdminOnPublicRegister($pdo);
$bootstrapAdminMode = $allowAdminSelection && strtolower(trim((string) ($_GET['bootstrap'] ?? ''))) === 'admin';
$plans = PublicRegistrationService::getPlansForRegister($pdo);
$selectablePlans = array_values(array_filter($plans, static function (array $plan): bool {
    return !dbBoolValue($plan['personalizado'] ?? false);
}));
$customPlans = array_values(array_filter($plans, static function (array $plan): bool {
    return dbBoolValue($plan['personalizado'] ?? false);
}));
$countryOptions = getCountryOptions();
$contactUrl = (string) ($flash['meta']['contact_url'] ?? saasPublicUrl('contact.php?plan=enterprise'));
$stripeReady = stripeIsConfigured();
$stripeTestMode = $stripeReady && stripeIsTestMode();
$googleReady = googleIsConfigured();
$googleClientId = $googleReady ? googleConfig()['client_id'] : '';
$googleNonce = $googleReady ? googleAuthNonce(true) : '';
$googleCallbackUrl = '/Saas/src/auth/google_callback.php';

$old = array_merge([
    'nombre_completo' => '',
    'email' => '',
    'username' => '',
    'pais' => 'El Salvador',
    'id_plan' => 0,
], is_array($flash['meta']['old'] ?? null) ? $flash['meta']['old'] : []);

$defaultPlan = null;
foreach ($selectablePlans as $candidate) {
    if (!empty($candidate['is_free'])) {
        $defaultPlan = $candidate;
        break;
    }
}

if ($defaultPlan === null) {
    $defaultPlan = $selectablePlans[0] ?? null;
}

$selectedPlanId = (int) ($old['id_plan'] ?? 0);
if ($selectedPlanId <= 0) {
    $selectedPlanId = (int) ($defaultPlan['id_plan'] ?? 0);
}

$selectedPlan = null;
foreach ($selectablePlans as $candidate) {
    if ((int) ($candidate['id_plan'] ?? 0) === $selectedPlanId) {
        $selectedPlan = $candidate;
        break;
    }
}

if ($selectedPlan === null) {
    $selectedPlan = $defaultPlan;
    $selectedPlanId = (int) ($selectedPlan['id_plan'] ?? 0);
}

$formatLimit = static function ($value, string $singular, string $plural, string $fallback): string {
    if ($value === null || $value === '') {
        return $fallback;
    }

    $number = (int) $value;
    return number_format($number) . ' ' . ($number === 1 ? $singular : $plural);
};

$planCheckoutMode = static function (array $plan): string {
    if (!empty($plan['is_free'])) {
        return 'free';
    }

    if (!empty($plan['is_custom'])) {
        return 'sales';
    }

    return 'stripe';
};

$planSubmitNote = static function (array $plan) use ($planCheckoutMode, $stripeReady): string {
    $mode = $planCheckoutMode($plan);

    if ($mode === 'free') {
        return 'La cuenta se crea de inmediato con este plan.';
    }

    if ($mode === 'sales') {
        return 'Este plan se coordina con un asesor comercial.';
    }

    if ($stripeReady) {
        return 'Al crear la cuenta te llevaremos a Stripe para completar el cobro.';
    }

    return 'Configura Stripe en este entorno para habilitar el cobro del plan.';
};

$planCardDescription = static function (array $plan): string {
    $description = trim((string) ($plan['descripcion'] ?? ''));
    if ($description !== '') {
        return $description;
    }

    if (!empty($plan['is_free'])) {
        return 'Ideal para explorar Zentra.';
    }

    if (dbBoolValue($plan['destacado'] ?? false)) {
        return 'Una opcion equilibrada para crecer con orden.';
    }

    return 'Pensado para una operacion mas organizada.';
};

$selectedPlanPriceLabel = (string) ($selectedPlan['precio_label'] ?? '');
$selectedPlanSummaryMeta = $selectedPlan !== null
    ? implode(' | ', [
        $formatLimit($selectedPlan['limite_empresas'] ?? null, 'empresa', 'empresas', 'Escalable'),
        $formatLimit($selectedPlan['limite_usuarios'] ?? null, 'usuario', 'usuarios', 'Escalable'),
        $formatLimit($selectedPlan['limite_documentos'] ?? null, 'documento', 'documentos', 'Sin tope fijo'),
    ])
    : '';
$selectedPlanNote = $selectedPlan !== null ? $planSubmitNote($selectedPlan) : '';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registro - Zentra</title>
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
      <?php if ($bootstrapAdminMode): ?>
      <main class="auth-shell auth-shell--admin">
        <section class="auth-surface auth-surface--register">
          <a href="register.php" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Crear primer administrador</h1>
            <p class="auth-subtitle">Configura la cuenta inicial para empezar a usar Zentra.</p>
          </header>

          <?php if ($flash): ?>
          <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
          </div>
          <?php endif; ?>

          <div class="auth-mode-note">
            <h2>Acceso administrativo inicial</h2>
            <p>Despues de esta cuenta, el registro publico seguira con planes y checkout normal.</p>
          </div>

          <form class="auth-form" method="POST" action="">
            <input type="hidden" name="rol" value="admin">

            <div class="auth-form-grid">
              <div class="auth-field">
                <label for="bootstrapUsername">Usuario</label>
                <input
                  type="text"
                  name="username"
                  id="bootstrapUsername"
                  class="auth-input"
                  placeholder="Usuario"
                  autocomplete="username"
                  value="<?php echo htmlspecialchars((string) ($old['username'] ?? '')); ?>"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="bootstrapCountry">Pais</label>
                <select name="pais" id="bootstrapCountry" class="auth-select">
                  <?php foreach ($countryOptions as $value => $label): ?>
                  <option value="<?php echo htmlspecialchars((string) $value); ?>" <?php echo (string) ($old['pais'] ?? 'El Salvador') === (string) $value ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $label); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="auth-form-grid">
              <div class="auth-field">
                <label for="bootstrapPassword">Contrasena</label>
                <input
                  type="password"
                  name="password"
                  id="bootstrapPassword"
                  class="auth-input"
                  placeholder="Contrasena"
                  autocomplete="new-password"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="bootstrapConfirmPassword">Confirmar contrasena</label>
                <input
                  type="password"
                  name="confirm_password"
                  id="bootstrapConfirmPassword"
                  class="auth-input"
                  placeholder="Confirmar contrasena"
                  autocomplete="new-password"
                  required
                >
              </div>
            </div>

            <button type="submit" class="auth-primary-btn">Crear cuenta</button>
          </form>

          <p class="auth-link-line">
            Ya tienes cuenta? <a href="login.php">Inicia sesion</a>
          </p>

          <div class="auth-center-links">
            <a href="register.php" class="auth-secondary-link">Volver al registro de usuarios</a>
          </div>
        </section>
      </main>
      <?php else: ?>
      <main class="auth-shell auth-shell--register">
        <section class="auth-surface auth-surface--register">
          <a href="<?php echo htmlspecialchars(saasPublicUrl('index.php')); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Crea tu cuenta</h1>
            <p class="auth-subtitle">Completa tus datos para empezar.</p>
          </header>

          <?php if ($flash): ?>
          <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
            <?php if (!empty($flash['meta']['contact_url'])): ?>
              <a href="<?php echo htmlspecialchars((string) $flash['meta']['contact_url']); ?>" class="auth-inline-link">Hablar con ventas</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div
            class="auth-social-stack"
            data-google-auth-root
            data-google-context="register"
            data-google-client-id="<?php echo htmlspecialchars($googleClientId); ?>"
            data-google-callback="<?php echo htmlspecialchars($googleCallbackUrl); ?>"
            data-google-nonce="<?php echo htmlspecialchars($googleNonce); ?>"
          >
            <?php if ($googleReady): ?>
            <div class="auth-google-slot" data-google-slot></div>
            <p class="auth-social-note">Crea tu cuenta con Google y conserva el plan que selecciones.</p>
            <?php else: ?>
            <button type="button" class="auth-social-btn" disabled aria-disabled="true">Registrarme con Google</button>
            <p class="auth-social-note">Configura GOOGLE_CLIENT_ID para habilitar este registro.</p>
            <?php endif; ?>
            <p class="auth-feedback-note" data-auth-feedback hidden></p>
          </div>

          <div class="auth-divider"><span>o completa tu cuenta</span></div>

          <form class="auth-form" method="POST" action="">
            <input type="hidden" name="rol" value="user">

            <div class="auth-form-grid">
              <div class="auth-field">
                <label for="registerName">Nombre completo</label>
                <input
                  type="text"
                  name="nombre_completo"
                  id="registerName"
                  class="auth-input"
                  placeholder="Nombre completo"
                  value="<?php echo htmlspecialchars((string) ($old['nombre_completo'] ?? '')); ?>"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="registerEmail">Correo</label>
                <input
                  type="email"
                  name="email"
                  id="registerEmail"
                  class="auth-input"
                  placeholder="Correo"
                  autocomplete="email"
                  value="<?php echo htmlspecialchars((string) ($old['email'] ?? '')); ?>"
                  required
                >
              </div>
            </div>

            <div class="auth-form-grid">
              <div class="auth-field">
                <label for="registerUsername">Usuario</label>
                <input
                  type="text"
                  name="username"
                  id="registerUsername"
                  class="auth-input"
                  placeholder="Usuario"
                  autocomplete="username"
                  value="<?php echo htmlspecialchars((string) ($old['username'] ?? '')); ?>"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="registerCountry">Pais</label>
                <select name="pais" id="registerCountry" class="auth-select">
                  <?php foreach ($countryOptions as $value => $label): ?>
                  <option value="<?php echo htmlspecialchars((string) $value); ?>" <?php echo (string) ($old['pais'] ?? 'El Salvador') === (string) $value ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $label); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="auth-form-grid">
              <div class="auth-field">
                <label for="registerPassword">Contrasena</label>
                <input
                  type="password"
                  name="password"
                  id="registerPassword"
                  class="auth-input"
                  placeholder="Contrasena"
                  autocomplete="new-password"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="registerPasswordConfirm">Confirmar contrasena</label>
                <input
                  type="password"
                  name="confirm_password"
                  id="registerPasswordConfirm"
                  class="auth-input"
                  placeholder="Confirmar contrasena"
                  autocomplete="new-password"
                  required
                >
              </div>
            </div>

            <?php if ($selectedPlan !== null): ?>
            <div class="auth-plan-section">
              <div class="auth-plan-heading">
                <div>
                  <p class="auth-plan-eyebrow">Plan disponible</p>
                  <h2>Selecciona la membresia que mejor se adapta a tu operacion.</h2>
                </div>
                <?php if ($stripeTestMode): ?>
                <p class="auth-plan-status-note">Los pagos estan en modo de prueba.</p>
                <?php elseif (!$stripeReady): ?>
                <p class="auth-plan-status-note">Configura Stripe para habilitar los cobros en este entorno.</p>
                <?php endif; ?>
              </div>

              <div class="auth-plan-grid">
                <?php foreach ($selectablePlans as $plan): ?>
                <?php
                  $isSelected = (int) ($plan['id_plan'] ?? 0) === $selectedPlanId;
                  $planCompanies = $formatLimit($plan['limite_empresas'] ?? null, 'empresa', 'empresas', 'Escalable');
                  $planUsers = $formatLimit($plan['limite_usuarios'] ?? null, 'usuario', 'usuarios', 'Escalable');
                  $planDocs = $formatLimit($plan['limite_documentos'] ?? null, 'documento', 'documentos', 'Sin tope fijo');
                  $planSummaryMeta = implode(' | ', [$planCompanies, $planUsers, $planDocs]);
                  $planNote = $planSubmitNote($plan);
                  $planMode = $planCheckoutMode($plan);
                  $planBadge = dbBoolValue($plan['destacado'] ?? false) ? 'Recomendado' : '';
                  $planAction = $isSelected
                      ? 'Plan seleccionado'
                      : ($planMode === 'free' ? 'Crear cuenta' : 'Continuar con pago');
                ?>
                <label
                  class="auth-plan-option <?php echo $isSelected ? 'is-selected' : ''; ?>"
                  data-plan-card
                  data-plan-name="<?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>"
                  data-plan-price-label="<?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?>"
                  data-plan-summary-meta="<?php echo htmlspecialchars($planSummaryMeta); ?>"
                  data-plan-checkout="<?php echo htmlspecialchars($planMode); ?>"
                  data-plan-note="<?php echo htmlspecialchars($planNote); ?>"
                >
                  <input
                    type="radio"
                    name="id_plan"
                    value="<?php echo (int) ($plan['id_plan'] ?? 0); ?>"
                    data-plan-radio
                    <?php echo $isSelected ? 'checked' : ''; ?>
                  >
                  <?php if ($planBadge !== ''): ?>
                  <span class="auth-plan-option-badge"><?php echo htmlspecialchars($planBadge); ?></span>
                  <?php endif; ?>
                  <span class="auth-plan-option-head">
                    <span class="auth-plan-option-main">
                      <span class="auth-plan-option-name"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></span>
                      <span class="auth-plan-option-caption"><?php echo htmlspecialchars($planCardDescription($plan)); ?></span>
                    </span>
                    <span class="auth-plan-option-price"><?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?></span>
                  </span>
                  <ul class="auth-plan-option-list">
                    <li><?php echo htmlspecialchars($planCompanies); ?></li>
                    <li><?php echo htmlspecialchars($planUsers); ?></li>
                    <li><?php echo htmlspecialchars($planDocs); ?></li>
                  </ul>
                  <span class="auth-plan-card-action" data-plan-card-action><?php echo htmlspecialchars($planAction); ?></span>
                </label>
                <?php endforeach; ?>
              </div>

              <div class="auth-plan-summary">
                <p class="auth-plan-summary-title">Plan seleccionado</p>
                <p class="auth-plan-summary-line">
                  <span data-plan-summary-name><?php echo htmlspecialchars((string) ($selectedPlan['nombre'] ?? 'Plan')); ?></span>
                  &mdash;
                  <span data-plan-summary-price><?php echo htmlspecialchars($selectedPlanPriceLabel); ?></span>
                </p>
                <p class="auth-plan-summary-text" data-plan-summary-meta><?php echo htmlspecialchars($selectedPlanSummaryMeta); ?></p>
                <p class="auth-inline-note" data-plan-note><?php echo htmlspecialchars($selectedPlanNote); ?></p>
              </div>

              <?php if ($customPlans !== []): ?>
              <a href="<?php echo htmlspecialchars($contactUrl); ?>" class="auth-plan-enterprise">
                <span class="auth-plan-enterprise-copy">
                  <strong>Enterprise</strong>
                  <span>Capacidad a medida, mas usuarios e implementacion personalizada.</span>
                </span>
                <span class="auth-plan-enterprise-link">Hablar con ventas</span>
              </a>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <label class="auth-check" for="acceptTerms">
              <input type="checkbox" id="acceptTerms" name="terms" value="1" required>
              <span>Acepto terminos y condiciones</span>
            </label>

            <div class="auth-submit-wrap">
              <button
                type="submit"
                class="auth-primary-btn"
                data-plan-submit
                data-plan-submit-free="Crear cuenta"
                data-plan-submit-stripe="Continuar con pago"
                data-plan-submit-pending="Plan no disponible"
              >
                Crear cuenta
              </button>
            </div>
          </form>

          <p class="auth-link-line">
            Ya tienes cuenta? <a href="login.php">Inicia sesion</a>
          </p>

          <?php if ($allowAdminSelection): ?>
          <div class="auth-center-links">
            <a href="register.php?bootstrap=admin" class="auth-secondary-link">Crear primer administrador</a>
          </div>
          <?php endif; ?>
        </section>
      </main>
      <?php endif; ?>
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
