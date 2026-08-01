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
require_once __DIR__ . '/../../lib/config_store.php';

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
            // config.php-native fields (Stripe, SMTP, currency) route through
            // config_set() to config.php instead of settings_registry -- see
            // CONFIG_FILE_PATHS in config_store.php for why these specifically,
            // not "everything sensitive": a value settings_registry has no real
            // claim on is a stale duplicate waiting to happen the moment
            // someone edits config.php by hand, which is the exact failure this
            // whole file exists to close off, not just a secrecy concern.
            //
            // Within that set, secrets (Stripe secret key, webhook secret, SMTP
            // password) get additional treatment: a blank submission means
            // "leave unchanged" rather than "clear it", since the field always
            // renders blank regardless of whether a value is set (see
            // normalise_setting_row() below) and there is no other way to tell
            // those two intents apart.
            $fileUpdates = [];
            $normalUpdates = [];
            foreach ($updates as $settingKey => $value) {
                $path = settings_field_to_config_path($category, $settingKey);
                if (in_array($path, CONFIG_FILE_PATHS, true)) {
                    if (!config_path_is_secret($path) || $value !== '') {
                        $fileUpdates[$settingKey] = $value;
                    }
                } else {
                    $normalUpdates[$settingKey] = $value;
                }
            }

            // Validate everything before writing anything, so a page of edits
            // either lands whole or reports what stopped it. batch_update_settings
            // used to report only a count, which made a partial save
            // indistinguishable from a clean one and a total failure
            // indistinguishable from a no-op.
            foreach ($normalUpdates as $settingKey => $value) {
                foreach (validate_setting($pdo, $category, $settingKey, $value) as $message) {
                    $errors[] = $settingKey . ': ' . $message;
                }
                foreach (settings_guardrail_check($pdo, $category, $settingKey, $value) as $message) {
                    $errors[] = $settingKey . ': ' . $message;
                }
            }
            // config_set() writes CONFIG_FILE_PATHS straight to config.php with
            // no format check of its own -- validate_setting() only knows
            // settings_registry rows, and config.php is a different destination
            // entirely. A malformed value here breaks Stripe or SMTP silently at
            // the next checkout or email send rather than at save time.
            foreach ($fileUpdates as $settingKey => $value) {
                if ($value === '') {
                    continue; // secrets: blank means "leave unchanged", nothing to validate
                }
                foreach (config_field_validate($category, $settingKey, $value) as $message) {
                    $errors[] = $settingKey . ': ' . $message;
                }
            }

            $updated = 0;
            if (empty($errors)) {
                foreach ($normalUpdates as $settingKey => $value) {
                    if (set_setting($pdo, $category, $settingKey, $value)) {
                        $updated++;
                    } else {
                        $errors[] = $settingKey . ': could not be saved.';
                    }
                }
                foreach ($fileUpdates as $settingKey => $value) {
                    $path = settings_field_to_config_path($category, $settingKey);
                    $writeErrors = config_set($config, $pdo, $path, $value);
                    if ($writeErrors) {
                        array_push($errors, ...$writeErrors);
                    } else {
                        $updated++;
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
    $settings = array_map(
        fn(array $row) => normalise_setting_row($row, $config, $pdo),
        get_all_settings($pdo, $category)
    );

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
 * Bridge the settings_registry column names to the names the view reads, and
 * for config.php-native fields, read the real value from config.php instead
 * of the (stale, unused) settings_registry column.
 *
 * The table stores display_label/help_text; the view asks for label/description.
 * Mapping here rather than in the view keeps the view free of schema knowledge,
 * and keeps the original keys available for anything that wants them.
 *
 * A CONFIG_FILE_PATHS row's settings_registry `value` column is never written
 * to after this change (config_set() routes it to config.php instead), so
 * displaying it would show whatever was left over from before this file
 * existed rather than the value actually in effect. Secrets go further:
 * blanked entirely rather than shown, with only an is_set hint, since
 * type="password" still round-trips its value attribute into view-source.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalise_setting_row(array $row, array $config, PDO $pdo): array
{
    $row['label'] = $row['display_label'] ?? $row['key_name'];
    $row['description'] = $row['help_text'] ?? '';

    $path = settings_field_to_config_path($row['category'], $row['key_name']);
    $row['is_secret'] = config_path_is_secret($path);

    if (in_array($path, CONFIG_FILE_PATHS, true)) {
        $real = config_get($config, $pdo, $path, '');
        if ($row['is_secret']) {
            $row['is_set'] = $real !== '';
            $row['value'] = '';
        } else {
            $row['value'] = (string) $real;
        }
    }

    return $row;
}

/**
 * Refuse a save that would break the admin's ability to manage the site
 * through the web, or promise a limit the host cannot actually honour.
 * Returns human-readable reasons; empty means the save may proceed.
 *
 * Each of these mirrors a way "wire every setting to something real" turns
 * into a way to lock yourself out or silently overpromise, found while
 * doing exactly that wiring:
 *
 * - security.ip_whitelist with a typo, or the admin's own network dropped
 *   from the list, is unrecoverable except by editing the database
 *   directly -- there is no password-reset equivalent for "the app no
 *   longer believes your IP is allowed to load the app".
 * - security.require_totp enabled before any admin has enrolled a device
 *   locks every admin out at the next login prompt.
 * - photos.max_upload_size_mb set above what PHP will actually accept
 *   (upload_max_filesize / post_max_size) fails uploads with a generic PHP
 *   error that gives no indication the configured limit was the lie.
 * - security.session_timeout_minutes at zero would expire a session
 *   instantly, which is indistinguishable from being permanently logged
 *   out.
 *
 * @return list<string>
 */
function settings_guardrail_check(PDO $pdo, string $category, string $key, mixed $value): array
{
    if ($category === 'security' && $key === 'ip_whitelist' && trim((string)$value) !== '') {
        $entries = array_map('trim', explode(',', (string)$value));
        $currentIp = client_ip();
        if (!in_array($currentIp, $entries, true)) {
            return ["Your current IP address ({$currentIp}) is not in this list. Saving would lock you out immediately, so this was refused. Add your own IP first."];
        }
    }

    if ($category === 'security' && $key === 'require_totp' && $value) {
        $enrolled = (int)$pdo->query('SELECT COUNT(*) FROM admin_users WHERE totp_enabled = 1')->fetchColumn();
        if ($enrolled === 0) {
            return ['No admin account has two-factor authentication enrolled yet. Enable TOTP on at least one account first (Admin Users), or this would lock every admin out at the next login.'];
        }
    }

    if ($category === 'photos' && $key === 'max_upload_size_mb') {
        $phpLimitMb = (int) floor(min(
            parse_php_size_to_bytes(ini_get('upload_max_filesize') ?: '2M'),
            parse_php_size_to_bytes(ini_get('post_max_size') ?: '8M')
        ) / (1024 * 1024));
        if ((int)$value > $phpLimitMb) {
            return ["This host's PHP configuration only accepts uploads up to {$phpLimitMb}MB (upload_max_filesize/post_max_size). Setting a higher limit here would not raise it -- uploads over {$phpLimitMb}MB would still fail. Lower this value or ask your host to raise the PHP limit."];
        }
    }

    if ($category === 'security' && $key === 'session_timeout_minutes' && (int)$value < 5) {
        return ['Session timeout must be at least 5 minutes. Anything shorter is indistinguishable from being logged out immediately.'];
    }

    return [];
}

/**
 * Format checks for CONFIG_FILE_PATHS fields, mirroring what the setup
 * wizard already checks inline (app/controllers/admin/setup_wizard.php's
 * handle_stripe_keys()/handle_business_details()) so the same mistake is
 * caught whether it's made during first-run setup or a later Settings edit.
 *
 * @return list<string>
 */
function config_field_validate(string $category, string $key, string $value): array
{
    if ($category === 'stripe' && $key === 'mode' && !in_array($value, ['test', 'live'], true)) {
        return ["Must be 'test' or 'live'."];
    }
    if ($category === 'stripe' && $key === 'publishable_key' && !str_starts_with($value, 'pk_')) {
        return ['Stripe publishable keys start with pk_.'];
    }
    if ($category === 'stripe' && $key === 'secret_key' && !str_starts_with($value, 'sk_')) {
        return ['Stripe secret keys start with sk_.'];
    }
    if ($category === 'stripe' && $key === 'webhook_secret' && !str_starts_with($value, 'whsec_')) {
        return ['Stripe webhook signing secrets start with whsec_.'];
    }
    if ($category === 'currency' && $key === 'code' && !preg_match('/^[A-Z]{3}$/', $value)) {
        return ['Currency must be a 3-letter ISO code (e.g., GBP, USD).'];
    }

    return [];
}

/** '8M' / '512K' / '2G' -> bytes. PHP ini shorthand, per php.ini's own docs. */
function parse_php_size_to_bytes(string $size): int
{
    $size = trim($size);
    $unit = strtolower(substr($size, -1));
    $number = (int) $size;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int) $size,
    };
}
