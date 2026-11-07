<?php
declare(strict_types=1);

require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sietelsa_abort(405);
}

$uuid = (string) ($_GET['import_uuid'] ?? '');
$import = $importService->requireAuthorized($uuid, (int) $companyContextUser['id']);
$metadataStatement = db()->prepare('SELECT e.nombre_comercial,l.periodo_anio,l.periodo_mes,CONCAT_WS(" ",u.nombre,u.apellido) usuario FROM importaciones_dte i INNER JOIN empresas e ON e.id=i.empresa_id INNER JOIN libros_contables l ON l.id=i.libro_id INNER JOIN usuarios u ON u.id=i.creado_por WHERE i.id=:id');
$metadataStatement->execute(['id' => $import['id']]);
$metadata = $metadataStatement->fetch(PDO::FETCH_ASSOC) ?: [];
$valid = (int) $import['importados'] + (int) $import['repetidos_lote'] + (int) $import['repetidos_libro'] + (int) $import['repetidos_otro_periodo'] + (int) $import['conflictos'] + (int) $import['fuera_periodo'] + (int) $import['incompatibles'];
$duration = ($import['iniciado_en'] && $import['finalizado_en'])
    ? max(0, strtotime((string) $import['finalizado_en']) - strtotime((string) $import['iniciado_en']))
    : null;
$safeCsv = static function (mixed $value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
};

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="informe-importacion-'.preg_replace('/[^a-z0-9-]/i', '', $uuid).'.csv"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'wb');
fputcsv($out, ['Resumen de importación DTE']);
foreach ([
    'Importación' => $import['uuid'],
    'Fecha' => $import['creado_en'],
    'Usuario' => $metadata['usuario'] ?? '',
    'Empresa' => $metadata['nombre_comercial'] ?? '',
    'Módulo' => $import['modulo'],
    'Período' => sprintf('%02d/%04d', (int) ($metadata['periodo_mes'] ?? 0), (int) ($metadata['periodo_anio'] ?? 0)),
    'Total seleccionado' => $import['total_declarado'],
    'Válidos estructuralmente' => $valid,
    'Importados' => $import['importados'],
    'Duplicados' => (int) $import['repetidos_lote'] + (int) $import['repetidos_libro'] + (int) $import['repetidos_otro_periodo'],
    'Fuera de período' => $import['fuera_periodo'],
    'Incompatibles' => $import['incompatibles'],
    'Inválidos' => $import['invalidos'],
    'Errores técnicos' => $import['errores'],
    'Cancelados' => $import['cancelados'],
    'Duración (segundos)' => $duration ?? 'En proceso',
] as $label => $value) {
    fputcsv($out, [$safeCsv($label), $safeCsv($value)]);
}
fputcsv($out, []);
fputcsv($out, ['No.', 'Archivo', 'Resultado', 'Tipo DTE', 'Fecha', 'Código de generación', 'Número de control', 'Mensaje', 'Duración ms']);
$statement = db()->prepare('SELECT secuencia,nombre_original_seguro,estado,tipo_dte,fecha_documento,codigo_generacion,numero_control,mensaje_usuario,duracion_ms FROM importacion_dte_archivos WHERE importacion_id=:id ORDER BY secuencia');
$statement->execute(['id' => $import['id']]);
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, array_map($safeCsv, array_values($row)));
}
fclose($out);
exit;
