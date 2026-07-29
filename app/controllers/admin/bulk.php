<?php
/**
 * Admin: Bulk photo operations.
 * Tag, price, delete, organize photos in bulk.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/bulk.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_bulk_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_upload($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $action = $_GET['action'] ?? 'select';
    $errors = [];
    $success = $_GET['success'] ?? false;
    $limits = get_bulk_limits($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'select';
        $photoIds = array_map('intval', explode(',', $_POST['photo_ids'] ?? ''));
        $photoIds = array_filter($photoIds);

        if (count($photoIds) > $limits['max_per_operation']) {
            $errors[] = 'Too many photos selected';
        } else {
            switch ($action) {
                case 'tag':
                    if ($limits['can_bulk_tag']) {
                        $updated = bulk_tag_photos($pdo, $photoIds, [
                            'kart' => $_POST['kart'] ?? '',
                            'driver' => $_POST['driver'] ?? '',
                            'class' => $_POST['class'] ?? '',
                        ]);
                        audit_log($pdo, 'bulk_tag', "Bulk tagged $updated photos");
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    }
                    break;

                case 'price':
                    // Per-photo pricing not supported - pricing is per-event
                    $errors[] = 'Per-photo pricing is not supported. Pricing is configured per event.';
                    break;

                case 'status':
                    $status = $_POST['status'] ?? 'hidden';
                    $updated = bulk_change_status($pdo, $photoIds, $status);
                    if ($updated > 0) {
                        audit_log($pdo, 'bulk_status', "Changed status to $status for $updated photos");
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    } else {
                        $errors[] = "Invalid status: $status. Valid values: processing, live, hidden, failed";
                    }
                    break;

                case 'delete':
                    if ($limits['can_delete']) {
                        $deleted = bulk_delete_photos($pdo, $photoIds);
                        audit_log($pdo, 'bulk_delete', "Bulk deleted $deleted photos");
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    }
                    break;
            }
        }
    }

    render(__DIR__ . '/../../views/admin/bulk.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'action' => $action,
        'limits' => $limits,
        'errors' => $errors,
        'success' => $success,
    ]);
}
