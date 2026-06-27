<?php
require_once __DIR__ . '/src/config/app.php';

$pdo = new PDO('mysql:host=localhost;dbname=' . app_db_name(), 'root', '');
$stmt = $pdo->query('DESCRIBE empresas');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
