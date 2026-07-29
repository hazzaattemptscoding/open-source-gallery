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

function admin_setup_wizard_controller(PDO $pdo, array $config): void {
    // Redirect if setup already complete (has admin account)
    $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount > 0) {
        http_response_code(403);
        echo 'Setup has already been completed. Go to /admin/login.';
        return;
    }

    $step = $_GET['step'] ?? $_POST['step'] ?? 'admin_account';
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

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['Contact email is required and must be valid.', false];
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        return ['Currency must be a 3-letter ISO code (e.g., GBP, USD).', false];
    }

    update_config_setting($pdo, 'site.name', $name);
    update_config_setting($pdo, 'site.support_email', $email);
    update_config_setting($pdo, 'currency', $currency);

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

    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['From email is required and must be valid.', false];
    }

    if ($host === '') {
        return ['SMTP host is required.', false];
    }

    if ($port < 1 || $port > 65535) {
        return ['SMTP port must be between 1 and 65535.', false];
    }

    update_config_setting($pdo, 'smtp.from_email', $fromEmail);
    update_config_setting($pdo, 'smtp.from_name', $fromName);
    update_config_setting($pdo, 'smtp.host', $host);
    update_config_setting($pdo, 'smtp.port', (string)$port);
    update_config_setting($pdo, 'smtp.user', $user);
    update_config_setting($pdo, 'smtp.pass', $pass);

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

    update_config_setting($pdo, 'stripe.publishable_key', $publishable);
    update_config_setting($pdo, 'stripe.secret_key', $secret);

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
        update_config_setting($pdo, 'storage_mode', 'local');
        return [null, true];
    }

    $mode = $post['storage_mode'] ?? 'local';
    if (!in_array($mode, ['local', 'remote-nas'], true)) {
        return ['Invalid storage mode.', false];
    }

    update_config_setting($pdo, 'storage_mode', $mode);

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
        update_config_setting($pdo, 'admin_mode', 'local');
        return [null, true];
    }

    $mode = $post['admin_mode'] ?? 'local';
    if (!in_array($mode, ['local', 'remote'], true)) {
        return ['Invalid admin mode.', false];
    }

    update_config_setting($pdo, 'admin_mode', $mode);

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

function update_config_setting(PDO $pdo, string $key, string $value): void {
    // Note: In production, this would write to config/config.php.
    // For now, store in settings table as a reference.
    // The actual config.php update is a manual step or done via
    // a config manager in production.
    $stmt = $pdo->prepare('
        INSERT INTO settings (skey, svalue) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)
    ');
    $stmt->execute(['setup_config_' . $key, $value]);
}
