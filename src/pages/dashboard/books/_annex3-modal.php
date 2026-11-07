<?php
declare(strict_types=1);
if(!defined('SIETELSA_DASHBOARD_RENDER')||!isset($book)){http_response_code(404);exit;}
?>
<div class="modal fade" id="annex3-modal" tabindex="-1" aria-labelledby="annex3-title" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form data-annex3-form class="d-flex flex-column" style="min-height:0;overflow:hidden">
  <div class="modal-header"><h2 class="modal-title h5" id="annex3-title">Clasificación de Renta — Anexo 3</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <div class="modal-body">
   <p>Los valores se aplicarán a <strong data-annex3-count>0</strong> filas del alcance indicado. El JSON original se conserva.</p>
   <p class="small text-muted" data-annex3-scope></p>
   <div class="alert alert-info" role="status" data-annex3-message>Comprobando registros…</div>
   <?php foreach(['tipo_operacion'=>'Q · Tipo de Operación','clasificacion'=>'R · Clasificación','sector'=>'S · Sector','tipo_costo_gasto'=>'T · Tipo de Costo / Gasto'] as $key=>$label): ?>
   <div class="mb-3"><label class="form-label" for="annex3-<?= e($key) ?>"><?= e($label) ?></label><select class="form-select" id="annex3-<?= e($key) ?>" name="<?= e($key) ?>"><option value="">Seleccionar</option></select></div>
   <?php endforeach; ?>
   <p class="small text-muted">El formato aplicable exige códigos en Q–T; no admite dejarlos vacíos. El código 0 corresponde a períodos anteriores a febrero de 2024.</p>
   <label class="form-label" for="annex-policy">Clasificación previa</label><select class="form-select mb-2" name="classification_policy" id="annex-policy"><option value="fill_missing">Completar vacíos; conservar valores existentes</option><option value="replace">Reemplazar Q–T en todas las filas del alcance</option></select>
   <label class="form-check d-none" data-annex-replace-wrap><input class="form-check-input" type="checkbox" name="replace_confirmation"><span class="form-check-label">Confirmo reemplazar los cuatro campos en todas estas filas.</span></label>
   <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="persist_classification"><span class="form-check-label">Guardar clasificación en los registros del libro</span></label>
   <?php if($companyExternalContext): ?><label class="form-label mt-2" for="annex-reason">Motivo administrativo para guardar</label><textarea class="form-control" id="annex-reason" name="admin_reason" minlength="10" maxlength="500"></textarea><?php endif; ?>
   <label class="form-check"><input class="form-check-input" type="checkbox" name="remember"><span class="form-check-label">Recordar mi selección para este libro</span></label>
   <div class="mt-3" data-annex-errors></div><button class="btn btn-sm btn-outline-primary mt-2" type="button" data-annex-recheck>Comprobar nuevamente</button>
  </div>
  <div class="modal-footer flex-shrink-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary text-white" disabled data-annex3-continue>Continuar</button></div>
 </form></div></div>
</div>
