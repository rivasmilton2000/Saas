<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$input = $_POST;
builder_api_require_post($input);

try {
    $image = (new WebsiteImageManager(db()))->upload($_FILES['image'] ?? [], (int) $currentUser['id']);
    builder_api_response(['ok' => true, 'message' => 'Imagen optimizada y almacenada.', 'image' => $image], 201);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
