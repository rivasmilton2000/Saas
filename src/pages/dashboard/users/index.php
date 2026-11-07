<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'role' => (string) ($_GET['role'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
    'provider' => (string) ($_GET['provider'] ?? ''),
    'from' => (string) ($_GET['from'] ?? ''),
    'to' => (string) ($_GET['to'] ?? ''),
];
$users = $userService->list($filters);
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
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
              <div>
                <h2 class="mb-1">Usuarios</h2>
                <p class="text-muted mb-0">Cuentas, invitaciones, roles y métodos de acceso.</p>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="<?= e(app_url('src/dashboard/users/form.php?mode=email')) ?>">
                  <i class="mdi mdi-email-plus-outline me-1"></i>Invitar por correo
                </a>
                <a class="btn btn-primary text-white" href="<?= e(app_url('src/dashboard/users/form.php?mode=complete')) ?>">
                  <i class="mdi mdi-account-plus-outline me-1"></i>Crear cuenta completa
                </a>
              </div>
            </div>

            <div class="card card-rounded mb-4">
              <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                  <div class="col-lg-4">
                    <label class="form-label" for="users-q">Buscar</label>
                    <input class="form-control" id="users-q" name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre, usuario o correo">
                  </div>
                  <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="users-role">Rol</label>
                    <select class="form-select" id="users-role" name="role">
                      <option value="">Todos</option>
                      <option value="administrador" <?= $filters['role'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                      <option value="usuario" <?= $filters['role'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                    </select>
                  </div>
                  <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="users-status">Estado</label>
                    <select class="form-select" id="users-status" name="status">
                      <option value="">Todos</option>
                      <?php foreach (['pendiente', 'activo', 'inactivo', 'bloqueado'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="users-provider">Acceso</label>
                    <select class="form-select" id="users-provider" name="provider">
                      <option value="">Todos</option>
                      <option value="local" <?= $filters['provider'] === 'local' ? 'selected' : '' ?>>Contraseña</option>
                      <option value="google" <?= $filters['provider'] === 'google' ? 'selected' : '' ?>>Google</option>
                      <option value="sin_acceso" <?= $filters['provider'] === 'sin_acceso' ? 'selected' : '' ?>>Sin acceso configurado</option>
                    </select>
                  </div>
                  <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="users-from">Registro desde</label>
                    <input class="form-control" id="users-from" type="date" name="from" value="<?= e($filters['from']) ?>">
                  </div>
                  <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="users-to">Registro hasta</label>
                    <input class="form-control" id="users-to" type="date" name="to" value="<?= e($filters['to']) ?>">
                  </div>
                  <div class="col-lg-2 d-grid">
                    <button class="btn btn-primary text-white" type="submit"><i class="mdi mdi-filter-outline me-1"></i>Filtrar</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="card card-rounded">
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-middle sietelsa-users-table">
                    <thead>
                      <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acceso</th>
                        <th>Creación</th>
                        <th>Último acceso</th>
                        <th class="text-end">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($users === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No se encontraron usuarios.</td></tr>
                      <?php endif; ?>
                      <?php foreach ($users as $user): ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <img class="sietelsa-user-avatar" data-avatar-user="<?= (int)$user['id'] ?>" src="<?= e(user_avatar_url($user)) ?>" alt="">
                              <div>
                                <strong><?= e(trim($user['nombre'] . ' ' . $user['apellido']) ?: 'Registro pendiente') ?></strong>
                                <span class="d-block small text-muted"><?= e((string) ($user['nombre_usuario'] ?: 'Sin usuario')) ?> · <?= e((string) $user['correo']) ?></span>
                              </div>
                            </div>
                          </td>
                          <td><?= e(ucfirst((string) $user['rol'])) ?></td>
                          <td><span class="badge sietelsa-status-<?= e((string) $user['estado']) ?>"><?= e(ucfirst((string) $user['estado'])) ?></span></td>
                          <td><?= e((string) $user['proveedores']) ?></td>
                          <td><?= e(date('d/m/Y H:i', strtotime((string) $user['creado_en']))) ?></td>
                          <td><?= $user['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime((string) $user['ultimo_acceso']))) : 'Nunca' ?></td>
                          <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                              <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('src/dashboard/users/view.php?id=' . (int) $user['id'])) ?>">
                                <i class="mdi mdi-eye-outline me-1"></i>Administrar
                              </a>
                              <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('src/dashboard/users/form.php?mode=edit&id=' . (int) $user['id'])) ?>">
                                <i class="mdi mdi-pencil-outline me-1"></i>Editar
                              </a>
                              <?php if ((int) $user['id'] !== (int) $currentUser['id'] && in_array($user['estado'], ['activo', 'inactivo'], true)): ?>
                                <?php $newStatus = $user['estado'] === 'activo' ? 'inactivo' : 'activo'; ?>
                                <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>" data-confirm="<?= $newStatus === 'inactivo' ? 'La cuenta quedará desactivada, conservará sus datos y sus sesiones activas serán invalidadas.' : 'La cuenta recuperará el acceso al sistema.' ?>">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                  <input type="hidden" name="action" value="status">
                                  <input type="hidden" name="value" value="<?= e($newStatus) ?>">
                                  <button class="btn btn-sm btn-outline-<?= $newStatus === 'inactivo' ? 'warning' : 'success' ?>" type="submit"><?= $newStatus === 'inactivo' ? 'Desactivar' : 'Reactivar' ?></button>
                                </form>
                              <?php endif; ?>
                              <?php if ((int) $user['id'] !== (int) $currentUser['id']): ?>
                                <form method="post" action="<?= e(app_url('src/dashboard/users/action.php')) ?>"
                                      data-confirm="Eliminarás definitivamente a <?= e(trim((string) $user['nombre'] . ' ' . (string) $user['apellido']) ?: 'esta cuenta') ?> (<?= e((string) $user['correo']) ?>). No podrá recuperarse desde el sistema."
                                      data-confirm-button="Sí, eliminar" data-confirm-danger="true">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                  <input type="hidden" name="action" value="delete_permanently">
                                  <input type="hidden" name="confirmation" value="ELIMINAR">
                                  <button class="btn btn-sm btn-outline-danger" type="submit"><i class="mdi mdi-delete-forever me-1"></i>Eliminar</button>
                                </form>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
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
