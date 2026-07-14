<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/modulos.php';
require_once __DIR__ . '/../../../models/FacturaModel.php';

FacturaModel::ensureExtendedSchema($pdo);

$moduleNames = [];
foreach (getLibroModules() as $module) {
    $moduleNames[(string) ($module['tipo'] ?? '')] = (string) ($module['nombre'] ?? ($module['tipo'] ?? 'Modulo'));
}

$search = trim((string) ($_GET['q'] ?? ''));
$tipoDte = trim((string) ($_GET['tipo_dte'] ?? ''));
$moduleType = trim((string) ($_GET['module'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));
$params = [];
$where = '1 = 1';

if ($search !== '') {
    $where .= " AND (
        LOWER(f.codigo_generacion) LIKE ?
        OR LOWER(COALESCE(f.numero_control, '')) LIKE ?
        OR LOWER(COALESCE(e.nombre, '')) LIKE ?
        OR LOWER(COALESCE(u.username, '')) LIKE ?
        OR LOWER(COALESCE(f.nombre_proveedor, '')) LIKE ?
        OR LOWER(COALESCE(f.nombre_cliente, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle, $needle, $needle, $needle);
}

if ($tipoDte !== '') {
    $where .= ' AND f.tipo_dte = ?';
    $params[] = $tipoDte;
}

if ($moduleType !== '') {
    $where .= ' AND l.tipo = ?';
    $params[] = $moduleType;
}

if ($dateFrom !== '') {
    $where .= ' AND f.fecha >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where .= ' AND f.fecha <= ?';
    $params[] = $dateTo;
}

$types = $pdo->query(
    "SELECT DISTINCT COALESCE(NULLIF(TRIM(tipo_dte), ''), 'Sin tipo') AS tipo_dte
     FROM facturas
     ORDER BY tipo_dte ASC"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];

$stmt = $pdo->prepare(
    "SELECT
        f.id,
        f.codigo_generacion,
        f.numero_control,
        f.tipo_dte,
        f.fecha,
        f.created_at,
        f.nombre_proveedor,
        f.nombre_cliente,
        COALESCE(NULLIF(f.total_compras, 0), NULLIF(f.total_ventas_diarias_propias, 0), NULLIF(f.ventas_totales, 0), NULLIF(f.monto_sujeto_retencion, 0), 0) AS monto_referencia,
        l.tipo AS libro_tipo,
        l.mes,
        l.anio,
        e.nombre AS empresa_nombre,
        u.username,
        u.email
     FROM facturas f
     INNER JOIN libros l ON l.id = f.id_libro
     INNER JOIN empresas e ON e.id = l.id_empresa
     INNER JOIN usuarios u ON u.id = f.id_usuario
     WHERE {$where}
     ORDER BY f.created_at DESC, f.id DESC
     LIMIT 300"
);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
?>

<div class="content-wrapper">
  <?php require __DIR__ . '/_nav.php'; ?>
  <div class="row mb-4">
    <div class="col-12">
      <h3 class="font-weight-bold mb-1">Documentos DTE</h3>
      <p class="text-muted mb-0">Busca documentos por codigo, control, empresa, usuario, cliente o proveedor.</p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form class="row g-3" method="GET" action="">
        <input type="hidden" name="page" value="dte.documents">
        <div class="col-lg-4">
          <label class="form-label" for="dte_q">Busqueda</label>
          <input type="search" class="form-control" id="dte_q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Codigo, empresa, usuario...">
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="dte_tipo">Tipo DTE</label>
          <select class="form-select" id="dte_tipo" name="tipo_dte">
            <option value="">Todos</option>
            <?php foreach ($types as $type): ?>
            <option value="<?php echo htmlspecialchars((string) $type); ?>" <?php echo $tipoDte === (string) $type ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $type); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="dte_module">Modulo</label>
          <select class="form-select" id="dte_module" name="module">
            <option value="">Todos</option>
            <?php foreach ($moduleNames as $key => $label): ?>
            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $moduleType === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="dte_from">Desde</label>
          <input type="date" class="form-control" id="dte_from" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="dte_to">Hasta</label>
          <input type="date" class="form-control" id="dte_to" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <a href="<?php echo htmlspecialchars($adminBaseUrl . '?page=dte.documents'); ?>" class="btn btn-light">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Resultados</h4>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Documento</th><th>Tipo</th><th>Empresa</th><th>Usuario</th><th>Monto ref.</th><th>Fecha</th><th>Importado</th></tr></thead>
          <tbody>
            <?php foreach ($documents as $document): ?>
            <?php $libroTipo = (string) ($document['libro_tipo'] ?? ''); ?>
            <tr>
              <td>
                <div class="text-truncate" style="max-width: 260px;"><?php echo htmlspecialchars((string) ($document['codigo_generacion'] ?? '')); ?></div>
                <div class="text-muted small text-truncate" style="max-width: 260px;"><?php echo htmlspecialchars((string) ($document['numero_control'] ?? '')); ?></div>
              </td>
              <td>
                <div><?php echo htmlspecialchars((string) ($document['tipo_dte'] ?? '')); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($moduleNames[$libroTipo] ?? $libroTipo); ?></div>
              </td>
              <td><?php echo htmlspecialchars((string) ($document['empresa_nombre'] ?? '')); ?></td>
              <td>
                <div><?php echo htmlspecialchars((string) ($document['username'] ?? '')); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($document['email'] ?? '')); ?></div>
              </td>
              <td>USD <?php echo number_format((float) ($document['monto_referencia'] ?? 0), 2); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['fecha'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars(adminDteDate($document['created_at'] ?? null)); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($documents === []): ?>
            <tr><td colspan="7" class="text-muted">No hay documentos con esos filtros.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
