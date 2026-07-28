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

  <p>Event and photo management lands in the next build step.</p>

  <form method="post" action="/admin/logout" class="logout-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <button type="submit">Log out</button>
  </form>
</div>
</body>
</html>
