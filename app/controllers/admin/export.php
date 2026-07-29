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
            o.stripe_session_id, o.download_token, o.created_at, o.updated_at
        FROM orders o
        ORDER BY o.created_at DESC
    SQL);
    $stmt->execute();

    $currencyCode = $config['currency'] ?? 'GBP';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['public_token'],
            $row['email'],
            $row['total_pence'],
            format_pence((int)$row['total_pence'], $currencyCode),
            $row['status'],
            $row['stripe_session_id'] ?? '',
            $row['download_token'] ? 'Generated' : 'Not yet',
            $row['created_at'],
            $row['updated_at'],
        ]);
    }

    fclose($output);

    audit_log($pdo, 'export_orders', current_admin_id(), 'Exported orders to CSV');
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
        'Caption',
        'Filename',
        'Price (pence)',
        'Watermarked',
        'Tags',
        'Upload Time',
        'Created',
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            p.id, p.public_token, e.title as event_title, p.caption, p.original_filename,
            p.price_pence, p.watermark_applied, p.created_at
        FROM photos p
        LEFT JOIN events e ON e.id = p.event_id
        ORDER BY p.created_at DESC
    SQL);
    $stmt->execute();

    $currencyCode = $config['currency'] ?? 'GBP';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tagsStmt = $pdo->prepare('SELECT GROUP_CONCAT(tag SEPARATOR ", ") as tags FROM photo_tags WHERE photo_id = ?');
        $tagsStmt->execute([$row['id']]);
        $tagsResult = $tagsStmt->fetch(PDO::FETCH_ASSOC);
        $tags = $tagsResult['tags'] ?? '';

        fputcsv($output, [
            $row['id'],
            $row['public_token'],
            $row['event_title'] ?? '(Not assigned)',
            $row['caption'],
            $row['original_filename'],
            $row['price_pence'],
            $row['watermark_applied'] ? 'Yes' : 'No',
            $tags,
            'In gallery',
            $row['created_at'],
        ]);
    }

    fclose($output);

    audit_log($pdo, 'export_photos', current_admin_id(), 'Exported photos to CSV');
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
        WHERE o.status = 'completed'
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

    audit_log($pdo, 'export_customers', current_admin_id(), 'Exported customer data to CSV');
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

    audit_log($pdo, 'export_events', current_admin_id(), 'Exported events to CSV');
}
