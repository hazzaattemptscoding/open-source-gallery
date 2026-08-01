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
            // Secrets (Stripe secret keys, the SMTP password) route to
            // config.php via config_set() instead of settings_registry: a
            // live key sitting in the database sits in every mysqldump the
            // backup job (app/lib/backup.php) archives to storage/backups/.
            // A blank submission is treated as "leave unchanged" rather than
            // "clear it" -- the field is rendered blank on every load
            // regardless of whether a value is set (see below), so there is
            // no other way to distinguish "admin left this alone" from
            // "admin wants to erase it".
            $secretUpdates = [];
            $normalUpdates = [];
            foreach ($updates as $settingKey => $value) {
                $path = "{$category}.{$settingKey}";
                if (in_array($path, CONFIG_SECRET_PATHS, true)) {
                    if ($value !== '') {
                        $secretUpdates[$settingKey] = $value;
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

            $updated = 0;
            if (empty($errors)) {
                foreach ($normalUpdates as $settingKey => $value) {
                    if (set_setting($pdo, $category, $settingKey, $value)) {
                        $updated++;
                    } else {
                        $errors[] = $settingKey . ': could not be saved.';
                    }
                }
                foreach ($secretUpdates as $settingKey => $value) {
                    $writeErrors = config_set($config, $pdo, "{$category}.{$settingKey}", $value);
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
 * for secrets (Stripe/SMTP), replace the display value with nothing at all.
 *
 * The table stores display_label/help_text; the view asks for label/description.
 * Mapping here rather than in the view keeps the view free of schema knowledge,
 * and keeps the original keys available for anything that wants them.
 *
 * A secret's row['value'] in settings_registry is stale/irrelevant after the
 * config_store.php change (secrets live in config.php, not this table), so it
 * is overwritten here regardless of what the row actually contains. Blank on
 * every load, never the real value: type="password" still round-trips into
 * the DOM in view-source, and a config.php secret has no business appearing
 * in a settings_registry row anyway.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalise_setting_row(array $row, array $config, PDO $pdo): array
{
    $row['label'] = $row['display_label'] ?? $row['key_name'];
    $row['description'] = $row['help_text'] ?? '';

    $path = "{$row['category']}.{$row['key_name']}";
    $row['is_secret'] = in_array($path, CONFIG_SECRET_PATHS, true);

    if ($row['is_secret']) {
        $row['is_set'] = config_get($config, $pdo, $path, '') !== '';
        $row['value'] = '';
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
