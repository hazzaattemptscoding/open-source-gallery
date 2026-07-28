<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Events — <?= e($siteName) ?></title>
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
  <h1>Events</h1>
  <p><a href="/admin">← Back to dashboard</a></p>

  <a href="/admin/events/new" style="display: inline-block; margin: 1rem 0; padding: 0.75rem 1.5rem; background: var(--gold); color: var(--ink); border-radius: 4px; text-decoration: none;">+ Create event</a>

  <?php if (empty($events)): ?>
    <p>No events yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Slug</th>
          <th>Date</th>
          <th>Published</th>
          <th>Single price</th>
          <th>Session price</th>
          <th>Event price</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td><?= e($event['slug']) ?></td>
            <td><?= e($event['event_date']) ?></td>
            <td><?= $event['is_published'] ? '✓' : '–' ?></td>
            <td><?= $event['price_single_pence'] ? e((int)$event['price_single_pence']) . ' ' . e($currencySymbol) : '–' ?></td>
            <td><?= $event['price_session_pence'] ? e((int)$event['price_session_pence']) . ' ' . e($currencySymbol) : '–' ?></td>
            <td><?= $event['price_event_pence'] ? e((int)$event['price_event_pence']) . ' ' . e($currencySymbol) : '–' ?></td>
            <td>
              <div class="actions">
                <a href="/admin/events/<?= e($event['id']) ?>">Edit</a>
                <form method="post" action="/admin/events/<?= e($event['id']) ?>/delete" style="display: inline;">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" onclick="return confirm('Delete this event? This cannot be undone.')">Delete</button>
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
