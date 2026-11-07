<?php
if(!defined('SIETELSA_DASHBOARD_RENDER')){http_response_code(404);exit;}
$canBatch=$sameActive&&in_array($book['estado'],['borrador','activo'],true)&&CompanyPermissionService::can($currentUser,'book.manual_create');
?>
<label class="form-check small mb-2"><input class="form-check-input" type="checkbox" data-selection-keep><span class="form-check-label">Conservar selección al cambiar de página (hasta 5000 filas para exportar)</span></label>
<div class="border rounded bg-light p-3 mb-3 d-none" role="status" data-selection-status>
 <div class="d-flex flex-wrap align-items-center gap-2">
  <span class="me-auto"><strong data-selection-count>0</strong> seleccionados</span>
  <?php if($canBatch): ?><button class="btn btn-sm btn-outline-primary" type="button" data-batch-open="edit">Editar seleccionados</button><button class="btn btn-sm btn-outline-danger" type="button" data-batch-open="delete">Eliminar seleccionados</button><?php endif; ?>
  <button class="btn btn-sm btn-light" type="button" data-selection-clear>Limpiar selección</button>
 </div><p class="small text-muted mb-0 mt-2">Seleccionar todo afecta esta página. La selección se limpia al cambiar empresa, libro o filtros. Edición y eliminación: hasta 100 filas.</p>
</div>
<?php if($canBatch): ?>
<div class="modal fade" id="record-batch-modal" tabindex="-1" aria-labelledby="record-batch-title" data-bs-backdrop="static" data-batch-url="<?= e(app_url('src/dashboard/books/batch.php')) ?>">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="record-batch-title">Registros seleccionados</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <form data-batch-form><div class="modal-body">
   <p data-batch-description></p><div class="alert alert-danger d-none" role="alert" data-batch-error></div>
   <div class="table-responsive" data-batch-table></div>
   <?php if($companyExternalContext): ?><label class="form-label mt-3" for="batch-admin-reason">Motivo administrativo</label><textarea class="form-control" id="batch-admin-reason" name="admin_reason" minlength="10" maxlength="500" required></textarea><?php endif; ?>
   <div class="form-check mt-3 d-none" data-batch-confirm-wrap><input class="form-check-input" type="checkbox" id="batch-confirm"><label class="form-check-label" for="batch-confirm" data-batch-confirm-label>Confirmo la eliminación</label></div>
  </div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-batch-save>Guardar cambios</button></div></form>
 </div></div>
</div>
<?php endif; ?>
