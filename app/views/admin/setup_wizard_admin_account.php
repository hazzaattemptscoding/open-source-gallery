<?php declare(strict_types=1); ?>
<h2 class="step-title">Create Admin Account</h2>
<p class="step-description">This is your login to the admin panel. Use a strong, unique password.</p>

<div class="form-group">
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" required autofocus
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
           placeholder="admin@example.com">
    <div class="help-text">You'll use this to log in</div>
</div>

<div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required
           placeholder="Min. 12 characters">
    <div class="help-text">Must be at least 12 characters</div>
</div>

<div class="form-group">
    <label for="password_confirm">Confirm Password</label>
    <input type="password" id="password_confirm" name="password_confirm" required
           placeholder="Repeat your password">
</div>
