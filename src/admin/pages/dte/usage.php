<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/modulos.php';
require_once __DIR__ . '/../../../models/FacturaModel.php';

FacturaModel::ensureExtendedSchema($pdo);

$moduleNames = [];
foreach (getLibroModules() as $module) {
    $moduleNames[(string) ($module['tipo'] ?? '')] = (string) ($module['nombre'] ?? ($module['tipo'] ?? 'Modulo'));
}

$period = trim((string) ($_GET['period'] ?? '30'));
$allowedPeriods = ['7', '30', '90', '365', 'all'];
if (!in_array($period, $allowedPeriods, true)) {
    $period = '30';
}

$dateCondition = $period === 'all' ? '1 = 1' : "f.created_at >= CURRENT_TIMESTAMP - INTERVAL '" . (int) $period . " day'";
$bookDateCondition = $period === 'all' ? '1 = 1' : "l.created_at >= CURRENT_TIMESTAMP - INTERVAL '" . (int) $period . " day'";

$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total_documents,
        COUNT(*) FILTER (WHERE {$dateCondition}) AS period_documents,
        COUNT(DISTINCT f.id_usuario) FILTER (WHERE {$dateCondition}) AS active_users,
        COUNT(DISTINCT l.id_empresa) FILTER (WHERE {$dateCondition}) AS active_companies,
        COUNT(DISTINCT f.id_libro) FILTER (WHERE {$dateCondition}) AS active_books
     FROM facturas f
     INNER JOIN libros l ON l.id = f.id_libro"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$quotaSummary = $pdo->query(
    "SELECT
        COALESCE(SUM(total), 0) AS total_quota,
        COALESCE(SUM(consumidas), 0) AS consumed_quota,
        COALESCE(SUM(total - consumidas), 0) AS available_quota,
        COUNT(*) FILTER (WHERE total > 0 AND consumidas >= total) AS exhausted_users
     FROM facturas_disponibles"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$docsByType = $pdo->query(
    "SELECT
        COALESCE(NULLIF(TRIM(tipo_dte), ''), 'Sin tipo') AS tipo_dte,
        COUNT(*) AS total
     FROM facturas f
     WHERE {$dateCondition}
     GROUP BY COALESCE(NULLIF(TRIM(tipo_dte), ''), 'Sin tipo')
     ORDER BY total DESC, tipo_dte ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$booksByModule = $pdo->query(
    "SELECT
        l.tipo,
        COUNT(*) AS total_books,
        COALESCE(SUM(documents.total_documents), 0) AS total_documents
     FROM libros l
     LEFT JOIN (
        SELECT id_libro, COUNT(*) AS total_documents
        FROM facturas
        GROUP BY id_libro
     ) documents ON documents.id_libro = l.id
     WHERE l.estado = TRUE
       AND {$bookDateCondition}
     GROUP BY l.tipo
     ORDER BY total_documents DESC, total_books DESC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topUsers = $pdo->query(
    "SELECT
        u.id,
        u.username,
        u.email,
        p.nombre AS plan_nombre,
        COUNT(f.id) AS total_documents,
        COUNT(DISTINCT l.id_empresa) AS total_companies,
        COALESCE(fd.total, 0) AS quota_total,
        COALESCE(fd.consumidas, 0) AS quota_consumed
     FROM facturas f
     INNER JOIN usuarios u ON u.id = f.id_usuario
     LEFT JOIN planes p ON p.id_plan = u.id_plan
     INNER JOIN libros l ON l.id = f.id_libro
     LEFT JOIN facturas_disponibles fd ON fd.id_usuario = u.id
     WHERE {$dateCondition}
     GROUP BY u.id, u.username, u.email, p.nombre, fd.total, fd.consumidas
     ORDER BY total_documents DESC, u.username ASC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topCompanies = $pdo->query(
    "SELECT
        e.id,
        e.nombre,
        u.username AS owner_username,
        COUNT(f.id) AS total_documents,
        COUNT(DISTINCT l.id) AS total_books,
        MAX(f.created_at) AS last_import_at
     FROM facturas f
     INNER JOIN libros l ON l.id = f.id_libro
     INNER JOIN empresas e ON e.id = l.id_empresa
     INNER JOIN usuarios u ON u.id = e.id_usuario
     WHERE {$dateCondition}
     GROUP BY e.id, e.nombre, u.username
     ORDER BY total_documents DESC, e.nombre ASC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$monthlyTrend = $pdo->query(
    "SELECT
        TO_CHAR(DATE_TRUNC('month', f.created_at), 'YYYY-MM') AS month_key,
        COUNT(*) AS total_documents
     FROM facturas f
     WHERE f.created_at >= CURRENT_TIMESTAMP - INTERVAL '12 month'
     GROUP BY DATE_TRUNC('month', f.created_at)
     ORDER BY month_key DESC
     LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$latestDocuments = $pdo->query(
    "SELECT
        f.id,
        f.codigo_generacion,
        f.numero_control,
        f.tipo_dte,
        f.fecha,
        f.created_at,
        l.tipo AS libro_tipo,
        e.nombre AS empresa_nombre,
        u.username
     FROM facturas f
     INNER JOIN libros l ON l.id = f.id_libro
     INNER JOIN empresas e ON e.id = l.id_empresa
     INNER JOIN usuarios u ON u.id = f.id_usuario
     ORDER BY f.created_at DESC, f.id DESC
     LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (!function_exists('adminDteDate')) {
    function adminDteDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('America/El_Salvador')))->format('d/m/Y H:i');
        } catch (Throwable $exception) {
            return $value;
        }
    }
}

if (!function_exists('adminDtePercent')) {
    function adminDtePercent(int $part, int $total): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return number_format(($part / $total) * 100, 1) . '%';
    }
}

$periodLabel = $period === 'all' ? 'Todo el historial' : 'Ultimos ' . $period . ' dias';
$quotaTotal = (int) ($quotaSummary['total_quota'] ?? 0);
$quotaConsumed = (int) ($quotaSummary['consumed_quota'] ?? 0);
?>

<div class="content-wrapper">
  <?php require __DIR__ . '/_nav.php'; ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Uso DTE</h3>
          <p class="text-muted mb-0">Supervision de documentos, libros, empresas y consumo de cuota.</p>
        </div>
        <form method="GET" action="">
          <input type="hidden" name="page" value="dte">
          <select class="form-select" name="period" onchange="this.form.submit()">
            <option value="7" <?php echo $period === '7' ? 'selected' : ''; ?>>Ultimos 7 dias</option>
            <option value="30" <?php echo $period === '30' ? 'selected' : ''; ?>>Ultimos 30 dias</option>
            <option value="90" <?php echo $period === '90' ? 'selected' : ''; ?>>Ultimos 90 dias</option>
            <option value="365" <?php echo $period === '365' ? 'selected' : ''; ?>>Ultimos 12 meses</option>
            <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>Todo el historial</option>
          </select>
        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Documentos <?php echo htmlspecialchars($periodLabel); ?></p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['period_documents'] ?? 0)); ?></h3>
        <p class="text-muted small mb-0">Historial: <?php echo number_format((int) ($summary['total_documents'] ?? 0)); ?></p>
      </div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Usuarios activos</p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['active_users'] ?? 0)); ?></h3>
      </div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Empresas activas</p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['active_companies'] ?? 0)); ?></h3>
      </div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Libros con movimiento</p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['active_books'] ?? 0)); ?></h3>
      </div></div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-4 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Cuota global</h4>
          <p class="text-muted mb-2">Documentos consumidos contra cuotas asignadas.</p>
          <h3><?php echo number_format($quotaConsumed); ?> / <?php echo number_format($quotaTotal); ?></h3>
          <div class="progress mb-3" style="height: 10px;">
            <div class="progress-bar bg-primary" style="width: <?php echo htmlspecialchars(adminDtePercent($quotaConsumed, $quotaTotal)); ?>"></div>
          </div>
          <div class="d-flex justify-content-between text-muted small">
            <span>Disponibles: <?php echo number_format((int) ($quotaSummary['available_quota'] ?? 0)); ?></span>
            <span>Agotados: <?php echo number_format((int) ($quotaSummary['exhausted_users'] ?? 0)); ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Tipos DTE</h4>
          <?php foreach ($docsByType as $row): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span><?php echo htmlspecialchars((string) ($row['tipo_dte'] ?? 'Sin tipo')); ?></span>
            <strong><?php echo number_format((int) ($row['total'] ?? 0)); ?></strong>
          </div>
          <?php endforeach; ?>
          <?php if ($docsByType === []): ?>
          <p class="text-muted mb-0">No hay documentos en este periodo.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Libros por modulo</h4>
          <?php foreach ($booksByModule as $row): ?>
          <?php $moduleType = (string) ($row['tipo'] ?? ''); ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span><?php echo htmlspecialchars($moduleNames[$moduleType] ?? $moduleType); ?></span>
            <span><strong><?php echo number_format((int) ($row['total_documents'] ?? 0)); ?></strong> docs</span>
          </div>
          <?php endforeach; ?>
          <?php if ($booksByModule === []): ?>
          <p class="text-muted mb-0">No hay libros en este periodo.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-6 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Usuarios con mas documentos</h4>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th>Usuario</th><th>Plan</th><th>Empresas</th><th>Docs</th><th>Cuota</th></tr></thead>
              <tbody>
                <?php foreach ($topUsers as $user): ?>
                <?php
                  $userQuotaTotal = (int) ($user['quota_total'] ?? 0);
                  $userQuotaConsumed = (int) ($user['quota_consumed'] ?? 0);
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($user['email'] ?? 'Sin correo')); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($user['plan_nombre'] ?? 'Sin plan')); ?></td>
                  <td><?php echo number_format((int) ($user['total_companies'] ?? 0)); ?></td>
                  <td><?php echo number_format((int) ($user['total_documents'] ?? 0)); ?></td>
                  <td><?php echo number_format($userQuotaConsumed); ?> / <?php echo number_format($userQuotaTotal); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($topUsers === []): ?>
                <tr><td colspan="5" class="text-muted">Sin actividad en este periodo.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-6 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Empresas con mas documentos</h4>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th>Empresa</th><th>Usuario</th><th>Libros</th><th>Docs</th><th>Ultimo</th></tr></thead>
              <tbody>
                <?php foreach ($topCompanies as $company): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) ($company['nombre'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($company['owner_username'] ?? '')); ?></td>
                  <td><?php echo number_format((int) ($company['total_books'] ?? 0)); ?></td>
                  <td><?php echo number_format((int) ($company['total_documents'] ?? 0)); ?></td>
                  <td><?php echo htmlspecialchars(adminDteDate($company['last_import_at'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($topCompanies === []): ?>
                <tr><td colspan="5" class="text-muted">Sin actividad en este periodo.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-5 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Tendencia mensual</h4>
          <?php foreach ($monthlyTrend as $month): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span><?php echo htmlspecialchars((string) ($month['month_key'] ?? '')); ?></span>
            <strong><?php echo number_format((int) ($month['total_documents'] ?? 0)); ?></strong>
          </div>
          <?php endforeach; ?>
          <?php if ($monthlyTrend === []): ?>
          <p class="text-muted mb-0">No hay tendencia disponible.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-7 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Ultimos documentos importados</h4>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th>Documento</th><th>Tipo</th><th>Empresa</th><th>Usuario</th><th>Importado</th></tr></thead>
              <tbody>
                <?php foreach ($latestDocuments as $document): ?>
                <?php $moduleType = (string) ($document['libro_tipo'] ?? ''); ?>
                <tr>
                  <td>
                    <div class="text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($document['codigo_generacion'] ?? '')); ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($document['numero_control'] ?? '')); ?></div>
                  </td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($document['tipo_dte'] ?? '')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($moduleNames[$moduleType] ?? $moduleType); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($document['empresa_nombre'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) ($document['username'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars(adminDteDate($document['created_at'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($latestDocuments === []): ?>
                <tr><td colspan="5" class="text-muted">No hay documentos importados.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
