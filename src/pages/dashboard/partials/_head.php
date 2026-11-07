<?php

declare(strict_types=1);

if (!defined('SIETELSA_DASHBOARD_RENDER')) {
    http_response_code(404);
    exit;
}

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'Dashboard | SIETELSA';
?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/feather/feather.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/mdi/css/materialdesignicons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/ti-icons/css/themify-icons.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/font-awesome/css/font-awesome.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/typicons/typicons.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/simple-line-icons/css/simple-line-icons.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/css/vendor.bundle.base.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/js/select.dataTables.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('src/dashboard/assets/css/style.css')) ?>">
  <link rel="icon" type="image/jpeg" href="<?= e(app_asset_url('includes/img/sietelsaPestana.jpg')) ?>">
  <link rel="apple-touch-icon" href="<?= e(app_asset_url('includes/img/sietelsaPestana.jpg')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/dashboard.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/account-avatar.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/sietelsa-stage-companies.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/sietelsa-stage-accounting.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/sietelsa-stage-user-dte.css')) ?>">
  <link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/sietelsa-stage-dte-import.css')) ?>">
  <?php if(defined('SIETELSA_MAIL_RENDER')): ?><link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/mail-center.css')) ?>"><?php endif; ?>
</head>
