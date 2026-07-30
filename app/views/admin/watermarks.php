<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Watermark Settings: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.watermark-container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
.preview { width: 100%; max-width: 400px; aspect-ratio: 4/3; background: var(--bg-alt); position: relative; margin: 2rem 0; border: 1px solid var(--border); border-radius: 0; }
.watermark-preview { position: absolute; font-weight: 500; opacity: 0.7; color: var(--text); }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.95rem; }
.form-group input, .form-group select { padding: 0.75rem; border: 1px solid var(--border); border-radius: 0; background: var(--bg); color: var(--text); width: 100%; font-family: inherit; font-size: 1rem; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus { outline: 2px solid var(--text); outline-offset: -1px; }
button[type="submit"] { padding: 0.875rem 1.5rem; background: var(--text); color: var(--bg); border: none; border-radius: 0; cursor: pointer; font-size: 1rem; font-weight: 500; transition: background 0.2s ease-out; }
button[type="submit"]:active { transform: scale(0.98); transition: transform 160ms ease-out; }
.preview p { text-align: center; padding: 5rem 1rem; color: var(--text-muted); margin: 0; }
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title">Watermark Settings</a>
</header>

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

</body>
</html>
