<?php
/**
 * Single accessor for application configuration.
 *
 * Before this file, the same setting could live in up to four places with no
 * defined precedence: config/config.php, the `settings` key/value table,
 * `settings_registry`, and storage/customize/*.json. That is what let the
 * watermark toggle exist twice with two different readers and no connection
 * between them (settings_registry vs the `settings` table in
 * app/lib/derivatives.php), and it is what let the setup wizard's
 * update_config_setting() write to a `setup_config_*` key that nothing ever
 * read back.
 *
 * Resolution order, always: config.php overrides the database, the database
 * overrides the built-in default. config.php is the self-hoster's own file —
 * whatever they put there must win, including over a settings row the app
 * itself wrote — and it is also the only file this app should ever regenerate
 * on disk, so keeping the override there rather than adding a fifth store
 * gives self-hosters one place to hand-edit if the UI is ever unavailable.
 *
 * storage/customize/*.json is deliberately untouched by this file: it is
 * presentation (colours, logo, copy), not configuration, and app/lib/customize.php
 * already owns it correctly.
 */

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/**
 * Dot-path keys that live in config.php rather than settings_registry,
 * whether or not they're secret. This must match config.php's *real* shape
 * exactly, not the shape a settings_registry row happens to describe --
 * settings_registry's original stripe category offered mode/
 * test_publishable_key/test_secret_key/live_publishable_key/live_secret_key,
 * a test-vs-live pair the runtime does not have: app/lib/stripe.php,
 * app/controllers/public/checkout.php and the webhook controller read a
 * single config['stripe']['secret_key']/['publishable_key']/
 * ['webhook_secret'], gated by a single config['stripe']['mode']
 * ('test'|'live', checked in app/lib/cli/commands.php). A save through the
 * old field names moved a secret safely out of the database but into a
 * config.php key nothing consumed -- the same "control does nothing" shape
 * as the watermark bug, just not yet caught. Migration 013 renames the
 * settings_registry rows to match.
 */
const CONFIG_FILE_PATHS = [
    'stripe.mode',
    'stripe.publishable_key',
    'stripe.secret_key',
    'stripe.webhook_secret',
    'smtp.pass',
    'currency',
];

/**
 * Subset of CONFIG_FILE_PATHS masked in the Settings UI and never
 * round-tripped into HTML. A live Stripe secret key or an SMTP password in
 * settings_registry would sit in cleartext in every mysqldump the backup
 * job (app/lib/backup.php) archives to storage/backups/; config.php never
 * enters that archive.
 */
const CONFIG_SECRET_PATHS = [
    'stripe.secret_key',
    'stripe.webhook_secret',
    'smtp.pass',
];

/**
 * Read a config value by dot path, e.g. config_get('stripe.mode', 'test').
 *
 * A CONFIG_FILE_PATHS key resolves from config.php only -- no database
 * fallback -- since a settings_registry row for one of these paths is by
 * definition a stale duplicate, not an alternate source of truth (this is
 * the exact bug CONFIG_FILE_PATHS exists to prevent, one file up). Every
 * other path checks config.php's in-memory $config first, then
 * settings_registry, then returns $default.
 */
function config_get(array $config, PDO $pdo, string $path, mixed $default = null): mixed
{
    if (in_array($path, CONFIG_FILE_PATHS, true)) {
        $fromFile = array_get_dot($config, $path);
        return $fromFile ?? $default;
    }

    $fromFile = array_get_dot($config, $path);
    if ($fromFile !== null) {
        return $fromFile;
    }

    [$category, $key] = config_path_to_setting($path);
    if ($category !== null) {
        $value = get_setting($pdo, $category, $key, null);
        if ($value !== null) {
            return $value;
        }
    }

    return $default;
}

/**
 * Write a config value, routed by where it actually lives: CONFIG_FILE_PATHS
 * to config.php, everything else to settings_registry. Returns an array of
 * human-readable problems; empty means the write succeeded. The caller
 * (admin/settings.php) shows these instead of a false "saved" message,
 * which is the exact failure update_config_setting() had -- it always
 * returned void and always looked like it worked.
 *
 * @return list<string> problems, empty on success
 */
function config_set(array &$config, PDO $pdo, string $path, mixed $value): array
{
    if (in_array($path, CONFIG_FILE_PATHS, true)) {
        return config_write_to_file($config, $path, $value);
    }

    [$category, $key] = config_path_to_setting($path);
    if ($category === null) {
        return ["Unknown setting: {$path}"];
    }

    $errors = validate_setting($pdo, $category, $key, $value);
    if ($errors) {
        return $errors;
    }

    if (!set_setting($pdo, $category, $key, $value)) {
        return ["Could not save {$path}."];
    }

    return [];
}

/** Whether a CONFIG_FILE_PATHS key should be masked in the Settings UI. */
function config_path_is_secret(string $path): bool
{
    return in_array($path, CONFIG_SECRET_PATHS, true);
}

/**
 * settings_registry's category.key_name -> the real config.php dot path.
 *
 * Identity for almost everything (stripe.mode, smtp.pass, ...). The one
 * exception: config.php stores currency as a bare top-level string, not
 * nested under a 'currency' category the way every other config.php-native
 * group is, so the Settings UI's currency.code field needs a translation
 * rather than a literal match against CONFIG_FILE_PATHS.
 */
function settings_field_to_config_path(string $category, string $key): string
{
    if ($category === 'currency' && $key === 'code') {
        return 'currency';
    }
    return "{$category}.{$key}";
}

/** 'stripe.live_secret_key' -> ['stripe', 'live_secret_key']. */
function config_path_to_setting(string $path): array
{
    $parts = explode('.', $path, 2);
    return count($parts) === 2 ? $parts : [null, null];
}

function array_get_dot(array $arr, string $path): mixed
{
    $value = $arr;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }
    return $value;
}

function array_set_dot(array $arr, string $path, mixed $value): array
{
    $segments = explode('.', $path);
    $cursor = &$arr;
    foreach ($segments as $i => $segment) {
        if ($i === count($segments) - 1) {
            $cursor[$segment] = $value;
        } else {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }
    return $arr;
}

/**
 * Regenerate config/config.php with one value changed, atomically.
 *
 * Written the same way bootstrap-config.php generates the file initially
 * (var_export, temp file, rename), so there is exactly one code path that
 * produces this file's format rather than two that could drift apart.
 * rename() is atomic on both POSIX and Windows filesystems, so a request
 * that reads config.php mid-write always sees either the old file or the
 * new one, never a half-written one.
 *
 * @return list<string> problems, empty on success
 */
function config_write_to_file(array &$config, string $path, mixed $value): array
{
    $configPath = dirname(__DIR__, 2) . '/config/config.php';

    if (!is_writable($configPath)) {
        // This is the failure the old stub never reported. On shared hosting
        // config.php is sometimes deployed read-only on purpose; the admin
        // needs to know a save silently didn't happen rather than see a
        // success message and find out later the value never took.
        return ["config/config.php is not writable by the web server. Ask your host to grant write access, or edit the file directly over FTP/SFTP."];
    }

    $updated = array_set_dot($config, $path, $value);

    $backupPath = $configPath . '.bak';
    if (!@copy($configPath, $backupPath)) {
        return ["Could not create config/config.php.bak before writing. Nothing was changed."];
    }

    $code = "<?php\nreturn " . var_export($updated, true) . ";\n";
    $tmpPath = $configPath . '.tmp.' . bin2hex(random_bytes(4));

    if (@file_put_contents($tmpPath, $code) === false) {
        return ["Could not write a temporary config file. Nothing was changed."];
    }

    if (!@rename($tmpPath, $configPath)) {
        @unlink($tmpPath);
        return ["Could not replace config/config.php. Nothing was changed."];
    }

    // Without this, a host running opcache would keep serving the old file's
    // compiled bytecode from cache until the worker process recycled, so the
    // save would look successful but not actually apply for an indeterminate
    // time. Most shared hosts run opcache with a timestamp check disabled for
    // performance, which is exactly the case this misses without the call.
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($configPath, true);
    }

    $config = $updated;

    return [];
}
