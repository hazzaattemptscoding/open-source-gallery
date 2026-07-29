<?php declare(strict_types=1); ?>
<h2 class="step-title">Email Setup</h2>
<p class="step-description">Customers receive order confirmations and download links via email. <strong>Skippable:</strong> the site works without this, but customers won't get emails.</p>

<div class="form-group">
    <label>Email Provider</label>
    <div class="provider-select">
        <button type="button" class="provider-option active" onclick="selectProvider('gmail')">Gmail</button>
        <button type="button" class="provider-option" onclick="selectProvider('outlook')">Outlook</button>
        <button type="button" class="provider-option" onclick="selectProvider('ionos')">IONOS</button>
        <button type="button" class="provider-option" onclick="selectProvider('custom')">Custom</button>
    </div>
    <input type="hidden" id="provider" name="provider" value="gmail">
</div>

<div class="form-group">
    <label for="from_email">From Email</label>
    <input type="email" id="from_email" name="from_email" required
           value="<?= htmlspecialchars($_POST['from_email'] ?? '') ?>"
           placeholder="noreply@gallery.example.com">
    <div class="help-text">Appears as sender in customer emails (should be your domain)</div>
</div>

<div class="form-group">
    <label for="from_name">From Name</label>
    <input type="text" id="from_name" name="from_name"
           value="<?= htmlspecialchars($_POST['from_name'] ?? '') ?>"
           placeholder="Your Gallery Name">
</div>

<div class="form-group">
    <label for="smtp_host">SMTP Host</label>
    <input type="text" id="smtp_host" name="smtp_host"
           value="<?= htmlspecialchars($_POST['smtp_host'] ?? 'smtp.gmail.com') ?>"
           placeholder="smtp.gmail.com">
</div>

<div class="form-group">
    <label for="smtp_port">SMTP Port</label>
    <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
           value="<?= htmlspecialchars($_POST['smtp_port'] ?? '587') ?>">
    <div class="help-text">587 (STARTTLS) or 465 (SSL)</div>
</div>

<div class="form-group">
    <label for="smtp_user">SMTP Username</label>
    <input type="text" id="smtp_user" name="smtp_user"
           value="<?= htmlspecialchars($_POST['smtp_user'] ?? '') ?>"
           placeholder="your@gmail.com (or IONOS username)">
</div>

<div class="form-group">
    <label for="smtp_pass">SMTP Password</label>
    <input type="password" id="smtp_pass" name="smtp_pass"
           placeholder="Password or app-specific password">
    <div class="help-text">For Gmail: use an App Password, not your account password</div>
</div>

<div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
    <button type="submit" name="skip" class="button button-secondary" style="width: 100%; margin-bottom: 12px;">Skip for now</button>
</div>

<script>
function selectProvider(provider) {
    document.getElementById('provider').value = provider;
    document.querySelectorAll('.provider-option').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const providers = {
        gmail: { host: 'smtp.gmail.com', port: 587 },
        outlook: { host: 'smtp-mail.outlook.com', port: 587 },
        ionos: { host: 'smtp.ionos.com', port: 587 },
        custom: { host: '', port: 587 }
    };

    const cfg = providers[provider];
    if (cfg.host) {
        document.getElementById('smtp_host').value = cfg.host;
        document.getElementById('smtp_port').value = cfg.port;
    } else {
        document.getElementById('smtp_host').value = '';
        document.getElementById('smtp_port').value = 587;
    }
}
</script>
