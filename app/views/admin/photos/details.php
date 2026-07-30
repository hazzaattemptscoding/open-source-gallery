<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photo: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/admin-refined.css">
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

  <form method="post" action="/admin/photos/<?= e($photo['id']) ?>/delete" class="form-spaced">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <button type="submit" data-confirm="Delete this photo? This cannot be undone.">Delete photo</button>
  </form>
</div>
<script src="/assets/js/admin-common.js" defer></script>
</body>
</html>
