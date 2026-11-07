<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$input = builder_api_input();
builder_api_require_post($input);
if (!isset($input['draft_id'], $input['revision']) || (int) $input['draft_id'] < 1 || (int) $input['revision'] < 1) builder_api_response(['ok' => false, 'message' => 'Recarga el editor para obtener la versión actual.'], 409);

try {
    $result = (new WebsiteService(db()))->publish(
        (int) $currentUser['id'],
        (int) ($input['revision'] ?? 0),
        (int) $input['draft_id']
    );
    builder_api_response([
        'ok' => true,
        'message' => 'El Website fue publicado de forma transaccional.',
        'published_id' => $result['published']['id'],
        'draft' => [
            'id' => $result['draft']['id'],
            'revision' => $result['draft']['revision'],
            'values' => WebsiteValidator::flatten($result['draft']['content']),
        ],
        'history' => (new WebsiteService(db()))->history(),
    ]);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
