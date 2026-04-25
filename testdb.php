<?php
$pdo = new PDO('pgsql:host=localhost;port=5432;dbname=Saas', 'postgres', '1581');
$stmt = $pdo->query(
    "SELECT column_name, data_type
     FROM information_schema.columns
     WHERE table_schema = 'public' AND table_name = 'empresas'
     ORDER BY ordinal_position"
);

print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
