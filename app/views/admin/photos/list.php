<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.photos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem; margin: 2rem 0; }
.photo-thumb { position: relative; aspect-ratio: 1; border: 2px solid var(--purple); border-radius: 4px; overflow: hidden; cursor: pointer; }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-status { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.7); color: white; padding: 0.25rem; font-size: 0.75rem; text-align: center; }
.status-live { background: var(--purple); }
.status-processing { background: orange; }
.status-hidden { background: gray; }
.status-failed { background: red; }
table { width: 100%; border-collapse: collapse; margin: 2rem 0; }
th, td { border: 1px solid var(--dark-purple); padding: 0.75rem; text-align: left; font-size: 0.875rem; }
th { background: var(--purple); color: white; }
a { color: var(--gold); text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="dashboard">
  <h1>Photos in "<?= e($sessionSlug) ?>"</h1>
  <p><a href="/admin/sessions?event=<?= e($eventId) ?>">← Back to sessions</a></p>

  <?php if (empty($photos)): ?>
    <p>No photos in this session yet.</p>
  <?php else: ?>
    <div class="photos-grid">
      <?php foreach ($photos as $photo): ?>
        <div class="photo-thumb" onclick="location.href='/admin/photos/<?= e($photo['id']) ?>'">
          <img src="/media/d/<?= e($photo['public_token']) ?>-400.jpg" alt="<?= e($photo['public_token']) ?>" loading="lazy">
          <div class="photo-status status-<?= e($photo['status']) ?>"><?= e(ucfirst($photo['status'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <table>
      <thead>
        <tr>
          <th>Token</th>
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
</body>
</html>
