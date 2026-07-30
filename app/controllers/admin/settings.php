<?php
/**
 * Admin: Comprehensive settings management.
 * Basic and advanced modes with descriptions for every setting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/settings.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_settings_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_view_settings($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $category = $_GET['category'] ?? 'site';
    $mode = $_GET['mode'] ?? 'basic';
    $errors = [];
    $success = $_GET['success'] ?? false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'CSRF verification failed.';
            return;
        }
        $updates = [];

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8);
                $updates[$settingKey] = $value;
            }
        }

        if (!empty($updates)) {
            $updated = batch_update_settings($pdo, $updates, $category);
            if ($updated > 0) {
                audit_log($pdo, 'admin', 'update_settings', 'settings', null, ['category' => $category, 'count' => $updated], client_ip());
                header("Location: /admin/settings?category=$category&mode=$mode&success=1");
                exit;
            }
        }
    }

    $categories = get_setting_categories($pdo);
    $settings = get_all_settings($pdo, $category);

    $includeAdvanced = ($mode === 'advanced');
    $displaySettings = array_filter($settings, function($s) use ($includeAdvanced) {
        return $includeAdvanced || !$s['is_advanced'];
    });

    render(__DIR__ . '/../../views/admin/settings.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'csrfToken' => csrf_token(),
        'categories' => $categories,
        'category' => $category,
        'mode' => $mode,
        'settings' => $displaySettings,
        'allSettings' => $settings,
        'errors' => $errors,
        'success' => $success,
    ]);
}
