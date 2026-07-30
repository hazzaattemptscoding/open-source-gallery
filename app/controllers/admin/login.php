<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_login_controller(PDO $pdo, array $config): void
{
    if (admin_is_logged_in()) {
        header('Location: /admin');
        exit;
    }

    // No admin exists yet: a login form here could never succeed, so send
    // straight to first-run setup instead of showing a dead end.
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount === 0) {
        header('Location: /admin/setup');
        exit;
    }

    $error = null;
    $needsTotp = false;
    $emailValue = '';
    $passwordValue = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $error = 'Session expired. Reload the page and try again.';
        } else {
            $emailValue = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $totpCode = trim($_POST['totp_code'] ?? '');
            $totpCode = $totpCode === '' ? null : $totpCode;

            $result = admin_attempt_login($pdo, $config, $emailValue, $password, $totpCode, client_ip());

            if ($result['ok']) {
                header('Location: /admin');
                exit;
            }

            switch ($result['reason']) {
                case 'rate_limited':
                    $error = 'Too many attempts. Try again in 15 minutes.';
                    break;
                case 'totp_required':
                    // The password field is `required`, so it must come back
                    // filled or a real browser blocks the second submit via
                    // HTML5 validation before the request ever reaches the
                    // server — this isn't visible with a tool that skips
                    // client-side validation (curl, direct POSTs), only with
                    // an actual browser enforcing the `required` attribute.
                    $needsTotp = true;
                    $passwordValue = $password;
                    $error = 'Enter the 6-digit code from your authenticator app.';
                    break;
                default:
                    $error = 'Invalid email, password, or code.';
            }
        }
    }

    render(__DIR__ . '/../../views/admin/login.php', [
        'error' => $error,
        'needsTotp' => $needsTotp,
        'emailValue' => $emailValue,
        'passwordValue' => $passwordValue,
        'siteName' => $config['site']['name'],
        'csrfToken' => csrf_token(),
    ]);
}
