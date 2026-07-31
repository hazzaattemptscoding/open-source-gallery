<?php
$pageTitle = 'Watermarks';
$currentPage = 'watermarks';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="watermark-container">
  <h1>Watermark Customization</h1>
  <p>Configure how watermarks appear on your gallery photos (800px and larger).</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $error): ?>
        <p><?= e($error) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?php
        $messages = [
          'settings_updated' => 'Watermark settings updated.',
          'preset_saved' => 'Preset saved successfully.',
          'preset_loaded' => 'Preset loaded successfully.',
          'preset_deleted' => 'Preset deleted successfully.',
        ];
        echo e($messages[$success] ?? 'Action completed.');
      ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <div class="form-group">
      <label>Position:</label>
      <select name="position">
        <option value="bottom_right" <?= $settings && $settings['position'] === 'bottom_right' ? 'selected' : '' ?>>Bottom Right</option>
        <option value="bottom_left" <?= $settings && $settings['position'] === 'bottom_left' ? 'selected' : '' ?>>Bottom Left</option>
        <option value="bottom_center" <?= $settings && $settings['position'] === 'bottom_center' ? 'selected' : '' ?>>Bottom Center</option>
        <option value="center" <?= $settings && $settings['position'] === 'center' ? 'selected' : '' ?>>Center</option>
      </select>
    </div>

    <div class="form-group">
      <label>Opacity (0.0 - 1.0):</label>
      <input type="number" name="opacity" value="<?= $settings ? $settings['opacity'] : 0.8 ?>" step="0.1" min="0" max="1">
    </div>

    <div class="form-group">
      <label>Custom Text (optional):</label>
      <input type="text" name="text" placeholder="Your gallery name" value="<?= $settings ? e($settings['text']) : '' ?>">
    </div>

    <div class="form-group">
      <label>Apply to image sizes:</label>
      <input type="text" name="apply_to_sizes" placeholder="sm,md,lg,xl" value="<?= $settings ? e($settings['apply_to_sizes']) : 'sm,md,lg' ?>">
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="enabled" <?= $settings && $settings['enabled'] ? 'checked' : '' ?>> Enable watermarks</label>
    </div>

    <button type="submit">Update Settings</button>
  </form>

  <div class="preset-section">
    <h2>Save Current Settings as Preset</h2>
    <form method="post" class="preset-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="save_preset">
      <div class="form-group">
        <label for="preset_name">Preset Name:</label>
        <input type="text" id="preset_name" name="preset_name" placeholder="e.g., Subtle, Prominent, etc." required>
      </div>
      <button type="submit">Save as Preset</button>
    </form>
  </div>

  <?php if (!empty($presets)): ?>
    <div class="preset-section">
      <h2>Saved Presets</h2>
      <div class="preset-list">
        <?php foreach ($presets as $preset): ?>
          <div class="preset-item">
            <div class="preset-info">
              <strong><?= e($preset['name']) ?></strong>
              <span class="preset-meta">
                <?= e($preset['position']) ?> •
                <?= e((float)$preset['opacity'] . ' opacity') ?> •
                <?= $preset['enabled'] ? 'Enabled' : 'Disabled' ?>
              </span>
            </div>
            <div class="preset-actions">
              <form method="post" class="preset-action-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="load_preset">
                <input type="hidden" name="preset_name" value="<?= e($preset['name']) ?>">
                <button type="submit" class="btn-secondary">Load</button>
              </form>
              <form method="post" class="preset-action-form" onsubmit="return confirm('Delete this preset?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_preset">
                <input type="hidden" name="preset_name" value="<?= e($preset['name']) ?>">
                <button type="submit" class="btn-danger">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <h2>Preview</h2>
  <div class="preview">
    <div class="watermark-preview" style="bottom: 1rem; right: 1rem;">Sample Photo © 2024</div>
    <p style="text-align: center; padding: 5rem 1rem; color: #999;">Photo will appear here</p>
  </div>
</div>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
