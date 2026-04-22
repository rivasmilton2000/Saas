<?php
$centroResumen    = $centroMando['resumen'] ?? [];
$centroEmpresas   = $centroMando['empresas'] ?? [];
$centroPeriodo    = $centroMando['periodo'] ?? [];
$saludoTitulo     = $saludoTitulo ?? 'Buenas noches';
$saludoPersona    = $saludoPersona ?? 'equipo';
$periodoLabel     = (string) ($centroPeriodo['label'] ?? '');
$diasRestantes    = (int) ($centroPeriodo['dias_restantes'] ?? 0);
$empresasTotal    = (int) ($centroResumen['empresas_total'] ?? 0);
$empresasConfig   = (int) ($centroResumen['empresas_configuradas'] ?? 0);
$empresasAlDia    = (int) ($centroResumen['empresas_al_dia'] ?? 0);
$primeraSinConfig = $centroMando['bienvenida']['primera_sin_config'] ?? null;
$empresaInicial   = null;

if (is_array($primeraSinConfig) && !empty($primeraSinConfig['id'])) {
    foreach ($centroEmpresas as $empresaCentro) {
        if ((int) ($empresaCentro['id'] ?? 0) === (int) $primeraSinConfig['id']) {
            $empresaInicial = $empresaCentro;
            break;
        }
    }
}

if ($empresaInicial === null && $centroEmpresas !== []) {
    $empresaInicial = $centroEmpresas[0];
}

$empresaInicialId = (int) ($empresaInicial['id'] ?? 0);
$empresaInicialNombre = (string) ($empresaInicial['nombre'] ?? '');
$empresaInicialKeys = htmlspecialchars(
    json_encode($empresaInicial['selected_keys'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES
);
?>
<div class="row mb-4">
  <div class="col-12">
    <div class="card command-hero-card">
      <div class="card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
          <div class="command-hero-copy">
            <span class="command-kicker">Centro de mando mensual</span>
            <h2 class="command-hero-title"><?php echo htmlspecialchars($saludoTitulo . ', ' . $saludoPersona); ?></h2>
            <p class="command-hero-text">
              Controla que modulo ya esta trabajado, que declaracion sigue pendiente y como va cada empresa durante el mes.
            </p>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empresaModal">
                <i class="mdi mdi-domain-plus me-1"></i> Agregar empresa
              </button>
              <button
                type="button"
                class="btn btn-outline-primary btn-command-open-config"
                data-company-id="<?php echo $empresaInicialId; ?>"
                data-company-name="<?php echo htmlspecialchars($empresaInicialNombre, ENT_QUOTES); ?>"
                data-selected-keys="<?php echo $empresaInicialKeys; ?>"
                <?php echo $empresasTotal > 0 ? '' : 'disabled'; ?>
              >
                <i class="mdi mdi-tune-variant me-1"></i> Configurar checklist
              </button>
            </div>
          </div>
          <div class="command-metrics-grid">
            <div class="command-metric-card">
              <span class="command-metric-label">Empresas al dia</span>
              <strong class="command-metric-value"><?php echo $empresasAlDia; ?> de <?php echo max($empresasConfig, $empresasTotal); ?></strong>
              <span class="command-metric-note">Solo cuentan las empresas con checklist activo.</span>
            </div>
            <div class="command-metric-card">
              <span class="command-metric-label">Periodo actual</span>
              <strong class="command-metric-value"><?php echo htmlspecialchars($periodoLabel); ?></strong>
              <span class="command-metric-note"><?php echo max($diasRestantes, 0); ?> dias restantes del mes.</span>
            </div>
            <div class="command-metric-card">
              <span class="command-metric-label">Empresas registradas</span>
              <strong class="command-metric-value"><?php echo $empresasTotal; ?></strong>
              <span class="command-metric-note">Trabaja sobre las empresas reales del modulo Empresas.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
          <div>
            <h4 class="card-title mb-1">Seguimiento por empresa</h4>
            <p class="text-muted mb-0">Activa los controles que si quieres vigilar y marca el avance manual sin perder el periodo actual.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge badge-light px-3 py-2">Periodo: <?php echo htmlspecialchars($periodoLabel); ?></span>
            <span class="badge badge-light px-3 py-2">Configuradas: <?php echo $empresasConfig; ?></span>
          </div>
        </div>

        <?php if ($centroEmpresas === []): ?>
        <div class="command-empty-state">
          <div class="command-empty-icon">
            <i class="mdi mdi-domain-off"></i>
          </div>
          <h5 class="mb-2">Todavia no tienes empresas para controlar</h5>
          <p class="text-muted mb-3">Crea tu primera empresa y luego define que tareas quieres seguir cada mes desde este tablero.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empresaModal">
            Crear empresa
          </button>
        </div>
        <?php else: ?>
          <?php foreach ($centroEmpresas as $empresaCentro): ?>
          <?php
            $status = $empresaCentro['status'] ?? ['label' => 'Configurar', 'class' => 'secondary'];
            $statusClass = 'secondary';
            if (($status['class'] ?? '') === 'success') {
                $statusClass = 'success';
            } elseif (($status['class'] ?? '') === 'warning') {
                $statusClass = 'warning';
            } elseif (($status['class'] ?? '') === 'danger') {
                $statusClass = 'danger';
            }
          ?>
          <div class="command-company-card" id="empresa-<?php echo (int) $empresaCentro['id']; ?>">
            <div class="command-company-head">
              <div class="d-flex align-items-center gap-3">
                <div class="command-company-avatar" style="background: <?php echo htmlspecialchars((string) $empresaCentro['color_emblema']); ?>;">
                  <?php echo htmlspecialchars((string) $empresaCentro['iniciales']); ?>
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0"><?php echo htmlspecialchars((string) $empresaCentro['nombre']); ?></h5>
                    <span class="badge badge-pill badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars((string) ($status['label'] ?? 'Configurar')); ?></span>
                  </div>
                  <p class="text-muted mb-2"><?php echo htmlspecialchars((string) ($empresaCentro['hint'] ?? '')); ?></p>
                  <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="command-mini-progress">
                      <div class="progress">
                        <div class="progress-bar bg-primary" style="width: <?php echo (int) ($empresaCentro['progress_percent'] ?? 0); ?>%;"></div>
                      </div>
                    </div>
                    <span class="text-muted small"><?php echo (int) ($empresaCentro['completed_count'] ?? 0); ?>/<?php echo (int) ($empresaCentro['selected_count'] ?? 0); ?> completados</span>
                  </div>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary btn-command-config"
                  data-company-id="<?php echo (int) $empresaCentro['id']; ?>"
                  data-company-name="<?php echo htmlspecialchars((string) $empresaCentro['nombre'], ENT_QUOTES); ?>"
                  data-selected-keys="<?php echo htmlspecialchars(json_encode($empresaCentro['selected_keys'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>"
                >
                  <i class="mdi mdi-tune-variant me-1"></i> Configurar
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary btn-command-note"
                  data-company-id="<?php echo (int) $empresaCentro['id']; ?>"
                  data-company-name="<?php echo htmlspecialchars((string) $empresaCentro['nombre'], ENT_QUOTES); ?>"
                  data-company-note="<?php echo htmlspecialchars((string) ($empresaCentro['nota_mensual'] ?? ''), ENT_QUOTES); ?>"
                >
                  <i class="mdi mdi-note-text-outline me-1"></i> Nota del mes
                </button>
              </div>
            </div>

            <?php if (!empty($empresaCentro['groups'])): ?>
              <?php foreach ($empresaCentro['groups'] as $group): ?>
              <div class="command-group-block">
                <div class="command-group-head">
                  <span class="command-group-title">
                    <i class="<?php echo htmlspecialchars((string) ($group['icon'] ?? 'mdi mdi-view-grid-outline')); ?>"></i>
                    <?php echo htmlspecialchars((string) $group['label']); ?>
                    <small><?php echo (int) ($group['completado'] ?? 0); ?>/<?php echo (int) ($group['total'] ?? 0); ?></small>
                  </span>
                </div>
                <div class="command-chip-wrap">
                  <?php foreach ($group['items'] as $item): ?>
                    <?php if (($item['kind'] ?? '') === 'libro' && !empty($item['ruta'])): ?>
                    <a href="<?php echo htmlspecialchars((string) $item['ruta']); ?>" class="command-chip <?php echo !empty($item['completado']) ? 'is-done' : ''; ?>">
                      <i class="<?php echo htmlspecialchars((string) ($item['icon'] ?? 'mdi mdi-check')); ?>"></i>
                      <span><?php echo htmlspecialchars((string) $item['label']); ?></span>
                    </a>
                    <?php else: ?>
                    <form method="post" class="d-inline-block">
                      <input type="hidden" name="action" value="toggle_command_center_item">
                      <input type="hidden" name="empresa_id" value="<?php echo (int) $empresaCentro['id']; ?>">
                      <input type="hidden" name="item_key" value="<?php echo htmlspecialchars((string) $item['key']); ?>">
                      <input type="hidden" name="completed" value="<?php echo !empty($item['completado']) ? '0' : '1'; ?>">
                      <button type="submit" class="command-chip <?php echo !empty($item['completado']) ? 'is-done' : ''; ?>">
                        <i class="<?php echo htmlspecialchars((string) ($item['icon'] ?? 'mdi mdi-check')); ?>"></i>
                        <span><?php echo htmlspecialchars((string) $item['label']); ?></span>
                      </button>
                    </form>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <div class="command-company-note">
              <?php if (!empty($empresaCentro['nota_mensual'])): ?>
              <strong>Nota del mes:</strong> <?php echo nl2br(htmlspecialchars((string) $empresaCentro['nota_mensual'])); ?>
              <?php else: ?>
              <span class="text-muted">Sin nota guardada para este mes.</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
