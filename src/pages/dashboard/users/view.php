<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$user = $userService->find((int) ($_GET['id'] ?? 0));
if ($user === null || $user['eliminado_en'] !== null) {
    flash_set('error', 'Usuario no encontrado', 'La cuenta solicitada no existe.');
    users_redirect();
}
$birthYear = $user['fecha_nacimiento']
    ? (new DateTimeImmutable((string) $user['fecha_nacimiento']))->format('Y')
    : 'No registrada';
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
            <a href="<?= e(app_url('src/dashboard/users/index.php')) ?>" class="text-decoration-none"><i class="mdi mdi-arrow-left"></i> Volver a usuarios</a>
            <div class="row mt-3 g-4">
              <div class="col-xl-4">
                <div class="card card-rounded h-100">
                  <div class="card-body text-center">
                    <img class="sietelsa-user-avatar sietelsa-user-avatar-lg mb-3" data-avatar-user="<?= (int)$user['id'] ?>" src="<?= e(user_avatar_url($user)) ?>" alt="Avatar de <?= e((string) ($user['nombre_usuario'] ?: $user['correo'])) ?>">
                    <h2 class="h4"><?= e(trim($user['nombre'] . ' ' . $user['apellido']) ?: 'Registro pendiente') ?></h2>
                    <p class="text-muted"><?= e((string) ($user['nombre_usuario'] ?: 'Sin nombre de usuario')) ?></p>
                    <span class="badge sietelsa-status-<?= e((string) $user['estado']) ?>"><?= e(ucfirst((string) $user['estado'])) ?></span>
                    <div class="d-grid gap-2 mt-4">
                      <a class="btn btn-outline-primary" href="<?= e(app_url('src/dashboard/users/form.php?mode=edit&id=' . (int) $user['id'])) ?>"><i class="mdi mdi-pencil-outline me-1"></i>Editar datos</a>
                      <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>" data-confirm="Se restablecerá el avatar de esta cuenta. ¿Deseas continuar?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="action" value="reset_avatar">
                        <button class="btn btn-outline-secondary w-100" type="submit">Restablecer avatar</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-8">
                <div class="card card-rounded mb-4">
                  <div class="card-body">
                    <h3 class="h5 mb-3">Información de la cuenta</h3>
                    <dl class="row mb-0 sietelsa-user-details">
                      <dt class="col-sm-5">Correo</dt><dd class="col-sm-7 text-break"><?= e((string) $user['correo']) ?></dd>
                      <dt class="col-sm-5">Rol</dt><dd class="col-sm-7"><?= e(ucfirst((string) $user['rol'])) ?></dd>
                      <dt class="col-sm-5">Fecha de nacimiento</dt><dd class="col-sm-7"><?= $user['fecha_nacimiento'] ? e(date('d/m/Y', strtotime((string) $user['fecha_nacimiento']))) : 'No registrada' ?></dd>
                      <dt class="col-sm-5">Año de nacimiento</dt><dd class="col-sm-7"><?= e($birthYear) ?></dd>
                      <dt class="col-sm-5">Proveedor</dt><dd class="col-sm-7"><?= e((string) $user['proveedores']) ?></dd>
                      <dt class="col-sm-5">Correo verificado</dt><dd class="col-sm-7"><?= $user['correo_verificado_en'] ? e(date('d/m/Y H:i', strtotime((string) $user['correo_verificado_en']))) : 'No' ?></dd>
                      <dt class="col-sm-5">Debe cambiar contraseña</dt><dd class="col-sm-7"><?= (int) $user['debe_cambiar_password'] === 1 ? 'Sí' : 'No' ?></dd>
                      <dt class="col-sm-5">Creación</dt><dd class="col-sm-7"><?= e(date('d/m/Y H:i', strtotime((string) $user['creado_en']))) ?></dd>
                      <dt class="col-sm-5">Último inicio</dt><dd class="col-sm-7"><?= $user['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime((string) $user['ultimo_acceso']))) : 'Nunca' ?></dd>
                      <dt class="col-sm-5">Fecha de desactivación</dt><dd class="col-sm-7"><?= $user['desactivado_en'] ? e(date('d/m/Y H:i', strtotime((string) $user['desactivado_en']))) : 'No aplica' ?></dd>
                    </dl>
                  </div>
                </div>

                <div class="card card-rounded">
                  <div class="card-body">
                    <h3 class="h5 mb-3">Administración</h3>
                    <?php if ($user['estado'] === 'pendiente'): ?>
                      <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>" class="mb-3">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="action" value="resend">
                        <button class="btn btn-outline-primary" type="submit"><i class="mdi mdi-email-sync-outline me-1"></i>Reenviar invitación</button>
                      </form>
                    <?php endif; ?>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>" data-confirm="Confirma el cambio de rol para esta cuenta.">
                          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="action" value="role">
                          <label class="form-label" for="detail-role">Cambiar rol</label>
                          <div class="input-group">
                            <select class="form-select" id="detail-role" name="value">
                              <option value="usuario" <?= $user['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                              <option value="administrador" <?= $user['rol'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Aplicar</button>
                          </div>
                        </form>
                      </div>
                      <div class="col-md-6">
                        <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>" data-confirm="Confirma el cambio de estado para esta cuenta.">
                          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="action" value="status">
                          <label class="form-label" for="detail-status">Desactivar o reactivar</label>
                          <div class="input-group">
                            <select class="form-select" id="detail-status" name="value">
                              <?php foreach (['activo', 'inactivo', 'bloqueado'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $user['estado'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Aplicar</button>
                          </div>
                        </form>
                      </div>
                    </div>
                    <hr class="my-4">
                    <div class="border border-danger rounded p-3">
                      <h4 class="h6 text-danger">Eliminar definitivamente</h4>
                      <p class="small text-muted">Esta acción elimina la cuenta y sus métodos de acceso. No puede recuperarse desde el sistema y se bloqueará si existe historial que deba conservarse.</p>
                      <?php if ((int) $user['id'] === (int) $currentUser['id']): ?>
                        <button class="btn btn-outline-danger" type="button" disabled>No puedes eliminar tu propia cuenta</button>
                      <?php else: ?>
                        <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>"
                              data-confirm="Eliminarás definitivamente a <?= e(trim((string) $user['nombre'] . ' ' . (string) $user['apellido']) ?: 'esta cuenta') ?> (<?= e((string) $user['correo']) ?>). Esta acción no se puede deshacer."
                              data-confirm-button="Sí, eliminar" data-confirm-danger="true">
                          <?= csrf_field() ?>
                          <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                          <input type="hidden" name="action" value="delete_permanently">
                          <input type="hidden" name="confirmation" value="ELIMINAR">
                          <button class="btn btn-outline-danger" type="submit"><i class="mdi mdi-delete-forever me-1"></i>Eliminar definitivamente</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
  </body>
</html>
