<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/includes/dashboard/modules.php';
require_once dirname(__DIR__, 4) . '/includes/website/bootstrap.php';

$currentUser = require_dashboard_module('website_builder');

function builder_api_input(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) builder_api_response(['ok' => false, 'message' => 'El documento supera el tamaño permitido.'], 413);
        try {
            $value = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);

            return is_array($value) ? $value : [];
        } catch (JsonException $exception) {
            return [];
        }
    }

    return $_POST;
}

function builder_api_require_post(array $input): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        builder_api_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
    $token = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_is_valid(is_string($token) ? $token : null)) {
        builder_api_response(['ok' => false, 'message' => 'La sesión del editor venció. Recarga la página.'], 419);
    }
}

function builder_api_response(array $payload, int $status = 200): void
{
    if (($payload['ok'] ?? true) === false) AuditService::failure($status === 403 ? 'denegado' : 'fallido');
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function builder_api_error(Throwable $exception): void
{
    if ($exception instanceof InvalidArgumentException) {
        builder_api_response(['ok' => false, 'message' => $exception->getMessage()], 422);
    }
    if ($exception instanceof RuntimeException && !($exception instanceof PDOException)) {
        builder_api_response(['ok' => false, 'message' => $exception->getMessage()], 409);
    }
    error_log('[SIETELSA][builder] Error interno en el editor visual.');
    builder_api_response(['ok' => false, 'message' => 'No fue posible completar la operación.'], 500);
}
