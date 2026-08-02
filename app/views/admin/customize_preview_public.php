<?php
$pageTitle = 'Public Preview';
$includeAdminStyles = true;
$extraStyles = '<style>' . "\n" . $cssOverrides . "\n" . "body { margin-top: 40px; }\n" . "</style>\n";
require_once __DIR__ . '/partials/minimal_header.php';
?>
<div class="preview-banner">
  📐 PREVIEW MODE — Customizations applied. <a href="/admin/customize">Back to editor</a>
</div>

<header class="site-header">
  <a href="/" class="site-title"><?= e($settings['site_name'] ?? $siteName) ?></a>
</header>

<main class="event-list-page preview-main">
  <h1>Public Site Preview</h1>
  <p class="preview-lede">This preview shows how your customizations look on the public gallery.</p>

  <!-- Event Cards -->
  <h2 class="preview-section-title">Sample Events</h2>
  <div class="preview-event-grid">
    <?php if (!empty($sampleEvents)): ?>
      <?php foreach ($sampleEvents as $event): ?>
      <div class="preview-event-card">
        <div class="preview-event-thumb">No Photo</div>
        <div class="preview-event-body">
          <h3><?= e($event['title']) ?></h3>
          <p><?= date('d M Y', strtotime($event['event_date'])) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="preview-event-empty">
        <p>No events yet. Create some in the admin panel to see them here.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Sample UI Elements -->
  <div class="preview-components">
    <h2>UI Components Preview</h2>

    <div class="preview-component-block">
      <h3>Typography Samples</h3>
      <h4>This is an h4 heading</h4>
      <p>This is body text in the <?= e($settings['body_font']) ?> font. Colors are applied from your customization settings. This text demonstrates how your chosen typography looks in context.</p>
      <code>Code text in <?= e($settings['mono_font']) ?></code>
    </div>

    <div class="preview-component-block">
      <h3>Button Styles</h3>
      <button class="preview-button-primary">Primary Button</button>
      <button class="preview-button-secondary">Secondary Button</button>
    </div>

    <div class="preview-component-block">
      <h3>Color Swatches</h3>
      <div class="preview-swatch-grid">
        <div class="preview-swatch preview-swatch-text">
          <div>Text Color</div>
          <code><?= e($settings['text_color']) ?></code>
        </div>
        <div class="preview-swatch preview-swatch-bg">
          <div>Background</div>
          <code><?= e($settings['bg_color']) ?></code>
        </div>
        <div class="preview-swatch preview-swatch-bg-alt">
          <div>Alt Background</div>
          <code><?= e($settings['bg_alt_color']) ?></code>
        </div>
        <div class="preview-swatch preview-swatch-border">
          <div>Border Color</div>
          <code><?= e($settings['border_color']) ?></code>
        </div>
      </div>
    </div>
  </div>

  <div class="preview-cta">
    <h2>Your Gallery</h2>
    <p>This is how your site will appear with the current customization settings applied globally.</p>
    <p><a href="/admin/customize">← Return to customization editor</a></p>
  </div>
</main>
<?php require_once __DIR__ . '/partials/minimal_footer.php'; ?>
