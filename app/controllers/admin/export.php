<?php
/**
 * Admin data export: orders, photos, and customer data in CSV format.
 * Used for backups, GDPR compliance, analytics, and migration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/currency.php';

function admin_export_page_controller(PDO $pdo, array $config): void {
    require_admin();

    render(__DIR__ . '/../../views/admin/export.php', [
        'pageTitle' => 'Export Data',
        'currentPage' => 'export',
        'siteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

function admin_export_orders_controller(PDO $pdo, array $config): void {
    require_admin();

    $filename = 'orders-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    if (!$output) {
        http_response_code(500);
        exit;
    }

    fputcsv($output, [
        'Order ID',
        'Public Token',
        'Customer Email',
        'Total (pence)',
        'Total (formatted)',
        'Status',
        'Stripe Session ID',
        'Download Token',
        'Created',
        'Updated',
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            o.id, o.public_token, o.email, o.total_pence, o.status,
            o.stripe_checkout_id, o.paid_at, o.created_at, o.updated_at
        FROM orders o
        ORDER BY o.created_at DESC
    SQL);
    $stmt->execute();

    $currencyCode = $config['currency'] ?? 'GBP';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Check if this order has any download links generated
        $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM download_links WHERE order_id = ?');
        $stmt2->execute([$row['id']]);
        $hasDownloadLinks = (int)$stmt2->fetchColumn() > 0;

        fputcsv($output, [
            $row['id'],
            $row['public_token'],
            $row['email'],
            $row['total_pence'],
            format_pence((int)$row['total_pence'], $currencyCode),
            $row['status'],
            $row['stripe_checkout_id'] ?? '',
            $hasDownloadLinks ? 'Generated' : 'Not yet',
            $row['created_at'],
            $row['updated_at'],
        ]);
    }

    fclose($output);

    audit_log($pdo, 'admin', 'export_orders', 'orders', null, null, client_ip());
}

function admin_export_photos_controller(PDO $pdo, array $config): void {
    require_admin();

    $filename = 'photos-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    if (!$output) {
        http_response_code(500);
        exit;
    }

    fputcsv($output, [
        'Photo ID',
        'Public Token',
        'Event',
        'Session',
        'Filename',
        'Status',
        'Media Type',
        'Dimensions',
        'View Count',
        'Tags',
        'Created',
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            p.id, p.public_token, e.title as event_title, s.name as session_name,
            p.original_filename, p.status, p.media_type, p.width, p.height,
            p.view_count, p.created_at
        FROM photos p
        LEFT JOIN events e ON e.id = p.event_id
        LEFT JOIN sessions s ON s.id = p.session_id
        ORDER BY p.created_at DESC
    SQL);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get tags for this photo
        $tagsStmt = $pdo->prepare('
            SELECT GROUP_CONCAT(kart_number || " (" || driver_name || " - " || class || ")", ", ") as tags
            FROM photo_tags WHERE photo_id = ?
        ');
        $tagsStmt->execute([$row['id']]);
        $tagsResult = $tagsStmt->fetch(PDO::FETCH_ASSOC);
        $tags = $tagsResult['tags'] ?? '';

        $dimensions = ($row['width'] && $row['height']) ? $row['width'] . 'x' . $row['height'] : 'Unknown';

        fputcsv($output, [
            $row['id'],
            $row['public_token'],
            $row['event_title'] ?? '(Not assigned)',
            $row['session_name'] ?? '(Not assigned)',
            $row['original_filename'],
            $row['status'],
            $row['media_type'],
            $dimensions,
            $row['view_count'],
            $tags,
            $row['created_at'],
        ]);
    }

    fclose($output);

    audit_log($pdo, 'admin', 'export_photos', 'photos', null, null, client_ip());
}

function admin_export_customers_controller(PDO $pdo, array $config): void {
    require_admin();

    $filename = 'customers-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    if (!$output) {
        http_response_code(500);
        exit;
    }

    fputcsv($output, [
        'Email',
        'Orders',
        'Total Spent (pence)',
        'Total Spent (formatted)',
        'First Purchase',
        'Last Purchase',
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            o.email,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_pence) as total_pence,
            MIN(o.created_at) as first_order,
            MAX(o.created_at) as last_order
        FROM orders o
        WHERE o.status = 'paid'
        GROUP BY o.email
        ORDER BY total_pence DESC
    SQL);
    $stmt->execute();

    $currencyCode = $config['currency'] ?? 'GBP';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['email'],
            $row['order_count'],
            $row['total_pence'],
            format_pence((int)$row['total_pence'], $currencyCode),
            $row['first_order'],
            $row['last_order'],
        ]);
    }

    fclose($output);

    audit_log($pdo, 'admin', 'export_customers', 'orders', null, null, client_ip());
}

function admin_export_events_controller(PDO $pdo, array $config): void {
    require_admin();

    $filename = 'events-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    if (!$output) {
        http_response_code(500);
        exit;
    }

    fputcsv($output, [
        'Event',
        'Slug',
        'Published',
        'Date',
        'Venue',
        'Photos',
        'Orders',
        'Revenue (pence)',
        'Revenue (formatted)',
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            e.id, e.title, e.slug, e.published, e.event_date, e.venue_name,
            COUNT(DISTINCT p.id) as photo_count,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.line_total_pence) as revenue
        FROM events e
        LEFT JOIN photos p ON p.event_id = e.id
        LEFT JOIN order_items oi ON oi.photo_id = p.id
        GROUP BY e.id
        ORDER BY e.event_date DESC
    SQL);
    $stmt->execute();

    $currencyCode = $config['currency'] ?? 'GBP';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['title'],
            $row['slug'],
            $row['published'] ? 'Yes' : 'No',
            $row['event_date'],
            $row['venue_name'],
            $row['photo_count'],
            $row['order_count'],
            $row['revenue'] ?? 0,
            format_pence((int)($row['revenue'] ?? 0), $currencyCode),
        ]);
    }

    fclose($output);

    audit_log($pdo, 'admin', 'export_events', 'events', null, null, client_ip());
}
