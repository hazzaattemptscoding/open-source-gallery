<?php
/**
 * Admin settings page: configure Stripe keys, email, site settings at runtime.
 * Stores in database 'settings' table and makes available to controllers via
 * a runtime settings function, so admins never touch config.php after install.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_settings_controller(PDO $pdo, array $config): void {
    require_admin();

    $csrfToken = csrf_token();
    $errors = [];
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $errors[] = 'CSRF verification failed.';
        } else {
            $settingsToUpdate = [
                'stripe_publishable_key' => trim($_POST['stripe_publishable_key'] ?? ''),
                'stripe_secret_key' => trim($_POST['stripe_secret_key'] ?? ''),
                'stripe_webhook_secret' => trim($_POST['stripe_webhook_secret'] ?? ''),
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => trim($_POST['smtp_port'] ?? '587'),
                'smtp_user' => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass' => trim($_POST['smtp_pass'] ?? ''),
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
                'site_name' => trim($_POST['site_name'] ?? ''),
                'support_email' => trim($_POST['support_email'] ?? ''),
                'currency' => strtoupper(trim($_POST['currency'] ?? 'GBP')),
            ];

            $allValid = true;
            foreach ($settingsToUpdate as $key => $value) {
                if ($key === 'support_email' || $key === 'smtp_from_email') {
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' is not a valid email address.';
                        $allValid = false;
                    }
                }
                if ($key === 'smtp_port' && !empty($value)) {
                    if (!is_numeric($value) || (int)$value < 1 || (int)$value > 65535) {
                        $errors[] = 'SMTP port must be between 1 and 65535.';
                        $allValid = false;
                    }
                }
                if ($key === 'currency' && strlen($value) !== 3) {
                    $errors[] = 'Currency code must be 3 characters (e.g., GBP, USD, EUR).';
                    $allValid = false;
                }
            }

            if ($allValid) {
                foreach ($settingsToUpdate as $key => $value) {
                    update_setting($pdo, $key, $value);
                }

                audit_log($pdo, 'setting_update', current_admin_id(), 'Updated site settings');
                $success = 'Settings saved successfully.';
            }
        }
    }

    $settings = load_all_settings($pdo);

    render(__DIR__ . '/../../views/admin/settings.php', [
        'csrfToken' => $csrfToken,
        'errors' => $errors,
        'success' => $success,
        'settings' => $settings,
        'defaultCurrency' => $config['currency'] ?? 'GBP',
        'defaultSiteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

/**
 * Load all settings from database.
 */
function load_all_settings(PDO $pdo): array {
    $stmt = $pdo->query('SELECT skey, svalue FROM settings WHERE skey LIKE ?', [
        'stripe_%', 'smtp_%', 'site_%', 'support_%', 'currency'
    ]);

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT skey, svalue FROM settings
        WHERE skey IN (
            'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name',
            'site_name', 'support_email', 'currency'
        )
    SQL);
    $stmt->execute();

    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['skey']] = $row['svalue'];
    }

    return $settings;
}

/**
 * Update or insert a setting.
 */
function update_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('
        INSERT INTO settings (skey, svalue, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE svalue = ?, updated_at = NOW()
    ');
    $stmt->execute([$key, $value, $value]);
}

/**
 * Get a runtime setting (from database, not config file).
 */
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();

    $cache[$key] = $result ?? $default;
    return $cache[$key];
}
