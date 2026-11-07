<?php 
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

requireLogin();
requireAdmin();

$session = sessionData();
$basePath = '../';
$adminBaseUrl = saasUrl('src/admin/index.php');
$adminAssetBase = '../assets/';

$routeKey = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));
$pageTitle = 'Admin';

ob_start();
require __DIR__ . '/routes.php';
$pageContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Admin Zentra</title>
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>vendors/feather/feather.css">
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="<?php echo $adminAssetBase; ?>css/style.css">
    <link rel="shortcut icon" href="<?php echo $adminAssetBase; ?>images/favicon.png" />
  </head>
  <body>
    <div class="container-scroller">
      <?php require __DIR__ . '/../partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require __DIR__ . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <?php echo $pageContent; ?>
          <?php require __DIR__ . '/../partials/_footer.php'; ?>
        </div>
      </div>
    </div>

    <script src="<?php echo $adminAssetBase; ?>vendors/js/vendor.bundle.base.js"></script>
    <script src="<?php echo $adminAssetBase; ?>js/off-canvas.js"></script>
    <script src="<?php echo $adminAssetBase; ?>js/template.js"></script>
    <script src="<?php echo $adminAssetBase; ?>js/settings.js"></script>
    <script src="<?php echo $adminAssetBase; ?>js/todolist.js"></script>
  </body>
</html>
