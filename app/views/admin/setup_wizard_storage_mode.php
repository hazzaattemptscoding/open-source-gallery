<?php declare(strict_types=1); ?>
<h2 class="step-title">File Storage</h2>
<p class="step-description">Where to store original photo files. <strong>Skippable:</strong> defaults to local storage.</p>

<div class="help-section">
    <div class="help-toggle" onclick="toggleHelp(this)">
        <span class="help-toggle-icon">▸</span>
        <span>Which storage mode should I choose?</span>
    </div>
    <div class="help-content hidden">
        <strong>Local storage:</strong> All your photos live on this server. This is the default and works great for most galleries.
        <br><br>
        <strong>Remote NAS:</strong> You have a home server or NAS device connected to the internet and an always-on machine running a poller script. This stores original high-resolution files on your NAS to save space on the hosting server, while keeping preview images here. Only choose this if you're comfortable running a background script on a home machine.
        <br><br>
        <strong>Not sure?</strong> Start with Local storage. You can change it later in your settings.
    </div>
</div>

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
