<?php 
// Connection database
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/PlanModel.php';

function getActivePlansWithFeatures(PDO $pdo): array
{
    return PlanModel::getActivePlansWithFeatures($pdo);
}
