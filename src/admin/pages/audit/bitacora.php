<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../models/BitacoraModel.php';

BitacoraModel::ensureSchema($pdo);

$userFilter = (int) ($_GET['usuario'] ?? 0);
$moduleFilter = trim((string) ($_GET['modulo'] ?? ''));
$actionFilter = trim((string) ($_GET['accion'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '1 = 1';

if ($userFilter > 0) {
    $where .= ' AND b.id_usuario = ?';
    $params[] = $userFilter;
}

if ($moduleFilter !== '') {
    $where .= ' AND b.modulo = ?';
    $params[] = $moduleFilter;
}

if ($actionFilter !== '') {
    $where .= ' AND b.accion = ?';
    $params[] = $actionFilter;
}

if ($dateFrom !== '') {
    $where .= ' AND b.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where .= ' AND b.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

if ($search !== '') {
    $where .= " AND (
        LOWER(b.username_snapshot) LIKE ?
        OR LOWER(b.descripcion) LIKE ?
        OR LOWER(COALESCE(b.contexto_json, '')) LIKE ?
        OR LOWER(COALESCE(b.ip_address, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}

$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT id_usuario) AS usuarios,
        COUNT(*) FILTER (WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hour') AS last_24h,
        MAX(created_at) AS ultima_actividad
     FROM bitacora_movimientos"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$users = BitacoraModel::getUsuariosConActividad($pdo);
$modules = $pdo->query(
    "SELECT modulo, COUNT(*) AS total
     FROM bitacora_movimientos
     GROUP BY modulo
     ORDER BY modulo ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$actions = $pdo->query(
    "SELECT accion, COUNT(*) AS total
     FROM bitacora_movimientos
     GROUP BY accion
     ORDER BY accion ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    "SELECT
        b.*,
        u.username AS username_actual,
        u.email,
        u.rol AS rol_actual,
        CASE WHEN COALESCE(u.estado, FALSE) THEN 1 ELSE 0 END AS usuario_estado
     FROM bitacora_movimientos b
     LEFT JOIN usuarios u ON u.id = b.id_usuario
     WHERE {$where}
     ORDER BY b.created_at DESC, b.id DESC
     LIMIT 500"
);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (!function_exists('adminAuditDate')) {
    function adminAuditDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('America/El_Salvador')))->format('d/m/Y h:i A');
        } catch (Throwable $exception) {
            return $value;
        }
    }
}

if (!function_exists('adminAuditContext')) {
    function adminAuditContext($json): string
    {
        $json = trim((string) $json);
        if ($json === '') {
            return '-';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        $parts = [];
        foreach (['empresa', 'libro', 'periodo', 'documentos', 'formato', 'usuario_objetivo', 'rol_objetivo', 'detalle', 'plan', 'precio', 'moneda'] as $key) {
            if (isset($data[$key]) && (string) $data[$key] !== '') {
                $parts[] = $key . ': ' . (string) $data[$key];
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

$lastActivityText = adminAuditDate($summary['ultima_actividad'] ?? null);
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Bitacora</h3>
          <p class="text-muted mb-0">Audita sesiones, cambios administrativos, DTE, pagos y movimientos operativos.</p>
        </div>
        <a href="<?php echo htmlspecialchars($adminBaseUrl . '?page=audit'); ?>" class="btn btn-light">Limpiar filtros</a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Movimientos</p><h3 class="mb-0"><?php echo number_format((int) ($summary['total'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Usuarios auditados</p><h3 class="mb-0"><?php echo number_format((int) ($summary['usuarios'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Ultimas 24h</p><h3 class="mb-0"><?php echo number_format((int) ($summary['last_24h'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Ultima actividad</p><h5 class="mb-0"><?php echo htmlspecialchars($lastActivityText); ?></h5></div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form class="row g-3" method="GET" action="">
        <input type="hidden" name="page" value="audit">
        <div class="col-lg-3">
          <label class="form-label" for="audit_q">Busqueda</label>
          <input type="search" class="form-control" id="audit_q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Usuario, descripcion, IP...">
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="audit_usuario">Usuario</label>
          <select class="form-select" id="audit_usuario" name="usuario">
            <option value="0">Todos</option>
            <?php foreach ($users as $user): ?>
            <option value="<?php echo (int) ($user['id_usuario'] ?? 0); ?>" <?php echo $userFilter === (int) ($user['id_usuario'] ?? 0) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($user['username'] ?? 'usuario')); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="audit_modulo">Modulo</label>
          <select class="form-select" id="audit_modulo" name="modulo">
            <option value="">Todos</option>
            <?php foreach ($modules as $module): ?>
            <option value="<?php echo htmlspecialchars((string) ($module['modulo'] ?? '')); ?>" <?php echo $moduleFilter === (string) ($module['modulo'] ?? '') ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($module['modulo'] ?? '')); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="audit_accion">Accion</label>
          <select class="form-select" id="audit_accion" name="accion">
            <option value="">Todas</option>
            <?php foreach ($actions as $action): ?>
            <option value="<?php echo htmlspecialchars((string) ($action['accion'] ?? '')); ?>" <?php echo $actionFilter === (string) ($action['accion'] ?? '') ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($action['accion'] ?? '')); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-1">
          <label class="form-label" for="audit_from">Desde</label>
          <input type="date" class="form-control" id="audit_from" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="col-lg-1">
          <label class="form-label" for="audit_to">Hasta</label>
          <input type="date" class="form-control" id="audit_to" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="col-lg-1 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Movimientos recientes</h4>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Modulo</th>
              <th>Accion</th>
              <th>Descripcion</th>
              <th>Contexto</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $movement): ?>
            <tr>
              <td><?php echo htmlspecialchars(adminAuditDate($movement['created_at'] ?? null)); ?></td>
              <td>
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($movement['username_actual'] ?? $movement['username_snapshot'] ?? 'usuario')); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($movement['rol_actual'] ?? $movement['rol_snapshot'] ?? 'user')); ?></div>
              </td>
              <td><span class="badge badge-info"><?php echo htmlspecialchars((string) ($movement['modulo'] ?? 'general')); ?></span></td>
              <td><?php echo htmlspecialchars((string) ($movement['accion'] ?? 'accion')); ?></td>
              <td style="min-width: 260px;"><?php echo htmlspecialchars((string) ($movement['descripcion'] ?? '')); ?></td>
              <td style="min-width: 260px;"><?php echo htmlspecialchars(adminAuditContext($movement['contexto_json'] ?? null)); ?></td>
              <td><?php echo htmlspecialchars((string) ($movement['ip_address'] ?? '-')); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($movements === []): ?>
            <tr><td colspan="7" class="text-muted">No hay movimientos con esos filtros.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
