<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sessions — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="dashboard">
  <h1>Sessions in "<?= e($eventSlug) ?>"</h1>
  <p><a href="/admin/events">← Back to events</a></p>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <a href="/admin/sessions/new?event=<?= e($eventId) ?>" class="btn-pill btn-pill-gold">+ Create session</a>

  <?php if (empty($sessions)): ?>
    <p>No sessions yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Photos</th>
          <th>Sort order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $session): ?>
          <tr>
            <td><?= e($session['name']) ?></td>
            <td><?= e($session['slug']) ?></td>
            <td><?= (int)$session['photo_count'] ?></td>
            <td><?= (int)$session['sort_order'] ?></td>
            <td>
              <div class="row-actions">
                <a href="/admin/sessions/<?= e($session['id']) ?>">Edit</a>
                <a href="/admin/photos?session=<?= e($session['id']) ?>">View photos</a>
                <form method="post" action="/admin/sessions/<?= e($session['id']) ?>/delete" class="form-inline">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" data-confirm="Delete this session? This cannot be undone.">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<script src="/assets/js/admin-common.js" defer></script>
</body>
</html>
