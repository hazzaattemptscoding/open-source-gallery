<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photo — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.photo-preview { max-width: 400px; margin: 2rem 0; }
.photo-preview img { max-width: 100%; }
.details { margin: 2rem 0; }
.detail-row { display: flex; gap: 2rem; margin: 1rem 0; padding: 0.5rem 0; border-bottom: 1px solid rgba(109, 40, 217, 0.2); }
.detail-label { font-weight: bold; min-width: 150px; }
.detail-value { font-family: monospace; }
.form-group { margin: 1.5rem 0; }
select { padding: 0.5rem; border: 1px solid var(--purple); border-radius: 4px; }
button { padding: 0.75rem 1.5rem; background: var(--gold); color: var(--ink); border: none; border-radius: 4px; cursor: pointer; }
button:hover { opacity: 0.9; }
</style>
</head>
<body>
<div class="dashboard">
  <h1>Photo details</h1>
  <p><a href="/admin/photos?session=<?= e($sessionId) ?>">← Back to photos</a></p>

  <div class="photo-preview">
    <img src="/media/d/<?= e($photo['public_token']) ?>-800.jpg" alt="<?= e($photo['public_token']) ?>">
  </div>

  <div class="details">
    <div class="detail-row">
      <span class="detail-label">Token:</span>
      <span class="detail-value"><?= e($photo['public_token']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Original filename:</span>
      <span class="detail-value"><?= e($photo['original_filename']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Size:</span>
      <span class="detail-value"><?= e(number_format((int)$photo['hires_size_bytes'] / 1024 / 1024, 1)) ?> MB</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Dimensions:</span>
      <span class="detail-value"><?= (int)$photo['width'] ?> × <?= (int)$photo['height'] ?> px</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Views:</span>
      <span class="detail-value"><?= (int)$photo['view_count'] ?></span>
    </div>
  </div>

  <form method="post" action="/admin/photos/<?= e($photo['id']) ?>/status">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="form-group">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="live" <?= $photo['status'] === 'live' ? 'selected' : '' ?>>Live</option>
        <option value="hidden" <?= $photo['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
        <option value="failed" <?= $photo['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>

    <button type="submit">Update status</button>
  </form>

  <form method="post" action="/admin/photos/<?= e($photo['id']) ?>/delete" style="margin-top: 2rem;">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <button type="submit" onclick="return confirm('Delete this photo? This cannot be undone.')">Delete photo</button>
  </form>
</div>
</body>
</html>
