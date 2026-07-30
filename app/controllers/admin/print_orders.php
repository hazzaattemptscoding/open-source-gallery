<?php
/**
 * Admin: Print order management.
 * View and manage orders sent to print fulfillment providers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/print.php';

function admin_print_orders_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_manage_print_orders($pdo)) {
        http_response_code(403);
        echo '403 Forbidden: Print order management access required';
        exit;
    }

    // Check if print is enabled
    if (!is_print_enabled($pdo)) {
        render_disabled_page();
        return;
    }

    $action = $_GET['action'] ?? 'list';
    $errors = [];
    $success = $_GET['success'] ?? false;

    // Get all print orders
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT po.*, pp.name as provider_name, o.email,
                   COUNT(pi.id) as item_count
            FROM print_orders po
            JOIN print_providers pp ON pp.id = po.provider_id
            JOIN orders o ON o.id = po.order_id
            LEFT JOIN print_items pi ON pi.print_order_id = po.id
            GROUP BY po.id
            ORDER BY po.created_at DESC
        SQL);
        $printOrders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $printOrders = [];
    }

    render(__DIR__ . '/../../views/admin/print_orders.php', [
        'pageTitle' => 'Print Orders',
        'currentPage' => 'print_orders',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'printOrders' => $printOrders,
        'action' => $action,
        'errors' => $errors,
        'success' => $success,
    ]);
}

function render_disabled_page(): void {
    echo <<<'HTML'
    <!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <title>Print Orders: Admin</title>
    <link rel="stylesheet" href="/assets/css/podium-ink.css">
    </head>
    <body>
    <header class="site-header">
      <a href="/admin" class="site-title">Print Orders</a>
    </header>
    <main style="max-width: 800px; margin: 3rem auto; padding: 0 1rem; text-align: center;">
      <h1>Print Fulfillment Not Enabled</h1>
      <p>Print fulfillment is not currently enabled. Configure it in Admin Settings to get started.</p>
      <p><a href="/admin/settings">Go to Settings</a></p>
    </main>
    </body>
    </html>
    HTML;
    exit;
}
