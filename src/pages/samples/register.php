<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';

requireGuest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::register();
}

$flash = getFlash('register');
$allowAdminSelection = UsuarioModel::canSelectAdminOnPublicRegister($pdo);
$old = array_merge([
    'username' => '',
    'rol'      => $allowAdminSelection ? 'admin' : 'user',
], is_array($flash['meta']['old'] ?? null) ? $flash['meta']['old'] : []);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registro - Saas Contabilidad</title>
    <link rel="stylesheet" href="../../assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="../../assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="shortcut icon" href="../../assets/images/favicon.png" />
  </head>
  <body>
    <div class="container-scroller">
      <div class="container-fluid page-body-wrapper full-page-wrapper">
        <div class="content-wrapper d-flex align-items-center auth px-0">
          <div class="row w-100 mx-0">
            <div class="col-lg-4 mx-auto">
              <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                <div class="brand-logo">
                  <img src="../../assets/images/logo.svg" alt="logo">
                </div>
                <h4>Crea tu cuenta</h4>
                <h6 class="font-weight-light">Registra un perfil para entrar al sistema.</h6>
                <form class="pt-3" method="POST" action="">
                  <?php if ($flash): ?>
                  <div class="alert alert-<?php echo ($flash['type'] ?? 'info') === 'danger' ? 'danger' : 'info'; ?>" role="alert">
                    <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                  </div>
                  <?php endif; ?>
                  <div class="form-group">
                    <input
                      type="text"
                      name="username"
                      class="form-control form-control-lg"
                      id="registerUsername"
                      placeholder="Nombre de usuario"
                      autocomplete="username"
                      value="<?php echo htmlspecialchars((string) $old['username']); ?>"
                      required
                    >
                  </div>
                  <?php if ($allowAdminSelection): ?>
                  <div class="form-group">
                    <select class="form-select form-select-lg" name="rol" id="registerRole">
                      <option value="admin" <?php echo ($old['rol'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                      <option value="user" <?php echo ($old['rol'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Usuario</option>
                    </select>
                    <small class="text-muted d-block mt-2">Aun no existe un administrador, asi que puedes crear el primero desde esta pantalla.</small>
                  </div>
                  <?php else: ?>
                  <input type="hidden" name="rol" value="user">
                  <div class="alert alert-light border" role="alert">
                    El registro publico crea cuentas tipo <strong>user</strong>. Los perfiles <strong>admin</strong> se agregan desde el dashboard.
                  </div>
                  <?php endif; ?>
                  <div class="form-group">
                    <input
                      type="password"
                      name="password"
                      class="form-control form-control-lg"
                      id="registerPassword"
                      placeholder="Clave"
                      autocomplete="new-password"
                      required
                    >
                  </div>
                  <div class="form-group">
                    <input
                      type="password"
                      name="confirm_password"
                      class="form-control form-control-lg"
                      id="registerPasswordConfirm"
                      placeholder="Confirmar clave"
                      autocomplete="new-password"
                      required
                    >
                  </div>
                  <div class="mt-3 d-grid gap-2">
                    <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">CREAR CUENTA</button>
                  </div>
                  <div class="text-center mt-4 font-weight-light"> Ya tienes una cuenta? <a href="login.php" class="text-primary">Inicia sesion</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="../../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../../assets/js/off-canvas.js"></script>
    <script src="../../assets/js/template.js"></script>
    <script src="../../assets/js/settings.js"></script>
    <script src="../../assets/js/todolist.js"></script>
  </body>
</html>
