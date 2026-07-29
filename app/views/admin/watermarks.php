<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Watermark Settings — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.watermark-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
.preview { width: 400px; height: 300px; background: #f0f0f0; position: relative; margin: 2rem 0; border: 1px solid #ddd; }
.watermark-preview { position: absolute; font-weight: bold; opacity: 0.8; color: rgba(0,0,0,0.6); }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
</style>
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title">Watermark Settings</a>
</header>

<div class="watermark-container">
  <h1>Watermark Customization</h1>
  <p>Configure how watermarks appear on your gallery photos (800px and larger).</p>

  <form method="post">
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

</body>
</html>
