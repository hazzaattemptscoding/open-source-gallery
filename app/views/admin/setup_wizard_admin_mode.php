<?php declare(strict_types=1); ?>
<h2 class="step-title">Admin Location</h2>
<p class="step-description">Where the admin panel runs. <strong>Skippable:</strong> defaults to local (same server as gallery).</p>

<div class="form-group">
    <label>Admin Mode</label>
    <div class="mode-options">
        <?php foreach (($data['modes'] ?? []) as $key => $mode): ?>
            <label class="mode-option <?= $_POST['admin_mode'] === $key ? 'active' : '' ?>">
                <input type="radio" name="admin_mode" value="<?= htmlspecialchars($key) ?>"
                       <?= $_POST['admin_mode'] === $key ? 'checked' : '' ?>>
                <div class="mode-label"><?= htmlspecialchars($mode['label']) ?></div>
                <div class="mode-description"><?= htmlspecialchars($mode['description']) ?></div>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<div style="background: #f5f5f5; border: 1px solid #d0d0d0; border-radius: 6px; padding: 16px; margin: 24px 0; font-size: 13px; color: #666;">
    <strong>Remote admin</strong> is for users who want to manage the gallery from their home machine while hosting the public gallery on shared hosting. See docs for setup details.
</div>

<div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
    <button type="submit" name="skip" class="button button-secondary" style="width: 100%; margin-bottom: 12px;">Skip for now (use Local)</button>
</div>

<script>
document.querySelectorAll('.mode-option').forEach(label => {
    label.addEventListener('click', function() {
        document.querySelectorAll('.mode-option').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input[type="radio"]').checked = true;
    });
});
</script>
