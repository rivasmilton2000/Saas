<?php
if (!defined('SIETELSA_DASHBOARD_RENDER')) { http_response_code(404); exit; }
$identityQuery = db()->prepare('SELECT correo_proveedor FROM identidades_autenticacion WHERE usuario_id = ? AND proveedor = "google"');
$identityQuery->execute([$dashboardUser['id']]);
$googleIdentity = $identityQuery->fetch();
$avatarCandidate=$_SESSION['account_google_photo']??[];
$candidateValid=(int)($avatarCandidate['id']??0)===(int)$dashboardUser['id'] && (int)($avatarCandidate['time']??0)>time()-600;
$googlePhoto=$candidateValid?($avatarCandidate['url']??null):($dashboardUser['google_avatar_url']??null);
$googlePhoto=AvatarManager::googleUrlIsValid($googlePhoto)?$googlePhoto:null;
?>
<div class="modal fade" id="accountSettings" tabindex="-1" aria-labelledby="accountSettingsTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="accountSettingsTitle">Configuración de cuenta</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
    <div class="modal-body">
      <section id="account-avatar" class="mb-4" data-user="<?= (int)$dashboardUser['id'] ?>" data-url="<?= e(app_url('src/dashboard/profile/avatar.php')) ?>" data-current="<?= e(user_avatar_url($dashboardUser)) ?>" data-default="<?= e(app_url('includes/img/sietelsaPestana.jpg')) ?>" data-google="<?= e((string)$googlePhoto) ?>" data-source="<?= e($dashboardUser['avatar_tipo']??'predeterminado') ?>" data-candidate="<?= $candidateValid&&$googlePhoto?'1':'0' ?>">
        <h3 class="h6">Foto de perfil</h3><div class="d-flex flex-wrap align-items-start gap-3 mb-3"><img id="account-avatar-preview" src="<?= e(user_avatar_url($dashboardUser)) ?>" alt="Vista previa de tu foto" width="128" height="128" class="rounded-circle"><canvas id="account-avatar-canvas" width="384" height="384" class="rounded-circle" hidden></canvas><div class="flex-grow-1">
          <label class="form-label" for="account-avatar-source">Fuente de la foto</label><select id="account-avatar-source" class="form-select mb-2"><option value="current">Conservar foto actual</option><option value="reset">Foto predeterminada</option><option value="upload">Elegir foto del sistema</option><?php if($googleIdentity&&$googlePhoto): ?><option value="google">Foto verificada de Google</option><?php endif; ?></select>
          <input id="account-avatar-file" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" hidden><p class="small text-muted">JPEG, PNG o WebP, hasta 10 MB y 24 millones de píxeles. Se guarda un avatar cuadrado sin deformar la imagen.</p>
        </div></div>
        <div id="account-avatar-crop" hidden class="mb-3"><label for="account-avatar-x" class="form-label">Posición horizontal</label><input id="account-avatar-x" class="form-range" type="range" min="0" max="100" value="50"><label for="account-avatar-y" class="form-label">Posición vertical</label><input id="account-avatar-y" class="form-range" type="range" min="0" max="100" value="50"><label for="account-avatar-zoom" class="form-label">Acercar</label><input id="account-avatar-zoom" class="form-range" type="range" min="1" max="4" step="0.05" value="1"></div>
        <div class="d-flex flex-wrap gap-2"><button id="account-avatar-save" type="button" class="btn btn-primary" disabled>Guardar foto</button><button id="account-avatar-cancel" type="button" class="btn btn-outline-secondary">Cancelar cambio de foto</button></div><progress id="account-avatar-progress" max="100" value="0" class="w-100 mt-2" hidden></progress><p id="account-avatar-message" class="small mt-2" role="status" aria-live="polite"></p>
        <form action="<?= e(app_url('src/dashboard/profile/account.php')) ?>" method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="photo"><button type="submit" class="btn btn-outline-primary"><?= $googleIdentity?'Obtener foto de perfil de Google':'Vincular Google y obtener su foto' ?></button></form>
        <input type="hidden" id="account-avatar-csrf" value="<?= e(csrf_token()) ?>">
      </section>
      <dl><?php foreach (['nombre'=>'Nombre', 'apellido'=>'Apellido', 'correo'=>'Correo', 'rol'=>'Rol', 'nombre_usuario'=>'Usuario', 'telefono'=>'Teléfono', 'fecha_nacimiento'=>'Fecha de nacimiento'] as $key=>$label): ?>
      <?php if (!empty($dashboardUser[$key])): ?><dt><?= e($label) ?></dt><dd><?= e((string)$dashboardUser[$key]) ?></dd><?php endif; ?><?php endforeach; ?></dl>
      <p>Google: <strong><?= $googleIdentity ? 'Vinculado' : 'Sin vincular' ?></strong><?php if ($googleIdentity): ?> · <?= e((string)$googleIdentity['correo_proveedor']) ?><?php endif; ?></p>
      <?php if (!$googleIdentity || empty($dashboardUser['password_hash'])): ?>
      <form action="<?= e(app_url('src/dashboard/profile/account.php')) ?>" method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $googleIdentity ? 'verify' : 'link' ?>"><button class="btn btn-outline-primary" type="submit"><?= $googleIdentity ? 'Verificar Google para establecer contraseña' : 'Vincular Google' ?></button></form>
      <?php endif; ?>
      <form action="<?= e(app_url('src/dashboard/profile/account.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <h3 class="h6">Cambiar contraseña</h3>
        <?php if (!empty($dashboardUser['password_hash'])): ?><label for="accountCurrent">Contraseña actual</label><div class="input-group mb-3"><input class="form-control" id="accountCurrent" type="password" name="current_password" autocomplete="current-password" required><button class="btn btn-outline-secondary" type="button" data-password-toggle="#accountCurrent" aria-label="Mostrar contraseña"><i class="mdi mdi-eye-outline"></i></button></div><?php else: ?><p class="small">Primero verifica tu identidad vinculada con Google. La verificación dura cinco minutos.</p><?php endif; ?>
        <label for="accountPassword">Nueva contraseña</label><div class="input-group mb-3"><input class="form-control" id="accountPassword" type="password" name="password" autocomplete="new-password" maxlength="128" required data-password-input><button class="btn btn-outline-secondary" type="button" data-password-toggle="#accountPassword" aria-label="Mostrar contraseña"><i class="mdi mdi-eye-outline"></i></button></div>
        <?php require SIETELSA_ROOT . '/includes/users/password-requirements.php'; ?>
        <label for="accountConfirmation" class="mt-3">Confirmar contraseña</label><div class="input-group mb-2"><input class="form-control" id="accountConfirmation" type="password" name="password_confirmation" autocomplete="new-password" maxlength="128" required data-password-confirmation="#accountPassword"><button class="btn btn-outline-secondary" type="button" data-password-toggle="#accountConfirmation" aria-label="Mostrar contraseña"><i class="mdi mdi-eye-outline"></i></button></div><p class="small" data-password-match></p>
        <p class="small text-muted">Al guardar, tu sesión actual continúa y se cierran las demás sesiones y accesos recordados.</p><button class="btn btn-primary" type="submit">Guardar contraseña</button>
      </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>
