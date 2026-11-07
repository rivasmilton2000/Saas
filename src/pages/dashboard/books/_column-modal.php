<?php
declare(strict_types=1);
if (!defined('SIETELSA_DASHBOARD_RENDER') || !isset($book)) { http_response_code(404); exit; }
$preferenceDefaults=$accountingService->defaultColumnPreferences($accountingModule);
?>
<div class="modal fade sietelsa-column-modal" id="columns-modal" tabindex="-1" aria-labelledby="columns-title" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form method="post" action="<?= e(app_url('src/dashboard/books/action.php')) ?>" data-column-preferences data-defaults="<?= e(json_encode($preferenceDefaults)) ?>" data-loading-text="Guardando...">
   <div class="modal-header"><div><h2 class="modal-title h5" id="columns-title">Personalizar columnas</h2><p class="small text-muted mb-0">Preferencias para esta empresa y tipo de libro. No cambian los anexos fiscales.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
   <div class="modal-body">
    <?= csrf_field() ?><input type="hidden" name="module" value="<?= e($accountingModule) ?>"><input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>"><input type="hidden" name="action" value="columns">
    <div class="sietelsa-column-grid small text-muted mb-2" aria-hidden="true"><span>Columna</span><span>Tabla</span><span>Excel interno</span></div>
    <ul class="sietelsa-column-order">
    <?php foreach($columnCatalog as $key=>$meta): $mandatory=$key==='acciones'; ?>
     <li class="sietelsa-column-grid"><span><?= e($meta['label']) ?></span>
      <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="table_columns[]" value="<?= e($key) ?>" <?= in_array($key,$columns,true)?'checked':'' ?> <?= $mandatory?'disabled':'' ?> aria-label="Mostrar <?= e($meta['label']) ?> en tabla"><span class="form-check-label">Mostrar</span></label>
      <?php if(!$mandatory): ?><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="excel_columns[]" value="<?= e($key) ?>" <?= in_array($key,$exportColumns,true)?'checked':'' ?> aria-label="Incluir <?= e($meta['label']) ?> en Excel"><span class="form-check-label">Incluir</span></label><?php else: ?><span class="small text-muted">Solo tabla</span><?php endif; ?>
     </li>
    <?php endforeach; ?>
    </ul>
    <h3 class="h6 mt-4">Tipos de documento</h3>
    <p class="small text-muted">Solo las notas de crédito marcadas llevan fondo rosa. Los demás tipos se identifican con una insignia, sobre filas blancas.</p>
    <div class="row g-2"><?php foreach($documentTypes as $type): $code=(string)$type['codigo']; ?>
     <div class="col-md-6"><div class="border rounded p-3"><strong><?= e($type['nombre']) ?></strong>
      <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="doc_show[]" value="<?= e($code) ?>" <?= in_array($code,$documentPreferences['show_name'],true)?'checked':'' ?>><span class="form-check-label">Mostrar nombre</span></label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="doc_mark[]" value="<?= e($code) ?>" <?= in_array($code,$documentPreferences['mark'],true)?'checked':'' ?>><span class="form-check-label">Marcar visualmente</span></label>
     </div></div>
    <?php endforeach; ?></div>
    <p class="text-danger small mt-3 d-none" role="alert" data-column-error>Incluye al menos una columna en el Excel interno.</p>
   </div>
   <div class="modal-footer"><button class="btn btn-outline-secondary me-auto" type="button" data-reset-column-preferences>Restaurar por defecto</button><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary text-white" type="submit">Guardar</button></div>
  </form>
 </div></div>
</div>
