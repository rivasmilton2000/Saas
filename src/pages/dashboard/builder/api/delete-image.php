<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$input = builder_api_input();
builder_api_require_post($input);

try {
    (new WebsiteImageManager(db()))->delete((int) ($input['image_id'] ?? 0), (int) $currentUser['id']);
    builder_api_response(['ok' => true, 'message' => 'La imagen fue retirada de la biblioteca.']);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
