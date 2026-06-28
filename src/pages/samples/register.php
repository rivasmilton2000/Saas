<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/stripe.php';
require_once __DIR__ . '/../../config/countries.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';
require_once __DIR__ . '/../../services/PublicRegistrationService.php';

requireGuest();

$registerRedirect = static function (): void {
    header('Location: register.php');
    exit;
};

$checkoutAction = strtolower(trim((string) ($_GET['checkout'] ?? '')));
if ($checkoutAction !== '') {
    try {
        if ($checkoutAction === 'success') {
            $sessionId = trim((string) ($_GET['session_id'] ?? ''));
            if ($sessionId === '') {
                setFlash('register', 'No recibimos una sesión válida de Stripe para confirmar la membresía.', 'danger');
                $registerRedirect();
            }

            $result = PublicRegistrationService::finalizeCheckoutReturn($pdo, $sessionId);
            if ($result['ok'] ?? false) {
                setFlash(
                    'login',
                    'Tu cuenta quedó activa con el plan ' . (string) ($result['plan_nombre'] ?? 'seleccionado') . '. Inicia sesión para continuar.',
                    'success'
                );
                header('Location: login.php');
                exit;
            }

            setFlash(
                'register',
                (string) ($result['message'] ?? 'Stripe aún no confirma tu suscripción. Intenta de nuevo en unos segundos.'),
                (string) ($result['flash_type'] ?? 'warning')
            );
            $registerRedirect();
        }

        if ($checkoutAction === 'cancel') {
            setFlash('register', 'Cancelaste el checkout. Puedes elegir otro plan o intentarlo de nuevo cuando quieras.', 'warning');
            $registerRedirect();
        }
    } catch (Throwable $exception) {
        setFlash('register', 'No se pudo validar el checkout de Stripe en este momento. Intenta nuevamente.', 'danger');
        $registerRedirect();
    }
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

$planBenefitsText = static function (array $plan): string {
    $benefits = array_filter(array_map(
        static fn(array $feature): string => trim((string) ($feature['caracteristica'] ?? '')),
        array_slice($plan['caracteristicas'] ?? [], 0, 3)
    ));

    return $benefits === [] ? '' : 'Incluye: ' . implode(' · ', $benefits);
};

$planCheckoutMode = static function (array $plan): string {
    if (!empty($plan['is_free'])) {
        return 'free';
    }

    return !empty($plan['supports_checkout']) ? 'stripe' : 'pending';
};

$planSubmitNote = static function (array $plan) use ($planCheckoutMode): string {
    $mode = $planCheckoutMode($plan);

    if ($mode === 'free') {
        return 'La cuenta se crea de inmediato con este plan.';
    }

    if ($mode === 'stripe') {
        return 'Al crear la cuenta te llevaremos a Stripe para completar el cobro.';
    }

    return 'Este plan estará disponible cuando Stripe esté configurado.';
};

$selectedPlanPriceLabel = (string) ($selectedPlan['precio_label'] ?? '');
$selectedPlanDescription = (string) ($selectedPlan['descripcion'] ?? '');
$selectedPlanBenefits = $selectedPlan !== null ? $planBenefitsText($selectedPlan) : '';
$selectedPlanNote = $selectedPlan !== null ? $planSubmitNote($selectedPlan) : '';
$selectedPlanCompanies = $selectedPlan !== null
    ? $formatLimit($selectedPlan['limite_empresas'] ?? null, 'empresa', 'empresas', 'Escalable')
    : 'Escalable';
$selectedPlanUsers = $selectedPlan !== null
    ? $formatLimit($selectedPlan['limite_usuarios'] ?? null, 'usuario', 'usuarios', 'Escalable')
    : 'Escalable';
$selectedPlanDocs = $selectedPlan !== null
    ? $formatLimit($selectedPlan['limite_documentos'] ?? null, 'documento', 'documentos', 'Sin tope fijo')
    : 'Sin tope fijo';
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
            <p>Después de esta cuenta, el registro público seguirá con planes y checkout normal.</p>
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
                <label for="bootstrapCountry">País</label>
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
                <label for="bootstrapPassword">Contraseña</label>
                <input
                  type="password"
                  name="password"
                  id="bootstrapPassword"
                  class="auth-input"
                  placeholder="Contraseña"
                  autocomplete="new-password"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="bootstrapConfirmPassword">Confirmar contraseña</label>
                <input
                  type="password"
                  name="confirm_password"
                  id="bootstrapConfirmPassword"
                  class="auth-input"
                  placeholder="Confirmar contraseña"
                  autocomplete="new-password"
                  required
                >
              </div>
            </div>

            <button type="submit" class="auth-primary-btn">Crear cuenta</button>
          </form>

          <p class="auth-link-line">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
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

          <div class="auth-social-stack">
            <button type="button" class="auth-social-btn" disabled aria-disabled="true">
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.3 0-5.9-2.7-5.9-6s2.6-6 5.9-6c1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 6.8 2.2 2.6 6.4 2.6 11.6S6.8 21 12 21c6.9 0 9.1-4.8 9.1-7.3 0-.5-.1-.9-.1-1.3H12Z"></path>
                <path fill="#34A853" d="M3.6 7.1l3.2 2.4c.9-1.8 2.8-3.1 5.2-3.1 1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 8.3 2.2 5 4.3 3.6 7.1Z"></path>
                <path fill="#FBBC05" d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.5c-.8.6-1.9 1-3.3 1-2.5 0-4.5-1.7-5.3-4l-3.3 2.5C4.8 18.6 8.1 21 12 21Z"></path>
                <path fill="#4285F4" d="M21.1 13.7c0-.5-.1-.9-.1-1.3H12v3.9h5.4c-.3 1.2-1 2.2-2.1 3l3.1 2.5c1.8-1.7 2.7-4.1 2.7-7.1Z"></path>
              </svg>
              Registrarme con Google
            </button>
            <p class="auth-social-note">Disponible pronto.</p>
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
                <label for="registerCountry">País</label>
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
                <label for="registerPassword">Contraseña</label>
                <input
                  type="password"
                  name="password"
                  id="registerPassword"
                  class="auth-input"
                  placeholder="Contraseña"
                  autocomplete="new-password"
                  required
                >
              </div>

              <div class="auth-field">
                <label for="registerPasswordConfirm">Confirmar contraseña</label>
                <input
                  type="password"
                  name="confirm_password"
                  id="registerPasswordConfirm"
                  class="auth-input"
                  placeholder="Confirmar contraseña"
                  autocomplete="new-password"
                  required
                >
              </div>
            </div>

            <?php if ($selectedPlan !== null): ?>
            <div class="auth-plan-section">
              <div class="auth-plan-heading">
                <div>
                  <h2>Plan</h2>
                  <p>Elige una opción para continuar.</p>
                </div>
                <?php if ($customPlans !== []): ?>
                <a href="<?php echo htmlspecialchars($contactUrl); ?>" class="auth-inline-link">Enterprise</a>
                <?php endif; ?>
              </div>

              <div class="auth-plan-grid">
                <?php foreach ($selectablePlans as $plan): ?>
                <?php
                  $isSelected = (int) ($plan['id_plan'] ?? 0) === $selectedPlanId;
                  $planCompanies = $formatLimit($plan['limite_empresas'] ?? null, 'empresa', 'empresas', 'Escalable');
                  $planUsers = $formatLimit($plan['limite_usuarios'] ?? null, 'usuario', 'usuarios', 'Escalable');
                  $planDocs = $formatLimit($plan['limite_documentos'] ?? null, 'documento', 'documentos', 'Sin tope fijo');
                  $planNote = $planSubmitNote($plan);
                  $planMode = $planCheckoutMode($plan);
                ?>
                <label
                  class="auth-plan-option <?php echo $isSelected ? 'is-selected' : ''; ?>"
                  data-plan-card
                  data-plan-name="<?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>"
                  data-plan-price-label="<?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?>"
                  data-plan-description="<?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?>"
                  data-plan-companies="<?php echo htmlspecialchars($planCompanies); ?>"
                  data-plan-users="<?php echo htmlspecialchars($planUsers); ?>"
                  data-plan-docs="<?php echo htmlspecialchars($planDocs); ?>"
                  data-plan-benefits="<?php echo htmlspecialchars($planBenefitsText($plan)); ?>"
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
                  <span class="auth-plan-option-main">
                    <span class="auth-plan-option-name"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></span>
                    <span class="auth-plan-option-caption"><?php echo htmlspecialchars($planMode === 'free' ? 'Acceso inmediato' : ($planMode === 'stripe' ? 'Pago con Stripe' : 'Pendiente')); ?></span>
                  </span>
                  <span class="auth-plan-option-price"><?php echo htmlspecialchars((string) ($plan['precio_label'] ?? '')); ?></span>
                </label>
                <?php endforeach; ?>
              </div>

              <div class="auth-plan-summary">
                <p class="auth-plan-summary-title">Plan seleccionado</p>
                <p class="auth-plan-summary-line">
                  <span data-plan-summary-name><?php echo htmlspecialchars((string) ($selectedPlan['nombre'] ?? 'Plan')); ?></span>
                  —
                  <span data-plan-summary-price><?php echo htmlspecialchars($selectedPlanPriceLabel); ?></span>
                </p>
                <p class="auth-plan-summary-text" data-plan-summary-description><?php echo htmlspecialchars($selectedPlanDescription); ?></p>
                <div class="auth-plan-summary-meta">
                  <span data-plan-summary-companies><?php echo htmlspecialchars($selectedPlanCompanies); ?></span>
                  <span data-plan-summary-users><?php echo htmlspecialchars($selectedPlanUsers); ?></span>
                  <span data-plan-summary-docs><?php echo htmlspecialchars($selectedPlanDocs); ?></span>
                </div>
                <p class="auth-inline-note" data-plan-summary-benefits><?php echo htmlspecialchars($selectedPlanBenefits); ?></p>
                <p class="auth-inline-note" data-plan-note><?php echo htmlspecialchars($selectedPlanNote); ?></p>
                <?php if (!$stripeReady): ?>
                <p class="auth-inline-note auth-inline-note--warning">Los planes pagos se habilitan cuando configures Stripe.</p>
                <?php endif; ?>
                <?php if ($customPlans !== []): ?>
                <p class="auth-inline-note">¿Necesitas Enterprise? <a href="<?php echo htmlspecialchars($contactUrl); ?>">Hablar con ventas</a>.</p>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <label class="auth-check" for="acceptTerms">
              <input type="checkbox" id="acceptTerms" name="terms" value="1" required>
              <span>Acepto términos y condiciones</span>
            </label>

            <div class="auth-submit-wrap">
              <button type="submit" class="auth-primary-btn" data-plan-submit>Crear cuenta</button>
            </div>
          </form>

          <p class="auth-link-line">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
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
  </body>
</html>
