<?php

declare(strict_types=1);

if (!defined('SIETELSA_DASHBOARD_RENDER')) {
    http_response_code(404);
    exit;
}
?>
<script src="<?= e(app_asset_url('src/dashboard/assets/vendors/js/vendor.bundle.base.js')) ?>"></script>
<script src="<?= e(app_asset_url('src/dashboard/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js')) ?>"></script>
<script src="<?= e(app_asset_url('src/dashboard/assets/js/off-canvas.js')) ?>"></script>
<script src="<?= e(app_asset_url('src/dashboard/assets/js/template.js')) ?>"></script>
<script src="<?= e(app_asset_url('src/dashboard/assets/js/hoverable-collapse.js')) ?>"></script>
<script src="<?= e(app_asset_url('includes/assets/js/session-client.js')) ?>"></script>
<script src="<?= e(app_asset_url('includes/assets/js/password-strength.js')) ?>"></script>
<script src="<?= e(app_asset_url('includes/assets/js/account-avatar.js')) ?>"></script>
<?php
// Scripts de demostración de Star Admin (settings y todolist).
// Permanecen en assets como referencia, pero no se cargan porque no corresponden
// a módulos funcionales de SIETELSA.
?>
<?php if (($dashboardIncludeCharts ?? false) || (($currentUser['rol'] ?? '') === 'administrador' && ($dashboardIncludeAnalytics ?? true))): ?>
  <script src="<?= e(app_asset_url('src/dashboard/assets/vendors/chart.js/chart.umd.js')) ?>"></script>
<?php endif; ?>
<?php if (($currentUser['rol'] ?? '') === 'administrador' && ($dashboardIncludeAnalytics ?? true)): ?>
  <script src="<?= e(app_asset_url('includes/assets/js/admin-dashboard.js')) ?>"></script>
<?php endif; ?>
<?php require SIETELSA_ROOT . '/includes/components/scripts.php'; ?>
<?php if (($companyExternalContext ?? false) === true): ?><script>window.SietelsaAdministrativeDteContext=true;</script><?php endif; ?>
<script src="<?= e(app_asset_url('includes/assets/js/dashboard-navigation.js')) ?>"></script>
<script src="<?= e(app_asset_url('includes/assets/js/dashboard-forms.js')) ?>"></script>
<?php foreach (($dashboardPageScripts ?? []) as $dashboardPageScript): ?>
  <script src="<?= e(app_asset_url((string) $dashboardPageScript)) ?>"></script>
<?php endforeach; ?>
<?php $dashboardFlash = $dashboardFlash ?? flash_pull(); ?>
<?php if ($dashboardFlash !== null): ?>
  <script>
    window.SietelsaAlert[<?= json_encode($dashboardFlash['type'], JSON_HEX_TAG | JSON_HEX_AMP) ?>](
      <?= json_encode($dashboardFlash['title'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
      <?= json_encode($dashboardFlash['message'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
    );
  </script>
<?php endif; ?>
