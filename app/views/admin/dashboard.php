<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<div class="dashboard">
  <h1><?= e($siteName) ?> admin</h1>
  <p>Logged in as <?= e($adminEmail) ?>.</p>

  <?php if (!$totpEnabled): ?>
    <p class="error">Two-factor authentication is not enabled on this account. <a href="/admin/totp/enroll" style="color: var(--gold)">Set it up now</a>.</p>
  <?php endif; ?>

  <div style="margin: 2rem 0; padding: 1rem; background: rgba(109, 40, 217, 0.1); border-radius: 4px;">
    <h2>Manage content</h2>
    <ul style="list-style: none; padding: 0;">
      <li><a href="/admin/events" style="color: var(--gold);">→ Events</a> — Create and manage events, set prices</li>
      <li><a href="/admin/events" style="color: var(--gold);">→ Sessions</a> — Organise photos into sessions within events</li>
      <li><a href="/admin/events" style="color: var(--gold);">→ Upload & photos</a> — Coming next: bulk upload, derivatives, tagging</li>
    </ul>
  </div>

  <form method="post" action="/admin/logout" class="logout-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <button type="submit">Log out</button>
  </form>
</div>
</body>
</html>
