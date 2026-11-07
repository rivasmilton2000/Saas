<?php

declare(strict_types=1);

if (!defined('SIETELSA_MAIL_RENDER')) {
    http_response_code(404);
    exit;
}

define('SIETELSA_DASHBOARD_RENDER', true);
$dashboardIncludeAnalytics = false;
$mailContext=$mailContext??mail_list_context($_GET);
$mailCounts=$mailCounts??$repository->counts();
$mailBoxes=['recibidos'=>['Recibidos','inbox-arrow-down-outline','no_leidos'],'enviados'=>['Enviados','send-outline','enviados'],'pendientes'=>['Por enviar','clock-outline','pendientes'],'fallidos'=>['Fallidos','alert-circle-outline','fallidos']];
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
            <div class="sietelsa-mail-workspace">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div><p class="small text-primary text-uppercase fw-bold mb-1">Administración</p><h1 class="h3 mb-1">Centro de correos</h1><p class="text-muted mb-0">Mensajes del sitio y del buzón corporativo.</p></div>
                <a class="btn btn-primary text-white" href="<?= e(mail_route_url('compose.php',$mailContext)) ?>"><i class="mdi mdi-pencil me-1"></i>Redactar</a>
              </div>
              <div class="sietelsa-mail-layout">
                <nav class="sietelsa-mail-folders" aria-label="Bandejas de correo">
                  <?php foreach($mailBoxes as $key=>[$label,$icon,$counter]): ?>
                    <a class="sietelsa-mail-folder <?= $mailContext['bandeja']===$key?'active':'' ?>" href="<?= e(mail_route_url('index.php',mail_list_context(['bandeja'=>$key]))) ?>" <?= $mailContext['bandeja']===$key?'aria-current="page"':'' ?>>
                      <i class="mdi mdi-<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span><span class="badge badge-opacity-primary"><?= (int)($mailCounts[$counter]??0) ?></span>
                    </a>
                  <?php endforeach; ?>
                  <p class="small text-muted mt-3 mb-0 sietelsa-mail-pending"><?= (int)($mailCounts['sin_responder']??0) ?> recibido(s) pendientes de respuesta.</p>
                </nav>
                <main class="sietelsa-mail-main">
