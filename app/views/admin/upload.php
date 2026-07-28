<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="dashboard">
  <h1>Upload photos</h1>
  <p><a href="/admin/events">← Back to events</a></p>

  <div class="upload-zone" id="uploadZone">
    <p>Drag photos here or <button type="button" id="chooseFileBtn" class="btn-choose">choose from folder</button></p>
    <p class="hint">JPEG or PNG. Recommended: 2000×2000 px minimum. Max 16384×16384 px.</p>
    <input type="file" id="fileInput" multiple accept="image/jpeg,image/png">
  </div>

  <div id="sessionSelect" class="session-select-row">
    <label for="session">Destination session:</label>
    <select id="session" required>
      <option value="">Select a session</option>
      <?php foreach ($sessionsByEvent as $eventId => $event): ?>
        <?php foreach ($event['sessions'] as $session): ?>
          <option value="<?= (int)$session['id'] ?>">
            <?= e($event['slug']) ?> / <?= e($session['slug']) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>
    <button type="button" id="startUploadBtn" class="btn-start-upload">Start upload</button>
  </div>

  <ul class="upload-files-list" id="filesList"></ul>

  <p id="jobDrain" class="job-drain-notice">
    <strong>Generating derivatives…</strong> Keeping this tab open speeds up processing.
  </p>
</div>
<script src="/assets/js/admin-upload.js" defer></script>
</body>
</html>
