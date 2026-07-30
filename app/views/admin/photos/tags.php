<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tag photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/admin-refined.css">
</head>
<body>
<div class="dashboard">
  <h1>Tag photos</h1>
  <p><a href="/admin/photos?session=<?= e($sessionId) ?>">← Back to photos</a></p>

  <div class="bulk-panel">
    <h3>Bulk tagging</h3>
    <p>Select photos (click thumbnails), then add tags below. Click "Apply" to assign all tags to selected photos.</p>

    <div class="tag-input-group">
      <label for="kart">Kart number</label>
      <input type="text" id="kart" placeholder="e.g., 42" pattern="[0-9a-zA-Z]+">
      <div id="kartLookup" class="hint kart-lookup-hint"></div>
    </div>

    <div class="tag-input-group">
      <label for="driver">Driver name</label>
      <input type="text" id="driver" placeholder="e.g., J. Smith">
    </div>

    <div class="tag-input-group">
      <label for="class">Class</label>
      <input type="text" id="class" placeholder="e.g., Junior">
    </div>

    <ul class="tag-list" id="pendingTags"></ul>

    <button type="button" id="addTagBtn" class="btn-add-tag">+ Add tag</button>

    <button type="button" id="applyBtn" class="btn-apply" disabled>Apply to <?php echo count($selectedPhotos ?? []) > 0 ? count($selectedPhotos) : '0'; ?> photo(s)</button>
  </div>

  <h3>Photos in this session</h3>
  <div class="tag-photos-grid" id="photosGrid" data-event-entries="<?= e(json_encode($eventEntries ?? [])) ?>">
    <?php foreach ($photos as $photo): ?>
      <div class="tag-photo-thumb" data-photo-id="<?= (int)$photo['id'] ?>">
        <img src="/media/d/<?= e($photo['public_token']) ?>-400.jpg" alt="">
        <input type="checkbox" value="<?= (int)$photo['id'] ?>">
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script src="/assets/js/admin-tagging.js" defer></script>
</body>
</html>
