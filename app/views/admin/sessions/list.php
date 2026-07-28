<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sessions — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
table { width: 100%; border-collapse: collapse; margin: 2rem 0; }
th, td { border: 1px solid var(--dark-purple); padding: 0.75rem; text-align: left; }
th { background: var(--purple); color: white; }
tr:nth-child(even) { background: rgba(109, 40, 217, 0.05); }
a { color: var(--gold); text-decoration: none; }
a:hover { text-decoration: underline; }
.actions { display: flex; gap: 1rem; }
button { cursor: pointer; }
</style>
</head>
<body>
<div class="dashboard">
  <h1>Sessions in "<?= e($eventSlug) ?>"</h1>
  <p><a href="/admin/events">← Back to events</a></p>

  <a href="/admin/sessions/new?event=<?= e($eventId) ?>" style="display: inline-block; margin: 1rem 0; padding: 0.75rem 1.5rem; background: var(--gold); color: var(--ink); border-radius: 4px; text-decoration: none;">+ Create session</a>

  <?php if (empty($sessions)): ?>
    <p>No sessions yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Slug</th>
          <th>Photos</th>
          <th>Sort order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $session): ?>
          <tr>
            <td><?= e($session['slug']) ?></td>
            <td><?= (int)$session['photo_count'] ?></td>
            <td><?= (int)$session['sort_order'] ?></td>
            <td>
              <div class="actions">
                <a href="/admin/sessions/<?= e($session['id']) ?>">Edit</a>
                <a href="/admin/photos?session=<?= e($session['id']) ?>">View photos</a>
                <form method="post" action="/admin/sessions/<?= e($session['id']) ?>/delete" style="display: inline;">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" onclick="return confirm('Delete this session? This cannot be undone.')">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
