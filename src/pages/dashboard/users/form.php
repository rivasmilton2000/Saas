<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$mode = (string) ($_GET['mode'] ?? 'complete');
$editing = $mode === 'edit';
$user = $editing ? $userService->find((int) ($_GET['id'] ?? 0)) : null;
if ($editing && ($user === null || $user['eliminado_en'] !== null)) {
    flash_set('error', 'Usuario no encontrado', 'La cuenta solicitada no existe.');
    users_redirect();
}
$suggestedUsername = !$editing && $mode === 'complete'
    ? $userService->generateUsername('nuevo.usuario@example.test')
    : '';
define('SIETELSA_DASHBOARD_RENDER', true);
?>
<!DOCTYPE html>
<html lang="es">
  <?php require dirname(__DIR__) . '/partials/_head.php'; ?>
  <body class="with-welcome-text">
    <div class="container-scroller">
      <?php require dirname(__DIR__) . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require dirname(__DIR__) . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="mb-4">
              <a href="<?= e(app_url('src/dashboard/users/index.php')) ?>" class="text-decoration-none"><i class="mdi mdi-arrow-left"></i> Volver a usuarios</a>
              <h2 class="mt-3 mb-1"><?= $mode === 'email' ? 'Invitar por correo' : ($editing ? 'Editar usuario' : 'Crear cuenta completa') ?></h2>
              <p class="text-muted">
                <?= $mode === 'email'
                    ? 'La cuenta permanecerá pendiente hasta que el usuario complete su perfil.'
                    : ($editing ? 'Actualiza los datos del perfil. El rol se administra desde el detalle.' : 'La contraseña inicial será temporal y deberá cambiarse en el primer acceso.') ?>
              </p>
            </div>

            <div class="card card-rounded">
              <div class="card-body p-4">
                <form method="post" action="<?= e(app_url('src/dashboard/users/save.php')) ?>" enctype="multipart/form-data" class="sietelsa-user-form" novalidate>
                  <?= csrf_field() ?>
                  <input type="hidden" name="mode" value="<?= e($mode) ?>">
                  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><?php endif; ?>

                  <?php if ($mode === 'email'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="user-email">Correo electrónico</label>
                      <input class="form-control form-control-lg" type="email" id="user-email" name="correo" maxlength="190" required autocomplete="off">
                      <div class="form-text">No se creará una cuenta para correos no autorizados desde el flujo público.</div>
                    </div>
                  <?php else: ?>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label" for="user-name">Nombre</label>
                        <input class="form-control" id="user-name" name="nombre" maxlength="120" required value="<?= e((string) ($user['nombre'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="user-last-name">Apellido</label>
                        <input class="form-control" id="user-last-name" name="apellido" maxlength="120" required value="<?= e((string) ($user['apellido'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="user-username">Nombre de usuario</label>
                        <input class="form-control" id="user-username" name="nombre_usuario" maxlength="32" required value="<?= e((string) ($user['nombre_usuario'] ?? $suggestedUsername)) ?>" pattern="[a-z0-9][a-z0-9._-]{2,31}">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="user-email">Correo electrónico</label>
                        <input class="form-control" type="email" id="user-email" name="correo" maxlength="190" required value="<?= e((string) ($user['correo'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="user-birth-date">Fecha de nacimiento</label>
                        <input class="form-control" type="date" id="user-birth-date" name="fecha_nacimiento" required max="<?= e(date('Y-m-d')) ?>" value="<?= e((string) ($user['fecha_nacimiento'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="user-role">Rol</label>
                        <select class="form-select" id="user-role" name="rol" <?= $editing ? 'disabled' : '' ?>>
                          <option value="usuario" <?= ($user['rol'] ?? '') === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                          <option value="administrador" <?= ($user['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                        <?php if ($editing): ?><input type="hidden" name="rol" value="<?= e((string) $user['rol']) ?>"><?php endif; ?>
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="user-avatar">Avatar personalizado opcional</label>
                        <input class="form-control" type="file" id="user-avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG o WEBP; máximo 2 MB; entre 128 y 3000 píxeles.</div>
                      </div>

                      <?php if (!$editing): ?>
                        <div class="col-md-6">
                          <label class="form-label" for="user-password">Contraseña temporal</label>
                          <div class="input-group">
                            <input class="form-control" type="password" id="user-password" name="password" maxlength="4096" required autocomplete="new-password" data-password-input data-email-source="#user-email" data-username-source="#user-username">
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle="#user-password" aria-label="Mostrar contraseña"><i class="mdi mdi-eye-outline"></i></button>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label" for="user-password-confirm">Confirmar contraseña</label>
                          <div class="input-group">
                            <input class="form-control" type="password" id="user-password-confirm" name="password_confirmation" maxlength="4096" required autocomplete="new-password" data-password-confirmation="#user-password">
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle="#user-password-confirm" aria-label="Mostrar contraseña"><i class="mdi mdi-eye-outline"></i></button>
                          </div>
                          <div class="form-text" data-password-match></div>
                        </div>
                        <div class="col-12"><?php require SIETELSA_ROOT . '/includes/users/password-requirements.php'; ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary text-white" type="submit">
                      <i class="mdi mdi-content-save-outline me-1"></i><?= $mode === 'email' ? 'Crear invitación' : 'Guardar usuario' ?>
                    </button>
                    <a class="btn btn-light" href="<?= e(app_url('src/dashboard/users/index.php')) ?>">Cancelar</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
    <script src="<?= e(app_url('includes/assets/js/password-strength.js')) ?>"></script>
  </body>
</html>
