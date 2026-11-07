<?php
$pdo = new PDO('mysql:host=localhost;dbname=saas_contabilidad', 'root', '');
$stmt = $pdo->query('DESCRIBE empresas');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
