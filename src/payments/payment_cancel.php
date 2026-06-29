<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/app.php';

$publicHomeUrl = saasPublicUrl('index.php');
$isLogged = isLoggedIn();
$retryUrl = $isLogged ? '/Saas/src/pages/samples/select-plan.php' : '/Saas/src/pages/samples/register.php';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Pago cancelado - Zentra</title>
    <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth-zentra.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
  </head>
  <body class="zentra-auth-body">
    <div class="zentra-auth-page">
      <main class="auth-shell auth-shell--login">
        <section class="auth-surface">
          <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-brand" aria-label="Zentra">
            <img src="../assets/images/logo.svg" alt="Zentra">
          </a>

          <header class="auth-header">
            <h1 class="auth-title">Pago cancelado</h1>
            <p class="auth-subtitle">Puedes volver a elegir tu plan o intentarlo de nuevo cuando quieras.</p>
          </header>

          <div class="auth-submit-wrap">
            <a href="<?php echo htmlspecialchars($retryUrl); ?>" class="auth-primary-btn">Volver a elegir plan</a>
            <a href="<?php echo htmlspecialchars($publicHomeUrl); ?>" class="auth-social-btn">Volver al sitio</a>
          </div>
        </section>
      </main>
    </div>
  </body>
</html>
