<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Public Preview: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
<?= $cssOverrides ?>

.preview-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: #333333;
  color: white;
  padding: 0.75rem;
  text-align: center;
  font-size: 0.9rem;
  z-index: 10000;
}

body {
  margin-top: 40px;
}

.preview-banner a {
  color: #ffff00;
  text-decoration: underline;
}
</style>
</head>
<body>
<div class="preview-banner">
  📐 PREVIEW MODE — Customizations applied. <a href="/admin/customize">Back to editor</a>
</div>

<header class="site-header">
  <a href="/" class="site-title"><?= e($settings['site_name'] ?? $siteName) ?></a>
</header>

<main class="event-list-page" style="padding: 2rem; max-width: 100%;">
  <h1>Public Site Preview</h1>
  <p style="color: var(--text-muted); margin-bottom: 3rem;">This preview shows how your customizations look on the public gallery.</p>

  <!-- Event Cards -->
  <h2 style="margin-bottom: 2rem;">Sample Events</h2>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <?php if (!empty($sampleEvents)): ?>
      <?php foreach ($sampleEvents as $event): ?>
      <div style="background: var(--bg); border: 1px solid var(--border); overflow: hidden;">
        <div style="aspect-ratio: 1; background: linear-gradient(135deg, var(--bg-alt) 0%, var(--border) 100%); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
          No Photo
        </div>
        <div style="padding: 1rem;">
          <h3 style="margin: 0 0 0.5rem; font-size: 1.1rem;"><?= e($event['title']) ?></h3>
          <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);"><?= date('d M Y', strtotime($event['event_date'])) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="grid-column: 1 / -1; background: var(--bg-alt); border: 1px solid var(--border); padding: 2rem; text-align: center; border-radius: 4px;">
        <p style="color: var(--text-muted);">No events yet. Create some in the admin panel to see them here.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Sample UI Elements -->
  <div style="background: var(--bg-alt); border: 1px solid var(--border); padding: 2rem; border-radius: 8px; margin-bottom: 2rem;">
    <h2>UI Components Preview</h2>

    <div style="margin: 2rem 0; padding: 1rem; background: white; border: 1px solid var(--border);">
      <h3 style="margin-top: 0;">Typography Samples</h3>
      <h4>This is an h4 heading</h4>
      <p>This is body text in the <?= e($settings['body_font']) ?> font. Colors are applied from your customization settings. This text demonstrates how your chosen typography looks in context.</p>
      <code style="background: var(--bg-alt); padding: 0.25rem 0.5rem; border-radius: 3px;">Code text in <?= e($settings['mono_font']) ?></code>
    </div>

    <div style="margin: 2rem 0; padding: 1rem; background: white; border: 1px solid var(--border);">
      <h3 style="margin-top: 0;">Button Styles</h3>
      <button style="background: var(--text); color: var(--bg); padding: 0.875rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; margin-right: 0.5rem;">Primary Button</button>
      <button style="background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); padding: 0.875rem 1.5rem; border-radius: 4px; cursor: pointer; font-weight: 500;">Secondary Button</button>
    </div>

    <div style="margin: 2rem 0; padding: 1rem; background: white; border: 1px solid var(--border);">
      <h3 style="margin-top: 0;">Color Swatches</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <div style="padding: 1rem; background: var(--text); color: white; border-radius: 4px; text-align: center; font-size: 0.85rem;">
          <div>Text Color</div>
          <code style="font-size: 0.75rem;"><?= e($settings['text_color']) ?></code>
        </div>
        <div style="padding: 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; text-align: center; font-size: 0.85rem;">
          <div>Background</div>
          <code style="font-size: 0.75rem;"><?= e($settings['bg_color']) ?></code>
        </div>
        <div style="padding: 1rem; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 4px; text-align: center; font-size: 0.85rem;">
          <div>Alt Background</div>
          <code style="font-size: 0.75rem;"><?= e($settings['bg_alt_color']) ?></code>
        </div>
        <div style="padding: 1rem; background: var(--border); border-radius: 4px; text-align: center; font-size: 0.85rem; color: var(--text);">
          <div>Border Color</div>
          <code style="font-size: 0.75rem;"><?= e($settings['border_color']) ?></code>
        </div>
      </div>
    </div>
  </div>

  <div style="background: var(--text); color: var(--bg); padding: 2rem; border-radius: 8px; text-align: center;">
    <h2 style="margin-top: 0;">Your Gallery</h2>
    <p>This is how your site will appear with the current customization settings applied globally.</p>
    <p><a href="/admin/customize" style="color: #ffff00; text-decoration: underline;">← Return to customization editor</a></p>
  </div>
</main>
</body>
</html>
