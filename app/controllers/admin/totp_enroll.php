<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/totp.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_totp_enroll_controller(PDO $pdo, array $config): void
{
    require_admin();

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => current_admin_id()]);
    $admin = $stmt->fetch();

    if ((bool) $admin['totp_enabled']) {
        http_response_code(403);
        echo '2FA is already enabled on this account.';
        return;
    }

    // The secret is generated once and held in the session across the GET
    // (show it) -> POST (confirm a code against it) round trip, so a page
    // reload doesn't hand the admin a QR code that no longer matches what
    // gets saved.
    if (empty($_SESSION['totp_enroll_secret'])) {
        $_SESSION['totp_enroll_secret'] = totp_generate_secret();
    }
    $secret = $_SESSION['totp_enroll_secret'];

    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $error = 'Session expired. Reload the page and try again.';
        } else {
            $code = trim($_POST['totp_code'] ?? '');
            $matchedStep = totp_verify($secret, $code, null);

            if ($matchedStep === null) {
                $error = 'That code did not match. Check the time on your device and try the current code.';
            } else {
                $pdo->prepare('UPDATE admin_users SET totp_secret = :secret, totp_enabled = 1, totp_last_step = :step WHERE id = :id')
                    ->execute(['secret' => $secret, 'step' => $matchedStep, 'id' => current_admin_id()]);

                unset($_SESSION['totp_enroll_secret']);
                audit_log($pdo, 'admin', 'totp_enabled', 'admin_users', current_admin_id(), null, client_ip());

                header('Location: /admin');
                exit;
            }
        }
    }

    render(__DIR__ . '/../../views/admin/totp_enroll.php', [
        'secret' => $secret,
        'enrollmentUri' => totp_enrollment_uri($secret, $admin['email'], $config['site']['name']),
        'error' => $error,
        'siteName' => $config['site']['name'],
        'csrfToken' => csrf_token(),
    ]);
}
