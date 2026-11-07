<?php
declare(strict_types=1);

$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = "u.estado = TRUE AND u.rol = 'user'";

if ($search !== '') {
    $where .= " AND (
        LOWER(u.username) LIKE ?
        OR LOWER(COALESCE(u.email, '')) LIKE ?
        OR LOWER(COALESCE(p.nombre, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle);
}

$stmt = $pdo->prepare(
    "SELECT
        u.id,
        u.username,
        u.email,
        p.nombre AS plan_nombre,
        COALESCE(fd.total, 0) AS total,
        COALESCE(fd.consumidas, 0) AS consumidas,
        GREATEST(COALESCE(fd.total, 0) - COALESCE(fd.consumidas, 0), 0) AS disponibles,
        fd.updated_at,
        COALESCE(imported.total_documents, 0) AS imported_documents
     FROM usuarios u
     LEFT JOIN planes p ON p.id_plan = u.id_plan
     LEFT JOIN facturas_disponibles fd ON fd.id_usuario = u.id
     LEFT JOIN (
        SELECT id_usuario, COUNT(*) AS total_documents
        FROM facturas
        GROUP BY id_usuario
     ) imported ON imported.id_usuario = u.id
     WHERE {$where}
     ORDER BY consumidas DESC, u.username ASC
     LIMIT 300"
);
$stmt->execute($params);
$quotas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

        return number_format(min(100, ($part / $total) * 100), 1) . '%';
    }
}
?>

<div class="content-wrapper">
  <?php require __DIR__ . '/_nav.php'; ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Cuotas DTE</h3>
          <p class="text-muted mb-0">Revisa documentos disponibles y consumidos por usuario.</p>
        </div>
        <form method="GET" action="">
          <input type="hidden" name="page" value="dte.quotas">
          <div class="input-group">
            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
            <input type="search" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar usuario...">
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Cuotas por usuario</h4>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Usuario</th><th>Plan</th><th>Consumidas</th><th>Disponibles</th><th>Uso</th><th>Docs reales</th><th>Actualizado</th></tr></thead>
          <tbody>
            <?php foreach ($quotas as $quota): ?>
            <?php
              $total = (int) ($quota['total'] ?? 0);
              $consumed = (int) ($quota['consumidas'] ?? 0);
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($quota['username'] ?? '')); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($quota['email'] ?? 'Sin correo')); ?></div>
              </td>
              <td><?php echo htmlspecialchars((string) ($quota['plan_nombre'] ?? 'Sin plan')); ?></td>
              <td><?php echo number_format($consumed); ?> / <?php echo number_format($total); ?></td>
              <td><?php echo number_format((int) ($quota['disponibles'] ?? 0)); ?></td>
              <td style="min-width: 160px;">
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar <?php echo $total > 0 && $consumed >= $total ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?php echo htmlspecialchars(adminDtePercent($consumed, $total)); ?>"></div>
                </div>
                <div class="text-muted small mt-1"><?php echo htmlspecialchars(adminDtePercent($consumed, $total)); ?></div>
              </td>
              <td><?php echo number_format((int) ($quota['imported_documents'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars(adminDteDate($quota['updated_at'] ?? null)); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($quotas === []): ?>
            <tr><td colspan="7" class="text-muted">No hay cuotas con esos filtros.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
