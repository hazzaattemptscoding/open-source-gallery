<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
body { background: #fafafa; }
.settings-header { background: #fff; padding: 2rem; border-bottom: 1px solid #eee; }
.settings-wrapper { max-width: 1200px; margin: 0 auto; display: flex; }
.settings-nav { width: 200px; padding: 2rem 0; background: #fff; border-right: 1px solid #eee; }
.settings-nav a { display: block; padding: 0.75rem 1.5rem; text-decoration: none; color: #666; border-left: 3px solid transparent; }
.settings-nav a:hover { background: #f5f5f5; }
.settings-nav a.active { color: #000; font-weight: 600; border-left-color: #000; background: #f9f9f9; }
.settings-main { flex: 1; padding: 2rem; }
.mode-toggle { margin-bottom: 2rem; }
.mode-toggle button { padding: 0.5rem 1rem; margin-right: 0.5rem; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
.mode-toggle button.active { background: #000; color: #fff; border-color: #000; }
.settings-section { background: #fff; margin-bottom: 2rem; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
.settings-section-title { padding: 1.5rem; background: #f9f9f9; border-bottom: 1px solid #eee; }
.settings-section-title h3 { margin: 0; font-size: 1.1rem; }
.settings-group { padding: 1.5rem; border-bottom: 1px solid #eee; }
.settings-group:last-child { border-bottom: none; }
.setting-field { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start; }
.setting-label h4 { margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 600; }
.setting-label p { margin: 0; color: #666; font-size: 0.9rem; line-height: 1.4; }
.setting-input input, .setting-input select, .setting-input textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
.setting-input textarea { resize: vertical; min-height: 100px; font-family: monospace; }
.setting-input input[type="checkbox"] { width: auto; }
.setting-help { background: #f0f7ff; padding: 1rem; border-left: 4px solid #2196F3; margin-top: 0.5rem; border-radius: 2px; }
.setting-help strong { color: #1976D2; }
.advanced-badge { display: inline-block; padding: 0.25rem 0.5rem; background: #e3f2fd; color: #1976d2; font-size: 0.75rem; font-weight: 600; border-radius: 3px; }
button.submit { padding: 0.75rem 1.5rem; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
button.submit:hover { background: #333; }
.success-msg { background: #c8e6c9; color: #2e7d32; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; }
@media (max-width: 900px) {
  .settings-wrapper { flex-direction: column; }
  .settings-nav { width: 100%; border-right: none; border-bottom: 1px solid #eee; display: flex; gap: 0.5rem; overflow-x: auto; padding: 0 1.5rem; }
  .settings-nav a { padding: 0.75rem 1rem; white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
  .settings-nav a.active { border-bottom-color: #000; background: transparent; }
  .setting-field { grid-template-columns: 1fr; gap: 0.5rem; }
}
</style>
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title">Settings</a>
</header>

<div class="settings-header">
  <h1>Gallery Settings</h1>
  <p>Customize your gallery, payment, email, and advanced configurations.</p>
</div>

<div class="settings-wrapper">
  <nav class="settings-nav">
    <?php foreach ($categories as $cat): ?>
      <a href="?category=<?= $cat ?>&mode=<?= $mode ?>" class="<?= $category === $cat ? 'active' : '' ?>">
        <?= e(get_category_label($cat)) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="settings-main">
    <?php if ($success): ?>
      <div class="success-msg">✓ Settings updated successfully</div>
    <?php endif; ?>

    <div class="mode-toggle">
      <button class="<?= $mode === 'basic' ? 'active' : '' ?>" onclick="location.href='?category=<?= $category ?>&mode=basic'">
        Basic Settings
      </button>
      <button class="<?= $mode === 'advanced' ? 'active' : '' ?>" onclick="location.href='?category=<?= $category ?>&mode=advanced'">
        Advanced Settings
      </button>
      <?php if ($mode === 'advanced'): ?>
        <span style="color: #666; font-size: 0.9rem; padding: 0.5rem 1rem;">
          ⚠ Advanced settings may affect performance or security. Change carefully.
        </span>
      <?php endif; ?>
    </div>

    <form method="post">
      <div class="settings-section">
        <div class="settings-section-title">
          <h3><?= e(get_category_label($category)) ?></h3>
        </div>

        <?php if (empty($displaySettings)): ?>
          <div style="padding: 1.5rem; text-align: center; color: #999;">
            No <?= $mode === 'advanced' ? 'advanced' : 'basic' ?> settings available in this category.
          </div>
        <?php else: ?>
          <?php foreach ($displaySettings as $setting): ?>
            <div class="settings-group">
              <div class="setting-field">
                <div class="setting-label">
                  <h4>
                    <?= e($setting['label']) ?>
                    <?php if ($setting['is_advanced']): ?>
                      <span class="advanced-badge">Advanced</span>
                    <?php endif; ?>
                  </h4>
                  <?php if ($setting['description']): ?>
                    <p><?= e($setting['description']) ?></p>
                  <?php endif; ?>
                </div>

                <div class="setting-input">
                  <?php if ($setting['type'] === 'boolean'): ?>
                    <label>
                      <input type="checkbox" name="setting_<?= e($setting['key_name']) ?>" value="1" <?= $setting['value'] === '1' ? 'checked' : '' ?>>
                      Enable
                    </label>
                  <?php elseif ($setting['type'] === 'text'): ?>
                    <textarea name="setting_<?= e($setting['key_name']) ?>" placeholder="<?= e($setting['placeholder'] ?? '') ?>"><?= e($setting['value']) ?></textarea>
                  <?php else: ?>
                    <input type="<?= $setting['type'] === 'email' ? 'email' : 'text' ?>" name="setting_<?= e($setting['key_name']) ?>" value="<?= e($setting['value']) ?>" placeholder="<?= e($setting['placeholder'] ?? '') ?>" <?= $setting['required'] ? 'required' : '' ?>>
                  <?php endif; ?>

                  <?php if ($setting['type'] === 'integer'): ?>
                    <div class="setting-help">
                      <strong>Format:</strong> Enter a number
                    </div>
                  <?php elseif ($setting['type'] === 'email'): ?>
                    <div class="setting-help">
                      <strong>Format:</strong> Valid email address (name@example.com)
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <div style="padding: 1.5rem; text-align: right; border-top: 1px solid #eee;">
            <button type="submit" class="submit">Save Changes</button>
          </div>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($mode === 'basic' && !empty(array_filter($allSettings, fn($s) => $s['is_advanced']))): ?>
      <div style="margin-top: 3rem; padding: 1.5rem; background: #f5f5f5; border-radius: 4px;">
        <h3>Need more options?</h3>
        <p>Switch to <strong>Advanced Settings</strong> above to access additional configuration options for power users.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
