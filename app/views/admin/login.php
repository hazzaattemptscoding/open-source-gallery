<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <h1><?= e($siteName) ?></h1>
    <p class="subtitle">Admin login</p>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/login">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($emailValue) ?>" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <?php if ($needsTotp): ?>
        <label for="totp_code">Authenticator code</label>
        <input type="text" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
      <?php endif; ?>

      <button type="submit">Log in</button>
    </form>
  </div>
</div>
</body>
</html>
