<?php

declare(strict_types=1);

if (!defined('SIETELSA_BUILDER_RENDER')) {
    http_response_code(404);
    exit;
}

define('SIETELSA_DASHBOARD_RENDER', true);
$dashboardIncludeAnalytics = false;
?>
<!DOCTYPE html>
<html lang="es">
  <?php require dirname(__DIR__) . '/partials/_head.php'; ?>
  <body class="website-editor">
    <div class="container-scroller">
      <?php require dirname(__DIR__) . '/partials/_navbar.php'; ?>
      <div class="container-fluid page-body-wrapper">
        <?php require dirname(__DIR__) . '/partials/_sidebar.php'; ?>
        <div class="main-panel">
          <div class="content-wrapper builder-content-wrapper">
