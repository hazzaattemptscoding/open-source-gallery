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
        'summary' => get_analytics_summary($pdo),
        'revenue_trend' => get_revenue_trend($pdo, 'daily', 30),
        'top_photos' => get_top_photos($pdo, 10),
        'sales_by_event' => get_sales_by_event($pdo),
        'customer_insights' => get_customer_insights($pdo),
        'conversion_metrics' => get_conversion_metrics($pdo, 30),
        'hourly_distribution' => get_order_distribution_by_hour($pdo),
    ];

    $currencyCode = $config['currency'] ?? 'GBP';
    render(__DIR__ . '/../../views/admin/analytics.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'analytics' => $analytics,
        'currencyCode' => $currencyCode,
        'currencySymbol' => currency_symbol($currencyCode),
    ]);
}
