<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../models/EmpresaModel.php';

EmpresaModel::getByUsuario($pdo, 0);

$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = "e.estado = TRUE";

if ($search !== '') {
    $where .= " AND (
        LOWER(e.nombre) LIKE ?
        OR LOWER(COALESCE(e.nit, '')) LIKE ?
        OR LOWER(COALESCE(e.nrc, '')) LIKE ?
        OR LOWER(COALESCE(u.username, '')) LIKE ?
        OR LOWER(COALESCE(u.email, '')) LIKE ?
    )";
    $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
    $params = [$needle, $needle, $needle, $needle, $needle];
}

$stmt = $pdo->prepare(
    "SELECT
        e.id,
        e.id_usuario,
        e.nombre,
        e.iniciales,
        e.color_emblema,
        e.dui,
        e.nit,
        e.nrc,
        e.tipo_legal,
        e.created_at,
        e.ultima_vez_usada,
        u.username AS owner_username,
        u.email AS owner_email,
        COALESCE(libros.total_libros, 0) AS total_libros,
        COALESCE(facturas.total_facturas, 0) AS total_facturas
     FROM empresas e
     INNER JOIN usuarios u ON u.id = e.id_usuario
     LEFT JOIN (
        SELECT id_empresa, COUNT(*) AS total_libros
        FROM libros
        WHERE estado = TRUE
        GROUP BY id_empresa
     ) libros ON libros.id_empresa = e.id
     LEFT JOIN (
        SELECT l.id_empresa, COUNT(f.id) AS total_facturas
        FROM libros l
        LEFT JOIN facturas f ON f.id_libro = l.id
        WHERE l.estado = TRUE
        GROUP BY l.id_empresa
     ) facturas ON facturas.id_empresa = e.id
     WHERE {$where}
     ORDER BY e.ultima_vez_usada DESC NULLS LAST, e.created_at DESC
     LIMIT 300"
);
$stmt->execute($params);
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$summaryStmt = $pdo->query(
    "SELECT
        COUNT(*) FILTER (WHERE estado = TRUE) AS total_empresas,
        COUNT(DISTINCT id_usuario) FILTER (WHERE estado = TRUE) AS usuarios_con_empresa
     FROM empresas"
);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!function_exists('adminCompanyDate')) {
    function adminCompanyDate($value): string
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
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
          <h3 class="font-weight-bold mb-1">Empresas</h3>
          <p class="text-muted mb-0">Consulta las empresas registradas por los clientes del SaaS.</p>
        </div>
        <form class="d-flex" method="GET" action="">
          <input type="hidden" name="page" value="companies">
          <div class="input-group" style="min-width: min(100%, 360px);">
            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
            <input type="search" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar empresa...">
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Empresas activas</p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['total_empresas'] ?? 0)); ?></h3>
      </div></div>
    </div>
    <div class="col-md-4 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Usuarios con empresa</p>
        <h3 class="mb-0"><?php echo number_format((int) ($summary['usuarios_con_empresa'] ?? 0)); ?></h3>
      </div></div>
    </div>
    <div class="col-md-4 grid-margin stretch-card">
      <div class="card"><div class="card-body">
        <p class="text-muted mb-1">Resultados visibles</p>
        <h3 class="mb-0"><?php echo number_format(count($companies)); ?></h3>
      </div></div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title">Listado de empresas</h4>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Empresa</th>
                  <th>Propietario</th>
                  <th>NIT / NRC</th>
                  <th>Tipo</th>
                  <th>Libros</th>
                  <th>Facturas</th>
                  <th>Ultimo uso</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($companies as $company): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="d-inline-flex align-items-center justify-content-center text-white fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo htmlspecialchars((string) ($company['color_emblema'] ?? '#4b49ac')); ?>;">
                        <?php echo htmlspecialchars((string) ($company['iniciales'] ?? 'EMP')); ?>
                      </span>
                      <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($company['nombre'] ?? '')); ?></div>
                        <div class="text-muted small">ID <?php echo (int) ($company['id'] ?? 0); ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($company['owner_username'] ?? '')); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($company['owner_email'] ?? 'Sin correo')); ?></div>
                  </td>
                  <td>
                    <div><?php echo htmlspecialchars((string) ($company['nit'] ?? '-')); ?></div>
                    <div class="text-muted small">NRC: <?php echo htmlspecialchars((string) ($company['nrc'] ?? '-')); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($company['tipo_legal'] ?? '-')); ?></td>
                  <td><?php echo number_format((int) ($company['total_libros'] ?? 0)); ?></td>
                  <td><?php echo number_format((int) ($company['total_facturas'] ?? 0)); ?></td>
                  <td><?php echo htmlspecialchars(adminCompanyDate($company['ultima_vez_usada'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if ($companies === []): ?>
                <tr>
                  <td colspan="7" class="text-muted">No hay empresas que coincidan con la busqueda.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
