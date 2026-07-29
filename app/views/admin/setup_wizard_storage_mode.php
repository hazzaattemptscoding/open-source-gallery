<?php declare(strict_types=1); ?>
<h2 class="step-title">File Storage</h2>
<p class="step-description">Where to store original photo files. <strong>Skippable:</strong> defaults to local storage.</p>

<div class="form-group">
    <label>Storage Mode</label>
    <div class="mode-options">
        <?php foreach (($data['modes'] ?? []) as $key => $mode): ?>
            <label class="mode-option <?= $_POST['storage_mode'] === $key ? 'active' : '' ?>">
                <input type="radio" name="storage_mode" value="<?= htmlspecialchars($key) ?>"
                       <?= $_POST['storage_mode'] === $key ? 'checked' : '' ?>>
                <div class="mode-label"><?= htmlspecialchars($mode['label']) ?></div>
                <div class="mode-description"><?= htmlspecialchars($mode['description']) ?></div>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<div style="background: #f5f5f5; border: 1px solid #d0d0d0; border-radius: 6px; padding: 16px; margin: 24px 0; font-size: 13px; color: #666;">
    <strong>Remote NAS mode</strong> is for advanced users with a home NAS and always-on machine. It stores originals on your NAS and only previews on this server. See <a href="/docs/NAS-FULFILLMENT.md" style="color: #111; text-decoration: underline;">setup guide</a> for details.
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
