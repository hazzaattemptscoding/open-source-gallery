<?php
/**
 * Advanced analytics and reporting.
 * Generates revenue trends, top photos, customer insights, etc.
 */

declare(strict_types=1);

require_once __DIR__ . '/db_compat.php';

/**
 * Get revenue trend data (daily/weekly/monthly).
 */
function get_revenue_trend(
    PDO $pdo,
    string $period = 'daily',
    int $days = 30
): array {
    try {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $cutoff = (clone $now)->modify("-{$days} days")->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(<<<'SQL'
            SELECT created_at, COUNT(*) as orders, SUM(total_pence) as revenue_pence, AVG(total_pence) as avg_order_value
            FROM orders
            WHERE status = 'paid' AND created_at > ?
            ORDER BY created_at ASC
        SQL);
        $stmt->execute([$cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $dt = new DateTime($row['created_at']);
            $key = match ($period) {
                'weekly' => $dt->format('Y-W'),
                'monthly' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };

            if (!isset($grouped[$key])) {
                $grouped[$key] = ['period' => $key, 'orders' => 0, 'revenue_pence' => 0, 'count' => 0, 'sum_values' => 0];
            }
            $grouped[$key]['orders'] += (int)$row['orders'];
            $grouped[$key]['revenue_pence'] += (int)($row['revenue_pence'] ?? 0);
            $grouped[$key]['count'] += (int)$row['orders'];
            $grouped[$key]['sum_values'] += (float)$row['avg_order_value'] * (int)$row['orders'];
        }

        $result = [];
        foreach ($grouped as $item) {
            $result[] = [
                'period' => $item['period'],
                'orders' => $item['orders'],
                'revenue_pence' => $item['revenue_pence'],
                'avg_order_value' => $item['count'] > 0 ? (int)($item['sum_values'] / $item['count']) : 0,
            ];
        }
        return $result;
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get top-selling photos.
 */
function get_top_photos(PDO $pdo, int $limit = 10): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT
                p.id, p.public_token, p.original_filename,
                COALESCE(p.price_pence, e.price_single_pence) AS price_pence, e.title as event_title,
                COUNT(oi.id) as times_sold,
                SUM(oi.quantity) as units_sold,
                SUM(oi.unit_price_pence * oi.quantity) as revenue_pence
            FROM photos p
            JOIN events e ON p.event_id = e.id
            LEFT JOIN order_items oi ON p.id = oi.photo_id
            WHERE oi.id IS NOT NULL
            GROUP BY p.id, p.public_token, p.original_filename, p.price_pence, e.price_single_pence, e.title
            ORDER BY revenue_pence DESC, units_sold DESC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get sales by event.
 */
function get_sales_by_event(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT
                e.id, e.title, e.slug,
                COUNT(DISTINCT o.id) as orders,
                COUNT(DISTINCT oi.id) as items_sold,
                SUM(o.total_pence) as revenue_pence
            FROM events e
            JOIN photos p ON e.id = p.event_id
            LEFT JOIN order_items oi ON p.id = oi.photo_id AND oi.item_type = 'photo'
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'paid'
            GROUP BY e.id, e.title, e.slug
            ORDER BY revenue_pence DESC NULLS LAST
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get customer insights (lifetime value, repeat customers, etc.).
 */
function get_customer_insights(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT
                COUNT(DISTINCT email) as total_customers,
                COUNT(*) as total_orders,
                AVG(revenue_per_customer) as avg_customer_ltv,
                MAX(revenue_per_customer) as top_customer_ltv,
                SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers
            FROM (
                SELECT
                    email,
                    COUNT(*) as order_count,
                    SUM(total_pence) as revenue_per_customer
                FROM orders
                WHERE status = 'paid'
                GROUP BY email
            ) customer_data
        SQL);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get conversion metrics.
 */
function get_conversion_metrics(PDO $pdo, int $days = 30): array {
    try {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $cutoff = (clone $now)->modify("-{$days} days")->format('Y-m-d H:i:s');

        // Total gallery views
        $stmt = $pdo->prepare('SELECT SUM(view_count) as total_views FROM photos');
        $stmt->execute();
        $views = $stmt->fetch(PDO::FETCH_ASSOC)['total_views'] ?? 0;

        // Total orders in period
        $stmt = $pdo->prepare('SELECT COUNT(*) as total_orders FROM orders WHERE status = ? AND created_at > ?');
        $stmt->execute(['paid', $cutoff]);
        $orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

        // Session events (gallery page views)
        $sessions = 0; // Would require analytics table

        return [
            'total_views' => (int)$views,
            'total_orders' => (int)$orders,
            'conversion_rate' => ($orders > 0) ? ($orders / max($views, 1)) * 100 : 0,
        ];
    } catch (Throwable $e) {
        return ['total_views' => 0, 'total_orders' => 0, 'conversion_rate' => 0];
    }
}

/**
 * Get summary statistics.
 */
function get_analytics_summary(PDO $pdo): array {
    try {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $cutoff30 = (clone $now)->modify('-30 days')->format('Y-m-d H:i:s');

        $stats = [];

        // Revenue (all time)
        $stmt = $pdo->prepare('SELECT SUM(total_pence) as total FROM orders WHERE status = ?');
        $stmt->execute(['paid']);
        $stats['all_time_revenue'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Orders (all time)
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders WHERE status = ?');
        $stmt->execute(['paid']);
        $stats['all_time_orders'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Photos uploaded
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM photos WHERE status = ?');
        $stmt->execute(['live']);
        $stats['photos_live'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Last 30 days
        $stmt = $pdo->prepare('SELECT SUM(total_pence) as revenue, COUNT(*) as orders FROM orders WHERE status = ? AND created_at > ?');
        $stmt->execute(['paid', $cutoff30]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats['last_30_days_revenue'] = (int)($row['revenue'] ?? 0);
        $stats['last_30_days_orders'] = (int)($row['orders'] ?? 0);

        // Average order value
        $stmt = $pdo->prepare('SELECT AVG(total_pence) as avg FROM orders WHERE status = ?');
        $stmt->execute(['paid']);
        $stats['avg_order_value'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0);

        return $stats;
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get hourly distribution (when orders come in).
 */
function get_order_distribution_by_hour(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT created_at, SUM(total_pence) as revenue_pence FROM orders WHERE status = ? GROUP BY created_at ORDER BY created_at ASC');
        $stmt->execute(['paid']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byHour = [];
        foreach ($rows as $row) {
            $dt = new DateTime($row['created_at']);
            $hour = (int)$dt->format('H');
            if (!isset($byHour[$hour])) {
                $byHour[$hour] = ['hour' => $hour, 'orders' => 0, 'revenue_pence' => 0];
            }
            $byHour[$hour]['orders']++;
            $byHour[$hour]['revenue_pence'] += (int)($row['revenue_pence'] ?? 0);
        }

        ksort($byHour);
        return array_values($byHour);
    } catch (Throwable $e) {
        error_log('Analytics error: ' . $e->getMessage());
        return [];
    }
}
