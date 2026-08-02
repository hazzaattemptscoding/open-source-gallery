<?php
$pageTitle = 'Set up admin account';
require_once __DIR__ . '/partials/minimal_header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h1>Create the admin account</h1>
    <p class="subtitle">This runs once. It won't show again after an account exists.</p>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/setup">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($emailValue) ?>" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="12">

      <label for="password_confirm">Confirm password</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="12">

      <button type="submit">Create account</button>
    </form>

    <p class="hint">At least 12 characters. You can turn on two-factor authentication after your first login.</p>
  </div>
</div>
<?php require_once __DIR__ . '/partials/minimal_footer.php'; ?>
