<?php
/**
 * Analytics queries for admin dashboards: revenue, customer cohorts,
 * photo intelligence, trends, and business metrics.
 */

declare(strict_types=1);

/**
 * Get dashboard summary metrics: all-time totals alongside a trailing
 * 30-day window, so the admin can see both "the business overall" and
 * "what's happened lately" side by side. average_order_value is computed
 * from the all-time totals (a single stable number reads better next to
 * the two windowed pairs than a rolling 30-day average would).
 */
function get_dashboard_metrics(PDO $pdo): array {
    $stmt = $pdo->prepare('
        SELECT SUM(total_pence) as revenue, COUNT(*) as orders
        FROM orders
        WHERE status IN (?, ?)
    ');
    $stmt->execute(['paid', 'partial_refund']);
    $allTime = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['revenue' => 0, 'orders' => 0];

    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $stmt = $pdo->prepare('
        SELECT SUM(total_pence) as revenue, COUNT(*) as orders
        FROM orders
        WHERE status IN (?, ?) AND DATE(paid_at) >= ?
    ');
    $stmt->execute(['paid', 'partial_refund', $thirtyDaysAgo]);
    $last30Days = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['revenue' => 0, 'orders' => 0];

    $allTimeOrders = (int)($allTime['orders'] ?? 0);
    $allTimeRevenue = (int)($allTime['revenue'] ?? 0);
    $avgOrderValue = $allTimeOrders > 0 ? (int)round($allTimeRevenue / $allTimeOrders) : 0;

    $photosLive = (int)$pdo->query("SELECT COUNT(*) FROM photos WHERE status = 'live'")->fetchColumn();

    return [
        'all_time_revenue' => $allTimeRevenue,
        'all_time_orders' => $allTimeOrders,
        'last_30_days_revenue' => (int)($last30Days['revenue'] ?? 0),
        'last_30_days_orders' => (int)($last30Days['orders'] ?? 0),
        'avg_order_value' => $avgOrderValue,
        'photos_live' => $photosLive,
    ];
}

/**
 * Get revenue trend data (daily breakdown for graph).
 */
function get_revenue_trend(PDO $pdo, int $days = 30): array {
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    $stmt = $pdo->prepare('
        SELECT DATE(paid_at) as period, SUM(total_pence) as revenue_pence, COUNT(*) as order_count
        FROM orders
        WHERE status IN (?, ?) AND DATE(paid_at) >= ?
        GROUP BY DATE(paid_at)
        ORDER BY period ASC
    ');
    $stmt->execute(['paid', 'partial_refund', $startDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Orders bucketed by hour of day, for spotting when buyers actually shop.
 *
 * The analytics view has always had an "Orders by Hour" panel, but nothing ever
 * computed the data for it, so it rendered its empty state permanently.
 *
 * Returns all 24 hours including zero-count ones. A bar chart with gaps where
 * quiet hours should be misreads badly — 03:00 having no orders is a finding,
 * not a missing data point, and the x-axis has to stay evenly spaced to show it.
 *
 * @return list<array{hour:int, orders:int, revenue_pence:int}>
 */
function get_hourly_distribution(PDO $pdo): array {
    require_once __DIR__ . '/db_compat.php';

    // '%H' is one of the formats db_date_format_sql handles on both drivers:
    // DATE_FORMAT(paid_at, '%H') on MySQL, strftime('%H', paid_at) on SQLite.
    $hourExpr = db_date_format_sql($pdo, 'paid_at', '%H');

    $stmt = $pdo->prepare("
        SELECT {$hourExpr} as hour, COUNT(*) as orders, SUM(total_pence) as revenue_pence
        FROM orders
        WHERE status IN (?, ?) AND paid_at IS NOT NULL
        GROUP BY {$hourExpr}
    ");
    $stmt->execute(['paid', 'partial_refund']);

    $byHour = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byHour[(int)$row['hour']] = [
            'orders' => (int)$row['orders'],
            'revenue_pence' => (int)$row['revenue_pence'],
        ];
    }

    $out = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $out[] = [
            'hour' => $hour,
            'orders' => $byHour[$hour]['orders'] ?? 0,
            'revenue_pence' => $byHour[$hour]['revenue_pence'] ?? 0,
        ];
    }
    return $out;
}

/**
 * Get customer cohorts: first-time, repeat (2-5x), loyal (5+x).
 */
function get_customer_cohorts(PDO $pdo): array {
    $stmt = $pdo->prepare('
        SELECT
            email,
            COUNT(*) as order_count,
            SUM(total_pence) as total_spent_pence,
            MIN(paid_at) as first_purchase_date,
            MAX(paid_at) as last_purchase_date,
            CASE
                WHEN COUNT(*) = 1 THEN "first-time"
                WHEN COUNT(*) BETWEEN 2 AND 5 THEN "repeat"
                ELSE "loyal"
            END as cohort
        FROM orders
        WHERE status IN (?, ?)
        GROUP BY email
        ORDER BY last_purchase_date DESC
    ');
    $stmt->execute(['paid', 'partial_refund']);
    $cohorts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Aggregate by cohort
    $aggregated = [
        'first-time' => ['count' => 0, 'total_spent_pence' => 0, 'avg_order_value' => 0],
        'repeat' => ['count' => 0, 'total_spent_pence' => 0, 'avg_order_value' => 0],
        'loyal' => ['count' => 0, 'total_spent_pence' => 0, 'avg_order_value' => 0],
    ];

    foreach ($cohorts as $customer) {
        $c = $customer['cohort'];
        $aggregated[$c]['count']++;
        $aggregated[$c]['total_spent_pence'] += (int)($customer['total_spent_pence'] ?? 0);
    }

    // Compute averages
    foreach ($aggregated as &$cohort) {
        $cohort['avg_order_value'] = $cohort['count'] > 0 ? (int)($cohort['total_spent_pence'] / $cohort['count']) : 0;
    }

    return $aggregated;
}

/**
 * Get top-performing photos by purchase count or view count.
 */
function get_top_photos(PDO $pdo, string $metric = 'purchases', int $limit = 10): array {
    if ($metric === 'purchases') {
        // times_sold/units_sold differ only when a line item's quantity is
        // >1 (not currently possible for photos, but order_items.quantity
        // exists for that case) — keeping both distinct rather than
        // assuming they're always equal.
        $stmt = $pdo->prepare('
            SELECT p.id, p.public_token, p.original_filename, e.title as event_title,
                   COUNT(*) as times_sold, SUM(oi.quantity) as units_sold, SUM(oi.line_total_pence) as revenue_pence
            FROM order_items oi
            JOIN photos p ON oi.photo_id = p.id
            JOIN events e ON p.event_id = e.id
            WHERE p.status = ?
            GROUP BY p.id
            ORDER BY revenue_pence DESC
            LIMIT ?
        ');
        $stmt->execute(['live', $limit]);
    } elseif ($metric === 'views') {
        $stmt = $pdo->prepare('
            SELECT p.id, p.public_token, p.original_filename, e.title as event_title, p.view_count
            FROM photos p
            JOIN events e ON p.event_id = e.id
            WHERE p.status = ?
            ORDER BY p.view_count DESC
            LIMIT ?
        ');
        $stmt->execute(['live', $limit]);
    } else {
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Get sales by event (revenue breakdown per event).
 */
function get_sales_by_event(PDO $pdo): array {
    // Sums oi.line_total_pence (each item's own value), not o.total_pence:
    // an order can span multiple events, and summing the order's full total
    // once per joined item row would attribute the whole order to every
    // event it touches instead of each event's actual share.
    $stmt = $pdo->prepare('
        SELECT e.id, e.title, COUNT(DISTINCT o.id) as order_count, SUM(oi.quantity) as item_count, SUM(oi.line_total_pence) as revenue_pence
        FROM order_items oi
        JOIN photos p ON oi.photo_id = p.id
        JOIN events e ON p.event_id = e.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status IN (?, ?)
        GROUP BY e.id
        ORDER BY revenue_pence DESC
    ');
    $stmt->execute(['paid', 'partial_refund']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Get customer lifetime value (LTV) distribution.
 */
function get_ltv_distribution(PDO $pdo): array {
    $stmt = $pdo->prepare('
        SELECT
            CASE
                WHEN total_spent_pence < 5000 THEN "under_50"
                WHEN total_spent_pence < 10000 THEN "50_to_100"
                WHEN total_spent_pence < 25000 THEN "100_to_250"
                ELSE "over_250"
            END as ltv_bracket,
            COUNT(*) as customer_count
        FROM (
            SELECT email, SUM(total_pence) as total_spent_pence
            FROM orders
            WHERE status IN (?, ?)
            GROUP BY email
        ) customer_spending
        GROUP BY ltv_bracket
        ORDER BY ltv_bracket ASC
    ');
    $stmt->execute(['paid', 'partial_refund']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Get repeat customer rate (% of returning customers).
 */
function get_repeat_customer_rate(PDO $pdo): float {
    $stmt = $pdo->prepare('
        SELECT
            COUNT(CASE WHEN order_count > 1 THEN 1 END) as repeat_count,
            COUNT(*) as total_count
        FROM (
            SELECT email, COUNT(*) as order_count
            FROM orders
            WHERE status IN (?, ?)
            GROUP BY email
        ) cohort_counts
    ');
    $stmt->execute(['paid', 'partial_refund']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || $result['total_count'] === 0) {
        return 0.0;
    }

    return (float)(($result['repeat_count'] ?? 0) / $result['total_count'] * 100);
}

/**
 * Get average days between purchases (for returning customers).
 */
function get_average_repurchase_interval(PDO $pdo): float {
    $stmt = $pdo->prepare('
        SELECT AVG(DATEDIFF(MAX(paid_at), MIN(paid_at)) / (COUNT(*) - 1)) as avg_interval
        FROM orders
        WHERE status IN (?, ?) AND email IN (
            SELECT email FROM orders WHERE status IN (?, ?) GROUP BY email HAVING COUNT(*) > 1
        )
        GROUP BY email
    ');
    $stmt->execute(['paid', 'partial_refund', 'paid', 'partial_refund']);
    $result = $stmt->fetchColumn();
    return (float)($result ?? 0);
}
