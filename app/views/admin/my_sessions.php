<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Sessions — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.session-card {
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.session-card.current {
    background-color: #f0f8ff;
    border-left: 4px solid #1a1a1a;
}
.session-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.session-field {
    font-size: 0.9rem;
}
.session-field strong {
    display: block;
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
}
.ip-address {
    font-family: monospace;
    font-size: 0.9rem;
}
.user-agent {
    word-break: break-all;
    font-size: 0.85rem;
    color: var(--text-muted);
    font-family: monospace;
}
.revoke-button {
    padding: 0.5rem 1rem;
    background-color: #d32f2f;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    font-weight: 500;
}
.revoke-button:hover {
    background-color: #b71c1c;
}
.current-badge {
    display: inline-block;
    padding: 0.3rem 0.6rem;
    background-color: #1a1a1a;
    color: white;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: 600;
}
.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}
.alert-error {
    background-color: #fee;
    color: #c33;
    border: 1px solid #fcc;
}
.alert-success {
    background-color: #efe;
    color: #3c3;
    border: 1px solid #cfc;
}
.time-ago {
    color: var(--text-muted);
    font-size: 0.85rem;
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">My Sessions</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

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

</body>
</html>

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
