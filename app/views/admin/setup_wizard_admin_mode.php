<?php declare(strict_types=1); ?>
<h2 class="step-title">Admin Location</h2>
<p class="step-description">Where the admin panel runs. <strong>Skippable:</strong> defaults to local (same server as gallery).</p>

<div class="help-section">
    <div class="help-toggle" onclick="toggleHelp(this)">
        <span class="help-toggle-icon">▸</span>
        <span>What does Admin Location mean?</span>
    </div>
    <div class="help-content hidden">
        <strong>Local:</strong> You manage the gallery through the admin panel hosted on the same server as your public gallery. This is the default setup.
        <br><br>
        <strong>Remote:</strong> You manage the gallery from your home machine or another location. The public gallery stays on shared hosting, but the admin panel runs on your personal computer. Advanced setup.
        <br><br>
        <strong>Not sure?</strong> Use Local. It's simpler and you can change this later.
    </div>
</div>

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

<div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
    <button type="submit" name="skip" class="button button-secondary" style="width: 100%; margin-bottom: 12px;">Skip for now (use Local)</button>
</div>

<script>
function toggleHelp(el) {
    const content = el.nextElementSibling;
    const icon = el.querySelector('.help-toggle-icon');
    content.classList.toggle('hidden');
    icon.style.transform = content.classList.contains('hidden') ? '' : 'rotate(90deg)';
}

document.querySelectorAll('.mode-option').forEach(label => {
    label.addEventListener('click', function() {
        document.querySelectorAll('.mode-option').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input[type="radio"]').checked = true;
    });
});
</script>
