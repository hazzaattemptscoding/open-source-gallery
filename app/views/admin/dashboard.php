<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="dashboard">
  <h1><?= e($siteName) ?> admin</h1>
  <p>Logged in as <?= e($adminEmail) ?>.</p>

  <?php if (!$totpEnabled): ?>
    <p class="error">Two-factor authentication is not enabled on this account. <a href="/admin/totp/enroll" class="link-gold">Set it up now</a>.</p>
  <?php endif; ?>

  <div class="panel">
    <h2>Manage content</h2>
    <ul class="list-plain">
      <li><a href="/admin/events" class="link-gold">→ Events</a> — Create and manage events, sessions, prices</li>
      <li><a href="/admin/upload" class="link-gold">→ Upload photos</a> — Chunked upload with derivative generation</li>
      <li><a href="/admin/migrations" class="link-gold">→ Migrations</a> — Apply pending database schema changes</li>
    </ul>
  </div>

  <form method="post" action="/admin/logout" class="logout-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <button type="submit">Log out</button>
  </form>
</div>
</body>
</html>
