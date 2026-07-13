<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../models/PlanModel.php';
require_once __DIR__ . '/../../../models/UsuarioModel.php';
require_once __DIR__ . '/../../../services/BitacoraService.php';

$redirectToPlans = static function () use ($adminBaseUrl): void {
    header('Location: ' . $adminBaseUrl . '?page=plans');
    exit;
};

$adminUserId = (int) ($session['id_usuario'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_plan') {
        PlanModel::ensureSchema($pdo);

        $planId = (int) ($_POST['id_plan'] ?? 0);
        $plan = PlanModel::getById($pdo, $planId);
        if ($plan === null) {
            setFlash('admin_plans', 'El plan seleccionado no existe.', 'danger');
            $redirectToPlans();
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $precioRaw = trim((string) ($_POST['precio'] ?? ''));
        $precio = $precioRaw === '' ? null : number_format((float) $precioRaw, 2, '.', '');
        $moneda = strtoupper(trim((string) ($_POST['moneda'] ?? 'USD'))) ?: 'USD';
        $periodo = trim((string) ($_POST['periodo'] ?? 'mes')) ?: 'mes';
        $billingInterval = trim((string) ($_POST['billing_interval'] ?? ''));
        $limiteEmpresas = trim((string) ($_POST['limite_empresas'] ?? ''));
        $limiteUsuarios = trim((string) ($_POST['limite_usuarios'] ?? ''));
        $limiteDocumentos = trim((string) ($_POST['limite_documentos'] ?? ''));
        $featuresText = (string) ($_POST['caracteristicas_text'] ?? '');

        if ($nombre === '') {
            setFlash('admin_plans', 'El nombre del plan es obligatorio.', 'danger');
            $redirectToPlans();
        }

        $stmt = $pdo->prepare(
            "UPDATE planes
             SET nombre = ?,
                 descripcion = ?,
                 precio = ?,
                 moneda = ?,
                 periodo = ?,
                 billing_interval = ?,
                 destacado = ?,
                 personalizado = ?,
                 activo = ?,
                 limite_empresas = ?,
                 limite_usuarios = ?,
                 limite_documentos = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id_plan = ?"
        );
        $stmt->execute([
            $nombre,
            $descripcion !== '' ? $descripcion : null,
            $precio,
            $moneda,
            $periodo,
            $billingInterval !== '' ? $billingInterval : null,
            dbBoolParam(!empty($_POST['destacado'])),
            dbBoolParam(!empty($_POST['personalizado'])),
            dbBoolParam(!empty($_POST['activo'])),
            $limiteEmpresas !== '' ? (int) $limiteEmpresas : null,
            $limiteUsuarios !== '' ? (int) $limiteUsuarios : null,
            $limiteDocumentos !== '' ? (int) $limiteDocumentos : null,
            $planId,
        ]);

        $deleteFeatures = $pdo->prepare("DELETE FROM planes_caracteristicas WHERE id_plan = ?");
        $insertFeature = $pdo->prepare(
            "INSERT INTO planes_caracteristicas (id_plan, caracteristica, incluido, orden)
             VALUES (?, ?, ?, ?)"
        );
        $deleteFeatures->execute([$planId]);

        $featureLines = preg_split('/\R/u', $featuresText) ?: [];
        $featureOrder = 1;
        foreach ($featureLines as $featureLine) {
            $featureLine = trim((string) $featureLine);
            if ($featureLine === '') {
                continue;
            }

            $included = true;
            if (str_starts_with($featureLine, '- ') || str_starts_with($featureLine, '!')) {
                $included = false;
                $featureLine = trim(ltrim(substr($featureLine, 1)));
            }

            if ($featureLine === '') {
                continue;
            }

            $insertFeature->execute([
                $planId,
                $featureLine,
                dbBoolParam($included),
                $featureOrder,
            ]);
            $featureOrder++;
        }

        BitacoraService::registrar(
            $pdo,
            $adminUserId,
            'admin_planes',
            'editar_plan',
            'Actualizo el plan ' . $nombre . '.',
            [
                'username' => (string) ($session['username'] ?? ''),
                'rol' => (string) ($session['rol'] ?? 'admin'),
                'entidad_tipo' => 'plan',
                'entidad_id' => $planId,
                'contexto' => [
                    'plan' => $nombre,
                    'precio' => $precio,
                    'moneda' => $moneda,
                ],
            ]
        );

        setFlash('admin_plans', 'Plan actualizado correctamente.', 'success');
        $redirectToPlans();
    }

    setFlash('admin_plans', 'Accion no reconocida.', 'danger');
    $redirectToPlans();
}

$plans = PlanModel::getActivePlansWithFeatures($pdo);
$userCounts = UsuarioModel::getUserCountsByPlan($pdo);
$flash = getFlash('admin_plans');
$flashClass = match ((string) ($flash['type'] ?? 'info')) {
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'info',
};
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div>
        <h3 class="font-weight-bold mb-1">Planes</h3>
        <p class="text-muted mb-0">Administra precios, limites y disponibilidad de los planes del SaaS.</p>
      </div>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="alert alert-<?php echo htmlspecialchars($flashClass); ?>" role="alert">
    <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
  </div>
  <?php endif; ?>

  <div class="row">
    <?php foreach ($plans as $plan): ?>
    <?php
      $planId = (int) ($plan['id_plan'] ?? 0);
      $usersTotal = (int) ($userCounts[$planId]['usuarios_total'] ?? 0);
    ?>
    <div class="col-lg-6 col-xl-4 grid-margin stretch-card">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h4 class="card-title mb-1"><?php echo htmlspecialchars((string) ($plan['nombre'] ?? 'Plan')); ?></h4>
              <p class="text-muted mb-0"><?php echo htmlspecialchars((string) ($plan['slug'] ?? '')); ?></p>
            </div>
            <span class="badge <?php echo dbBoolValue($plan['activo'] ?? true) ? 'badge-success' : 'badge-secondary'; ?>">
              <?php echo dbBoolValue($plan['activo'] ?? true) ? 'Activo' : 'Inactivo'; ?>
            </span>
          </div>

          <h3 class="mb-2"><?php echo htmlspecialchars(PlanModel::formatPriceLabel($plan)); ?></h3>
          <p class="text-muted flex-grow-1"><?php echo htmlspecialchars((string) ($plan['descripcion'] ?? '')); ?></p>

          <div class="row text-muted small mb-3">
            <div class="col-4">Empresas<br><strong><?php echo htmlspecialchars((string) ($plan['limite_empresas'] ?? 'Libre')); ?></strong></div>
            <div class="col-4">Usuarios<br><strong><?php echo htmlspecialchars((string) ($plan['limite_usuarios'] ?? 'Libre')); ?></strong></div>
            <div class="col-4">Docs<br><strong><?php echo htmlspecialchars((string) ($plan['limite_documentos'] ?? 'Libre')); ?></strong></div>
          </div>

          <ul class="list-unstyled text-muted small mb-3">
            <?php foreach (array_slice(($plan['caracteristicas'] ?? []), 0, 5) as $feature): ?>
            <li class="mb-1">
              <i class="mdi <?php echo dbBoolValue($feature['incluido'] ?? true) ? 'mdi-check text-success' : 'mdi-close text-danger'; ?>"></i>
              <?php echo htmlspecialchars((string) ($feature['caracteristica'] ?? '')); ?>
            </li>
            <?php endforeach; ?>
            <?php if (count($plan['caracteristicas'] ?? []) > 5): ?>
            <li>+ <?php echo number_format(count($plan['caracteristicas']) - 5); ?> mas</li>
            <?php endif; ?>
          </ul>

          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small"><?php echo number_format($usersTotal); ?> usuarios</span>
            <button
              type="button"
              class="btn btn-sm btn-outline-primary admin-edit-plan"
              data-bs-toggle="modal"
              data-bs-target="#adminEditPlanModal"
              data-plan='<?php echo htmlspecialchars(json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>'
            >
              Editar
            </button>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="adminEditPlanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?php echo htmlspecialchars($adminBaseUrl . '?page=plans'); ?>">
        <input type="hidden" name="action" value="update_plan">
        <input type="hidden" name="id_plan" id="plan_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Editar plan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="plan_nombre">Nombre</label>
              <input type="text" class="form-control" id="plan_nombre" name="nombre" required>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label" for="plan_precio">Precio</label>
              <input type="number" step="0.01" min="0" class="form-control" id="plan_precio" name="precio">
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label" for="plan_moneda">Moneda</label>
              <input type="text" maxlength="3" class="form-control" id="plan_moneda" name="moneda">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="plan_descripcion">Descripcion</label>
            <textarea class="form-control" id="plan_descripcion" name="descripcion" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-3 mb-3">
              <label class="form-label" for="plan_periodo">Periodo</label>
              <input type="text" class="form-control" id="plan_periodo" name="periodo">
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label" for="plan_billing_interval">Intervalo Stripe</label>
              <select class="form-select" id="plan_billing_interval" name="billing_interval">
                <option value="">Sin intervalo</option>
                <option value="day">Dia</option>
                <option value="week">Semana</option>
                <option value="month">Mes</option>
                <option value="year">Año</option>
              </select>
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label" for="plan_limite_empresas">Empresas</label>
              <input type="number" min="0" class="form-control" id="plan_limite_empresas" name="limite_empresas">
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label" for="plan_limite_usuarios">Usuarios</label>
              <input type="number" min="0" class="form-control" id="plan_limite_usuarios" name="limite_usuarios">
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label" for="plan_limite_documentos">Docs</label>
              <input type="number" min="0" class="form-control" id="plan_limite_documentos" name="limite_documentos">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="plan_destacado" name="destacado" value="1">
                <label class="form-check-label" for="plan_destacado">Destacado</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="plan_personalizado" name="personalizado" value="1">
                <label class="form-check-label" for="plan_personalizado">Personalizado</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="plan_activo" name="activo" value="1">
              <label class="form-check-label" for="plan_activo">Activo</label>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label" for="plan_caracteristicas_text">Caracteristicas</label>
            <textarea class="form-control" id="plan_caracteristicas_text" name="caracteristicas_text" rows="9"></textarea>
            <p class="text-muted small mt-2 mb-0">
              Escribe una caracteristica por linea. Para marcar una como no incluida, inicia la linea con "- " o "!". Ejemplo: "- Soporte prioritario".
            </p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar plan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-edit-plan').forEach(function (button) {
      button.addEventListener('click', function () {
        const plan = JSON.parse(button.getAttribute('data-plan') || '{}');
        document.getElementById('plan_id').value = plan.id_plan || 0;
        document.getElementById('plan_nombre').value = plan.nombre || '';
        document.getElementById('plan_precio').value = plan.precio === null || plan.precio === undefined ? '' : plan.precio;
        document.getElementById('plan_moneda').value = plan.moneda || 'USD';
        document.getElementById('plan_descripcion').value = plan.descripcion || '';
        document.getElementById('plan_periodo').value = plan.periodo || 'mes';
        document.getElementById('plan_billing_interval').value = plan.billing_interval || '';
        document.getElementById('plan_limite_empresas').value = plan.limite_empresas || '';
        document.getElementById('plan_limite_usuarios').value = plan.limite_usuarios || '';
        document.getElementById('plan_limite_documentos').value = plan.limite_documentos || '';
        document.getElementById('plan_destacado').checked = Boolean(plan.destacado === true || plan.destacado === 1 || plan.destacado === '1');
        document.getElementById('plan_personalizado').checked = Boolean(plan.personalizado === true || plan.personalizado === 1 || plan.personalizado === '1');
        document.getElementById('plan_activo').checked = Boolean(plan.activo === true || plan.activo === 1 || plan.activo === '1');
        document.getElementById('plan_caracteristicas_text').value = Array.isArray(plan.caracteristicas)
          ? plan.caracteristicas.map(function (feature) {
              const text = feature.caracteristica || '';
              const included = !(feature.incluido === false || feature.incluido === 0 || feature.incluido === '0');
              return included ? text : '- ' + text;
            }).join('\n')
          : '';
      });
    });
  });
</script>
