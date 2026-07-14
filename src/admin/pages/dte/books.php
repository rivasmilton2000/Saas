<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/modulos.php';

$moduleNames = [];
foreach (getLibroModules() as $module) {
    $moduleNames[(string) ($module['tipo'] ?? '')] = (string) ($module['nombre'] ?? ($module['tipo'] ?? 'Modulo'));
}

$search = trim((string) ($_GET['q'] ?? ''));
$moduleType = trim((string) ($_GET['module'] ?? ''));
$params = [];
$where = 'l.estado = TRUE';

if ($search !== '') {
    $where .= " AND (
        LOWER(e.nombre) LIKE ?
        OR LOWER(u.username) LIKE ?
        OR LOWER(COALESCE(u.email, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    array_push($params, $needle, $needle, $needle);
}

if ($moduleType !== '') {
    $where .= ' AND l.tipo = ?';
    $params[] = $moduleType;
}

$stmt = $pdo->prepare(
    "SELECT
        l.id,
        l.tipo,
        l.mes,
        l.anio,
        l.created_at,
        e.nombre AS empresa_nombre,
        u.username,
        u.email,
        COUNT(f.id) AS total_documents,
        MAX(f.created_at) AS last_document_at
     FROM libros l
     INNER JOIN empresas e ON e.id = l.id_empresa
     INNER JOIN usuarios u ON u.id = l.id_usuario
     LEFT JOIN facturas f ON f.id_libro = l.id
     WHERE {$where}
     GROUP BY l.id, l.tipo, l.mes, l.anio, l.created_at, e.nombre, u.username, u.email
     ORDER BY l.anio DESC, l.mes DESC, total_documents DESC
     LIMIT 300"
);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
      <h3 class="font-weight-bold mb-1">Libros DTE</h3>
      <p class="text-muted mb-0">Consulta libros creados por modulo, empresa, usuario y periodo.</p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form class="row g-3" method="GET" action="">
        <input type="hidden" name="page" value="dte.books">
        <div class="col-lg-6">
          <label class="form-label" for="book_q">Busqueda</label>
          <input type="search" class="form-control" id="book_q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Empresa, usuario o correo...">
        </div>
        <div class="col-lg-4">
          <label class="form-label" for="book_module">Modulo</label>
          <select class="form-select" id="book_module" name="module">
            <option value="">Todos</option>
            <?php foreach ($moduleNames as $key => $label): ?>
            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $moduleType === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Libros</h4>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Modulo</th><th>Empresa</th><th>Usuario</th><th>Periodo</th><th>Docs</th><th>Creado</th><th>Ultimo doc.</th></tr></thead>
          <tbody>
            <?php foreach ($books as $book): ?>
            <?php $type = (string) ($book['tipo'] ?? ''); ?>
            <tr>
              <td><?php echo htmlspecialchars($moduleNames[$type] ?? $type); ?></td>
              <td><?php echo htmlspecialchars((string) ($book['empresa_nombre'] ?? '')); ?></td>
              <td>
                <div><?php echo htmlspecialchars((string) ($book['username'] ?? '')); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($book['email'] ?? '')); ?></div>
              </td>
              <td><?php echo str_pad((string) ($book['mes'] ?? 0), 2, '0', STR_PAD_LEFT); ?>/<?php echo htmlspecialchars((string) ($book['anio'] ?? '')); ?></td>
              <td><?php echo number_format((int) ($book['total_documents'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars(adminDteDate($book['created_at'] ?? null)); ?></td>
              <td><?php echo htmlspecialchars(adminDteDate($book['last_document_at'] ?? null)); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($books === []): ?>
            <tr><td colspan="7" class="text-muted">No hay libros con esos filtros.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
