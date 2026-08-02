<?php
$pageTitle = 'Set up two-factor authentication';
require_once __DIR__ . '/partials/minimal_header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h1>Set up two-factor authentication</h1>
    <p class="subtitle">Add this account to your authenticator app (Google Authenticator, Authy, 1Password, etc.), then enter the current code below.</p>

    <label>Secret key (manual entry)</label>
    <div class="secret"><?= e($secret) ?></div>
    <p class="hint">Or paste this URI if your app accepts one: <span class="secret"><?= e($enrollmentUri) ?></span></p>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/totp/enroll">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <label for="totp_code">Current code</label>
      <input type="text" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>

      <button type="submit">Confirm and enable</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/partials/minimal_footer.php'; ?>
