<?php declare(strict_types=1); ?>
<h2 class="step-title">Business Details</h2>
<p class="step-description">These appear in emails, invoices, and throughout the site.</p>

<div class="form-group">
    <label for="name">Gallery Name</label>
    <input type="text" id="name" name="name" required autofocus
           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
           placeholder="e.g., PowerMedia Racing">
    <div class="help-text">Used in page titles, emails, and login screen</div>
</div>

<div class="form-group">
    <label for="email">Contact Email</label>
    <input type="email" id="email" name="email" required
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
           placeholder="contact@gallery.example.com">
    <div class="help-text">For customer support and admin alerts</div>
</div>

<div class="form-group">
    <label for="currency">Currency</label>
    <select id="currency" name="currency">
        <option value="GBP" <?= ($_POST['currency'] ?? 'GBP') === 'GBP' ? 'selected' : '' ?>>GBP (British Pound)</option>
        <option value="USD" <?= ($_POST['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
        <option value="EUR" <?= ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
        <option value="AUD" <?= ($_POST['currency'] ?? '') === 'AUD' ? 'selected' : '' ?>>AUD (Australian Dollar)</option>
        <option value="CAD" <?= ($_POST['currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>CAD (Canadian Dollar)</option>
    </select>
    <div class="help-text">Used for pricing and Stripe integration</div>
</div>
