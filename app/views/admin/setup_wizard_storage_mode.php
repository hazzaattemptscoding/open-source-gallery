<?php declare(strict_types=1); ?>
<h2 class="step-title">File Storage</h2>
<p class="step-description">Where to store original photo files. <strong>Skippable:</strong> defaults to local storage.</p>

<div class="help-section">
    <div class="help-toggle" data-help-toggle>
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

<div class="wizard-skip-section">
    <button type="submit" name="skip" class="button button-secondary wizard-skip-button">Skip for now (use Local)</button>
</div>
