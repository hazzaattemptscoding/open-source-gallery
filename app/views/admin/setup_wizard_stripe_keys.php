<?php declare(strict_types=1); ?>
<h2 class="step-title">Stripe Payments</h2>
<p class="step-description">Accept customer payments. Optional but required for checkout functionality.</p>

<div class="help-section">
    <div class="help-toggle" data-help-toggle>
        <span class="help-toggle-icon">▸</span>
        <span>Where do I find my Stripe API keys?</span>
    </div>
    <div class="help-content hidden">
        <ol style="margin: 8px 0 8px 20px;">
            <li>Log in to <a href="https://dashboard.stripe.com" target="_blank" style="color: #111; text-decoration: underline;">Stripe Dashboard →</a></li>
            <li>Click <strong>Developers</strong> (top left menu)</li>
            <li>Click <strong>API keys</strong></li>
            <li>Copy your <code>Publishable key</code> (starts with <code>pk_</code>)</li>
            <li>Copy your <code>Secret key</code> (starts with <code>sk_</code>) — keep this secure!</li>
        </ol>
        <strong>Don't have a Stripe account?</strong> <a href="https://dashboard.stripe.com/register" target="_blank" style="color: #111; text-decoration: underline;">Create one free →</a> Takes 5 minutes.
    </div>
</div>

<div class="form-group">
    <label for="publishable_key">Publishable Key</label>
    <input type="text" id="publishable_key" name="publishable_key"
           value="<?= htmlspecialchars($_POST['publishable_key'] ?? '') ?>"
           placeholder="pk_live_abc123...">
    <div class="help-text">Starts with <code>pk_</code> — safe to share</div>
</div>

<div class="form-group">
    <label for="secret_key">Secret Key</label>
    <input type="password" id="secret_key" name="secret_key"
           value="<?= htmlspecialchars($_POST['secret_key'] ?? '') ?>"
           placeholder="sk_live_def456...">
    <div class="help-text">Starts with <code>sk_</code> — keep this secret! Paste here, not shared anywhere</div>
</div>

<div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
    <button type="submit" name="skip" class="button button-secondary" style="width: 100%; margin-bottom: 12px;">Skip for now</button>
</div>
