<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$input = builder_api_input();
builder_api_require_post($input);
if (!isset($input['draft_id'], $input['revision']) || (int) $input['draft_id'] < 1 || (int) $input['revision'] < 1) builder_api_response(['ok' => false, 'message' => 'Recarga el editor para obtener la versión actual.'], 409);

try {
    $changes = $input['changes'] ?? null;
    if (!is_array($changes) || $changes === []) {
        throw new InvalidArgumentException('No hay cambios válidos para guardar.');
    }
    $draft = (new WebsiteService(db()))->saveDraft(
        (int) $currentUser['id'],
        $changes,
        (int) ($input['revision'] ?? 0),
        (int) $input['draft_id']
    );
    builder_api_response([
        'ok' => true,
        'message' => 'Borrador guardado.',
        'draft' => [
            'id' => $draft['id'],
            'revision' => $draft['revision'],
            'values' => WebsiteValidator::flatten($draft['content']),
            'updated_at' => $draft['updated_at'],
            'editor' => $draft['editor'],
        ],
    ]);
} catch (Throwable $exception) {
    builder_api_error($exception);
}
