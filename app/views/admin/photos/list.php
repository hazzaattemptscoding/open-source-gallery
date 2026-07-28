<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="dashboard">
  <h1>Photos in "<?= e($sessionSlug) ?>"</h1>
  <p><a href="/admin/sessions?event=<?= e($eventId) ?>">← Back to sessions</a></p>

  <a href="/admin/photos/tags?session=<?= e($sessionId) ?>" class="btn-pill btn-pill-purple">Tag photos</a>

  <?php if (empty($photos)): ?>
    <p>No photos in this session yet.</p>
  <?php else: ?>
    <div class="admin-photo-grid">
      <?php foreach ($photos as $photo): ?>
        <div class="admin-photo-thumb" data-href="/admin/photos/<?= e($photo['id']) ?>">
          <img src="/media/d/<?= e($photo['public_token']) ?>-400.jpg" alt="<?= e($photo['public_token']) ?>" loading="lazy">
          <div class="photo-status-badge status-<?= e($photo['status']) ?>"><?= e(ucfirst($photo['status'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <table class="admin-table">
      <thead>
        <tr>
          <th>Token</th>
          <th>Filename</th>
          <th>Dimensions</th>
          <th>Size</th>
          <th>Status</th>
          <th>Views</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($photos as $photo): ?>
          <tr>
            <td><code><?= e($photo['public_token']) ?></code></td>
            <td><?= e($photo['original_filename']) ?></td>
            <td><?= (int)$photo['width'] ?> × <?= (int)$photo['height'] ?></td>
            <td><?= e(number_format((int)$photo['hires_size_bytes'] / 1024 / 1024, 1)) ?> MB</td>
            <td><?= e(ucfirst($photo['status'])) ?></td>
            <td><?= (int)$photo['view_count'] ?></td>
            <td>
              <a href="/admin/photos/<?= e($photo['id']) ?>">Edit</a>
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
