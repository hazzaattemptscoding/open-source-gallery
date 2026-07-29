<?php
/**
 * Advanced customer reporting and segmentation.
 * Cohorts, LTV, repeat rates, retention metrics.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/reporting.php';

function admin_reporting_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_view_analytics($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $reporting = [
        'segments' => get_customer_segments($pdo),
        'cohorts' => get_cohort_analysis($pdo),
        'top_customers' => get_top_customers($pdo, 15),
        'repeat_rate' => get_repeat_rate($pdo),
    ];

    render(__DIR__ . '/../../views/admin/reporting.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'reporting' => $reporting,
        'currencySymbol' => $config['currency']['symbol'] ?? '£',
    ]);
}
