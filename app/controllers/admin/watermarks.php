<?php
/**
 * Admin: Watermark customization.
 * Control watermark position, opacity, style across photo sizes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_watermarks_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_manage_events($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $errors = [];
    $success = $_GET['success'] ?? false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $stmt = $pdo->prepare(<<<'SQL'
                UPDATE watermark_settings
                SET position = ?, opacity = ?, text = ?, enabled = ?, apply_to_sizes = ?
                WHERE id = 1
            SQL);

            if ($stmt->execute([
                $_POST['position'] ?? 'bottom_right',
                (float)($_POST['opacity'] ?? 0.8),
                $_POST['text'] ?? '',
                isset($_POST['enabled']) ? 1 : 0,
                $_POST['apply_to_sizes'] ?? 'sm,md,lg',
            ])) {
                audit_log($pdo, 'update_watermark', 'Updated watermark settings');
                header('Location: /admin/watermarks?success=1');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Failed to update watermark settings';
        }
    }

    try {
        $stmt = $pdo->query('SELECT id, position, opacity, scale, text, enabled, updated_at FROM watermark_settings LIMIT 1');
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $settings = null;
    }

    render(__DIR__ . '/../../views/admin/watermarks.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'settings' => $settings,
        'errors' => $errors,
        'success' => $success,
    ]);
}
