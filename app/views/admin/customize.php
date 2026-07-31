<?php
$pageTitle = 'Customization';
$currentPage = 'customize';
require_once __DIR__ . '/partials/layout_header.php';
?>

<div class="customize-layout">
  <div class="customize-form-panel">
    <h1>Site Customization</h1>
    <p class="customize-intro">Personalize colors, fonts, and layout. Preview changes in real-time.</p>

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
          <input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>" placeholder="Gallery" class="customize-control">
        </div>
        <div class="form-group">
          <label for="site_logo">Site Logo</label>
          <input type="file" id="site_logo" name="site_logo" accept="image/jpeg,image/png,image/webp" class="customize-control">
          <small class="customize-hint customize-hint-loose">JPEG, PNG, or WebP (max 5MB)</small>
          <?php if (!empty($settings['site_logo_filename'])): ?>
            <?php $logoUrl = \get_logo_url($settings['site_logo_filename']); ?>
            <?php if ($logoUrl): ?>
            <div class="customize-logo-current">
              <img src="<?= e($logoUrl) ?>" alt="Current logo" class="customize-logo-img"><br>
              <small class="customize-hint customize-hint-loose">✓ Logo on file</small>
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
            <small class="customize-hint">Body copy and headings</small>
          </div>
          <div class="color-input-wrapper">
            <label for="text_muted">Secondary Text</label>
            <input type="color" id="text_muted" name="text_muted" value="<?= e($settings['text_muted'] ?? '#787774') ?>">
            <small class="customize-hint">Labels, hints, metadata</small>
          </div>
          <div class="color-input-wrapper">
            <label for="bg">Page Background</label>
            <input type="color" id="bg" name="bg" value="<?= e($settings['bg'] ?? '#ffffff') ?>">
            <small class="customize-hint">Main page background</small>
          </div>
          <div class="color-input-wrapper">
            <label for="bg_alt">Section Background</label>
            <input type="color" id="bg_alt" name="bg_alt" value="<?= e($settings['bg_alt'] ?? '#f9f9f8') ?>">
            <small class="customize-hint">Panels, cards, sections</small>
          </div>
          <div class="color-input-wrapper">
            <label for="border">Borders & Dividers</label>
            <input type="color" id="border" name="border" value="<?= e($settings['border'] ?? '#eaeaea') ?>">
            <small class="customize-hint">Lines, borders, separators</small>
          </div>
          <div class="color-input-wrapper">
            <label for="accent">Accent</label>
            <input type="color" id="accent" name="accent" value="<?= e($settings['accent'] ?? '#933bac') ?>">
            <small class="customize-hint">Add to cart, checkout, focus rings, selection</small>
          </div>
          <div class="color-input-wrapper">
            <label for="accent_hover">Accent (hover)</label>
            <input type="color" id="accent_hover" name="accent_hover" value="<?= e($settings['accent_hover'] ?? '#802199') ?>">
            <small class="customize-hint">Accent buttons on hover/press</small>
          </div>
        </div>
      </div>

      <!-- Fonts -->
      <div class="customize-section">
        <h3>Typography</h3>
        <div class="form-group">
          <label for="body_font">Body Font</label>
          <select id="body_font" name="body_font" class="customize-control">
            <?php foreach ($availableFonts['body'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['body_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="heading_font">Heading Font</label>
          <select id="heading_font" name="heading_font" class="customize-control">
            <?php foreach ($availableFonts['display'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['heading_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="mono_font">Monospace Font</label>
          <select id="mono_font" name="mono_font" class="customize-control">
            <?php foreach ($availableFonts['mono'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $settings['mono_font'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="heading_letter_spacing">Heading Letter Spacing</label>
          <input type="text" id="heading_letter_spacing" name="heading_letter_spacing" value="<?= e($settings['heading_letter_spacing'] ?? '-0.02em') ?>" placeholder="-0.02em" class="customize-control">
        </div>
      </div>

      <!-- Layout -->
      <div class="customize-section">
        <h3>Layout</h3>
        <div class="form-group">
          <label for="grid_columns">Photo Grid Columns</label>
          <select id="grid_columns" name="grid_columns" class="customize-control">
            <option value="2" <?= ($settings['grid_columns'] ?? 3) == 2 ? 'selected' : '' ?>>2 columns</option>
            <option value="3" <?= ($settings['grid_columns'] ?? 3) == 3 ? 'selected' : '' ?>>3 columns</option>
            <option value="4" <?= ($settings['grid_columns'] ?? 3) == 4 ? 'selected' : '' ?>>4 columns</option>
            <option value="5" <?= ($settings['grid_columns'] ?? 3) == 5 ? 'selected' : '' ?>>5 columns</option>
          </select>
        </div>
        <div class="form-group">
          <label for="max_content_width">Max Content Width</label>
          <input type="text" id="max_content_width" name="max_content_width" value="<?= e($settings['max_content_width'] ?? '1200px') ?>" placeholder="1200px" class="customize-control">
        </div>
        <div class="form-group">
          <label for="spacing_multiplier">Spacing Multiplier</label>
          <input type="number" id="spacing_multiplier" name="spacing_multiplier" value="<?= e($settings['spacing_multiplier'] ?? '1') ?>" min="0.5" max="2" step="0.1" class="customize-control">
          <small class="customize-hint customize-hint-loose">0.5 = compact, 1.0 = default, 2.0 = spacious</small>
        </div>
      </div>

      <!--
        Contrast advisor. Populated by admin-customize.js from the colour
        inputs above; stays hidden until it has something to say.
      -->
      <div class="contrast-advisor" id="contrastAdvisor" hidden>
        <p class="contrast-advisor-title">Readability check</p>
        <ul class="contrast-advisor-list" id="contrastAdvisorList"></ul>
      </div>

      <div class="customize-buttons">
        <button type="submit" class="customize-btn-primary">Save Changes</button>
        <button type="button" class="preview-button customize-btn-preview" data-popup="/admin/customize?preview=public" data-popup-name="customize_preview">Preview</button>
      </div>
    </form>

    <!-- Reset form -->
    <form method="post" class="customize-form-stacked">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="reset">
      <button type="submit" data-confirm="Reset all customizations to defaults? This will delete any uploaded logo." class="customize-btn-secondary">Reset to Defaults</button>
    </form>

    <!-- Delete logo form (only shown if logo exists) -->
    <?php if (!empty($settings['site_logo_filename'])): ?>
    <form method="post" class="customize-form-stacked-tight">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="delete_logo">
      <button type="submit" data-confirm="Delete the uploaded logo?" class="customize-btn-secondary">Delete Logo</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="customize-preview-panel">
    <h2>Live Preview</h2>
    <p class="customize-preview-note">Customize form preview below:</p>
    <div class="customize-preview-surface">
      <style><?= $cssOverrides ?></style>
      <h3 class="customize-sample-heading">Preview: Site Layout</h3>
      <p>Colors, fonts, and spacing are previewed live as you change them.</p>
      <div class="customize-sample">
        <strong>Sample Text</strong> in body font (<?= e($settings['body_font']) ?>)
      </div>
      <div class="customize-sample">
        <h4>Sample Heading</h4>
        <small>in heading font (<?= e($settings['heading_font']) ?>)</small>
      </div>
      <div class="customize-sample customize-sample-mono">
        monospace_code_preview
      </div>
      <button class="customize-sample-button">Sample Button</button>
    </div>
  </div>
</div>

<script src="/assets/js/admin-common.js" defer></script>
<script src="/assets/js/admin-customize.js" defer></script>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
