<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    font-size: 0.95rem;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.95rem;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--text-primary);
}
.form-group .help-text {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
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
.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}
button[type="submit"] {
    background-color: #1a1a1a;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    font-size: 0.95rem;
}
button[type="submit"]:hover {
    background-color: #333;
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Settings</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Logout</button>
  </form>
</header>

<main class="dashboard">
  <h1>Site Settings</h1>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $error): ?>
        <div>• <?= e($error) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <form method="post" style="max-width: 1200px;">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <!-- Site Settings -->
    <div class="panel">
      <h2 class="section-title">Site Information</h2>

      <div class="form-group">
        <label for="site_name">Gallery Name</label>
        <input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name'] ?? $defaultSiteName) ?>" required>
        <div class="help-text">Shown in page titles, emails, and header</div>
      </div>

      <div class="form-group">
        <label for="support_email">Support Email</label>
        <input type="email" id="support_email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>">
        <div class="help-text">Displayed in order receipts and refund emails</div>
      </div>

      <div class="form-group">
        <label for="currency">Currency</label>
        <input type="text" id="currency" name="currency" value="<?= e($settings['currency'] ?? $defaultCurrency) ?>" maxlength="3" placeholder="GBP" required>
        <div class="help-text">ISO 4217 code (GBP, USD, EUR, etc.). All prices display in this currency</div>
      </div>
    </div>

    <!-- Stripe Settings -->
    <div class="panel">
      <h2 class="section-title">Stripe Payment Keys</h2>

      <div class="form-group">
        <label for="stripe_publishable_key">Publishable Key</label>
        <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" value="<?= e($settings['stripe_publishable_key'] ?? '') ?>" placeholder="pk_test_...">
        <div class="help-text">From <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard</a> (test or live)</div>
      </div>

      <div class="form-group">
        <label for="stripe_secret_key">Secret Key</label>
        <input type="text" id="stripe_secret_key" name="stripe_secret_key" value="<?= e($settings['stripe_secret_key'] ?? '') ?>" placeholder="sk_test_...">
        <div class="help-text">Keep this secret — never share it. Used for payment processing</div>
      </div>

      <div class="form-group">
        <label for="stripe_webhook_secret">Webhook Secret</label>
        <input type="text" id="stripe_webhook_secret" name="stripe_webhook_secret" value="<?= e($settings['stripe_webhook_secret'] ?? '') ?>" placeholder="whsec_...">
        <div class="help-text">From Stripe Dashboard → Webhooks (for order confirmation and refunds)</div>
      </div>
    </div>

    <!-- Email Settings -->
    <div class="panel">
      <h2 class="section-title">Email Configuration</h2>

      <p style="margin-bottom: 1rem; color: var(--text-muted);">
        Leave SMTP host blank to use your server's <code>mail()</code> function (most shared hosting).
        Or configure SMTP for guaranteed delivery. See <a href="/docs/EMAIL.md">docs/EMAIL.md</a> for provider details.
      </p>

      <div class="form-group">
        <label for="smtp_from_email">From Email Address</label>
        <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>" placeholder="noreply@yourdomain.com">
        <div class="help-text">Sender email (use your domain for better deliverability)</div>
      </div>

      <div class="form-group">
        <label for="smtp_from_name">From Name</label>
        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? '') ?>" placeholder="Your Gallery Name">
        <div class="help-text">Name shown in customer's email client</div>
      </div>

      <div style="margin-bottom: 2rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
        <strong style="display: block; margin-bottom: 1rem;">SMTP Server (optional)</strong>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="smtp_host">SMTP Host</label>
          <input type="text" id="smtp_host" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
          <div class="help-text">e.g., smtp.gmail.com, smtp.sendgrid.net. Leave blank to use mail()</div>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="smtp_port">SMTP Port</label>
          <input type="number" id="smtp_port" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>" min="1" max="65535">
          <div class="help-text">587 (STARTTLS) or 465 (SSL/TLS)</div>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="smtp_user">SMTP Username</label>
          <input type="text" id="smtp_user" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>" placeholder="your-email@gmail.com">
          <div class="help-text">For Gmail: your email. For others: check provider docs</div>
        </div>

        <div class="form-group">
          <label for="smtp_pass">SMTP Password</label>
          <input type="password" id="smtp_pass" name="smtp_pass" value="<?= e($settings['smtp_pass'] ?? '') ?>" placeholder="••••••••">
          <div class="help-text">
            For Gmail: <a href="https://myaccount.google.com/security" target="_blank">create an App Password</a>, not your account password.
            Stored encrypted in database.
          </div>
        </div>
      </div>
    </div>

    <div style="margin-top: 2rem;">
      <button type="submit">Save Settings</button>
    </div>
  </form>

  <div style="margin-top: 3rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">Testing Email Configuration</h3>
    <ol style="margin-bottom: 0;">
      <li>Save settings above</li>
      <li>Create a test event and upload a test photo</li>
      <li>Complete a test order using Stripe test card <code>4242 4242 4242 4242</code></li>
      <li>Check your email inbox for the receipt</li>
      <li>If not received, check spam folder, then verify config with <code>php verify-setup.php</code></li>
    </ol>
  </div>

</main>

</body>
</html>
