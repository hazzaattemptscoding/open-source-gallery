<?php declare(strict_types=1); ?>
<h2 class="step-title">Email Delivery</h2>
<p class="step-description">Customers receive order confirmations and download links. Optional but recommended for customer experience.</p>

<div class="help-section">
    <div class="help-toggle" data-help-toggle>
        <span class="help-toggle-icon">▸</span>
        <span>How to find your email provider's SMTP settings</span>
    </div>
    <div class="help-content hidden">
        <strong>Gmail:</strong> Use your full Gmail address. <a href="https://support.google.com/accounts/answer/185833" target="_blank" style="color: #111; text-decoration: underline;">Create an App Password</a> (not your regular password).
        <br><br>
        <strong>Outlook:</strong> Use your full Outlook/Hotmail address and regular password.
        <br><br>
        <strong>IONOS:</strong> Check your IONOS control panel → Email → Settings for SMTP details.
        <br><br>
        <strong>Other provider?</strong> Search "[your-provider] SMTP settings" or contact their support. Most provide a help article.
    </div>
</div>

<div class="form-group">
    <label>Which email provider do you use?</label>
    <div class="provider-select">
        <button type="button" class="provider-option active" data-provider="gmail">Gmail</button>
        <button type="button" class="provider-option" data-provider="outlook">Outlook</button>
        <button type="button" class="provider-option" data-provider="ionos">IONOS</button>
        <button type="button" class="provider-option" data-provider="custom">Other</button>
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
