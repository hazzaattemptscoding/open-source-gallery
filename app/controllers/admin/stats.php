<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/stats.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/auth.php';

/**
 * GET /admin/stats — sales dashboard with top photos and event performance.
 */
function admin_stats_controller(PDO $pdo, array $config): void {
    require_admin();
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    // Fetch all events with stats
    $stmt = $pdo->prepare('
        SELECT id, title, slug, event_date, is_published
        FROM events
        ORDER BY event_date DESC
    ');
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eventStats = [];
    foreach ($events as $event) {
        $eventId = (int)$event['id'];
        $revenue = get_event_revenue($pdo, $eventId);
        $orderCount = get_event_order_count($pdo, $eventId);
        $topPhotos = get_top_photos_by_sales($pdo, $eventId, 5);

        $eventStats[] = [
            'event' => $event,
            'revenue' => $revenue,
            'orderCount' => $orderCount,
            'topPhotos' => $topPhotos,
        ];
    }

    // Overall site stats
    $stmt = $pdo->prepare('
        SELECT
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(o.total_pence), 0) as total_revenue
        FROM orders o
        WHERE o.status = ?
    ');
    $stmt->execute(['paid']);
    $siteStats = $stmt->fetch(PDO::FETCH_ASSOC);

    render(__DIR__ . '/../../views/admin/stats.php', [
        'pageTitle' => 'Sales Dashboard',
        'currentPage' => 'stats',
        'siteName' => $siteName,
        'currencyCode' => $currencyCode,
        'eventStats' => $eventStats,
        'siteStats' => $siteStats,
    ]);
}
