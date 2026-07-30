<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload photos: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<div class="dashboard">
  <h1>Upload photos</h1>
  <p><a href="/admin">← Back to dashboard</a></p>

  <div class="upload-zone" id="uploadZone" data-csrf-token="<?= e($csrfToken) ?>">
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

  <?php if ($recentBatch && !empty($recentFiles)): ?>
    <div class="recent-uploads">
      <h3>Recent upload</h3>
      <p class="upload-timestamp">
        <?= e(date('M d, Y H:i:s', strtotime($recentBatch['created_at']))) ?>
      </p>
      <ul class="upload-files-list">
        <?php foreach ($recentFiles as $file): ?>
          <li class="upload-file">
            <span class="filename"><?= e($file['original_filename']) ?></span>
            <span class="status status-<?= e($file['status']) ?>">
              <?php
                $statusLabels = [
                    'pending' => 'Pending',
                    'uploaded' => 'Uploaded',
                    'processing' => 'Processing',
                    'live' => 'Live',
                    'failed' => 'Failed',
                ];
                echo isset($statusLabels[$file['status']]) ? $statusLabels[$file['status']] : ucfirst($file['status']);
              ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <ul class="upload-files-list" id="filesList"></ul>

  <p id="jobDrain" class="job-drain-notice">
    <strong>Generating derivatives…</strong> Keeping this tab open speeds up processing.
  </p>
</div>
<script src="/assets/js/admin-upload.js" defer></script>
</body>
</html>
