<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$input = builder_api_input();
builder_api_require_post($input);
if (!isset($input['draft_id'], $input['revision']) || (int) $input['draft_id'] < 1 || (int) $input['revision'] < 1) builder_api_response(['ok' => false, 'message' => 'Recarga el editor para obtener la versión actual.'], 409);

try {
    $draft = (new WebsiteService(db()))->restoreToDraft(
        (int) $currentUser['id'],
        (int) ($input['version_id'] ?? 0),
        (int) $input['revision'],
        (int) $input['draft_id']
    );
    builder_api_response([
        'ok' => true,
        'message' => 'La versión fue restaurada como borrador. Publícala cuando termines de revisarla.',
        'draft' => [
            'id' => $draft['id'],
            'revision' => $draft['revision'],
            'values' => WebsiteValidator::flatten($draft['content']),
        ],
    ]);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
