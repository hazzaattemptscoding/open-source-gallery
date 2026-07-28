<?php
/**
 * First-run admin creation. Shared hosting users typically only have FTP/file
 * manager access, not SSH, so this is a web page rather than a CLI script —
 * it's the only way most self-hosters could create the first account at all.
 * Locks itself out the moment any admin_users row exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_setup_controller(PDO $pdo, array $config): void
{
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount > 0) {
        http_response_code(403);
        echo 'Setup has already been completed. Go to /admin/login.';
        return;
    }

    $error = null;
    $emailValue = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $error = 'Session expired. Reload the page and try again.';
        } else {
            $emailValue = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } elseif (strlen($password) < 12) {
                $error = 'Password must be at least 12 characters.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (:email, :hash)');
                $stmt->execute(['email' => strtolower($emailValue), 'hash' => $hash]);

                audit_log($pdo, 'system', 'admin_created', 'admin_users', (int) $pdo->lastInsertId(), null, client_ip());

                header('Location: /admin/login');
                exit;
            }
        }
    }

    render(__DIR__ . '/../../views/admin/setup.php', [
        'error' => $error,
        'emailValue' => $emailValue,
        'siteName' => $config['site']['name'],
        'csrfToken' => csrf_token(),
    ]);
}
