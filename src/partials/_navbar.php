<?php
require_once __DIR__ . '/../models/UsuarioModel.php';

$basePath = $basePath ?? '';
$session  = $session ?? sessionData();
$empresaActivaNavbar = $empresaActivaNavbar ?? null;
$profileFlash = getFlash('profile');
$profileMeta = is_array($profileFlash['meta'] ?? null) ? $profileFlash['meta'] : [];
$profileUsuario = !empty($session['id_usuario']) ? UsuarioModel::getById($pdo, (int) $session['id_usuario'], false) : null;
$profileOld = array_merge([
    'username' => (string) ($profileUsuario['username'] ?? ($session['username'] ?? '')),
    'nombre_completo' => (string) ($profileUsuario['nombre_completo'] ?? ''),
], is_array($profileMeta['old'] ?? null) ? $profileMeta['old'] : []);
$profileOpen = (bool) ($profileMeta['open_modal'] ?? false);
$profileRole = (string) ($profileUsuario['rol'] ?? ($session['rol'] ?? 'user'));
$profileRoleLabel = $profileRole === 'admin' ? 'Administrador' : 'Usuario';
$profilePhotoPath = trim((string) ($profileUsuario['foto_perfil'] ?? ''));
$profilePhotoDefaultUrl = $basePath . 'assets/images/faces/face28.jpg';
$profilePhotoUrl = $profilePhotoPath !== '' ? $profilePhotoPath : $profilePhotoDefaultUrl;
$profileDisplayName = trim((string) ($profileUsuario['nombre_completo'] ?? '')) !== ''
    ? trim((string) $profileUsuario['nombre_completo'])
    : (string) ($profileUsuario['username'] ?? ($session['username'] ?? 'Usuario'));
$memberSince = '-';

if (!empty($profileUsuario['created_at'])) {
    try {
        $memberSince = (new DateTimeImmutable((string) $profileUsuario['created_at'], new DateTimeZone('America/El_Salvador')))
            ->format('d/m/Y');
    } catch (Throwable $exception) {
        $memberSince = (string) $profileUsuario['created_at'];
    }
}

$profileRedirectTo = (string) ($_SERVER['REQUEST_URI'] ?? '/Saas/src/app/index.php');
$profileAlertClass = 'info';
if (!empty($profileFlash['type'])) {
    $profileAlertClass = match ((string) $profileFlash['type']) {
        'success' => 'success',
        'danger'  => 'danger',
        'warning' => 'warning',
        default   => 'info',
    };
}
?>
<style>
  .navbar-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(75, 73, 172, 0.14);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
  }

  .profile-toast {
    position: fixed;
    top: 86px;
    right: 24px;
    z-index: 1085;
    min-width: 320px;
    max-width: 420px;
    border-radius: 18px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
  }

  .profile-modal .modal-content {
    border: 0;
    border-radius: 28px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 100%;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.2);
  }

  .profile-modal .modal-dialog {
    max-width: 980px;
    margin: 1rem auto;
    height: calc(100vh - 2rem);
  }

  .profile-modal form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .profile-modal-header {
    background: linear-gradient(135deg, #ffffff 0%, #f4f6ff 60%, #eef2ff 100%);
    padding: 1.5rem 1.5rem 1.2rem;
    border-bottom: 1px solid rgba(75, 73, 172, 0.08);
  }

  .profile-modal-title {
    margin: 0;
    color: #111827;
    font-size: 1.65rem;
    font-weight: 700;
  }

  .profile-modal-copy {
    margin: 0.45rem 0 0;
    color: #64748b;
    font-size: 0.93rem;
  }

  .profile-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1rem;
  }

  .profile-hero-avatar {
    width: 84px;
    height: 84px;
    border-radius: 24px;
    object-fit: cover;
    border: 3px solid rgba(75, 73, 172, 0.1);
    background: #ffffff;
  }

  .profile-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
  }

  .profile-meta-card {
    padding: 0.9rem 1rem;
    border-radius: 18px;
    border: 1px solid rgba(75, 73, 172, 0.08);
    background: rgba(255, 255, 255, 0.88);
  }

  .profile-meta-card span {
    display: block;
    margin-bottom: 0.2rem;
    color: #7c8699;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .profile-meta-card strong {
    display: block;
    color: #111827;
    font-weight: 700;
  }

  .profile-modal .modal-body {
    padding: 1.25rem 1.5rem 1.5rem;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
  }

  .profile-modal .modal-body::-webkit-scrollbar {
    width: 8px;
  }

  .profile-modal .modal-body::-webkit-scrollbar-track {
    background: transparent;
  }

  .profile-modal .modal-body::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.55);
    border-radius: 999px;
  }

  .profile-modal .modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.75);
  }

  .profile-modal .modal-footer {
    flex-shrink: 0;
    padding: 1rem 1.5rem 1.25rem;
    border-top: 1px solid #e8edf5;
    background: #ffffff;
  }

  .profile-section-card {
    height: 100%;
    padding: 1.1rem;
    border-radius: 22px;
    border: 1px solid #e5e8f1;
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
  }

  .profile-section-card--accent {
    background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
  }

  .profile-section-eyebrow {
    display: inline-flex;
    margin-bottom: 0.35rem;
    color: #7c8699;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .profile-section-title {
    margin: 0 0 1rem;
    color: #111827;
    font-size: 1rem;
    font-weight: 700;
  }

  .profile-field + .profile-field {
    margin-top: 1rem;
  }

  .profile-field .form-label {
    margin-bottom: 0.45rem;
    color: #334155;
    font-weight: 600;
  }

  .profile-modal .form-control,
  .profile-modal .form-select {
    min-height: 54px;
    border-radius: 16px;
    border: 1px solid #dbe3f0;
    background: #ffffff;
    color: #111827;
    box-shadow: none;
    padding: 0.85rem 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .profile-modal textarea.form-control {
    min-height: 120px;
  }

  .profile-modal .form-control:focus,
  .profile-modal .form-select:focus {
    border-color: rgba(75, 73, 172, 0.45);
    box-shadow: 0 0 0 0.2rem rgba(75, 73, 172, 0.12);
    background: #ffffff;
  }

  .profile-modal .form-control[readonly] {
    background: #f8fafc;
    color: #475569;
  }

  .profile-check-card {
    margin-top: 0.85rem;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    border: 1px solid #e5e8f1;
    background: #f8fafc;
  }

  .profile-modal .form-check-input {
    width: 1.1rem;
    height: 1.1rem;
    margin-top: 0.2rem;
  }

  .profile-modal .form-check-label {
    color: #334155;
  }

  .profile-password-meter {
    margin-top: 0.75rem;
  }

  .profile-password-meter .progress {
    height: 10px;
    border-radius: 999px;
    background: #e5e7eb;
  }

  .profile-password-meter small {
    display: block;
    margin-top: 0.45rem;
    color: #64748b;
  }

  .profile-helper-list {
    display: grid;
    gap: 0.5rem;
    margin-top: 0.8rem;
  }

  .profile-helper-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.84rem;
  }

  .profile-mini-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 34px;
    padding: 0 0.8rem;
    border-radius: 999px;
    background: rgba(75, 73, 172, 0.08);
    color: #4b49ac;
    font-size: 0.8rem;
    font-weight: 700;
  }

  @media (max-width: 575.98px) {
    .profile-toast {
      left: 12px;
      right: 12px;
      top: 76px;
      max-width: none;
      min-width: 0;
    }

    .profile-meta-grid {
      grid-template-columns: 1fr;
    }

    .profile-hero {
      flex-direction: column;
      align-items: flex-start;
    }

    .profile-modal .modal-body,
    .profile-modal .modal-footer,
    .profile-modal-header {
      padding-left: 1rem;
      padding-right: 1rem;
    }

    .profile-modal .modal-dialog {
      height: calc(100vh - 1rem);
      margin: 0.5rem;
    }
  }
</style>
<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <a class="navbar-brand brand-logo me-5" href="<?php echo $basePath; ?>index.php"><img src="<?php echo $basePath; ?>assets/images/logo.svg" class="me-2" alt="Zentra" /></a>
    <a class="navbar-brand brand-logo-mini" href="<?php echo $basePath; ?>index.php"><img src="<?php echo $basePath; ?>assets/images/logo-mini.svg" alt="Zentra" /></a>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="icon-menu"></span>
    </button>
    <ul class="navbar-nav me-auto">
      <li class="nav-item d-none d-lg-flex align-items-center">
        <span class="text-muted">
          Usuario: <strong><?php echo htmlspecialchars((string) ($session['username'] ?? '')); ?></strong>
          <?php if ($empresaActivaNavbar): ?>
            | Empresa activa: <strong><?php echo htmlspecialchars((string) $empresaActivaNavbar['nombre']); ?></strong>
          <?php endif; ?>
        </span>
      </li>
    </ul>
    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item nav-profile dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown" id="profileDropdown">
          <img src="<?php echo htmlspecialchars((string) $profilePhotoUrl); ?>" alt="profile" class="navbar-avatar" />
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
          <div class="px-3 py-2 border-bottom">
            <strong class="d-block"><?php echo htmlspecialchars($profileDisplayName); ?></strong>
            <span class="text-muted small">Rol: <?php echo htmlspecialchars($profileRoleLabel); ?></span>
          </div>
          <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#profileModal">
            <i class="mdi mdi-account-edit-outline text-primary"></i> Mi perfil
          </button>
          <a class="dropdown-item" href="<?php echo $basePath; ?>pages/samples/logout.php">
            <i class="ti-power-off text-primary"></i> Cerrar sesion
          </a>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
      <span class="icon-menu"></span>
    </button>
  </div>
</nav>

<?php if ($profileFlash): ?>
<div class="alert alert-<?php echo htmlspecialchars($profileAlertClass); ?> profile-toast" role="alert">
  <?php echo htmlspecialchars((string) ($profileFlash['message'] ?? '')); ?>
</div>
<?php endif; ?>

<div class="modal fade profile-modal" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="profile-modal-header">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div>
            <h5 class="profile-modal-title" id="profileModalLabel">Mi perfil</h5>
            <p class="profile-modal-copy">Consulta tus datos, cambia tu foto y actualiza tu clave desde un solo lugar.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="profile-hero">
          <img
            src="<?php echo htmlspecialchars((string) $profilePhotoUrl); ?>"
            alt="Foto de perfil"
            class="profile-hero-avatar"
            id="profilePhotoPreview"
            data-default-src="<?php echo htmlspecialchars((string) $profilePhotoDefaultUrl); ?>"
          />
          <div>
            <h6 class="mb-1"><?php echo htmlspecialchars($profileDisplayName); ?></h6>
            <div class="text-muted small"><?php echo htmlspecialchars((string) ($profileUsuario['username'] ?? ($session['username'] ?? 'usuario'))); ?></div>
          </div>
        </div>

        <div class="profile-meta-grid">
          <div class="profile-meta-card">
            <span>Rol actual</span>
            <strong><?php echo htmlspecialchars($profileRoleLabel); ?></strong>
          </div>
          <div class="profile-meta-card">
            <span>Miembro desde</span>
            <strong><?php echo htmlspecialchars($memberSince); ?></strong>
          </div>
        </div>
      </div>

      <form method="POST" action="<?php echo $basePath; ?>actions/profile.php" enctype="multipart/form-data">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($profileRedirectTo); ?>">
        <div class="modal-body">
          <div class="row g-4">
            <div class="col-lg-7">
              <div class="profile-section-card">
                <span class="profile-section-eyebrow">Cuenta</span>
                <h6 class="profile-section-title">Datos principales</h6>

                <div class="profile-field">
                  <label for="profile_nombre_completo" class="form-label">Nombre visible</label>
                  <input
                    type="text"
                    class="form-control"
                    id="profile_nombre_completo"
                    name="nombre_completo"
                    maxlength="150"
                    value="<?php echo htmlspecialchars((string) ($profileOld['nombre_completo'] ?? '')); ?>"
                    placeholder="Como quieres que te identifique el sistema"
                  >
                </div>

                <div class="profile-field">
                  <label for="profile_username" class="form-label">Usuario</label>
                  <input
                    type="text"
                    class="form-control"
                    id="profile_username"
                    name="username"
                    maxlength="50"
                    value="<?php echo htmlspecialchars((string) ($profileOld['username'] ?? '')); ?>"
                    required
                  >
                </div>

                <div class="profile-field">
                  <label class="form-label">Rol</label>
                  <div>
                    <span class="profile-mini-chip">
                      <i class="mdi mdi-shield-account-outline"></i>
                      <?php echo htmlspecialchars($profileRoleLabel); ?>
                    </span>
                  </div>
                </div>

                <div class="profile-helper-list">
                  <div class="profile-helper-item"><i class="mdi mdi-account-circle-outline"></i> Puedes cambiar tu nombre visible y tu usuario sin salir del sistema.</div>
                  <div class="profile-helper-item"><i class="mdi mdi-lock-outline"></i> Si cambias la clave, el sistema te pedira confirmar la actual.</div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="profile-section-card profile-section-card--accent">
                <span class="profile-section-eyebrow">Imagen</span>
                <h6 class="profile-section-title">Foto de perfil</h6>
                <label for="profile_photo" class="form-label">Subir nueva foto</label>
                <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/png,image/jpeg,image/webp,image/gif">
                <div class="profile-check-card">
                  <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" value="1" id="remove_photo" name="remove_photo">
                    <label class="form-check-label" for="remove_photo">Quitar foto personalizada y volver a la predeterminada</label>
                  </div>
                </div>
                <div class="profile-helper-list">
                  <div class="profile-helper-item"><i class="mdi mdi-image-outline"></i> JPG, PNG, WEBP o GIF</div>
                  <div class="profile-helper-item"><i class="mdi mdi-weight"></i> Tamano maximo 2 MB</div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="profile-section-card">
                <span class="profile-section-eyebrow">Seguridad</span>
                <h6 class="profile-section-title">Cambiar clave</h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="profile-field">
                    <label for="current_password" class="form-label">Clave actual</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="profile-field">
                    <label for="new_password" class="form-label">Nueva clave</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="profile-field">
                    <label for="confirm_password" class="form-label">Confirmar nueva clave</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                    </div>
                  </div>
                </div>
                <div class="profile-password-meter">
                  <div class="progress">
                    <div class="progress-bar" id="profilePasswordStrengthBar" role="progressbar" style="width: 0%"></div>
                  </div>
                  <small id="profilePasswordStrengthText">Escribe una nueva clave si deseas cambiarla. Usa mayusculas, minusculas, numeros y simbolos para mejorarla.</small>
                </div>
                <div class="profile-helper-list">
                  <div class="profile-helper-item"><i class="mdi mdi-check-decagram-outline"></i> Una clave larga con mezcla de tipos siempre puntua mejor.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('profileModal');
    const shouldOpenProfileModal = <?php echo $profileOpen ? 'true' : 'false'; ?>;
    const profilePhotoInput = document.getElementById('profile_photo');
    const profilePhotoPreview = document.getElementById('profilePhotoPreview');
    const removePhotoCheckbox = document.getElementById('remove_photo');
    const newPasswordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('profilePasswordStrengthBar');
    const strengthText = document.getElementById('profilePasswordStrengthText');

    if (modalElement && shouldOpenProfileModal && window.bootstrap) {
      window.setTimeout(function () {
        const instance = new bootstrap.Modal(modalElement);
        instance.show();
      }, 120);
    }

    if (modalElement) {
      modalElement.addEventListener('shown.bs.modal', function () {
        const body = modalElement.querySelector('.modal-body');
        if (body) {
          body.scrollTop = 0;
        }
      });
    }

    if (profilePhotoInput && profilePhotoPreview) {
      profilePhotoInput.addEventListener('change', function (event) {
        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
        if (!file) {
          return;
        }

        if (removePhotoCheckbox) {
          removePhotoCheckbox.checked = false;
        }

        const reader = new FileReader();
        reader.onload = function (loadEvent) {
          profilePhotoPreview.src = loadEvent.target && loadEvent.target.result ? loadEvent.target.result : profilePhotoPreview.src;
        };
        reader.readAsDataURL(file);
      });
    }

    if (removePhotoCheckbox && profilePhotoPreview) {
      removePhotoCheckbox.addEventListener('change', function () {
        if (!removePhotoCheckbox.checked) {
          return;
        }

        const defaultSrc = profilePhotoPreview.getAttribute('data-default-src');
        if (defaultSrc) {
          profilePhotoPreview.src = defaultSrc;
        }

        if (profilePhotoInput) {
          profilePhotoInput.value = '';
        }
      });
    }

    if (newPasswordInput && strengthBar && strengthText) {
      const palette = {
        empty: ['#cbd5e1', 'Escribe una nueva clave si deseas cambiarla.'],
        weak: ['#ef4444', 'Seguridad baja. Agrega mas longitud y mezcla de caracteres.'],
        fair: ['#f59e0b', 'Seguridad media. Ya va bien, pero aun puede mejorar.'],
        good: ['#0ea5e9', 'Seguridad buena. Tienes una clave bastante solida.'],
        strong: ['#10b981', 'Seguridad alta. Esa clave se ve muy fuerte.']
      };

      const renderStrength = function () {
        const value = newPasswordInput.value || '';
        if (value === '') {
          strengthBar.style.width = '0%';
          strengthBar.style.backgroundColor = palette.empty[0];
          strengthText.textContent = palette.empty[1];
          return;
        }

        let score = 0;
        if (value.length >= 6) score += 15;
        if (value.length >= 8) score += 20;
        if (value.length >= 12) score += 15;
        if (/[a-z]/.test(value)) score += 15;
        if (/[A-Z]/.test(value)) score += 15;
        if (/[0-9]/.test(value)) score += 10;
        if (/[^A-Za-z0-9]/.test(value)) score += 10;

        let state = 'weak';
        if (score >= 85) {
          state = 'strong';
        } else if (score >= 65) {
          state = 'good';
        } else if (score >= 40) {
          state = 'fair';
        }

        strengthBar.style.width = Math.min(score, 100) + '%';
        strengthBar.style.backgroundColor = palette[state][0];
        strengthText.textContent = palette[state][1];
      };

      newPasswordInput.addEventListener('input', renderStrength);
      renderStrength();
    }
  });
</script>
