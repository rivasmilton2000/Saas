<?php

declare(strict_types=1);

if (!defined('SIETELSA_BUILDER_RENDER')) {
    http_response_code(404);
    exit;
}
?>
          </div>
          <?php require dirname(__DIR__) . '/partials/_footer.php'; ?>
        </div>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/partials/_scripts.php'; ?>
    <script src="<?= e(app_asset_url('includes/assets/js/website-builder.js')) ?>"></script>
  </body>
</html>
