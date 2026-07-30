<?php
/**
 * Admin audit log viewer: see all mutations, logins, exports, and webhooks.
 * Pagination, filtering by action/admin, and date range search.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';

function admin_audit_log_controller(PDO $pdo, array $config): void {
    require_admin();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    $filters = [
        'action' => $_GET['action'] ?? '',
        'actor' => $_GET['actor'] ?? '',
        'dateFrom' => $_GET['dateFrom'] ?? '',
        'dateTo' => $_GET['dateTo'] ?? '',
    ];

    $whereConditions = [];
    $params = [];

    if (!empty($filters['action'])) {
        $whereConditions[] = 'action LIKE ?';
        $params[] = '%' . $filters['action'] . '%';
    }

    if (!empty($filters['actor'])) {
        $whereConditions[] = 'actor = ?';
        $params[] = $filters['actor'];
    }

    if (!empty($filters['dateFrom'])) {
        $whereConditions[] = 'created_at >= ?';
        $params[] = $filters['dateFrom'] . ' 00:00:00';
    }

    if (!empty($filters['dateTo'])) {
        $whereConditions[] = 'created_at <= ?';
        $params[] = $filters['dateTo'] . ' 23:59:59';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log {$whereClause}");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $query = <<<SQL
        SELECT
            al.id, al.actor, al.action, al.entity_type, al.entity_id,
            al.meta, al.ip, al.created_at
        FROM audit_log al
        {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    SQL;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $adminStmt = $pdo->query('SELECT id, email FROM admin_users ORDER BY email');
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    $actionCategories = get_action_categories($pdo, $whereClause, $params);

    render(__DIR__ . '/../../views/admin/audit_log.php', [
        'logs' => $logs,
        'page' => $page,
        'totalPages' => $totalPages,
        'totalCount' => $totalCount,
        'perPage' => $perPage,
        'filters' => $filters,
        'admins' => $admins,
        'actionCategories' => $actionCategories,
        'siteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

/**
 * Get distinct action categories for filter dropdown.
 */
function get_action_categories(PDO $pdo, string $whereClause, array $params): array {
    if (!empty($whereClause)) {
        $query = "SELECT DISTINCT action FROM audit_log {$whereClause} ORDER BY action";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action');
    }

    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['action'];
    }

    return $categories;
}

/**
 * Get human-readable action description.
 */
function describe_action(string $action): string {
    $descriptions = [
        'login' => 'Admin logged in',
        'logout' => 'Admin logged out',
        'login_failed' => 'Login attempt failed',
        'totp_enrolled' => 'TOTP 2FA enabled',
        'event_create' => 'Event created',
        'event_update' => 'Event updated',
        'event_delete' => 'Event deleted',
        'event_publish' => 'Event published',
        'photo_upload' => 'Photo uploaded',
        'photo_tag' => 'Photo tagged',
        'photo_delete' => 'Photo deleted',
        'price_update' => 'Price updated',
        'order_create' => 'Order created (webhook)',
        'order_refund' => 'Refund processed',
        'download_link_created' => 'Download link created',
        'download_link_used' => 'Download link used',
        'export_orders' => 'Orders exported to CSV',
        'export_photos' => 'Photos exported to CSV',
        'export_customers' => 'Customers exported to CSV',
        'export_events' => 'Events exported to CSV',
        'setting_update' => 'Settings updated',
        'webhook_stripe' => 'Stripe webhook received',
        'webhook_verify_failed' => 'Webhook signature verification failed',
    ];

    return $descriptions[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
