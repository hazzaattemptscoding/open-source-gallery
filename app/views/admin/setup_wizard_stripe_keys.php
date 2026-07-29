<?php declare(strict_types=1); ?>
<h2 class="step-title">Stripe API Keys</h2>
<p class="step-description">Required to accept payments. <strong>Skippable:</strong> the site works without this, but customers can't checkout.</p>

<div class="form-group">
    <label for="publishable_key">Publishable Key</label>
    <input type="text" id="publishable_key" name="publishable_key"
           value="<?= htmlspecialchars($_POST['publishable_key'] ?? '') ?>"
           placeholder="pk_live_...">
    <div class="help-text">Starts with <code>pk_</code></div>
</div>

<div class="form-group">
    <label for="secret_key">Secret Key</label>
    <input type="password" id="secret_key" name="secret_key"
           value="<?= htmlspecialchars($_POST['secret_key'] ?? '') ?>"
           placeholder="sk_live_...">
    <div class="help-text">Starts with <code>sk_</code> — keep this secret!</div>
</div>

<div style="background: #f0f8f4; border: 1px solid #d6e5d2; border-radius: 6px; padding: 16px; margin: 24px 0; font-size: 13px; color: #346538;">
    <strong>Find your keys:</strong> Log in to <a href="<?= htmlspecialchars($dashboard_url ?? 'https://dashboard.stripe.com') ?>" target="_blank" style="color: #346538; text-decoration: underline;">Stripe Dashboard</a> → Developers → API Keys
</div>

<div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
    <button type="submit" name="skip" class="button button-secondary" style="width: 100%; margin-bottom: 12px;">Skip for now</button>
</div>
