<?php
$pageTitle = 'Sessions';
$currentPage = 'events';
require_once __DIR__ . '/../partials/layout_header.php';
?>
<div class="dashboard">
  <h1><?= e($isNew ? 'Create session' : 'Edit session') ?></h1>
  <p><a href="/admin/sessions?event=<?= e($eventId) ?>">← Back to sessions</a></p>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="form-narrow">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="event_id" value="<?= e($eventId) ?>">

    <label for="name">Session name</label>
    <input type="text" id="name" name="name" value="<?= e($session['name'] ?? '') ?>" required maxlength="190">
    <p class="hint">Shown to customers. Example: Morning Practice</p>

    <label for="slug">Session slug (unique within this event)</label>
    <input type="text" id="slug" name="slug" value="<?= e($session['slug'] ?? '') ?>" required pattern="[a-z0-9-]+">
    <p class="hint">Lowercase, hyphens, no spaces. Example: practice-morning</p>

    <label for="sort_order">Display order</label>
    <input type="number" id="sort_order" name="sort_order" value="<?= e($session['sort_order'] ?? '0') ?>" required>
    <p class="hint">Sessions appear in ascending order on the event page.</p>

    <button type="submit"><?= e($isNew ? 'Create session' : 'Update session') ?></button>
  </form>
</div>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>
