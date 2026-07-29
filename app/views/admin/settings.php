<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
body { background: var(--bg-alt); }

.settings-header {
  background: var(--bg);
  padding: 2rem;
  border-bottom: 1px solid var(--border);
  margin-bottom: 2rem;
}

.settings-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--text);
}

.settings-header p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

.settings-wrapper {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  gap: 2rem;
  padding: 0 2rem 2rem;
}

.settings-nav {
  width: 200px;
  flex-shrink: 0;
}

.settings-nav a {
  display: block;
  padding: 0.75rem 1rem;
  text-decoration: none;
  color: var(--text-muted);
  border-left: 3px solid transparent;
  margin-bottom: 0.25rem;
  transition: all 200ms var(--ease-out);
  font-size: 0.95rem;
}

.settings-nav a:hover {
  color: var(--text);
  background: var(--bg-alt);
}

.settings-nav a.active {
  color: var(--text);
  font-weight: 600;
  border-left-color: var(--text);
  background: var(--bg);
}

.settings-main {
  flex: 1;
  min-width: 0;
}

.mode-toggle {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 2rem;
  background: var(--bg);
  padding: 1rem;
  border-radius: 3px;
  border: 1px solid var(--border);
}

.mode-toggle button {
  padding: 0.5rem 1rem;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  color: var(--text-muted);
  cursor: pointer;
  font-weight: 500;
  font-size: 0.9rem;
  transition: all 160ms var(--ease-out);
}

.mode-toggle button:hover {
  color: var(--text);
  border-color: var(--text);
}

.mode-toggle button.active {
  background: var(--text);
  color: var(--bg);
  border-color: var(--text);
}

.mode-toggle > span {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin-left: auto;
}

.settings-section {
  background: var(--bg);
  margin-bottom: 2rem;
  border: 1px solid var(--border);
  overflow: hidden;
}

.settings-section-title {
  padding: 1.5rem;
  background: var(--bg-alt);
  border-bottom: 1px solid var(--border);
}

.settings-section-title h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text);
}

.settings-group {
  padding: 1.5rem;
  border-bottom: 1px solid var(--border);
  transition: background 200ms ease;
}

.settings-group:last-child {
  border-bottom: none;
}

.settings-group:hover {
  background: var(--bg-alt);
}

.setting-field {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
  align-items: start;
}

.setting-label h4 {
  margin: 0 0 0.5rem 0;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text);
}

.setting-label p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.9rem;
  line-height: 1.5;
}

.setting-input input,
.setting-input select,
.setting-input textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text);
  font-family: inherit;
  font-size: 0.95rem;
  transition: border-color 200ms ease, box-shadow 200ms ease;
}

.setting-input input:focus,
.setting-input select:focus,
.setting-input textarea:focus {
  outline: none;
  border-color: var(--text);
  box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.05);
}

.setting-input textarea {
  resize: vertical;
  min-height: 100px;
  font-family: 'Geist Mono', monospace;
  line-height: 1.5;
}

.setting-input input[type="checkbox"] {
  width: auto;
  margin-right: 0.5rem;
  cursor: pointer;
}

.setting-input label {
  display: flex;
  align-items: center;
  cursor: pointer;
  color: var(--text);
}

.setting-help {
  background: rgba(17, 17, 17, 0.02);
  border-left: 3px solid var(--text-muted);
  padding: 0.75rem 1rem;
  margin-top: 0.75rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.setting-help strong {
  color: var(--text);
  font-weight: 600;
}

.advanced-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  background: rgba(17, 17, 17, 0.06);
  color: var(--text-muted);
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-radius: 2px;
  margin-left: 0.5rem;
}

.settings-footer {
  padding: 1.5rem;
  border-top: 1px solid var(--border);
  background: var(--bg-alt);
  text-align: right;
}

button.submit {
  padding: 0.75rem 1.5rem;
  background: var(--text);
  color: var(--bg);
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 160ms var(--ease-out);
}

button.submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(17, 17, 17, 0.1);
}

button.submit:active {
  transform: scale(0.98);
}

.success-msg {
  background: rgba(52, 101, 56, 0.08);
  color: var(--success);
  padding: 1rem;
  margin-bottom: 1.5rem;
  border: 1px solid rgba(52, 101, 56, 0.2);
  border-left: 3px solid var(--success);
  font-weight: 500;
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.95rem;
}

.cta-box {
  margin-top: 3rem;
  padding: 2rem;
  background: var(--bg);
  border: 1px solid var(--border);
}

.cta-box h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text);
}

.cta-box p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

@media (max-width: 900px) {
  .settings-wrapper {
    flex-direction: column;
    gap: 0;
  }

  .settings-nav {
    width: 100%;
    display: flex;
    gap: 0;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
  }

  .settings-nav a {
    flex: 1;
    padding: 0.75rem;
    text-align: center;
    border-left: none;
    border-bottom: 3px solid transparent;
    margin-bottom: 0;
    white-space: nowrap;
    font-size: 0.85rem;
  }

  .settings-nav a.active {
    border-left: none;
    border-bottom-color: var(--text);
  }

  .setting-field {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .mode-toggle {
    flex-wrap: wrap;
  }

  .mode-toggle > span {
    width: 100%;
    margin-left: 0;
    margin-top: 0.75rem;
  }
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
        <span>⚠ Advanced settings may affect performance or security. Change carefully.</span>
      <?php endif; ?>
    </div>

    <form method="post">
      <div class="settings-section">
        <div class="settings-section-title">
          <h3><?= e(get_category_label($category)) ?></h3>
        </div>

        <?php if (empty($displaySettings)): ?>
          <div class="empty-state">
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

          <div class="settings-footer">
            <button type="submit" class="submit">Save Changes</button>
          </div>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($mode === 'basic' && !empty(array_filter($allSettings, fn($s) => $s['is_advanced']))): ?>
      <div class="cta-box">
        <h3>Need more options?</h3>
        <p>Switch to <strong>Advanced Settings</strong> above to access additional configuration options for power users.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
