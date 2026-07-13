<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../models/SubscriptionModel.php';

SubscriptionModel::ensureSchema($pdo);

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '1 = 1';

if ($statusFilter !== '') {
    $where .= ' AND LOWER(s.status) = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where .= " AND (
        LOWER(COALESCE(u.username, '')) LIKE ?
        OR LOWER(COALESCE(u.email, '')) LIKE ?
        OR LOWER(COALESCE(p.nombre, '')) LIKE ?
        OR LOWER(COALESCE(s.stripe_subscription_id, '')) LIKE ?
        OR LOWER(COALESCE(s.stripe_customer_id, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle, $needle, $needle);
}

$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status IN ('active', 'trialing')) AS active,
        COUNT(*) FILTER (WHERE status IN ('payment_pending', 'incomplete', 'past_due')) AS pending,
        COUNT(*) FILTER (WHERE status IN ('canceled', 'cancelled', 'expired')) AS canceled,
        COALESCE(SUM(amount) FILTER (WHERE status IN ('active', 'trialing')), 0) AS active_mrr
     FROM subscriptions"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    "SELECT
        s.*,
        u.username,
        u.email,
        u.suscripcion_estado AS user_subscription_status,
        p.nombre AS plan_nombre,
        p.slug AS plan_slug
     FROM subscriptions s
     LEFT JOIN usuarios u ON u.id = s.user_id
     LEFT JOIN planes p ON p.id_plan = s.plan_id
     WHERE {$where}
     ORDER BY s.updated_at DESC, s.created_at DESC
     LIMIT 300"
);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (!function_exists('adminBillingDate')) {
    function adminBillingDate($value): string
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

if (!function_exists('adminMoney')) {
    function adminMoney($amount, ?string $currency = 'USD'): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        return strtoupper((string) ($currency ?: 'USD')) . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('adminStatusBadge')) {
    function adminStatusBadge(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'active', 'trialing', 'paid' => 'badge-success',
            'payment_pending', 'pending', 'incomplete', 'past_due' => 'badge-warning',
            'canceled', 'cancelled', 'expired', 'failed' => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Suscripciones</h3>
          <p class="text-muted mb-0">Monitorea estados de membresias y referencias de Stripe.</p>
        </div>
        <form class="d-flex flex-column flex-sm-row gap-2" method="GET" action="">
          <input type="hidden" name="page" value="subscriptions">
          <select class="form-select" name="status">
            <option value="">Todos los estados</option>
            <?php foreach (['active', 'trialing', 'payment_pending', 'past_due', 'canceled', 'expired'] as $status): ?>
            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($status); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="input-group">
            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
            <input type="search" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar...">
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Total</p><h3 class="mb-0"><?php echo number_format((int) ($summary['total'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Activas</p><h3 class="mb-0"><?php echo number_format((int) ($summary['active'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Pendientes</p><h3 class="mb-0"><?php echo number_format((int) ($summary['pending'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">MRR activo</p><h3 class="mb-0"><?php echo adminMoney($summary['active_mrr'] ?? 0); ?></h3></div></div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Listado de suscripciones</h4>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Plan</th>
                  <th>Estado</th>
                  <th>Monto</th>
                  <th>Periodo</th>
                  <th>Stripe</th>
                  <th>Actualizada</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                <?php $status = (string) ($subscription['status'] ?? 'payment_pending'); ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($subscription['username'] ?? 'Usuario eliminado')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($subscription['email'] ?? 'Sin correo')); ?></div>
                  </td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($subscription['plan_nombre'] ?? 'Sin plan')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($subscription['plan_slug'] ?? '')); ?></div>
                  </td>
                  <td><span class="badge <?php echo adminStatusBadge($status); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                  <td><?php echo adminMoney($subscription['amount'] ?? null, $subscription['currency'] ?? 'USD'); ?></td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($subscription['billing_interval'] ?? '-')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars(adminBillingDate($subscription['current_period_end'] ?? null)); ?></div>
                  </td>
                  <td>
                    <div class="small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($subscription['stripe_subscription_id'] ?? '-')); ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($subscription['stripe_customer_id'] ?? '')); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars(adminBillingDate($subscription['updated_at'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if ($subscriptions === []): ?>
                <tr><td colspan="7" class="text-muted">No hay suscripciones que coincidan con el filtro.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
