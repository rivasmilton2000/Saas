<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once dirname(__DIR__, 3) . '/includes/website/bootstrap.php';

$currentUser = require_dashboard_module('website_builder');
(new WebsiteService(db()))->ensureInitialized((int) $currentUser['id']);
$pageTitle = 'Editor visual del Website | SIETELSA';
define('SIETELSA_BUILDER_RENDER', true);
require __DIR__ . '/_start.php';
?>
<link rel="stylesheet" href="<?= e(app_asset_url('includes/assets/css/website-builder.css')) ?>">

<div class="builder-toolbar card mb-3">
  <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h3 class="mb-1">Editor visual del Website</h3>
      <p class="text-muted mb-0">Los cambios permanecen en borrador hasta que decidas publicarlos.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span id="builder-status" class="badge bg-secondary">Cargando borrador…</span>
      <button id="builder-retry" type="button" class="btn btn-outline-secondary btn-sm" hidden>Reintentar carga</button>
      <button id="builder-undo" type="button" class="btn btn-outline-secondary btn-sm" disabled>↶ Deshacer</button>
      <button id="builder-redo" type="button" class="btn btn-outline-secondary btn-sm" disabled>↷ Rehacer</button>
      <button id="builder-mode" type="button" class="btn btn-outline-secondary btn-sm" aria-pressed="false">Vista previa</button>
      <button id="builder-discard" type="button" class="btn btn-outline-danger btn-sm">Descartar</button>
      <button id="builder-save" type="button" class="btn btn-outline-primary btn-sm" disabled>Guardar borrador</button>
      <button id="builder-publish" type="button" class="btn btn-primary text-white btn-sm">Publicar</button>
    </div>
  </div>
</div>

<div class="builder-workspace">
  <aside class="builder-properties card" aria-label="Propiedades del elemento">
    <div class="card-body">
      <details open class="mb-3"><summary>Páginas individuales</summary>
        <p class="small text-muted mt-2">Cada página tiene contenido propio y comparte la navbar y el footer.</p>
        <div class="d-flex flex-wrap gap-2"><button id="builder-page-create" class="btn btn-outline-primary btn-sm" type="button">Crear página de detalle</button><button id="builder-page-manage" class="btn btn-outline-secondary btn-sm" type="button" disabled>Configurar esta página</button></div>
      </details>
      <details id="builder-library-details">
        <summary>Agregar elementos</summary>
        <label for="builder-insert-position" class="form-label mt-2">Insertar respecto a la selección</label>
        <select id="builder-insert-position" class="form-select form-select-sm mb-2"><option value="after">Después</option><option value="before">Antes</option><option value="inside">Dentro del contenedor</option></select>
        <div id="builder-library" class="builder-library"></div>
      </details>
      <details class="mt-3"><summary>Árbol de estructura</summary><div id="builder-tree" class="builder-tree"></div></details>
      <details class="mt-3"><summary>Contenido y colores generales</summary>
      <label class="form-label mt-2" for="builder-element-search">Buscar contenido</label>
      <input id="builder-element-search" class="form-control form-control-sm mb-2" type="search" placeholder="Título, sección, color…">
      <select id="builder-element-list" class="form-select form-select-sm mb-4" size="8" aria-label="Elementos editables"></select>
      </details>

      <div id="builder-selection-empty" class="text-muted small py-3">
        Haz clic sobre un elemento de la página. Usa doble clic para escribir directamente y Escape para cancelar.
      </div>
      <div id="builder-selection" hidden>
        <p class="small text-muted mb-1" id="builder-selection-group"></p>
        <h5 id="builder-selection-label"></h5>
        <code id="builder-selection-path" class="d-block small text-break mb-3"></code>
        <div id="builder-control"></div>
        <div id="builder-context-properties"></div>
        <div id="builder-image-tools" hidden>
          <label class="form-label mt-3" for="builder-image-upload">Subir nueva imagen</label>
          <button id="builder-change-image" type="button" class="btn btn-outline-primary btn-sm mb-2">Cambiar imagen</button>
          <input id="builder-image-upload" class="form-control form-control-sm" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/bmp">
          <p class="text-muted small mt-1">Hasta 20 MB y 24 millones de píxeles. Se conserva el original y se genera una versión optimizada.</p>
          <progress id="builder-image-upload-progress" class="builder-image-upload-progress" max="100" value="0" hidden></progress>
        </div>
      </div>

      <hr><details id="builder-image-editor-details">
        <summary>Editor de imágenes</summary>
        <p class="small text-muted">Cada entrada es una utilización. Cambiarla conserva las demás; los elementos globales indican su alcance.</p>
        <label class="form-label" for="builder-image-search">Buscar por nombre, título o ruta</label>
        <input id="builder-image-search" type="search" class="form-control form-control-sm mb-2" placeholder="Buscar imágenes…">
        <label class="form-label" for="builder-image-page-filter">Página</label>
        <select id="builder-image-page-filter" class="form-select form-select-sm mb-2"><option value="">Todas las páginas</option></select>
        <label class="form-label" for="builder-image-section-filter">Apartado</label>
        <select id="builder-image-section-filter" class="form-select form-select-sm mb-2"><option value="">Todos los apartados</option></select>
        <label class="form-label" for="builder-image-sort">Ordenar</label>
        <select id="builder-image-sort" class="form-select form-select-sm mb-2"><option value="appearance">Orden de aparición</option><option value="name">Nombre</option><option value="date">Fecha de carga (cuando exista)</option></select>
        <button id="builder-image-refresh" class="btn btn-outline-secondary btn-sm" type="button">Actualizar listado</button>
        <p id="builder-image-inventory-status" class="small mt-2" role="status"></p>
        <div id="builder-image-usages"></div>
      </details>
      <hr><details id="builder-images-details">
      <summary>Imágenes disponibles</summary>
      <div id="builder-image-library" class="builder-image-library" aria-live="polite"></div>
      </details><hr><details>
      <summary>Recuperar una publicación anterior</summary>
      <h5>Historial publicado</h5>
      <p class="small text-muted">Restaurar crea un borrador; no publica automáticamente.</p>
      <div id="builder-history" class="builder-history"></div>
      </details>
    </div>
  </aside>

  <section class="builder-preview-area">
    <div class="builder-device-toolbar card mb-2">
      <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Tamaño de vista previa">
          <button type="button" class="btn btn-outline-primary active" data-builder-device="desktop"><i class="mdi mdi-monitor"></i> Escritorio</button>
          <button type="button" class="btn btn-outline-primary" data-builder-device="tablet"><i class="mdi mdi-tablet"></i> Tablet</button>
          <button type="button" class="btn btn-outline-primary" data-builder-device="mobile"><i class="mdi mdi-cellphone"></i> Móvil</button>
        </div>
        <label class="visually-hidden" for="builder-page">Página</label>
        <label for="builder-view-filter" class="small mb-0">Tipo de vista</label><select id="builder-view-filter" class="form-select form-select-sm builder-page-select"><option value="">Todas las vistas</option><option value="home">Inicio</option><option value="service">Service Details</option><option value="portfolio">Portfolio Details</option><option value="starter">Starter Page</option></select>
        <select id="builder-page" class="form-select form-select-sm builder-page-select" title="Cambiar de página guarda el borrador sin publicar"><option value="index.php">Inicio</option><option value="src/website/service-details.php">Servicios · sidebar público</option><option value="src/website/portfolio-details.php">Detalle de portafolio</option><option value="src/website/starter-page.php">Página adicional</option></select>
        <button id="builder-reload-preview" type="button" class="btn btn-light btn-sm"><i class="mdi mdi-refresh"></i> Recargar vista</button>
      </div>
    </div>
    <div id="builder-frame-shell" class="builder-frame-shell builder-device-desktop">
      <iframe
        id="builder-preview"
        title="Vista previa editable del Website"
        src="<?= e(app_url('index.php?website_preview=draft')) ?>"
        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"
      ></iframe>
    </div>
  </section>
</div>

<dialog id="builder-image-picker" aria-labelledby="builder-image-picker-title">
  <div class="builder-picker-header"><h4 id="builder-image-picker-title" class="mb-0">Cambiar imagen</h4><button id="builder-image-picker-close" type="button" class="btn btn-outline-secondary btn-sm">Cerrar</button></div>
  <div class="builder-picker-body">
    <p id="builder-image-picker-scope" class="small"></p>
    <img id="builder-image-picker-preview" class="builder-picker-preview" alt="Vista previa del recurso seleccionado">
    <p id="builder-image-recommendation" class="small text-muted"></p>
    <label for="builder-image-picker-upload" class="form-label">Subir una nueva imagen</label>
    <input id="builder-image-picker-upload" type="file" class="form-control mb-2" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/bmp">
    <progress id="builder-picker-progress" class="builder-image-upload-progress" max="100" value="0" hidden></progress>
    <p id="builder-picker-message" class="small" role="status"></p>
    <label for="builder-image-library-search" class="form-label">Seleccionar de la biblioteca</label>
    <input id="builder-image-library-search" type="search" class="form-control mb-2" placeholder="Nombre o ruta…">
    <div id="builder-picker-library" class="builder-picker-library"></div>
  </div>
</dialog>

<dialog id="builder-page-dialog" aria-labelledby="builder-page-dialog-title">
  <form id="builder-page-form" class="p-3">
    <h4 id="builder-page-dialog-title">Crear página de detalle</h4>
    <label class="form-label" for="builder-instance-type">Tipo de vista</label><select id="builder-instance-type" class="form-select mb-2"><option value="service">Service Details</option><option value="portfolio">Portfolio Details</option><option value="starter">Starter Page</option></select>
    <label class="form-label" for="builder-instance-title">Título</label><input id="builder-instance-title" class="form-control mb-2" maxlength="190" required>
    <label class="form-label" for="builder-instance-slug">Nombre en la URL</label><input id="builder-instance-slug" class="form-control mb-2" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="100" required>
    <p id="builder-instance-url" class="small text-break"></p><p class="small text-muted">Guardar conserva el borrador. La página será pública cuando publiques el website.</p>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Guardar página</button><button id="builder-instance-duplicate" type="button" class="btn btn-outline-primary" hidden>Duplicar página</button><button id="builder-instance-delete" type="button" class="btn btn-outline-danger" hidden>Eliminar página</button><button type="button" data-close-dialog="builder-page-dialog" class="btn btn-outline-secondary">Cancelar</button></div>
    <div id="builder-instance-dependencies" class="mt-3" hidden><p id="builder-instance-dependency-list" class="small"></p><label for="builder-instance-replacement" class="form-label">Corregir los enlaces dependientes</label><select id="builder-instance-replacement" class="form-select mb-2"></select><button type="button" id="builder-instance-delete-confirm" class="btn btn-danger">Corregir enlaces y eliminar</button></div>
  </form>
</dialog>
<dialog id="builder-link-dialog" aria-labelledby="builder-link-title">
  <form id="builder-link-form" class="p-3">
    <h4 id="builder-link-title">Añadir enlace</h4>
    <p id="builder-link-source" class="small"></p>
    <label class="form-label" for="builder-link-target">Destino</label><select id="builder-link-target" class="form-select mb-2"></select>
    <div id="builder-link-external-fields"><label class="form-label" for="builder-link-external">URL externa</label><input id="builder-link-external" class="form-control mb-2" maxlength="1000" placeholder="https://…"></div>
    <div id="builder-link-button-fields" hidden><label for="builder-link-button-text" class="form-label">Texto del botón</label><input id="builder-link-button-text" class="form-control mb-2" value="Ver más" maxlength="190"><label for="builder-link-button-style" class="form-label">Estilo</label><select id="builder-link-button-style" class="form-select mb-2"><option value="btn btn-primary">Principal</option><option value="btn btn-outline-primary">Contorno</option><option value="btn btn-secondary">Secundario</option></select><label for="builder-link-button-color" class="form-label">Color</label><input id="builder-link-button-color" type="color" class="form-control form-control-color mb-2" value="#1f3bb3"><label for="builder-link-button-alignment" class="form-label">Alineación</label><select id="builder-link-button-alignment" class="form-select mb-2"><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></div>
    <label for="builder-link-open" class="form-label">Abrir</label><select id="builder-link-open" class="form-select mb-3"><option value="same">En esta pestaña</option><option value="new">En otra pestaña</option></select>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Aplicar al borrador</button><button id="builder-link-create-page" type="button" class="btn btn-outline-primary">Crear página y vincular</button><button type="button" data-close-dialog="builder-link-dialog" class="btn btn-outline-secondary">Cancelar</button></div>
  </form>
</dialog>

<script>
window.SietelsaBuilderConfig = <?= json_encode([
    'csrfToken' => csrf_token(),
    'stateUrl' => app_url('src/dashboard/builder/api/state.php'),
    'saveUrl' => app_url('src/dashboard/builder/api/save.php'),
    'publishUrl' => app_url('src/dashboard/builder/api/publish.php'),
    'discardUrl' => app_url('src/dashboard/builder/api/discard.php'),
    'restoreUrl' => app_url('src/dashboard/builder/api/restore.php'),
    'uploadUrl' => app_url('src/dashboard/builder/api/upload.php'),
    'deleteImageUrl' => app_url('src/dashboard/builder/api/delete-image.php'),
    'previewUrl' => app_url('index.php?website_preview=draft'),
    'baseUrl' => app_url(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<?php require __DIR__ . '/_end.php'; ?>
