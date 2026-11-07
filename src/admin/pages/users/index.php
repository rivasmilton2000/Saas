<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/countries.php';
require_once __DIR__ . '/../../../models/PlanModel.php';
require_once __DIR__ . '/../../../models/UsuarioModel.php';
require_once __DIR__ . '/../../../services/BitacoraService.php';

$redirectToUsers = static function () use ($adminBaseUrl): void {
    header('Location: ' . $adminBaseUrl . '?page=users');
    exit;
};

$adminUserId = (int) ($session['id_usuario'] ?? 0);
$registerAdminLog = static function (
    string $action,
    string $description,
    int $targetUserId = 0,
    array $context = []
) use ($pdo, $adminUserId, $session): void {
    BitacoraService::registrar(
        $pdo,
        $adminUserId,
        'admin_usuarios',
        $action,
        $description,
        [
            'username' => (string) ($session['username'] ?? ''),
            'rol' => (string) ($session['rol'] ?? 'admin'),
            'entidad_tipo' => $targetUserId > 0 ? 'usuario' : null,
            'entidad_id' => $targetUserId > 0 ? $targetUserId : null,
            'contexto' => $context,
        ]
    );
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'create_user') {
        $validation = UsuarioModel::validateNewUser($pdo, $_POST, true);

        if (!($validation['ok'] ?? false)) {
            setFlash('admin_users', (string) ($validation['message'] ?? 'No se pudo crear el usuario.'), 'danger', [
                'open_create_modal' => true,
                'old' => $validation['old'] ?? [],
            ]);
            $redirectToUsers();
        }

        $newUserId = UsuarioModel::create($pdo, $validation['data']);
        $registerAdminLog(
            'crear_usuario',
            'Creo el usuario ' . (string) ($validation['data']['username'] ?? 'usuario') . '.',
            $newUserId,
            [
                'usuario_objetivo' => (string) ($validation['data']['username'] ?? ''),
                'rol_objetivo' => (string) ($validation['data']['rol'] ?? 'user'),
            ]
        );

        setFlash('admin_users', 'Usuario creado correctamente.', 'success');
        $redirectToUsers();
    }

    if ($action === 'edit_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            setFlash('admin_users', 'Debes indicar el usuario que quieres editar.', 'danger');
            $redirectToUsers();
        }

        $validation = UsuarioModel::validateUserUpdate($pdo, $targetUserId, $_POST, true);

        if (!($validation['ok'] ?? false)) {
            setFlash('admin_users', (string) ($validation['message'] ?? 'No se pudo actualizar el usuario.'), 'danger', [
                'open_edit_modal' => true,
                'old' => array_merge(['id' => $targetUserId], $validation['old'] ?? []),
            ]);
            $redirectToUsers();
        }

        UsuarioModel::update($pdo, $targetUserId, $validation['data']);
        $registerAdminLog(
            'editar_usuario',
            'Actualizo el usuario ' . (string) ($validation['data']['username'] ?? 'usuario') . '.',
            $targetUserId,
            [
                'usuario_objetivo' => (string) ($validation['data']['username'] ?? ''),
                'rol_objetivo' => (string) ($validation['data']['rol'] ?? 'user'),
            ]
        );

        if ($targetUserId === $adminUserId) {
            $_SESSION['username'] = $validation['data']['username'];
            $_SESSION['rol'] = $validation['data']['rol'];
        }

        setFlash('admin_users', 'Usuario actualizado correctamente.', 'success');
        $redirectToUsers();
    }

    if ($action === 'delete_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            setFlash('admin_users', 'Debes indicar el usuario que quieres desactivar.', 'danger');
            $redirectToUsers();
        }

        $targetUser = UsuarioModel::getById($pdo, $targetUserId);
        if ($targetUser === null) {
            setFlash('admin_users', 'El usuario que intentas desactivar ya no existe.', 'danger');
            $redirectToUsers();
        }

        if ($targetUserId === $adminUserId) {
            setFlash('admin_users', 'No puedes desactivar tu propia sesion.', 'danger');
            $redirectToUsers();
        }

        if ((string) ($targetUser['rol'] ?? '') === 'admin' && UsuarioModel::countAdmins($pdo, $targetUserId) === 0) {
            setFlash('admin_users', 'No puedes desactivar al ultimo administrador activo.', 'danger');
            $redirectToUsers();
        }

        UsuarioModel::deactivate($pdo, $targetUserId);
        $registerAdminLog(
            'desactivar_usuario',
            'Desactivo el usuario ' . (string) ($targetUser['username'] ?? 'usuario') . '.',
            $targetUserId,
            [
                'usuario_objetivo' => (string) ($targetUser['username'] ?? ''),
                'rol_objetivo' => (string) ($targetUser['rol'] ?? 'user'),
            ]
        );

        setFlash('admin_users', 'Usuario desactivado correctamente.', 'success');
        $redirectToUsers();
    }

    setFlash('admin_users', 'Accion no reconocida.', 'danger');
    $redirectToUsers();
}

$users = UsuarioModel::getActivos($pdo);
$plans = PlanModel::getSelectablePlans($pdo);
$countries = getCountryOptions();
$flash = getFlash('admin_users');
$flashMeta = is_array($flash['meta'] ?? null) ? $flash['meta'] : [];

$defaultPlanId = 0;
foreach ($plans as $plan) {
    if ((string) ($plan['slug'] ?? '') === 'free') {
        $defaultPlanId = (int) ($plan['id_plan'] ?? 0);
        break;
    }
}

$defaultCountry = array_key_exists('El Salvador', $countries) ? 'El Salvador' : (string) array_key_first($countries);
$createOld = array_merge([
    'username' => '',
    'rol' => 'user',
    'pais' => $defaultCountry,
    'id_plan' => $defaultPlanId,
], is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : []);
$editOld = is_array($flashMeta['old'] ?? null) ? $flashMeta['old'] : [];

$summary = [
    'total' => count($users),
    'admins' => 0,
    'customers' => 0,
    'paid' => 0,
];

foreach ($users as $user) {
    if ((string) ($user['rol'] ?? 'user') === 'admin') {
        $summary['admins']++;
    } else {
        $summary['customers']++;
    }

    if (($user['plan_precio'] ?? null) !== null && (float) ($user['plan_precio'] ?? 0) > 0) {
        $summary['paid']++;
    }
}

if (!function_exists('adminUserDate')) {
    function adminUserDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('America/El_Salvador')))->format('d/m/Y H:i');
        } catch (Throwable $exception) {
            return $value;
        }
    }
}

if (!function_exists('adminUserPlanLabel')) {
    function adminUserPlanLabel(array $user): string
    {
        $planName = trim((string) ($user['plan_nombre'] ?? ''));
        if ($planName === '') {
            return (string) (($user['rol'] ?? 'user') === 'admin' ? 'Sin plan' : 'Pendiente');
        }

        return $planName;
    }
}

$flashClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'info',
};
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Usuarios</h3>
          <p class="text-muted mb-0">Gestiona administradores, clientes, planes y estados de acceso.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminCreateUserModal">
          <i class="mdi mdi-account-plus-outline me-1"></i> Nuevo usuario
        </button>
      </div>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="alert alert-<?php echo htmlspecialchars($flashClass); ?>" role="alert">
    <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="text-muted mb-1">Usuarios activos</p>
          <h3 class="mb-0"><?php echo number_format($summary['total']); ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="text-muted mb-1">Clientes</p>
          <h3 class="mb-0"><?php echo number_format($summary['customers']); ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="text-muted mb-1">Administradores</p>
          <h3 class="mb-0"><?php echo number_format($summary['admins']); ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <p class="text-muted mb-1">Con plan pagado</p>
          <h3 class="mb-0"><?php echo number_format($summary['paid']); ?></h3>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
              <h4 class="card-title mb-1">Listado de usuarios</h4>
              <p class="text-muted mb-0">Busca por usuario, correo, rol, plan, pais o estado.</p>
            </div>
            <div style="min-width: min(100%, 320px);">
              <label class="visually-hidden" for="admin_user_search">Buscar usuario</label>
              <div class="input-group">
                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                <input
                  type="search"
                  class="form-control"
                  id="admin_user_search"
                  placeholder="Buscar usuario..."
                  autocomplete="off"
                >
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Rol</th>
                  <th>Plan</th>
                  <th>Suscripcion</th>
                  <th>Pais</th>
                  <th>Ultimo login</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                  $role = (string) ($user['rol'] ?? 'user');
                  $status = trim((string) ($user['suscripcion_estado'] ?? ''));
                  $statusLabel = $status !== '' ? $status : ($role === 'admin' ? 'admin' : 'sin estado');
                  $searchText = implode(' ', [
                      (string) ($user['username'] ?? ''),
                      (string) ($user['email'] ?? ''),
                      $role === 'admin' ? 'administrador admin' : 'cliente user',
                      adminUserPlanLabel($user),
                      $statusLabel,
                      (string) ($user['pais'] ?? 'El Salvador'),
                  ]);
                ?>
                <tr data-user-search="<?php echo htmlspecialchars(mb_strtolower($searchText, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($user['email'] ?? 'Sin correo')); ?></div>
                  </td>
                  <td>
                    <span class="badge <?php echo $role === 'admin' ? 'badge-primary' : 'badge-info'; ?>">
                      <?php echo htmlspecialchars($role === 'admin' ? 'Administrador' : 'Cliente'); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars(adminUserPlanLabel($user)); ?></td>
                  <td><?php echo htmlspecialchars($statusLabel); ?></td>
                  <td><?php echo htmlspecialchars((string) ($user['pais'] ?? 'El Salvador')); ?></td>
                  <td><?php echo htmlspecialchars(adminUserDate($user['ultimo_login_at'] ?? null)); ?></td>
                  <td class="text-end">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary admin-edit-user"
                      data-bs-toggle="modal"
                      data-bs-target="#adminEditUserModal"
                      data-user-id="<?php echo (int) ($user['id'] ?? 0); ?>"
                      data-username="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      data-role="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>"
                      data-country="<?php echo htmlspecialchars((string) ($user['pais'] ?? 'El Salvador'), ENT_QUOTES, 'UTF-8'); ?>"
                      data-plan-id="<?php echo (int) ($user['id_plan'] ?? 0); ?>"
                    >
                      Editar
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger admin-delete-user"
                      data-bs-toggle="modal"
                      data-bs-target="#adminDeleteUserModal"
                      data-user-id="<?php echo (int) ($user['id'] ?? 0); ?>"
                      data-username="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                      Desactivar
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>

                <?php if ($users === []): ?>
                <tr>
                  <td colspan="7" class="text-muted">No hay usuarios activos.</td>
                </tr>
                <?php endif; ?>
                <tr id="admin_users_no_results" hidden>
                  <td colspan="7" class="text-muted">No hay usuarios que coincidan con la busqueda.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminCreateUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?php echo htmlspecialchars($adminBaseUrl . '?page=users'); ?>">
        <input type="hidden" name="action" value="create_user">
        <div class="modal-header">
          <h5 class="modal-title">Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="admin_create_username">Usuario</label>
            <input type="text" class="form-control" id="admin_create_username" name="username" value="<?php echo htmlspecialchars((string) ($createOld['username'] ?? '')); ?>" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_create_role">Rol</label>
              <select class="form-select admin-role-field" id="admin_create_role" name="rol" data-plan-target="#admin_create_plan">
                <option value="user" <?php echo (string) ($createOld['rol'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Cliente</option>
                <option value="admin" <?php echo (string) ($createOld['rol'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_create_country">Pais</label>
              <select class="form-select" id="admin_create_country" name="pais">
                <?php foreach ($countries as $value => $label): ?>
                <option value="<?php echo htmlspecialchars((string) $value); ?>" <?php echo (string) ($createOld['pais'] ?? $defaultCountry) === (string) $value ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) $label); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="admin_create_plan">Plan</label>
            <select class="form-select" id="admin_create_plan" name="id_plan">
              <?php foreach ($plans as $plan): ?>
              <option value="<?php echo (int) ($plan['id_plan'] ?? 0); ?>" <?php echo (int) ($createOld['id_plan'] ?? $defaultPlanId) === (int) ($plan['id_plan'] ?? 0) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_create_password">Clave</label>
              <input type="password" class="form-control" id="admin_create_password" name="password" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_create_confirm_password">Confirmar clave</label>
              <input type="password" class="form-control" id="admin_create_confirm_password" name="confirm_password" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="adminEditUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?php echo htmlspecialchars($adminBaseUrl . '?page=users'); ?>">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" id="admin_edit_user_id" value="<?php echo (int) ($editOld['id'] ?? 0); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Editar usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="admin_edit_username">Usuario</label>
            <input type="text" class="form-control" id="admin_edit_username" name="username" value="<?php echo htmlspecialchars((string) ($editOld['username'] ?? '')); ?>" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_edit_role">Rol</label>
              <select class="form-select admin-role-field" id="admin_edit_role" name="rol" data-plan-target="#admin_edit_plan">
                <option value="user">Cliente</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_edit_country">Pais</label>
              <select class="form-select" id="admin_edit_country" name="pais">
                <?php foreach ($countries as $value => $label): ?>
                <option value="<?php echo htmlspecialchars((string) $value); ?>">
                  <?php echo htmlspecialchars((string) $label); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="admin_edit_plan">Plan</label>
            <select class="form-select" id="admin_edit_plan" name="id_plan">
              <?php foreach ($plans as $plan): ?>
              <option value="<?php echo (int) ($plan['id_plan'] ?? 0); ?>">
                <?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_edit_password">Nueva clave</label>
              <input type="password" class="form-control" id="admin_edit_password" name="password" autocomplete="new-password">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin_edit_confirm_password">Confirmar clave</label>
              <input type="password" class="form-control" id="admin_edit_confirm_password" name="confirm_password" autocomplete="new-password">
            </div>
          </div>
          <p class="text-muted small mb-0">Deja la clave vacia si no quieres cambiarla.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="adminDeleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?php echo htmlspecialchars($adminBaseUrl . '?page=users'); ?>">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="admin_delete_user_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Desactivar usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Se desactivara el acceso de <strong id="admin_delete_user_name">este usuario</strong>.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Desactivar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('adminCreateUserModal');
    const editModal = document.getElementById('adminEditUserModal');
    const deleteModal = document.getElementById('adminDeleteUserModal');
    const userSearchInput = document.getElementById('admin_user_search');
    const noResultsRow = document.getElementById('admin_users_no_results');
    const openCreateModal = <?php echo !empty($flashMeta['open_create_modal']) ? 'true' : 'false'; ?>;
    const openEditModal = <?php echo !empty($flashMeta['open_edit_modal']) ? 'true' : 'false'; ?>;
    const editOld = <?php echo json_encode($editOld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function syncPlanState(roleInput) {
      const targetSelector = roleInput ? roleInput.getAttribute('data-plan-target') : '';
      const planInput = targetSelector ? document.querySelector(targetSelector) : null;
      if (!roleInput || !planInput) {
        return;
      }

      const isAdmin = roleInput.value === 'admin';
      planInput.disabled = isAdmin;
      planInput.classList.toggle('bg-light', isAdmin);
    }

    document.querySelectorAll('.admin-role-field').forEach(function (field) {
      field.addEventListener('change', function () {
        syncPlanState(field);
      });
      syncPlanState(field);
    });

    if (userSearchInput) {
      userSearchInput.addEventListener('input', function () {
        const query = userSearchInput.value.trim().toLowerCase();
        let visibleRows = 0;

        document.querySelectorAll('tr[data-user-search]').forEach(function (row) {
          const haystack = row.getAttribute('data-user-search') || '';
          const visible = query === '' || haystack.indexOf(query) !== -1;
          row.hidden = !visible;
          if (visible) {
            visibleRows += 1;
          }
        });

        if (noResultsRow) {
          noResultsRow.hidden = visibleRows > 0;
        }
      });
    }

    document.querySelectorAll('.admin-edit-user').forEach(function (button) {
      button.addEventListener('click', function () {
        const roleInput = document.getElementById('admin_edit_role');
        document.getElementById('admin_edit_user_id').value = button.dataset.userId || '';
        document.getElementById('admin_edit_username').value = button.dataset.username || '';
        document.getElementById('admin_edit_country').value = button.dataset.country || 'El Salvador';
        document.getElementById('admin_edit_plan').value = button.dataset.planId || '';

        if (roleInput) {
          roleInput.value = button.dataset.role || 'user';
          syncPlanState(roleInput);
        }
      });
    });

    document.querySelectorAll('.admin-delete-user').forEach(function (button) {
      button.addEventListener('click', function () {
        document.getElementById('admin_delete_user_id').value = button.dataset.userId || '0';
        document.getElementById('admin_delete_user_name').textContent = button.dataset.username || 'este usuario';
      });
    });

    if (openCreateModal && createModal && window.bootstrap) {
      new bootstrap.Modal(createModal).show();
    }

    if (openEditModal && editModal && window.bootstrap) {
      document.getElementById('admin_edit_user_id').value = editOld.id || '';
      document.getElementById('admin_edit_username').value = editOld.username || '';
      document.getElementById('admin_edit_country').value = editOld.pais || 'El Salvador';
      document.getElementById('admin_edit_plan').value = editOld.id_plan || '';

      const roleInput = document.getElementById('admin_edit_role');
      if (roleInput) {
        roleInput.value = editOld.rol || 'user';
        syncPlanState(roleInput);
      }

      new bootstrap.Modal(editModal).show();
    }
  });
</script>
