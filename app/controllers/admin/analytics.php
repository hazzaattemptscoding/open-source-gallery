<?php
/**
 * Advanced analytics dashboard.
 * Shows revenue trends, top photos, customer insights, conversion metrics.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/analytics.php';
require_once __DIR__ . '/../../lib/currency.php';

function admin_analytics_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_view_analytics($pdo)) {
        http_response_code(403);
        echo '403 Forbidden: Analytics access required';
        exit;
    }

    // Get all analytics data
    $analytics = [
        'summary' => get_dashboard_metrics($pdo),
        'revenue_trend' => get_revenue_trend($pdo, 30),
        'hourly_distribution' => get_hourly_distribution($pdo),
        'top_photos' => get_top_photos($pdo, 'purchases', 10),
        'sales_by_event' => get_sales_by_event($pdo),
        'customer_cohorts' => get_customer_cohorts($pdo),
        'repeat_rate' => get_repeat_customer_rate($pdo),
        'ltv_distribution' => get_ltv_distribution($pdo),
    ];

    $currencyCode = $config['currency'] ?? 'GBP';
    render(__DIR__ . '/../../views/admin/analytics.php', [
        'pageTitle' => 'Analytics',
        'currentPage' => 'analytics',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'analytics' => $analytics,
        'currencyCode' => $currencyCode,
        'currencySymbol' => currency_symbol($currencyCode),
    ]);
}
