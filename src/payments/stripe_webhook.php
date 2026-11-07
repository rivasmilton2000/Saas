<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/PublicRegistrationService.php';
require_once __DIR__ . '/../services/StripeBillingService.php';

http_response_code(200);

try {
    $payload = file_get_contents('php://input');
    $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

    if ($payload === false || trim($payload) === '') {
        throw new RuntimeException('Payload vacio.');
    }

    $event = StripeBillingService::constructWebhookEvent($payload, $signature);
    PublicRegistrationService::handleStripeWebhook($pdo, $event);

    header('Content-Type: application/json');
    echo json_encode(['received' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('[Stripe Webhook] ' . $exception->getMessage());
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'received' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
