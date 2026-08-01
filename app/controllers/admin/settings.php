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
            // Validate everything before writing anything, so a page of edits
            // either lands whole or reports what stopped it. batch_update_settings
            // reported only a count, which made a partial save indistinguishable
            // from a clean one and a total failure indistinguishable from a no-op.
            $updated = 0;
            foreach ($updates as $settingKey => $value) {
                $fieldErrors = validate_setting($pdo, $category, $settingKey, $value);
                foreach ($fieldErrors as $message) {
                    $errors[] = $settingKey . ': ' . $message;
                }
            }

            if (empty($errors)) {
                foreach ($updates as $settingKey => $value) {
                    if (set_setting($pdo, $category, $settingKey, $value)) {
                        $updated++;
                    } else {
                        $errors[] = $settingKey . ': could not be saved.';
                    }
                }
            }

            if (empty($errors) && $updated > 0) {
                audit_log($pdo, 'admin', 'update_settings', 'settings', null, ['category' => $category, 'count' => $updated], client_ip());
                header("Location: /admin/settings/$category?mode=$mode&success=1");
                exit;
            }
        }
    }

    $categories = get_setting_categories($pdo);
    $settings = array_map('normalise_setting_row', get_all_settings($pdo, $category));

    $includeAdvanced = ($mode === 'advanced');
    $displaySettings = array_filter($settings, function($s) use ($includeAdvanced) {
        return $includeAdvanced || !$s['is_advanced'];
    });

    render(__DIR__ . '/../../views/admin/settings.php', [
        'pageTitle' => 'Settings',
        'currentPage' => 'settings',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'csrfToken' => csrf_token(),
        'categories' => $categories,
        'category' => $category,
        'mode' => $mode,
        // The view reads $displaySettings by that exact name. It previously
        // arrived as 'settings', so extract() never defined $displaySettings
        // and every category rendered the "no settings" empty state.
        'displaySettings' => $displaySettings,
        'allSettings' => $settings,
        'errors' => $errors,
        'success' => $success,
    ]);
}

/**
 * Bridge the settings_registry column names to the names the view reads.
 *
 * The table stores display_label/help_text; the view asks for label/description.
 * Mapping here rather than in the view keeps the view free of schema knowledge,
 * and keeps the original keys available for anything that wants them.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalise_setting_row(array $row): array
{
    $row['label'] = $row['display_label'] ?? $row['key_name'];
    $row['description'] = $row['help_text'] ?? '';

    return $row;
}
