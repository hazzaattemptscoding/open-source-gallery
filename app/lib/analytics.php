<?php
/**
 * Analytics queries for admin dashboards: revenue, customer cohorts,
 * photo intelligence, trends, and business metrics.
 */

declare(strict_types=1);

/**
 * Get dashboard summary metrics for a date range.
 */
function get_dashboard_metrics(PDO $pdo, ?string $dateStart = null, ?string $dateEnd = null): array {
    if (!$dateStart) $dateStart = date('Y-m-d', strtotime('-30 days'));
    if (!$dateEnd) $dateEnd = date('Y-m-d');

    // Total revenue in period
    $stmt = $pdo->prepare('
        SELECT SUM(total_pence) as total_revenue, COUNT(*) as order_count
        FROM orders
        WHERE status IN (?, ?) AND DATE(paid_at) BETWEEN ? AND ?
    ');
    $stmt->execute(['paid', 'partial_refund', $dateStart, $dateEnd]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total_revenue' => 0, 'order_count' => 0];

    // Average order value
    $aov = $revenue['order_count'] > 0 ? (int)($revenue['total_revenue'] ?? 0) / $revenue['order_count'] : 0;

    // Top photo (by purchase count)
    $stmt = $pdo->prepare('
        SELECT p.id, p.public_token, COUNT(*) as purchase_count
        FROM order_items oi
        JOIN photos p ON oi.photo_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.paid_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY purchase_count DESC
        LIMIT 1
    ');
    $stmt->execute([$dateStart, $dateEnd]);
    $topPhoto = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_revenue_pence' => (int)($revenue['total_revenue'] ?? 0),
        'order_count' => (int)($revenue['order_count'] ?? 0),
        'average_order_value_pence' => (int)$aov,
        'top_photo' => $topPhoto,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
    ];
}

/**
 * Get revenue trend data (daily breakdown for graph).
 */
function get_revenue_trend(PDO $pdo, int $days = 30): array {
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    $stmt = $pdo->prepare('
        SELECT DATE(paid_at) as date, SUM(total_pence) as revenue_pence, COUNT(*) as order_count
        FROM orders
        WHERE status IN (?, ?) AND DATE(paid_at) >= ?
        GROUP BY DATE(paid_at)
        ORDER BY date ASC
    ');
    $stmt->execute(['paid', 'partial_refund', $startDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $stmt = $pdo->prepare('
            SELECT p.id, p.public_token, e.title as event_title, COUNT(*) as metric_value
            FROM order_items oi
            JOIN photos p ON oi.photo_id = p.id
            JOIN events e ON p.event_id = e.id
            WHERE p.status = ?
            GROUP BY p.id
            ORDER BY metric_value DESC
            LIMIT ?
        ');
        $stmt->execute(['live', $limit]);
    } elseif ($metric === 'views') {
        $stmt = $pdo->prepare('
            SELECT id, public_token, title as event_title, views
            FROM photos
            WHERE status = ?
            ORDER BY views DESC
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
    $stmt = $pdo->prepare('
        SELECT e.id, e.title, COUNT(DISTINCT o.id) as order_count, SUM(oi.quantity) as item_count, SUM(o.total_pence) as revenue_pence
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
