<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/dashboard/modules.php';
require_once SIETELSA_ROOT . '/includes/analytics/bootstrap.php';

require_dashboard_module('admin_dashboard');
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    $service = new AnalyticsService(db());
    $filters = $service->filters($_GET);
    echo json_encode(
        [
            'ok' => true,
            'report' => $service->report($filters),
            'cards' => $service->dashboardSummary($filters),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('[SIETELSA][analytics] No fue posible generar el reporte.');
    echo json_encode([
        'ok' => false,
        'message' => 'No fue posible cargar las estadísticas.',
    ], JSON_UNESCAPED_UNICODE);
}
