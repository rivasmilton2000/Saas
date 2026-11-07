<?php
if(!defined('SIETELSA_DASHBOARD_RENDER')){http_response_code(404);exit;}
?>
<div class="row g-2 align-items-center mt-2 sietelsa-record-toolbar">
 <div class="col-12 col-xxl-4">
  <form class="input-group" method="get">
   <input type="hidden" name="book" value="<?= (int)$book['id'] ?>">
   <?php foreach($recordFilters as $key=>$value): if(in_array($key,['q','ids'],true)||!is_scalar($value))continue; ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string)$value) ?>"><?php endforeach; ?>
   <input type="hidden" name="per_page" value="<?= (int)$records['per_page'] ?>">
   <span class="input-group-text"><i class="mdi mdi-magnify"></i></span><input class="form-control" name="q" value="<?= e($recordFilters['q']) ?>" placeholder="Buscar..." aria-label="Búsqueda de registros"><button class="btn btn-outline-primary" type="submit">Buscar</button>
  </form>
 </div>
 <div class="col-12 col-md-6 col-xxl-auto">
  <div class="d-flex gap-2 sietelsa-toolbar-group">
   <?php if($sameActive&&in_array($book['estado'],['borrador','activo'],true)&&CompanyPermissionService::can($currentUser,'book.import')): ?><a class="btn btn-primary text-white" href="import/index.php?module=<?= e($accountingModule) ?>&book=<?= (int)$book['id'] ?>"><i class="mdi mdi-code-json me-1"></i>Importar JSON</a><?php endif; ?>
   <?php if($sameActive&&in_array($book['estado'],['borrador','activo'],true)&&CompanyPermissionService::can($currentUser,'book.manual_create')): ?><a class="btn btn-outline-primary" href="record-form.php?module=<?= e($accountingModule) ?>&book=<?= (int)$book['id'] ?>"><i class="mdi mdi-plus me-1"></i>Registro manual</a><?php endif; ?>
  </div>
 </div>
 <div class="col-12 col-md-6 col-xxl-auto ms-xxl-auto">
  <div class="d-flex gap-2 sietelsa-toolbar-group justify-content-md-end">
   <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#columns-modal"><i class="mdi mdi-table-column me-1"></i>Columnas</button>
   <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#record-filters" aria-expanded="<?= $activeRecordFilters?'true':'false' ?>"><i class="mdi mdi-filter-variant me-1"></i>Filtros</button>
   <div class="dropdown"><button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="mdi mdi-download me-1"></i>Exportar</button><div class="dropdown-menu dropdown-menu-end sietelsa-export-menu"><button class="dropdown-item" type="button" data-export-format="internal_xlsx">Exportar Excel</button><button class="dropdown-item" type="button" data-export-format="internal_pdf">Exportar PDF</button><?php if($accountingModule==='compras'): ?><button class="dropdown-item" type="button" data-open-annex3>Anexo MH A3</button><?php endif; ?></div></div>
  </div>
 </div>
</div>
