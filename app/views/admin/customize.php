<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site Customization: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.customize-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  min-height: 100vh;
}

.customize-form-panel {
  padding: 2rem;
  background: var(--bg);
  border-right: 1px solid var(--border);
  overflow-y: auto;
  max-height: 100vh;
}

.customize-preview-panel {
  padding: 2rem;
  background: var(--bg-alt);
  overflow-y: auto;
  max-height: 100vh;
}

.customize-section {
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border);
}

.customize-section h3 {
  font-size: 0.95rem;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 1rem;
  color: var(--text-muted);
}

.form-group {
  margin-bottom: 1rem;
}

.color-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.color-input-wrapper {
  position: relative;
}

.color-input-wrapper input[type="color"] {
  width: 100%;
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 4px;
  cursor: pointer;
}

.color-input-wrapper label {
  font-size: 0.85rem;
  font-weight: 500;
  display: block;
  margin-bottom: 0.5rem;
}

.preview-iframe {
  width: 100%;
  height: 100%;
  border: none;
  background: white;
}

.customize-buttons {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border);
}

.customize-buttons button {
  flex: 1;
  padding: 0.875rem;
  font-weight: 500;
}

.customize-buttons .secondary {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  color: var(--text);
}

.preview-button {
  background: var(--text);
  color: var(--bg);
  border: none;
  padding: 0.875rem 1.5rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: 4px;
  transition: background 200ms ease-out;
}

.preview-button:hover {
  background: #333333;
}

.success-message {
  background: #edf3ec;
  border: 1px solid #d6e5d2;
  color: #346538;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
}

.error-message {
  background: #fdebec;
  border: 1px solid #f5d0d2;
  color: #9f2f2d;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
}

@media (max-width: 1200px) {
  .customize-layout {
    grid-template-columns: 1fr;
  }

  .customize-form-panel {
    border-right: none;
    border-bottom: 1px solid var(--border);
    max-height: none;
  }

  .customize-preview-panel {
    max-height: 600px;
  }
}
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<div class="customize-layout">
  <div class="customize-form-panel">
    <h1>Site Customization</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Personalize colors, fonts, and layout. Preview changes in real-time.</p>

    <?php if ($success): ?>
    <div class="success-message">✓ Customization settings saved successfully.</div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
    <div class="error-message"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="customize-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <!-- Site Identity -->
      <div class="customize-section">
        <h3>Site Identity</h3>
        <div class="form-group">
          <label for="site_name">Site Name</label>
          <input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>" placeholder="Gallery" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div class="form-group">
          <label for="site_logo">Site Logo</label>
          <input type="file" id="site_logo" name="site_logo" accept="image/jpeg,image/png,image/webp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
          <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">JPEG, PNG, or WebP (max 5MB)</small>
          <?php if (!empty($settings['site_logo_filename'])): ?>
            <?php $logoUrl = \get_logo_url($settings['site_logo_filename']); ?>
            <?php if ($logoUrl): ?>
            <div style="margin-top: 0.75rem; padding: 0.75rem; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 4px;">
              <img src="<?= e($logoUrl) ?>" alt="Current logo" style="max-height: 60px; max-width: 100%;"><br>
              <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">✓ Logo on file</small>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Colors -->
      <div class="customize-section">
        <h3>Color Palette</h3>
        <div class="color-group">
          <div class="color-input-wrapper">
            <label for="text">Primary Text</label>
            <input type="color" id="text" name="text" value="<?= e($settings['text'] ?? '#111111') ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Body copy and headings</small>
          </div>
          <div class="color-input-wrapper">
            <label for="text_muted">Secondary Text</label>
            <input type="color" id="text_muted" name="text_muted" value="<?= e($settings['text_muted'] ?? '#787774') ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Labels, hints, metadata</small>
          </div>
          <div class="color-input-wrapper">
            <label for="bg">Page Background</label>
            <input type="color" id="bg" name="bg" value="<?= e($settings['bg'] ?? '#ffffff') ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Main page background</small>
          </div>
          <div class="color-input-wrapper">
            <label for="bg_alt">Section Background</label>
            <input type="color" id="bg_alt" name="bg_alt" value="<?= e($settings['bg_alt'] ?? '#f9f9f8') ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Panels, cards, sections</small>
          </div>
          <div class="color-input-wrapper">
            <label for="border">Borders & Dividers</label>
            <input type="color" id="border" name="border" value="<?= e($settings['border'] ?? '#eaeaea') ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Lines, borders, separators</small>
          </div>
        </div>
      </div>

      <!-- Fonts -->
      <div class="customize-section">
        <h3>Typography</h3>
        <div class="form-group">
          <label for="body_font">Body Font</label>
          <select id="body_font" name="body_font" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
            <?php foreach ($availableFonts['body'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['body_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="heading_font">Heading Font</label>
          <select id="heading_font" name="heading_font" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
            <?php foreach ($availableFonts['display'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['heading_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="mono_font">Monospace Font</label>
          <select id="mono_font" name="mono_font" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
            <?php foreach ($availableFonts['mono'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['mono_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="heading_letter_spacing">Heading Letter Spacing</label>
          <input type="text" id="heading_letter_spacing" name="heading_letter_spacing" value="<?= e($settings['heading_letter_spacing'] ?? '-0.02em') ?>" placeholder="-0.02em" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
        </div>
      </div>

      <!-- Layout -->
      <div class="customize-section">
        <h3>Layout</h3>
        <div class="form-group">
          <label for="grid_columns">Photo Grid Columns</label>
          <select id="grid_columns" name="grid_columns" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
            <option value="2" <?= ($settings['grid_columns'] ?? 3) == 2 ? 'selected' : '' ?>>2 columns</option>
            <option value="3" <?= ($settings['grid_columns'] ?? 3) == 3 ? 'selected' : '' ?>>3 columns</option>
            <option value="4" <?= ($settings['grid_columns'] ?? 3) == 4 ? 'selected' : '' ?>>4 columns</option>
            <option value="5" <?= ($settings['grid_columns'] ?? 3) == 5 ? 'selected' : '' ?>>5 columns</option>
          </select>
        </div>
        <div class="form-group">
          <label for="max_content_width">Max Content Width</label>
          <input type="text" id="max_content_width" name="max_content_width" value="<?= e($settings['max_content_width'] ?? '1200px') ?>" placeholder="1200px" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div class="form-group">
          <label for="spacing_multiplier">Spacing Multiplier</label>
          <input type="number" id="spacing_multiplier" name="spacing_multiplier" value="<?= e($settings['spacing_multiplier'] ?? '1') ?>" min="0.5" max="2" step="0.1" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px;">
          <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">0.5 = compact, 1.0 = default, 2.0 = spacious</small>
        </div>
      </div>

      <div class="customize-buttons">
        <button type="submit" style="background: var(--text); color: var(--bg); border: none; border-radius: 4px; cursor: pointer; flex: 2;">Save Changes</button>
        <button type="button" class="preview-button" onclick="openPublicPreview();" style="flex: 1;">Preview</button>
      </div>
    </form>

    <!-- Reset form -->
    <form method="post" style="margin-top: 1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="reset">
      <button type="submit" onclick="return confirm('Reset all customizations to defaults? This will delete any uploaded logo.');" style="width: 100%; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); padding: 0.875rem; font-weight: 500; cursor: pointer; border-radius: 4px;">Reset to Defaults</button>
    </form>

    <!-- Delete logo form (only shown if logo exists) -->
    <?php if (!empty($settings['site_logo_filename'])): ?>
    <form method="post" style="margin-top: 0.5rem;">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="delete_logo">
      <button type="submit" onclick="return confirm('Delete the uploaded logo?');" style="width: 100%; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); padding: 0.875rem; font-weight: 500; cursor: pointer; border-radius: 4px;">Delete Logo</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="customize-preview-panel">
    <h2>Live Preview</h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">Customize form preview below:</p>
    <div style="border: 1px solid var(--border); padding: 1.5rem; border-radius: 4px; background: white;">
      <style><?= $cssOverrides ?></style>
      <h3 style="margin-top: 0;">Preview: Site Layout</h3>
      <p>Colors, fonts, and spacing are previewed live as you change them.</p>
      <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg-alt); border: 1px solid var(--border);">
        <strong>Sample Text</strong> in body font (<?= e($settings['body_font']) ?>)
      </div>
      <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg-alt); border: 1px solid var(--border);">
        <h4>Sample Heading</h4>
        <small>in heading font (<?= e($settings['heading_font']) ?>)</small>
      </div>
      <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg-alt); border: 1px solid var(--border); font-family: monospace; font-size: 0.85rem;">
        monospace_code_preview
      </div>
      <button style="padding: 0.75rem 1.5rem; background: var(--text); color: var(--bg); border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Sample Button</button>
    </div>
  </div>
</div>

<script>
function openPublicPreview() {
  window.open('/admin/customize?preview=public', 'customize_preview', 'width=1200,height=800');
}
</script>
</body>
</html>
