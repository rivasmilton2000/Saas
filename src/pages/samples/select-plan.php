<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/stripe.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';
require_once __DIR__ . '/../../services/AuthSessionService.php';
require_once __DIR__ . '/../../services/PublicRegistrationService.php';
require_once __DIR__ . '/../../services/UserPlanSelectionService.php';

requireLogin();

$session = sessionData();
$user = UsuarioModel::getById($pdo, (int) ($session['id_usuario'] ?? 0), false);
if ($user === null) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!UserPlanSelectionService::canAccessSelection($user)) {
    header('Location: ' . (isAdmin() ? '/Saas/src/admin/index.php' : '/Saas/src/app/index.php'));
    exit;
}

$redirectSelf = static function (): void {
    header('Location: select-plan.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = UserPlanSelectionService::start($pdo, $user, (int) ($_POST['id_plan'] ?? 0));
    if (!($result['ok'] ?? false)) {
        setFlash('select_plan', (string) ($result['message'] ?? 'No se pudo continuar con la membresia seleccionada.'), (string) ($result['flash_type'] ?? 'danger'), [
            'contact_url' => $result['contact_url'] ?? null,
            'id_plan' => (int) ($_POST['id_plan'] ?? 0),
        ]);
        $redirectSelf();
    }

    if (($result['mode'] ?? '') === 'checkout' && !empty($result['checkout_url'])) {
        session_write_close();
        header('Location: ' . (string) $result['checkout_url']);
        exit;
    }

    $freshUser = UsuarioModel::getById($pdo, (int) ($user['id'] ?? 0), false);
    if ($freshUser !== null) {
        AuthSessionService::refreshFromDatabase($pdo, (int) $freshUser['id']);
    }

    header('Location: /Saas/src/app/index.php');
    exit;
}

$flash = getFlash('select_plan');
$alertClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'auth-alert--success',
    'danger' => 'auth-alert--danger',
    'warning' => 'auth-alert--warning',
    default => 'auth-alert--info',
};

$plans = PublicRegistrationService::getPlansForRegister($pdo);
$selectablePlans = array_values(array_filter($plans, static function (array $plan): bool {
    return !dbBoolValue($plan['personalizado'] ?? false);
}));
$customPlans = array_values(array_filter($plans, static function (array $plan): bool {
    return dbBoolValue($plan['personalizado'] ?? false);
}));
$contactUrl = (string) (($flash['meta']['contact_url'] ?? '') ?: saasPublicUrl('contact.php?plan=enterprise'));
$stripeReady = stripeIsConfigured();
$stripeTestMode = $stripeReady && stripeIsTestMode();
$selectedPlanId = (int) (($flash['meta']['id_plan'] ?? 0) ?: ($user['id_plan'] ?? 0));

if ($selectedPlanId <= 0) {
    $selectedPlanId = PublicRegistrationService::defaultPlanId($pdo);
}

$selectedPlan = null;
foreach ($selectablePlans as $candidate) {
    if ((int) ($candidate['id_plan'] ?? 0) === $selectedPlanId) {
        $selectedPlan = $candidate;
        break;
    }
}
if ($selectedPlan === null) {
    $selectedPlan = $selectablePlans[0] ?? null;
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

$planCardDescription = static function (array $plan): string {
    $description = trim((string) ($plan['descripcion'] ?? ''));
    if ($description !== '') {
        return $description;
    }

    return 'Selecciona la opcion que mejor se ajuste a tu operacion.';
};

$planSubmitNote = static function (array $plan) use ($planCheckoutMode, $stripeReady): string {
    $mode = $planCheckoutMode($plan);
    if ($mode === 'free') {
        return 'El acceso se activa de inmediato con este plan.';
    }

    if ($mode === 'sales') {
        return 'Este plan se coordina con ventas.';
    }

    if ($stripeReady) {
        return 'Te llevaremos a Stripe para completar el cobro con seguridad.';
    }

    return 'Configura Stripe en este entorno para habilitar el cobro del plan.';
};

$selectedPlanPriceLabel = $selectedPlan !== null ? PlanModel::formatPriceLabel($selectedPlan) : '';
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
    <title>Selecciona tu plan - Zentra</title>
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
      <main class="auth-shell auth-shell--register">
        <section class="auth-surface auth-surface--register">
          <a href="<?php echo htmlspecialchars(saasPublicUrl('index.php')); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Selecciona tu plan</h1>
            <p class="auth-subtitle">Elige la membresia con la que quieres continuar en Zentra.</p>
          </header>

          <?php if ($flash): ?>
          <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
            <?php if (!empty($flash['meta']['contact_url'])): ?>
              <a href="<?php echo htmlspecialchars((string) $flash['meta']['contact_url']); ?>" class="auth-inline-link">Hablar con ventas</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <form class="auth-form" method="POST" action="">
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
                  $planDescription = $planCardDescription($plan);
                  $planSummaryMeta = implode(' | ', [$planCompanies, $planUsers, $planDocs]);
                  $planNote = $planSubmitNote($plan);
                  $planMode = $planCheckoutMode($plan);
                  $planBadge = dbBoolValue($plan['destacado'] ?? false) ? 'Recomendado' : '';
                  $planAction = $isSelected
                      ? 'Plan seleccionado'
                      : ($planMode === 'free' ? 'Activar plan' : 'Continuar con pago');
                ?>
                <label
                  class="auth-plan-option <?php echo $isSelected ? 'is-selected' : ''; ?>"
                  data-plan-card
                  data-plan-name="<?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>"
                  data-plan-price-label="<?php echo htmlspecialchars(PlanModel::formatPriceLabel($plan)); ?>"
                  data-plan-summary-meta="<?php echo htmlspecialchars($planSummaryMeta); ?>"
                  data-plan-checkout="<?php echo htmlspecialchars($planMode); ?>"
                  data-plan-note="<?php echo htmlspecialchars($planNote); ?>"
                >
                  <input type="radio" name="id_plan" value="<?php echo (int) ($plan['id_plan'] ?? 0); ?>" data-plan-radio <?php echo $isSelected ? 'checked' : ''; ?>>
                  <?php if ($planBadge !== ''): ?>
                  <span class="auth-plan-option-badge"><?php echo htmlspecialchars($planBadge); ?></span>
                  <?php endif; ?>
                  <span class="auth-plan-option-head">
                    <span class="auth-plan-option-main">
                      <span class="auth-plan-option-name"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></span>
                      <span class="auth-plan-option-caption"><?php echo htmlspecialchars($planDescription); ?></span>
                    </span>
                    <span class="auth-plan-option-price"><?php echo htmlspecialchars(PlanModel::formatPriceLabel($plan)); ?></span>
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

            <div class="auth-submit-wrap">
              <button
                type="submit"
                class="auth-primary-btn"
                data-plan-submit
                data-plan-submit-free="Activar plan"
                data-plan-submit-stripe="Continuar con pago"
                data-plan-submit-pending="Plan no disponible"
              >
                Activar plan
              </button>
              <a href="logout.php" class="auth-secondary-link">Salir</a>
            </div>
          </form>
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
