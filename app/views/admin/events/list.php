<?php
$pageTitle = 'Events';
$currentPage = 'events';
require_once __DIR__ . '/../partials/layout_header.php';
?>
<div class="dashboard">
  <h1>Events</h1>
  <p><a href="/admin">← Back to dashboard</a></p>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <a href="/admin/events/new" class="btn-secondary">+ Create event</a>

  <?php if (empty($events)): ?>
    <p>No events yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
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
            <td><?= e($event['title']) ?></td>
            <td><?= e($event['slug']) ?></td>
            <td><?= e($event['event_date']) ?></td>
            <td><?= $event['is_published'] ? '✓' : '–' ?></td>
            <td><?= e(format_pence((int)$event['price_single_pence'], $currencyCode)) ?></td>
            <td><?= $event['price_session_pence'] ? e(format_pence((int)$event['price_session_pence'], $currencyCode)) : '–' ?></td>
            <td><?= $event['price_event_pence'] ? e(format_pence((int)$event['price_event_pence'], $currencyCode)) : '–' ?></td>
            <td>
              <div class="row-actions">
                <a href="/admin/events/<?= e($event['id']) ?>">Edit</a>
                <form method="post" action="/admin/events/<?= e($event['id']) ?>/delete" class="form-inline">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" data-confirm="Delete this event? This cannot be undone.">Delete</button>
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

<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>
