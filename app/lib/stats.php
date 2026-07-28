<?php
declare(strict_types=1);

/**
 * Photo and event sales reporting, built from the orders/order_items tables.
 * Daily stats are aggregated into stats_daily for historical trending.
 */

/**
 * Get sales count for a photo (all-time).
 */
function get_photo_sales_count(PDO $pdo, int $photoId): int {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM order_items
        WHERE photo_id = ? AND item_type = ?
    ');
    $stmt->execute([$photoId, 'photo']);
    return (int)($stmt->fetchColumn() ?? 0);
}

/**
 * Get total revenue from photo sales (in pence, all-time).
 */
function get_photo_revenue(PDO $pdo, int $photoId): int {
    $stmt = $pdo->prepare('
        SELECT SUM(line_total_pence) FROM order_items
        WHERE photo_id = ? AND item_type = ?
    ');
    $stmt->execute([$photoId, 'photo']);
    return (int)($stmt->fetchColumn() ?? 0);
}

/**
 * Get top selling photos for the event (by sales count).
 */
function get_top_photos_by_sales(PDO $pdo, int $eventId, int $limit = 10): array {
    $stmt = $pdo->prepare('
        SELECT
            p.id,
            p.public_token,
            COUNT(oi.id) as sales,
            COALESCE(SUM(oi.line_total_pence), 0) as revenue
        FROM photos p
        LEFT JOIN order_items oi ON oi.photo_id = p.id AND oi.item_type = ?
        WHERE p.event_id = ? AND p.status = ?
        GROUP BY p.id
        ORDER BY sales DESC, revenue DESC
        LIMIT ?
    ');
    $stmt->execute(['photo', $eventId, 'live', $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get total event revenue (all-time, all item types).
 */
function get_event_revenue(PDO $pdo, int $eventId): int {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(total_pence), 0) FROM orders o
        WHERE o.status = ? AND EXISTS (
            SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.event_id = ?
        )
    ');
    $stmt->execute(['paid', $eventId]);
    return (int)($stmt->fetchColumn() ?? 0);
}

/**
 * Get total orders for an event.
 */
function get_event_order_count(PDO $pdo, int $eventId): int {
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT o.id) FROM orders o
        WHERE o.status = ? AND EXISTS (
            SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.event_id = ?
        )
    ');
    $stmt->execute(['paid', $eventId]);
    return (int)($stmt->fetchColumn() ?? 0);
}
