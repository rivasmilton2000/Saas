<?php
declare(strict_types=1);
if (!defined('SIETELSA_DASHBOARD_RENDER')) { http_response_code(404); exit; }
?>
<div class="modal fade" id="create-book" tabindex="-1" aria-labelledby="create-book-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <form class="modal-content" method="post" action="<?= e(app_url('src/dashboard/books/action.php')) ?>" data-create-book-form data-module-periods="<?= e(json_encode($modulePeriods, JSON_UNESCAPED_UNICODE)) ?>" data-book-routes="<?= e(json_encode($bookRoutes)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="workspace" value="1">
        <input type="hidden" name="company_id" value="<?= (int)$activeCompany['id'] ?>">
        <input type="hidden" name="context_user_id" value="<?= (int)$companyContextUser['id'] ?>">
        <div class="modal-header">
          <h2 class="modal-title h5" id="create-book-title">Crear libro</h2>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted text-break"><?= e($activeCompany['nombre_comercial']) ?></p>
          <div class="mb-3">
            <label class="form-label" for="create-type">Tipo de libro</label>
            <select class="form-select" id="create-type" name="module" required>
              <?php foreach ($bookTypes as $key=>$type): ?><option value="<?= e($key) ?>" <?= $createType===$key?'selected':'' ?>><?= e($type['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-7">
              <label class="form-label" for="create-month">Mes</label>
              <select class="form-select" id="create-month" name="month" required>
                <option value="">Seleccionar</option>
                <?php foreach (AccountingCatalog::months() as $number=>$name): ?><option value="<?= $number ?>" <?= $createMonth===$number?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label" for="create-year">Año</label>
              <select class="form-select" id="create-year" name="year" required>
                <option value="">Seleccionar</option>
                <?php for ($year=$limits['max'];$year>=$limits['min'];$year--): ?><option value="<?= $year ?>" <?= $createYear===$year?'selected':'' ?>><?= $year ?></option><?php endfor; ?>
              </select>
            </div>
          </div>
          <?php if ($companyExternalContext): ?>
            <div class="mb-3">
              <label class="form-label" for="create-admin-reason">Motivo administrativo</label>
              <textarea class="form-control" id="create-admin-reason" name="admin_reason" rows="3" minlength="10" maxlength="500" required aria-describedby="create-admin-help"></textarea>
              <p class="form-text mb-0" id="create-admin-help">Describe la intervención en los libros del usuario (mínimo 10 caracteres).</p>
            </div>
          <?php endif; ?>
          <div class="alert alert-info mb-0" data-book-available role="status">El libro se creará en la empresa y el período seleccionados.</div>
          <div class="alert alert-warning d-none mb-0" data-book-duplicate role="status">
            <p class="mb-2" data-book-duplicate-message></p>
            <a class="alert-link" data-book-existing-link href="#">Abrir libro existente</a>
          </div>
        </div>
        <div class="modal-footer flex-wrap gap-2">
          <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary text-white" type="submit" data-create-book-button>Crear y abrir libro</button>
        </div>
      </form>
  </div>
</div>
