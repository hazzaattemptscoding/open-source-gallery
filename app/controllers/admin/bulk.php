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
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/tagging.php';

function admin_bulk_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_upload($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $csrfToken = csrf_token();
    $action = $_GET['action'] ?? 'select';
    $errors = [];
    $success = $_GET['success'] ?? false;
    $limits = get_bulk_limits($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'CSRF verification failed.';
            return;
        }
        $action = $_POST['action'] ?? 'select';
        $photoIds = array_map('intval', explode(',', $_POST['photo_ids'] ?? ''));
        $photoIds = array_filter($photoIds);

        if (count($photoIds) > $limits['max_per_operation']) {
            $errors[] = 'Too many photos selected';
        } else {
            switch ($action) {
                case 'tag':
                    if ($limits['can_bulk_tag']) {
                        // Old motorsport-specific tags (kart, driver, class)
                        $oldTags = [
                            'kart' => $_POST['kart'] ?? '',
                            'driver' => $_POST['driver'] ?? '',
                            'class' => $_POST['class'] ?? '',
                        ];
                        if (array_filter($oldTags)) {
                            bulk_tag_photos($pdo, $photoIds, $oldTags);
                        }

                        // New tag-based system (client, location, style, featured)
                        $newTags = [
                            'client' => $_POST['client'] ?? '',
                            'location' => $_POST['location'] ?? '',
                            'style' => $_POST['style'] ?? '',
                            'featured' => isset($_POST['featured']) && $_POST['featured'] === '1',
                        ];
                        if (array_filter($newTags, fn($v) => $v !== false)) {
                            bulk_tag_photos_new($pdo, $photoIds, $newTags);
                        }

                        $tagData = array_merge($oldTags, array_filter($newTags, fn($v) => $v !== false));
                        audit_log($pdo, 'admin', 'bulk_tag', 'photos', null, ['count' => count($photoIds), 'tags' => $tagData], client_ip());
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    }
                    break;

                case 'price':
                    $pricePence = (int)($_POST['price_pence'] ?? -1);
                    if ($pricePence < 0) {
                        $errors[] = 'Price must be a non-negative number of pence';
                        break;
                    }
                    $updated = bulk_update_prices($pdo, $photoIds, $pricePence);
                    audit_log($pdo, 'admin', 'bulk_price', 'photos', null, ['price_pence' => $pricePence, 'count' => $updated], client_ip());
                    header('Location: /admin/bulk?action=select&success=1');
                    exit;

                case 'status':
                    $status = $_POST['status'] ?? 'hidden';
                    $updated = bulk_change_status($pdo, $photoIds, $status);
                    if ($updated > 0) {
                        audit_log($pdo, 'admin', 'bulk_status', 'photos', null, ['status' => $status, 'count' => $updated], client_ip());
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    } else {
                        $errors[] = "Invalid status: $status. Valid values: processing, live, hidden, failed";
                    }
                    break;

                case 'delete':
                    if ($limits['can_delete']) {
                        $deleted = bulk_delete_photos($pdo, $photoIds);
                        audit_log($pdo, 'admin', 'bulk_delete', 'photos', null, ['count' => $deleted], client_ip());
                        header('Location: /admin/bulk?action=select&success=1');
                        exit;
                    }
                    break;
            }
        }
    }

    render(__DIR__ . '/../../views/admin/bulk.php', [
        'pageTitle' => 'Bulk Operations',
        'currentPage' => 'bulk',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'csrfToken' => csrf_token(),
        'action' => $action,
        'limits' => $limits,
        'errors' => $errors,
        'success' => $success,
    ]);
}
