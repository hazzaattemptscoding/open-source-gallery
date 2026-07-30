<?php
$pageTitle = 'Watermarks';
$currentPage = 'watermarks';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="watermark-container">
  <h1>Watermark Customization</h1>
  <p>Configure how watermarks appear on your gallery photos (800px and larger).</p>

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

  <h2>Preview</h2>
  <div class="preview">
    <div class="watermark-preview" style="bottom: 1rem; right: 1rem;">Sample Photo © 2024</div>
    <p style="text-align: center; padding: 5rem 1rem; color: #999;">Photo will appear here</p>
  </div>
</div>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
