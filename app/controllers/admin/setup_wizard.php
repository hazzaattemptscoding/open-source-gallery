<?php
/**
 * First-run setup wizard. Replaces the old one-page setup with a multi-step
 * guided experience: admin account → business details → email → Stripe → storage mode → admin mode → summary.
 *
 * Each step is stateless in session, but completion is persisted in settings table.
 * Users can skip optional steps and return later to complete them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/setup.php';
require_once __DIR__ . '/../../lib/db_compat.php';
require_once __DIR__ . '/../../lib/validation.php';
require_once __DIR__ . '/../../lib/config_store.php';

function admin_setup_wizard_controller(PDO $pdo, array $config): void {
    $adminExists = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;

    $step = $_GET['step'] ?? $_POST['step'] ?? 'admin_account';

    // Lock the wizard once it is genuinely finished -- every step the
    // checklist marks required is done or skipped -- rather than as soon as
    // an admin account exists. handle_admin_account() inserts that row on
    // the wizard's very first successful submission, so gating on it alone
    // meant every installer was redirected here from step 1's own "next
    // step" link straight into a 403, unable to reach business details,
    // email, Stripe, or storage/admin mode through the web UI at all.
    // $step !== 'summary' so the summary page itself can still render once
    // finished, to show what was configured.
    if (setup_wizard_is_complete($pdo) && $step !== 'summary') {
        http_response_code(403);
        echo 'Setup has already been completed. Go to /admin/login.';
        return;
    }

    // The one thing that must not repeat is creating a second admin account:
    // route straight past that step if today's admin already exists, rather
    // than showing the create-account form again (which would either fail
    // confusingly on the email uniqueness constraint, or succeed and leave a
    // stray second admin).
    if ($step === 'admin_account' && $adminExists) {
        header('Location: /admin/setup?step=business_details');
        exit;
    }
    $error = null;
    $success = null;
    $data = [];

    // Route to step handler
    $handlers = [
        'admin_account' => 'handle_admin_account',
        'business_details' => 'handle_business_details',
        'email_setup' => 'handle_email_setup',
        'stripe_keys' => 'handle_stripe_keys',
        'storage_mode' => 'handle_storage_mode',
        'admin_mode' => 'handle_admin_mode',
        'summary' => 'handle_summary',
    ];

    if (!isset($handlers[$step])) {
        $step = 'admin_account';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$error, $success] = call_user_func($handlers[$step], $pdo, $config, $_POST);
        if ($success) {
            // On success, either redirect to next step or return to current with success message
            if (isset($_POST['next_step'])) {
                header("Location: /admin/setup?step=" . urlencode($_POST['next_step']));
                exit;
            }
        }
    }

    // Load current step data for display
    $data = get_step_data($pdo, $config, $step);

    render(__DIR__ . '/../../views/admin/setup_wizard.php', [
        'step' => $step,
        'error' => $error,
        'success' => $success,
        'data' => $data,
        'checklist' => get_setup_checklist($pdo),
        'csrfToken' => csrf_token(),
        'siteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

function handle_admin_account(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $email = trim($post['email'] ?? '');
    $password = $post['password'] ?? '';
    $passwordConfirm = $post['password_confirm'] ?? '';

    if (!validate_email_for_mode($email, $config)) {
        return ['Enter a valid email address.', false];
    }
    if (strlen($password) < 12) {
        return ['Password must be at least 12 characters.', false];
    }
    if ($password !== $passwordConfirm) {
        return ['Passwords do not match.', false];
    }

    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (?, ?)');
    $stmt->execute([strtolower($email), $hash]);

    mark_step_complete($pdo, 'admin_account');
    audit_log($pdo, 'system', 'admin_created', 'admin_users', (int)$pdo->lastInsertId());

    return [null, true];
}

function handle_business_details(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $name = trim($post['name'] ?? '');
    $email = trim($post['email'] ?? '');
    $currency = trim($post['currency'] ?? 'GBP');

    if ($name === '') {
        return ['Gallery name is required.', false];
    }
    if (!validate_email_for_mode($email, $config)) {
        return ['Contact email is required and must be valid.', false];
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        return ['Currency must be a 3-letter ISO code (e.g., GBP, USD).', false];
    }

    $writeError = update_config_settings($pdo, [
        'site.name' => $name,
        'site.support_email' => $email,
        'currency' => $currency,
    ]);
    if ($writeError) {
        return [$writeError, false];
    }

    mark_step_complete($pdo, 'business_details');

    return [null, true];
}

function handle_email_setup(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $skip = isset($post['skip']);
    if ($skip) {
        mark_step_skipped($pdo, 'email_setup');
        return [null, true];
    }

    $fromEmail = trim($post['from_email'] ?? '');
    $fromName = trim($post['from_name'] ?? '');
    $provider = $post['provider'] ?? 'custom';
    $host = trim($post['smtp_host'] ?? '');
    $port = (int)($post['smtp_port'] ?? 587);
    $user = trim($post['smtp_user'] ?? '');
    $pass = $post['smtp_pass'] ?? '';

    if (!validate_email_for_mode($fromEmail, $config)) {
        return ['From email is required and must be valid.', false];
    }

    if ($host === '') {
        return ['SMTP host is required.', false];
    }

    if ($port < 1 || $port > 65535) {
        return ['SMTP port must be between 1 and 65535.', false];
    }

    $writeError = update_config_settings($pdo, [
        'smtp.from_email' => $fromEmail,
        'smtp.from_name' => $fromName,
        'smtp.host' => $host,
        'smtp.port' => (string)$port,
        'smtp.user' => $user,
        'smtp.pass' => $pass,
    ]);
    if ($writeError) {
        return [$writeError, false];
    }

    mark_step_complete($pdo, 'email_setup');

    return [null, true];
}

function handle_stripe_keys(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $skip = isset($post['skip']);
    if ($skip) {
        mark_step_skipped($pdo, 'stripe_keys');
        return [null, true];
    }

    $publishable = trim($post['publishable_key'] ?? '');
    $secret = trim($post['secret_key'] ?? '');

    if ($publishable === '') {
        return ['Publishable key is required.', false];
    }
    if ($secret === '') {
        return ['Secret key is required.', false];
    }

    if (!str_starts_with($publishable, 'pk_')) {
        return ['Publishable key must start with pk_.', false];
    }
    if (!str_starts_with($secret, 'sk_')) {
        return ['Secret key must start with sk_.', false];
    }

    $writeError = update_config_settings($pdo, [
        'stripe.publishable_key' => $publishable,
        'stripe.secret_key' => $secret,
    ]);
    if ($writeError) {
        return [$writeError, false];
    }

    mark_step_complete($pdo, 'stripe_keys');

    return [null, true];
}

function handle_storage_mode(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $skip = isset($post['skip']);
    if ($skip) {
        mark_step_skipped($pdo, 'storage_mode');
        // Ensure default is set
        if ($writeError = update_config_settings($pdo, ['storage_mode' => 'local'])) {
            return [$writeError, false];
        }
        return [null, true];
    }

    $mode = $post['storage_mode'] ?? 'local';
    if (!in_array($mode, ['local', 'remote-nas'], true)) {
        return ['Invalid storage mode.', false];
    }

    if ($writeError = update_config_settings($pdo, ['storage_mode' => $mode])) {
        return [$writeError, false];
    }

    // If remote-nas, generate poller token
    if ($mode === 'remote-nas') {
        $token = generate_poller_token($pdo);
        // Token is displayed on next view so user can copy it to their poller script
        $_SESSION['new_poller_token'] = $token;
    }

    mark_step_complete($pdo, 'storage_mode');

    return [null, true];
}

function handle_admin_mode(PDO $pdo, array $config, array $post): array {
    if (!csrf_verify($post['csrf_token'] ?? null)) {
        return ['Session expired. Reload and try again.', false];
    }

    $skip = isset($post['skip']);
    if ($skip) {
        mark_step_skipped($pdo, 'admin_mode');
        // Ensure default is set
        if ($writeError = update_config_settings($pdo, ['admin_mode' => 'local'])) {
            return [$writeError, false];
        }
        return [null, true];
    }

    $mode = $post['admin_mode'] ?? 'local';
    if (!in_array($mode, ['local', 'remote'], true)) {
        return ['Invalid admin mode.', false];
    }

    if ($writeError = update_config_settings($pdo, ['admin_mode' => $mode])) {
        return [$writeError, false];
    }

    mark_step_complete($pdo, 'admin_mode');

    return [null, true];
}

function handle_summary(PDO $pdo, array $config, array $post): array {
    // Summary is just a display, no form submission needed
    return [null, true];
}

function get_step_data(PDO $pdo, array $config, string $step): array {
    $data = [];

    switch ($step) {
        case 'email_setup':
            $data['providers'] = [
                'gmail' => ['host' => 'smtp.gmail.com', 'port' => 587],
                'outlook' => ['host' => 'smtp-mail.outlook.com', 'port' => 587],
                'ionos' => ['host' => 'smtp.ionos.com', 'port' => 587],
                'custom' => ['host' => '', 'port' => 587],
            ];
            break;

        case 'stripe_keys':
            $data['dashboard_url'] = 'https://dashboard.stripe.com/account/apikeys';
            break;

        case 'storage_mode':
            $data['modes'] = [
                'local' => [
                    'label' => 'Local (Default)',
                    'description' => 'All files stored on this server. Standard shared hosting setup.',
                ],
                'remote-nas' => [
                    'label' => 'Remote NAS',
                    'description' => 'Originals on home NAS, previews cached here. Advanced setup for large galleries.',
                ],
            ];
            break;

        case 'admin_mode':
            $data['modes'] = [
                'local' => [
                    'label' => 'Local (Default)',
                    'description' => 'Admin runs on the same server as the public gallery.',
                ],
                'remote' => [
                    'label' => 'Remote',
                    'description' => 'Admin runs elsewhere (home machine) and connects over network.',
                ],
            ];
            break;

        case 'summary':
            $data['completed'] = get_completed_steps($pdo);
            $data['skipped'] = get_skipped_steps($pdo);
            break;
    }

    return $data;
}

/**
 * Write one value into config/config.php.
 *
 * Previously wrote to a `setup_config_*` row in the legacy `settings` table
 * -- its own comment admitted this was a placeholder ("the actual config.php
 * update is a manual step... in production") -- and nothing anywhere ever
 * read a `setup_config_*` key back. Every value collected across the wizard's
 * business details, email, and Stripe steps was silently discarded; a fresh
 * install's only working path to configuring any of it was hand-editing
 * config.php over FTP.
 *
 * Delegates to config_write_to_file() (app/lib/config_store.php), the same
 * writer the Settings page's secret fields use: read, splice, write to a
 * temp file, atomic rename, .bak kept, opcache invalidated. Every wizard
 * value the setup flow collects belongs in config.php rather than
 * settings_registry -- these are first-run bootstrap values (site identity,
 * mail transport, payment keys, storage/admin mode), not the day-to-day
 * tunable preferences the Settings page manages, and several of them
 * (stripe.secret_key, smtp.pass) are secrets that must never sit in the
 * database per the same reasoning as config_store.php's CONFIG_SECRET_PATHS.
 *
 * Re-reads config.php itself on every call rather than trusting the $config
 * a handler was invoked with: handle_email_setup() alone calls this six
 * times in one request, and each write must build on the one before it, not
 * on the snapshot the request started with.
 *
 * A write failure (unwritable file, no permission) is surfaced to the admin
 * rather than silently discarded -- the stub's core failure -- by returning
 * the problem list config_write_to_file() produces; callers below add the
 * first one to the step's own $error rather than reporting success.
 *
 * @return list<string> problems, empty on success
 */
function update_config_setting(PDO $pdo, string $key, string $value): array {
    $configPath = dirname(__DIR__, 3) . '/config/config.php';
    $current = file_exists($configPath) ? require $configPath : [];

    return config_write_to_file($current, $key, $value);
}

/**
 * Write several config.php values in one step, stopping at the first
 * failure. Every step handler calls this instead of update_config_setting()
 * directly so a step's several fields are written as one intent and the
 * first human-readable problem (not an empty page reload) reaches the admin
 * if any write fails partway through.
 *
 * @param array<string,string> $values dot-path => value
 * @return string|null the first problem encountered, or null on success
 */
function update_config_settings(PDO $pdo, array $values): ?string {
    foreach ($values as $key => $value) {
        $problems = update_config_setting($pdo, $key, $value);
        if ($problems) {
            return $problems[0];
        }
    }
    return null;
}

/**
 * True once the admin has been through every step -- done or explicitly
 * skipped, required or not. Checking only the two required steps
 * (admin_account, business_details) was the first attempt at this and is
 * wrong: those two are typically the first ones finished, so it locked the
 * wizard out of the four optional steps (email, Stripe, storage mode, admin
 * mode) for anyone who did not skip them, the same shape of bug as the
 * admin_users-count check this replaced. "Finished" has to mean the whole
 * walk, not just the mandatory part of it.
 */
function setup_wizard_is_complete(PDO $pdo): bool {
    foreach (get_setup_checklist($pdo) as $step) {
        if ($step['status'] === 'pending') {
            return false;
        }
    }
    return true;
}
