<?php
$pageTitle = 'My Sessions';
$currentPage = 'my-sessions';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard">
  <h1>My Sessions</h1>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <p style="margin-bottom: 2rem; color: var(--text-muted);">
    You have <?= count($sessions) ?> active session<?= count($sessions) !== 1 ? 's' : '' ?>.
    Revoke any session you no longer recognize or don't remember using.
  </p>

  <?php if (empty($sessions)): ?>
    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
      You have no active sessions. This is unusual—log in to verify your account.
    </p>
  <?php else: ?>
    <?php foreach ($sessions as $session): ?>
      <div class="session-card <?= $session['id'] === $currentSessionId ? 'current' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
          <div>
            <div style="font-weight: 600; margin-bottom: 0.25rem;">
              <?php if ($session['id'] === $currentSessionId): ?>
                <span class="current-badge">Current Session</span>
              <?php else: ?>
                Session
              <?php endif; ?>
            </div>
          </div>
          <?php if ($session['id'] !== $currentSessionId): ?>
            <form method="post" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="revoke_session_id" value="<?= e($session['id']) ?>">
              <button type="submit" class="revoke-button" onclick="return confirm('Revoke this session?')">
                Revoke
              </button>
            </form>
          <?php endif; ?>
        </div>

        <div class="session-info">
          <div class="session-field">
            <strong>IP Address</strong>
            <div class="ip-address"><?= e($session['ip_address'] ?? '—') ?></div>
          </div>

          <div class="session-field">
            <strong>Last Activity</strong>
            <div><?= e(format_time_ago($session['last_activity'])) ?></div>
          </div>

          <div class="session-field" style="grid-column: span 2;">
            <strong>Browser / Device</strong>
            <div class="user-agent">
              <?php
                $ua = $session['user_agent'] ?? '';
                if (strpos($ua, 'Chrome') !== false) {
                    $device = '🖥️ Chrome';
                } elseif (strpos($ua, 'Firefox') !== false) {
                    $device = '🖥️ Firefox';
                } elseif (strpos($ua, 'Safari') !== false) {
                    $device = '🍎 Safari';
                } elseif (strpos($ua, 'Edge') !== false) {
                    $device = '🖥️ Edge';
                } else {
                    $device = '🖥️ Browser';
                }
              ?>
              <span style="font-style: normal;"><?= $device ?></span>
              <br>
              <span style="color: #999;"><?= e(substr($ua, 0, 60)) ?>...</span>
            </div>
          </div>

          <div class="session-field">
            <strong>Created</strong>
            <div class="time-ago"><?= e(date('F j, Y H:i:s', strtotime($session['created_at']))) ?></div>
          </div>

          <div class="session-field">
            <strong>Expires</strong>
            <div class="time-ago"><?= e(date('F j, Y H:i:s', strtotime($session['expires_at']))) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="margin-top: 3rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">Session Security</h3>
    <p>
      Sessions allow you to stay logged in while browsing. Each session is tied to your IP address and browser.
    </p>
    <ul>
      <li><strong>Don't recognize a session?</strong> Revoke it immediately—it may indicate unauthorized access</li>
      <li><strong>Traveling or switching devices?</strong> Multiple sessions from different IPs is normal</li>
      <li><strong>Lost a device?</strong> Revoke its session to prevent remote access</li>
      <li>Sessions expire after 30 days of inactivity</li>
    </ul>
  </div>

</main>


<?php
function format_time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
