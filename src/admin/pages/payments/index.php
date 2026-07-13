<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../models/PaymentModel.php';

PaymentModel::ensureSchema($pdo);

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

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '1 = 1';

if ($statusFilter !== '') {
    $where .= ' AND LOWER(pay.status) = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where .= " AND (
        LOWER(COALESCE(u.username, '')) LIKE ?
        OR LOWER(COALESCE(u.email, '')) LIKE ?
        OR LOWER(COALESCE(p.nombre, '')) LIKE ?
        OR LOWER(COALESCE(pay.stripe_invoice_id, '')) LIKE ?
        OR LOWER(COALESCE(pay.stripe_checkout_session_id, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle, $needle, $needle);
}

$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'paid') AS paid_count,
        COUNT(*) FILTER (WHERE status <> 'paid') AS pending_count,
        COALESCE(SUM(amount) FILTER (WHERE status = 'paid'), 0) AS paid_total
     FROM payments"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare(
    "SELECT
        pay.*,
        u.username,
        u.email,
        p.nombre AS plan_nombre,
        p.slug AS plan_slug,
        s.status AS subscription_status
     FROM payments pay
     LEFT JOIN usuarios u ON u.id = pay.user_id
     LEFT JOIN planes p ON p.id_plan = pay.plan_id
     LEFT JOIN subscriptions s ON s.id = pay.subscription_id
     WHERE {$where}
     ORDER BY pay.created_at DESC, pay.updated_at DESC
     LIMIT 300"
);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="content-wrapper">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Pagos</h3>
          <p class="text-muted mb-0">Revisa pagos confirmados, recibos y referencias de Stripe.</p>
        </div>
        <form class="d-flex flex-column flex-sm-row gap-2" method="GET" action="">
          <input type="hidden" name="page" value="payments">
          <select class="form-select" name="status">
            <option value="">Todos los estados</option>
            <?php foreach (['paid', 'pending', 'failed'] as $status): ?>
            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($status); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="input-group">
            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
            <input type="search" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar pago...">
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Pagos registrados</p><h3 class="mb-0"><?php echo number_format((int) ($summary['total'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Pagados</p><h3 class="mb-0"><?php echo number_format((int) ($summary['paid_count'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Pendientes / otros</p><h3 class="mb-0"><?php echo number_format((int) ($summary['pending_count'] ?? 0)); ?></h3></div></div>
    </div>
    <div class="col-md-6 col-xl-3 grid-margin stretch-card">
      <div class="card"><div class="card-body"><p class="text-muted mb-1">Ingresos pagados</p><h3 class="mb-0"><?php echo adminMoney($summary['paid_total'] ?? 0); ?></h3></div></div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Historial de pagos</h4>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Plan</th>
                  <th>Estado</th>
                  <th>Monto</th>
                  <th>Factura Stripe</th>
                  <th>Recibo</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $payment): ?>
                <?php $status = (string) ($payment['status'] ?? 'pending'); ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($payment['username'] ?? 'Usuario eliminado')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($payment['email'] ?? 'Sin correo')); ?></div>
                  </td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($payment['plan_nombre'] ?? 'Sin plan')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($payment['plan_slug'] ?? '')); ?></div>
                  </td>
                  <td><span class="badge <?php echo adminStatusBadge($status); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                  <td><?php echo adminMoney($payment['amount'] ?? null, $payment['currency'] ?? 'USD'); ?></td>
                  <td>
                    <div class="small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($payment['stripe_invoice_id'] ?? '-')); ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars((string) ($payment['stripe_checkout_session_id'] ?? '')); ?></div>
                  </td>
                  <td>
                    <?php if (!empty($payment['receipt_url'])): ?>
                    <a href="<?php echo htmlspecialchars((string) $payment['receipt_url']); ?>" target="_blank" rel="noopener">Ver recibo</a>
                    <?php else: ?>
                    <span class="text-muted">Sin recibo</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars(adminBillingDate($payment['created_at'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if ($payments === []): ?>
                <tr><td colspan="7" class="text-muted">No hay pagos que coincidan con el filtro.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
