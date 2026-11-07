<?php
$centroCatalogo       = $centroMando['catalogo'] ?? [];
$centroBienvenida     = $centroMando['bienvenida'] ?? [];
$primeraSinConfig     = $centroBienvenida['primera_sin_config'] ?? null;
$firstCompanyJson     = htmlspecialchars(json_encode($primeraSinConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
?>
<div class="modal fade" id="commandWelcomeModal" tabindex="-1" aria-labelledby="commandWelcomeModalLabel" aria-hidden="true" data-first-company="<?php echo $firstCompanyJson; ?>">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content command-modal command-modal--welcome">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="commandWelcomeModalLabel">Bienvenido a tu centro de mando</h5>
          <p class="text-muted mb-0">Vas a ver que empresa ya esta al dia, que modulo falta y como va el cierre del mes sin empezar de cero.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="command-welcome-list">
          <div class="command-welcome-item">
            <i class="mdi mdi-check-decagram-outline"></i>
            <div>
              <strong>Identifica rapido quien ya esta listo</strong>
              <span>Cada empresa resume su avance del mes en un solo bloque.</span>
            </div>
          </div>
          <div class="command-welcome-item">
            <i class="mdi mdi-refresh-circle"></i>
            <div>
              <strong>El avance se separa por mes</strong>
              <span>El seguimiento manual se guarda por periodo actual para no mezclar meses.</span>
            </div>
          </div>
          <div class="command-welcome-item">
            <i class="mdi mdi-domain"></i>
            <div>
              <strong>Trabaja sobre tus empresas reales</strong>
              <span>La configuracion nace desde tu modulo Empresas y se conecta con libros existentes.</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex flex-column flex-md-row gap-2">
        <form method="post" class="m-0">
          <input type="hidden" name="action" value="hide_command_center_welcome">
          <button type="submit" class="btn btn-light">No mostrar de nuevo</button>
        </form>
        <button type="button" class="btn btn-primary btn-command-start">Empezar a organizar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="commandConfigModal" tabindex="-1" aria-labelledby="commandConfigModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content command-modal">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="commandConfigModalLabel">Configurar checklist mensual</h5>
          <p class="text-muted mb-0">Selecciona solo las tareas que quieres vigilar para esta empresa. Los libros se marcan automatico cuando el periodo existe.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" id="commandConfigForm">
        <input type="hidden" name="action" value="save_command_center_config">
        <input type="hidden" name="empresa_id" id="command_config_empresa_id" value="">
        <div class="modal-body">
          <div class="alert alert-light border mb-4">
            Configurando empresa: <strong id="command_config_empresa_nombre">Empresa</strong>
          </div>

          <?php foreach ($centroCatalogo as $categoria): ?>
          <div class="command-config-section">
            <div class="command-config-head">
              <span>
                <i class="<?php echo htmlspecialchars((string) ($categoria['icon'] ?? 'mdi mdi-view-grid-outline')); ?>"></i>
                <?php echo htmlspecialchars((string) $categoria['label']); ?>
              </span>
            </div>
            <div class="row">
              <?php foreach (($categoria['items'] ?? []) as $itemKey => $item): ?>
              <div class="col-md-6 col-xl-4 mb-3">
                <label class="command-choice-card">
                  <input type="checkbox" class="command-config-checkbox" name="items[]" value="<?php echo htmlspecialchars((string) $itemKey); ?>">
                  <span class="command-choice-body">
                    <span class="command-choice-title"><?php echo htmlspecialchars((string) $item['label']); ?></span>
                    <span class="command-choice-copy"><?php echo htmlspecialchars((string) ($item['description'] ?? '')); ?></span>
                  </span>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer d-flex flex-column flex-md-row gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar seleccion</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="commandNoteModal" tabindex="-1" aria-labelledby="commandNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content command-modal">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="commandNoteModalLabel">Nota del mes</h5>
          <p class="text-muted mb-0">Guarda observaciones cortas para no perder contexto del periodo.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" id="commandNoteForm">
        <input type="hidden" name="action" value="save_command_center_note">
        <input type="hidden" name="empresa_id" id="command_note_empresa_id" value="">
        <div class="modal-body">
          <div class="alert alert-light border mb-3">
            Empresa: <strong id="command_note_empresa_nombre">Empresa</strong>
          </div>
          <div class="form-group mb-0">
            <label class="form-label" for="command_note_text">Nota</label>
            <textarea class="form-control" id="command_note_text" name="nota_mensual" rows="5" maxlength="1000" placeholder="Ejemplo: Falta confirmar pago, esperar respuesta del cliente, revisar F-910..."></textarea>
          </div>
        </div>
        <div class="modal-footer d-flex flex-column flex-md-row gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar nota</button>
        </div>
      </form>
    </div>
  </div>
</div>
