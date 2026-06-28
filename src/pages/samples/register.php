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
                setFlash('register', 'No recibimos una sesion valida de Stripe para confirmar la membresia.', 'danger');
                $registerRedirect();
            }

            $result = PublicRegistrationService::finalizeCheckoutReturn($pdo, $sessionId);
            if ($result['ok'] ?? false) {
                setFlash(
                    'login',
                    'Tu cuenta ya quedo activa con el plan ' . (string) ($result['plan_nombre'] ?? 'seleccionado') . '. Inicia sesion para continuar.',
                    'success'
                );
                header('Location: login.php');
                exit;
            }

            setFlash(
                'register',
                (string) ($result['message'] ?? 'Stripe aun no confirma tu suscripcion. Intenta de nuevo en unos segundos.'),
                (string) ($result['flash_type'] ?? 'warning')
            );
            $registerRedirect();
        }

        if ($checkoutAction === 'cancel') {
            setFlash('register', 'Cancelaste el checkout. Puedes elegir otro plan o intentarlo otra vez cuando quieras.', 'warning');
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

$formatLimit = static function ($value, string $suffix, string $fallback): string {
    return $value !== null && $value !== ''
        ? number_format((int) $value) . ' ' . $suffix
        : $fallback;
};

$planBenefitsPayload = static function (array $plan): string {
    $items = array_map(
        static fn(array $feature): string => (string) ($feature['caracteristica'] ?? ''),
        array_slice($plan['caracteristicas'] ?? [], 0, 4)
    );

    return htmlspecialchars(
        (string) json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ENT_QUOTES,
        'UTF-8'
    );
};
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
      <form method="POST" action="" class="zentra-auth-shell zentra-auth-shell--register">
        <aside class="auth-showcase">
          <a href="register.php" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <span class="auth-eyebrow">Modo instalacion</span>
          <h1 class="auth-title">Crea el primer administrador y deja lista la base del sistema.</h1>
          <p class="auth-copy">
            Este flujo especial solo aparece cuando aun no existe un administrador activo.
            Despues de esta cuenta, los demas registros publicos quedaran disponibles para usuarios normales con membresias.
          </p>

          <div class="auth-story-grid">
            <div class="auth-story-card">
              <h3>Control inicial</h3>
              <p>El primer admin podra crear usuarios, ver estadisticas, asignar membresias y organizar los modulos desde el dashboard.</p>
            </div>
            <div class="auth-story-card">
              <h3>Luego activas planes</h3>
              <p>Una vez creado el admin, el registro publico cambia al flujo con planes, beneficios visibles y cobro por Stripe.</p>
            </div>
          </div>

          <div class="auth-note-card" style="margin-top: 1.6rem; background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.18);">
            <h3 style="color: #ffffff;">Despues de instalar</h3>
            <p style="color: rgba(226, 232, 240, 0.86);">
              Podras volver a la vista de membresias para que los usuarios normales se registren con plan Free o con checkout en Stripe.
            </p>
          </div>
        </aside>

        <main class="auth-panel">
          <div class="auth-card auth-card--register">
            <span class="auth-panel-caption"><i class="mdi mdi-shield-crown-outline"></i> Primer administrador</span>
            <h2 class="auth-title auth-title--panel">Configura la cuenta inicial</h2>
            <p class="auth-copy auth-copy--panel">
              Esta cuenta tendra acceso al dashboard completo para terminar la configuracion de Zentra.
            </p>

            <?php if ($flash): ?>
            <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
              <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
            </div>
            <?php endif; ?>

            <input type="hidden" name="rol" value="admin">

            <div class="auth-social-stack">
              <button type="button" class="auth-social-btn" data-auth-google="register-admin">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.3 0-5.9-2.7-5.9-6s2.6-6 5.9-6c1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 6.8 2.2 2.6 6.4 2.6 11.6S6.8 21 12 21c6.9 0 9.1-4.8 9.1-7.3 0-.5-.1-.9-.1-1.3H12Z"></path>
                  <path fill="#34A853" d="M3.6 7.1l3.2 2.4c.9-1.8 2.8-3.1 5.2-3.1 1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 8.3 2.2 5 4.3 3.6 7.1Z"></path>
                  <path fill="#FBBC05" d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.5c-.8.6-1.9 1-3.3 1-2.5 0-4.5-1.7-5.3-4l-3.3 2.5C4.8 18.6 8.1 21 12 21Z"></path>
                  <path fill="#4285F4" d="M21.1 13.7c0-.5-.1-.9-.1-1.3H12v3.9h5.4c-.3 1.2-1 2.2-2.1 3l3.1 2.5c1.8-1.7 2.7-4.1 2.7-7.1Z"></path>
                </svg>
                Continuar con Google
                <span>Espacio listo</span>
              </button>
              <div class="auth-social-note" data-google-note="register-admin">
                Dejamos la entrada visual para Google lista en esta pantalla tambien. Falta conectar el proveedor OAuth para volverla operativa.
              </div>
              <div class="auth-divider">o crea el admin manualmente</div>
            </div>

            <div class="auth-form">
              <div class="auth-form-grid">
                <div class="auth-field">
                  <label for="bootstrapUsername">Usuario</label>
                  <input
                    type="text"
                    name="username"
                    id="bootstrapUsername"
                    class="auth-input"
                    placeholder="admin.zentra"
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
                  <label for="bootstrapPassword">Clave</label>
                  <input
                    type="password"
                    name="password"
                    id="bootstrapPassword"
                    class="auth-input"
                    placeholder="Minimo 6 caracteres"
                    autocomplete="new-password"
                    required
                  >
                </div>

                <div class="auth-field">
                  <label for="bootstrapConfirmPassword">Confirmar clave</label>
                  <input
                    type="password"
                    name="confirm_password"
                    id="bootstrapConfirmPassword"
                    class="auth-input"
                    placeholder="Repite la clave"
                    autocomplete="new-password"
                    required
                  >
                </div>
              </div>

              <div class="auth-note-card">
                <h3>Que ganas con esta cuenta</h3>
                <p>Acceso al dashboard administrativo, gestion de usuarios, asignacion de roles, estadisticas del sistema y configuracion global.</p>
              </div>

              <div class="auth-submit-stack">
                <button type="submit" class="auth-primary-btn">Crear primer administrador</button>
                <a href="register.php" class="auth-secondary-btn">Volver a membresias</a>
              </div>
            </div>

            <p class="auth-link-line">
              Ya tienes acceso?
              <a href="login.php">Inicia sesion</a>
            </p>
          </div>
        </main>
      </form>
      <?php else: ?>
      <form method="POST" action="" class="zentra-auth-shell zentra-auth-shell--register">
        <aside class="auth-showcase">
          <a href="<?php echo htmlspecialchars(saasPublicUrl('index.php')); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../../assets/images/logo.svg" alt="Zentra">
          </a>

          <span class="auth-eyebrow">Membresias Zentra</span>
          <h1 class="auth-title">Elige tu plan, mira tus beneficios y activa solo lo que tu operacion necesita.</h1>
          <p class="auth-copy">
            Tu registro ya no es solo una cuenta. Aqui defines la membresia, los limites, el soporte
            y el flujo de cobro correcto para que cada usuario entre con el alcance que realmente pago.
          </p>

          <div class="auth-chip-row">
            <span class="auth-chip"><i class="mdi mdi-check-decagram-outline"></i> Beneficios visibles</span>
            <span class="auth-chip"><i class="mdi mdi-credit-card-outline"></i> Cobro mensual con Stripe</span>
            <span class="auth-chip"><i class="mdi mdi-account-group-outline"></i> Usuarios segun plan</span>
          </div>

          <div class="auth-stat-grid">
            <div class="auth-stat-card">
              <strong><?php echo htmlspecialchars((string) count($plans)); ?></strong>
              <span>membresias disponibles</span>
            </div>
            <div class="auth-stat-card">
              <strong>100%</strong>
              <span>resumen claro de beneficios</span>
            </div>
            <div class="auth-stat-card">
              <strong>Stripe</strong>
              <span>cobro seguro para planes pagos</span>
            </div>
          </div>

          <div class="auth-plan-grid">
            <?php foreach ($selectablePlans as $plan): ?>
            <?php
              $isSelected = (int) ($plan['id_plan'] ?? 0) === $selectedPlanId;
              $priceValue = '$' . number_format((float) ($plan['precio'] ?? 0), 2);
              $periodLabel = !empty($plan['is_free']) ? ' / prueba' : ' / ' . (string) ($plan['periodo'] ?? 'mes');
              $companiesLabel = $formatLimit($plan['limite_empresas'] ?? null, 'empresas', 'Escalable');
              $usersLabel = $formatLimit($plan['limite_usuarios'] ?? null, 'usuarios', 'Escalable');
              $docsLabel = $formatLimit($plan['limite_documentos'] ?? null, 'docs', 'Sin tope fijo');
              $checkoutMode = !empty($plan['is_free']) ? 'free' : (!empty($plan['supports_checkout']) ? 'stripe' : 'pending');
              $featuresPreview = array_slice($plan['caracteristicas'] ?? [], 0, 4);
              $remainingFeatures = max(count($plan['caracteristicas'] ?? []) - count($featuresPreview), 0);
            ?>
            <label
              class="auth-plan-card <?php echo $isSelected ? 'is-selected' : ''; ?>"
              data-plan-card
              data-plan-name="<?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>"
              data-plan-price="<?php echo htmlspecialchars($priceValue); ?>"
              data-plan-period="<?php echo htmlspecialchars($periodLabel); ?>"
              data-plan-description="<?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?>"
              data-plan-companies="<?php echo htmlspecialchars($companiesLabel); ?>"
              data-plan-users="<?php echo htmlspecialchars($usersLabel); ?>"
              data-plan-docs="<?php echo htmlspecialchars($docsLabel); ?>"
              data-plan-checkout="<?php echo htmlspecialchars($checkoutMode); ?>"
              data-plan-benefits="<?php echo $planBenefitsPayload($plan); ?>"
            >
              <input
                type="radio"
                name="id_plan"
                value="<?php echo (int) ($plan['id_plan'] ?? 0); ?>"
                data-plan-radio
                <?php echo $isSelected ? 'checked' : ''; ?>
              >

              <div class="auth-plan-topline">
                <div>
                  <h3 class="auth-plan-title"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></h3>
                  <p class="auth-plan-description"><?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?></p>
                </div>
                <?php if (dbBoolValue($plan['destacado'] ?? false)): ?>
                <span class="auth-plan-badge">Recomendado</span>
                <?php elseif (!empty($plan['is_free'])): ?>
                <span class="auth-plan-badge">Empieza aqui</span>
                <?php else: ?>
                <span class="auth-plan-badge"><?php echo !empty($plan['supports_checkout']) ? 'Stripe listo' : 'Pago pendiente'; ?></span>
                <?php endif; ?>
              </div>

              <p class="auth-plan-price">
                <?php echo htmlspecialchars($priceValue); ?>
                <span><?php echo htmlspecialchars($periodLabel); ?></span>
              </p>

              <div class="auth-plan-meta">
                <span><?php echo htmlspecialchars($companiesLabel); ?></span>
                <span><?php echo htmlspecialchars($usersLabel); ?></span>
                <span><?php echo htmlspecialchars($docsLabel); ?></span>
              </div>

              <ul class="auth-plan-feature-list">
                <?php foreach ($featuresPreview as $feature): ?>
                <li><?php echo htmlspecialchars((string) ($feature['caracteristica'] ?? '')); ?></li>
                <?php endforeach; ?>
              </ul>

              <?php if ($remainingFeatures > 0): ?>
              <div class="auth-plan-footer">y <?php echo (int) $remainingFeatures; ?> beneficios mas dentro del plan.</div>
              <?php endif; ?>
            </label>
            <?php endforeach; ?>

            <?php foreach ($customPlans as $plan): ?>
            <?php
              $companiesLabel = $formatLimit($plan['limite_empresas'] ?? null, 'empresas', 'Escalable');
              $usersLabel = $formatLimit($plan['limite_usuarios'] ?? null, 'usuarios', 'Escalable');
              $docsLabel = $formatLimit($plan['limite_documentos'] ?? null, 'docs', 'Custom');
              $featuresPreview = array_slice($plan['caracteristicas'] ?? [], 0, 4);
            ?>
            <div class="auth-plan-card auth-plan-card--custom">
              <div class="auth-plan-topline">
                <div>
                  <h3 class="auth-plan-title"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Enterprise')); ?></h3>
                  <p class="auth-plan-description"><?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?></p>
                </div>
                <span class="auth-plan-badge">Cotizacion</span>
              </div>

              <p class="auth-plan-price">
                Personalizado
                <span> / ventas</span>
              </p>

              <div class="auth-plan-meta">
                <span><?php echo htmlspecialchars($companiesLabel); ?></span>
                <span><?php echo htmlspecialchars($usersLabel); ?></span>
                <span><?php echo htmlspecialchars($docsLabel); ?></span>
              </div>

              <ul class="auth-plan-feature-list">
                <?php foreach ($featuresPreview as $feature): ?>
                <li><?php echo htmlspecialchars((string) ($feature['caracteristica'] ?? '')); ?></li>
                <?php endforeach; ?>
              </ul>

              <a href="<?php echo htmlspecialchars($contactUrl); ?>" class="auth-plan-link">Hablar con ventas</a>
            </div>
            <?php endforeach; ?>
          </div>
        </aside>

        <main class="auth-panel">
          <div class="auth-card auth-card--register">
            <span class="auth-panel-caption"><i class="mdi mdi-account-plus-outline"></i> Registro de usuarios</span>
            <h2 class="auth-title auth-title--panel">Crea tu cuenta</h2>
            <p class="auth-copy auth-copy--panel">
              Completa tus datos, selecciona el plan y deja que Zentra aplique los beneficios correctos desde el primer acceso.
            </p>

            <?php if ($flash): ?>
            <div class="auth-alert <?php echo htmlspecialchars($alertClass); ?>" role="alert">
              <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
              <?php if (!empty($flash['meta']['contact_url'])): ?>
                <a href="<?php echo htmlspecialchars((string) $flash['meta']['contact_url']); ?>" class="auth-contact-link">Hablar con ventas</a>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$stripeReady): ?>
            <div class="auth-alert auth-alert--warning" role="alert">
              Los planes pagos ya estan preparados para Stripe, pero en este entorno aun faltan las claves o Price IDs de produccion.
            </div>
            <?php endif; ?>

            <?php if ($allowAdminSelection): ?>
            <div class="auth-setup-card" style="margin-top: 1.2rem;">
              <h3>Primera configuracion del sistema</h3>
              <p>Si aun no existe ningun administrador activo y necesitas crear el primero, puedes abrir el modo de instalacion.</p>
              <a href="register.php?bootstrap=admin" class="auth-inline-link">Crear primer administrador</a>
            </div>
            <?php endif; ?>

            <input type="hidden" name="rol" value="user">

            <div class="auth-social-stack">
              <button type="button" class="auth-social-btn" data-auth-google="register">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.3 0-5.9-2.7-5.9-6s2.6-6 5.9-6c1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 6.8 2.2 2.6 6.4 2.6 11.6S6.8 21 12 21c6.9 0 9.1-4.8 9.1-7.3 0-.5-.1-.9-.1-1.3H12Z"></path>
                  <path fill="#34A853" d="M3.6 7.1l3.2 2.4c.9-1.8 2.8-3.1 5.2-3.1 1.9 0 3.2.8 3.9 1.5l2.7-2.6C16.9 3.2 14.7 2.2 12 2.2 8.3 2.2 5 4.3 3.6 7.1Z"></path>
                  <path fill="#FBBC05" d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.5c-.8.6-1.9 1-3.3 1-2.5 0-4.5-1.7-5.3-4l-3.3 2.5C4.8 18.6 8.1 21 12 21Z"></path>
                  <path fill="#4285F4" d="M21.1 13.7c0-.5-.1-.9-.1-1.3H12v3.9h5.4c-.3 1.2-1 2.2-2.1 3l3.1 2.5c1.8-1.7 2.7-4.1 2.7-7.1Z"></path>
                </svg>
                Registrarme con Google
                <span>Espacio listo</span>
              </button>
              <div class="auth-social-note" data-google-note="register">
                Ya reservamos el espacio de Google en esta experiencia. Solo falta enlazar las credenciales `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET`.
              </div>
              <div class="auth-divider">o completa tu cuenta</div>
            </div>

            <div class="auth-form">
              <div class="auth-form-grid">
                <div class="auth-field">
                  <label for="registerName">Nombre completo</label>
                  <input
                    type="text"
                    name="nombre_completo"
                    id="registerName"
                    class="auth-input"
                    placeholder="Como aparecera tu cuenta"
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
                    placeholder="correo@empresa.com"
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
                    placeholder="tuusuario"
                    autocomplete="username"
                    value="<?php echo htmlspecialchars((string) ($old['username'] ?? '')); ?>"
                    required
                  >
                  <div class="auth-field-helper">Usa letras, numeros, punto, guion o guion bajo.</div>
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
                  <label for="registerPassword">Clave</label>
                  <input
                    type="password"
                    name="password"
                    id="registerPassword"
                    class="auth-input"
                    placeholder="Minimo 6 caracteres"
                    autocomplete="new-password"
                    required
                  >
                </div>

                <div class="auth-field">
                  <label for="registerPasswordConfirm">Confirmar clave</label>
                  <input
                    type="password"
                    name="confirm_password"
                    id="registerPasswordConfirm"
                    class="auth-input"
                    placeholder="Repite tu clave"
                    autocomplete="new-password"
                    required
                  >
                </div>
              </div>

              <?php if ($selectedPlan !== null): ?>
              <div class="auth-summary-card">
                <div class="auth-summary-topline">
                  <div>
                    <h3 data-plan-summary-name><?php echo htmlspecialchars((string) ($selectedPlan['nombre'] ?? 'Plan')); ?></h3>
                    <p data-plan-summary-description><?php echo htmlspecialchars((string) ($selectedPlan['descripcion'] ?? '')); ?></p>
                  </div>
                  <div class="auth-summary-price">
                    <span data-plan-summary-price><?php echo htmlspecialchars('$' . number_format((float) ($selectedPlan['precio'] ?? 0), 2)); ?></span>
                    <span data-plan-summary-period><?php echo htmlspecialchars(!empty($selectedPlan['is_free']) ? ' / prueba' : ' / ' . (string) ($selectedPlan['periodo'] ?? 'mes')); ?></span>
                  </div>
                </div>

                <div class="auth-summary-meta">
                  <span data-plan-summary-companies><?php echo htmlspecialchars($formatLimit($selectedPlan['limite_empresas'] ?? null, 'empresas', 'Escalable')); ?></span>
                  <span data-plan-summary-users><?php echo htmlspecialchars($formatLimit($selectedPlan['limite_usuarios'] ?? null, 'usuarios', 'Escalable')); ?></span>
                  <span data-plan-summary-docs><?php echo htmlspecialchars($formatLimit($selectedPlan['limite_documentos'] ?? null, 'docs', 'Sin tope fijo')); ?></span>
                </div>

                <ul class="auth-summary-benefits" data-plan-benefits-list>
                  <?php foreach (array_slice($selectedPlan['caracteristicas'] ?? [], 0, 4) as $feature): ?>
                  <li><?php echo htmlspecialchars((string) ($feature['caracteristica'] ?? '')); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <div class="auth-submit-stack">
                <button type="submit" class="auth-primary-btn" data-plan-submit>
                  <span data-plan-submit-label>Crear cuenta gratis</span>
                </button>
                <div class="auth-submit-note" data-plan-note>
                  La cuenta se crea al instante y luego podras cambiar de plan cuando quieras.
                </div>
                <a href="<?php echo htmlspecialchars($contactUrl); ?>" class="auth-secondary-btn">Hablar con ventas</a>
              </div>
            </div>

            <p class="auth-link-line">
              Ya tienes una cuenta?
              <a href="login.php">Inicia sesion</a>
            </p>
          </div>
        </main>
      </form>
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
