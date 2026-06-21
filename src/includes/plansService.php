<?php 
// Connection database
require_once __DIR__ . '/../config/db.php';

function getActivePlansWithFeatures(PDO $pdo): array
{
    $stmt = $pdo->query
    (
        "SELECT id_plan, nombre, descripcion, precio, moneda, periodo, destacado, activo, orden FROM planes 
        WHERE activo = TRUE 
        ORDER BY orden ASC"
    );

    $planes = $stmt->fetchAll();

    foreach($planes as &$plan)
        {
            $stmtFeatures = $pdo->prepare
            (
                "SELECT caracteristicas, incluido FROM planes_caracteristicas WHERE id_plan = ?
                ORDER BY orden ASC"
            );

            $stmtFeatures->execute([$plan['id_plan']]);
            $plan['caracteristicas'] = $stmtFeatures->fetchAll();
        }

    unset($plan);

    return $planes;
}