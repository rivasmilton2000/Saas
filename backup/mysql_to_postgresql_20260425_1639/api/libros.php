<?php

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require __DIR__ . '/libros/listar.php';
    return;
}

if ($method === 'POST') {
    require __DIR__ . '/libros/crear.php';
    return;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'data' => null, 'message' => 'Metodo no permitido.']);
