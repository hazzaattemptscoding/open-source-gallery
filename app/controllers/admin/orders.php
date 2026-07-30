<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/auth.php';

/**
 * GET /admin/orders — browse orders, refund status, customer emails.
 */
function admin_orders_controller(PDO $pdo, array $config): void {
    require_admin();
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    // Fetch orders with item count
    $stmt = $pdo->prepare('
        SELECT
            o.id,
            o.public_token,
            o.email,
            o.status,
            o.total_pence,
            o.paid_at,
            o.refunded_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$perPage, $offset]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders');
    $stmt->execute();
    $totalOrders = (int)$stmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);

    render(__DIR__ . '/../../views/admin/orders.php', [
        'pageTitle' => 'Order History',
        'currentPage' => 'orders',
        'siteName' => $siteName,
        'currencyCode' => $currencyCode,
        'orders' => $orders,
        'paginationPage' => $page,
        'totalPages' => $totalPages,
        'totalOrders' => $totalOrders,
    ]);
}
