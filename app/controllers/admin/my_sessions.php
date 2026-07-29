<?php
/**
 * Admin session management: view and revoke your own sessions.
 * Security feature to maintain control over your account.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_my_sessions_controller(PDO $pdo, array $config): void {
    require_admin();

    $adminId = current_admin_id();
    $csrfToken = csrf_token();

    // Handle session revocation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_session_id'])) {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $error = 'CSRF verification failed.';
        } else {
            $sessionId = (int)$_POST['revoke_session_id'];

            $stmt = $pdo->prepare('SELECT admin_id FROM sessions WHERE id = ?');
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session && $session['admin_id'] == $adminId) {
                $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sessionId]);
                audit_log($pdo, 'session_revoke', $adminId, "Revoked session ID $sessionId");
                $success = 'Session revoked.';
            }
        }
    }

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            id, token_hash, ip_address, user_agent, created_at, last_activity, expires_at
        FROM sessions
        WHERE admin_id = ?
        ORDER BY last_activity DESC
    SQL);
    $stmt->execute([$adminId]);
    $sessions = $stmt->fetch_all(PDO::FETCH_ASSOC);

    $currentSessionId = get_session_id();

    render(__DIR__ . '/../../views/admin/my_sessions.php', [
        'sessions' => $sessions,
        'currentSessionId' => $currentSessionId,
        'csrfToken' => $csrfToken,
        'error' => $error ?? '',
        'success' => $success ?? '',
        'siteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

/**
 * Get the current session ID from cookies.
 */
function get_session_id(): ?int {
    $token = $_COOKIE['session_token'] ?? null;
    if (!$token) return null;

    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) return null;

    $stmt = $pdo->prepare('SELECT id FROM sessions WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $token)]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    return $session ? (int)$session['id'] : null;
}

/**
 * Fetch all sessions (helper method).
 */
if (!method_exists('PDOStatement', 'fetch_all')) {
    PDOStatement::class;
}
