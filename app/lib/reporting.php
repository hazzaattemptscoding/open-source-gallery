<?php
/**
 * Advanced reporting: customer segments, cohorts, LTV, retention.
 */

declare(strict_types=1);

require_once __DIR__ . '/db_compat.php';

/**
 * Get customer insights: segmentation by LTV.
 */
function get_customer_segments(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT
                CASE
                    WHEN lifetime_value_pence >= 100000 THEN 'VIP'
                    WHEN lifetime_value_pence >= 50000 THEN 'High Value'
                    WHEN lifetime_value_pence >= 10000 THEN 'Regular'
                    ELSE 'New'
                END as segment,
                COUNT(*) as customer_count,
                AVG(lifetime_value_pence) as avg_ltv_pence,
                SUM(lifetime_value_pence) as total_revenue_pence,
                AVG(order_count) as avg_orders
            FROM customer_analytics
            GROUP BY segment
            ORDER BY total_revenue_pence DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Failed to get customer segments: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get cohort analysis (monthly acquisition cohorts).
 */
function get_cohort_analysis(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT cohort_month, customers_acquired, revenue_pence, retention_month_1, retention_month_3, retention_month_6, updated_at FROM cohorts ORDER BY cohort_month DESC LIMIT 12');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Failed to get cohort analysis: ' . $e->getMessage());
        return [];
    }
}

/**
 * Calculate customer lifetime value.
 */
function calculate_ltv(PDO $pdo, string $customerEmail): ?int {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT SUM(total_pence) as total FROM orders WHERE email = ?
        SQL);
        $stmt->execute([$customerEmail]);
        return (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) {
        error_log('Failed to calculate LTV for ' . $customerEmail . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Update customer analytics (run by cron).
 */
function update_customer_analytics(PDO $pdo): int {
    try {
        $pdo->beginTransaction();

        $pdo->query('DELETE FROM customer_analytics');

        $stmt = $pdo->query(<<<'SQL'
            INSERT INTO customer_analytics (customer_email, total_spent_pence, order_count, first_order_at, last_order_at, lifetime_value_pence)
            SELECT email, SUM(total_pence), COUNT(*), MIN(created_at), MAX(created_at), SUM(total_pence)
            FROM orders
            GROUP BY email
        SQL);

        $inserted = $stmt->rowCount();
        $pdo->commit();
        return $inserted;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Failed to update customer analytics: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get repeat customer rate (% of customers with 2+ orders).
 */
function get_repeat_rate(PDO $pdo): ?float {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT
                SUM(CASE WHEN order_count >= 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as rate
            FROM customer_analytics
        SQL);
        return (float)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) {
        error_log('Failed to get repeat rate: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get retention metrics for a cohort.
 */
function get_cohort_retention(PDO $pdo, string $cohortMonth): ?array {
    try {
        $stmt = $pdo->prepare('SELECT cohort_month, customers_acquired, revenue_pence, retention_month_1, retention_month_3, retention_month_6, updated_at FROM cohorts WHERE cohort_month = ?');
        $stmt->execute([$cohortMonth]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Failed to get cohort retention for ' . $cohortMonth . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Get top customers by LTV.
 */
function get_top_customers(PDO $pdo, int $limit = 10): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT customer_email, lifetime_value_pence, order_count, last_order_at
            FROM customer_analytics
            ORDER BY lifetime_value_pence DESC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Failed to get top customers: ' . $e->getMessage());
        return [];
    }
}
