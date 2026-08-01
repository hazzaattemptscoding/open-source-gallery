<?php
/**
 * Admin login/session lifecycle. Single-admin-user model (one row in
 * admin_users, per docs/architecture.md), but the auth flow is written the
 * normal multi-user way — lookup by email, verify hash — since that's no
 * more code and doesn't bake in an assumption the schema doesn't have.
 */

declare(strict_types=1);

require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/session.php';

// A real Argon2id hash of an arbitrary value, used only to keep
// password_verify()'s timing the same when the email doesn't match any
// account. Without this, "unknown email" returns near-instantly while
// "known email, wrong password" pays the Argon2id cost — an attacker
// timing responses could enumerate valid emails from that gap alone.
const AUTH_DECOY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$OC9adHRUeVZCN2FSVEJwVA$Q2uPhgrgX1OuCQCyP0wsN1cX8b82bhzMCC/yf1vISOQ';

/**
 * @return array{ok:bool, reason?:string} reason is one of:
 *   'rate_limited' | 'invalid_credentials' | 'totp_required' | 'invalid_totp'
 *   Only 'rate_limited' and 'totp_required' are safe to show verbatim to the
 *   user without leaking account existence; the controller maps
 *   'invalid_credentials' and 'invalid_totp' to the same generic message.
 */
function admin_attempt_login(PDO $pdo, array $config, string $email, string $password, ?string $totpCode, string $ip): array
{
    $email = strtolower(trim($email));

    // Per docs/architecture.md section 6: 5 attempts per 15 minutes, per IP
    // AND per account. Checked before touching the password hash so a
    // locked-out attempt doesn't cost an Argon2id verify either.
    $maxAttempts = adjust_rate_limit_for_dev($config, 5);
    $ipOk = check_rate_limit($pdo, 'login', 'ip:' . $ip, 900, $maxAttempts);
    $accountOk = check_rate_limit($pdo, 'login', 'acct:' . $email, 900, $maxAttempts);
    if (!$ipOk || !$accountOk) {
        audit_log($pdo, 'public', 'login_rate_limited', 'admin_users', null, ['email' => $email], $ip);
        return ['ok' => false, 'reason' => 'rate_limited'];
    }

    $stmt = $pdo->prepare('SELECT id, email, password_hash, totp_enabled, totp_secret, totp_last_step FROM admin_users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    $hashToCheck = $admin['password_hash'] ?? AUTH_DECOY_HASH;
    $passwordOk = password_verify($password, $hashToCheck);

    if (!$admin || !$passwordOk) {
        audit_log($pdo, 'public', 'login_fail', 'admin_users', null, ['email' => $email], $ip);
        return ['ok' => false, 'reason' => 'invalid_credentials'];
    }

    if ((bool) $admin['totp_enabled']) {
        if ($totpCode === null || $totpCode === '') {
            return ['ok' => false, 'reason' => 'totp_required'];
        }

        // Distinct from the password-step 'login' bucket above: a correct
        // password paired with a brute-forced TOTP code would otherwise hit
        // no dedicated limit at all, since the password step already
        // passed. docs/architecture.md's security requirements table
        // explicitly lists "TOTP attempts" as its own rate-limited bucket.
        if (!check_rate_limit($pdo, 'totp', 'acct:' . $email, 900, $maxAttempts)) {
            audit_log($pdo, 'admin', 'login_rate_limited_totp', 'admin_users', (int) $admin['id'], null, $ip);
            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        $matchedStep = totp_verify($admin['totp_secret'], $totpCode, $admin['totp_last_step']);
        if ($matchedStep === null) {
            audit_log($pdo, 'admin', 'login_fail_totp', 'admin_users', (int) $admin['id'], null, $ip);
            return ['ok' => false, 'reason' => 'invalid_totp'];
        }

        $pdo->prepare('UPDATE admin_users SET totp_last_step = :step WHERE id = :id')
            ->execute(['step' => $matchedStep, 'id' => $admin['id']]);
    }

    session_regenerate_after_login();
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];

    audit_log($pdo, 'admin', 'login_ok', 'admin_users', (int) $admin['id'], null, $ip);

    return ['ok' => true];
}

/** True if the current session belongs to a logged-in admin. */
function admin_is_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

/** Redirects to the login page and exits if there's no admin session. Call at the top of every admin controller. */
/**
 * True only when config/config.php explicitly sets security.dev_mode = true.
 * That key does not exist in config.example.php and is never written by
 * anything except the local dev installer (dev_setup.php) — a production
 * config produced any other way will not have it, so this defaults safely
 * closed rather than open.
 */
function dev_mode_enabled(): bool
{
    return ($GLOBALS['config']['security']['dev_mode'] ?? false) === true;
}

function require_admin(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    if (dev_mode_enabled()) {
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            $admin = $pdo->query('SELECT id FROM admin_users ORDER BY id ASC LIMIT 1')->fetch();
            if ($admin) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['dev_mode_autologin'] = true;
                return;
            }
        }
        // No admin row exists yet even though dev_mode is on — fall through
        // to the normal redirect rather than silently failing open.
    }

    header('Location: /admin/login');
    exit;
}

function current_admin_id(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

function admin_logout(PDO $pdo): void
{
    if (admin_is_logged_in()) {
        audit_log($pdo, 'admin', 'logout', 'admin_users', current_admin_id(), null, client_ip());
    }

    $_SESSION = [];
    session_destroy();
}
