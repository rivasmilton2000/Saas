<?php
declare(strict_types=1);
if (!defined('SIETELSA_DASHBOARD_RENDER') || !isset($columns,$columnCatalog)) { http_response_code(404); exit; }
$grouped=false;
$heading=static function(string $column,int $rowspan=1)use($columnCatalog,$sortMap,$recordFilters,$recordQueryBase):void {
    $meta=$columnCatalog[$column];
    $next=$recordFilters['sort']===$column && $recordFilters['direction']==='asc'?'desc':'asc';
    ?>
    <th class="sietelsa-col-<?= e($column) ?>" scope="col" rowspan="<?= $rowspan ?>">
      <?php if($column==='acciones'): ?>
        <div class="sietelsa-record-actions"><input class="form-check-input" type="checkbox" data-select-all aria-label="Seleccionar p?gina"><span>Acciones</span></div>
      <?php elseif($column==='seleccion'): ?>
        <input class="form-check-input" type="checkbox" data-select-all aria-label="Seleccionar página">
      <?php elseif(isset($sortMap[$column])): ?>
        <a class="sietelsa-sort-link" href="?<?= e(http_build_query($recordQueryBase+['sort'=>$column,'direction'=>$next,'page'=>1])) ?>">
          <?= e($meta['label']) ?>
          <?php if($recordFilters['sort']===$column): ?><i class="mdi mdi-arrow-<?= $recordFilters['direction']==='asc'?'up':'down' ?>"></i><?php endif; ?>
        </a>
      <?php else: ?><?= e($meta['label']) ?><?php endif; ?>
    </th>
    <?php
};
?>
<thead>
 <tr>
  <?php for($index=0;$index<count($columns);$index++):
      $column=$columns[$index];$group=$grouped?AccountingCatalog::columnGroup($accountingModule,$column):'';
      if($group==='') { $heading($column,$grouped?2:1); continue; }
      $span=1;
      while(isset($columns[$index+$span]) && AccountingCatalog::columnGroup($accountingModule,$columns[$index+$span])===$group)$span++;
      $index+=$span-1;
  ?><th class="sietelsa-column-group" scope="colgroup" colspan="<?= $span ?>"><?= e($group) ?></th><?php endfor; ?>
 </tr>
 <?php if($grouped): ?><tr><?php foreach($columns as $column)if(AccountingCatalog::columnGroup($accountingModule,$column)!=='')$heading($column); ?></tr><?php endif; ?>
</thead>
