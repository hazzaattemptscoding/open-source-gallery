<?php
$pageTitle = 'Upload';
$currentPage = 'upload';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="dashboard">
  <h1>Upload photos</h1>
  <p><a href="/admin">&larr; Back to dashboard</a></p>

  <div class="step" data-step="1">
    <span class="step-label">Choose a destination session</span>
    <div id="sessionSelect" class="session-select-row">
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
    </div>
  </div>

  <div class="step" data-step="2">
    <span class="step-label">Add photos</span>

    <div class="upload-zone" id="uploadZone" data-csrf-token="<?= e($csrfToken) ?>">
      <p>Drag photos here or <button type="button" id="chooseFileBtn" class="btn-choose">choose from folder</button></p>
      <p class="hint">JPEG or PNG. Recommended: 2000&times;2000 px minimum. Max 16384&times;16384 px.</p>
      <input type="file" id="fileInput" multiple accept="image/jpeg,image/png">
    </div>

    <ul class="upload-files-list" id="filesList"></ul>

    <button type="button" id="startUploadBtn" class="btn-start-upload">Start upload</button>
  </div>

  <p id="jobDrain" class="job-drain-notice">
    Generating derivatives &mdash; keep this tab open to speed up processing.
  </p>

  <?php if ($recentBatch && !empty($recentFiles)): ?>
    <div class="admin-section recent-uploads">
      <h3>Previous upload to this session</h3>
      <p class="upload-timestamp">
        <?= e(date('M d, Y H:i', strtotime($recentBatch['created_at']))) ?>
      </p>
      <ul class="upload-files-list">
        <?php foreach ($recentFiles as $file): ?>
          <li class="upload-file">
            <span class="filename"><?= e($file['original_filename']) ?></span>
            <span class="upload-file-status status-<?= e($file['status']) ?>">
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
</div>
<script src="/assets/js/admin-upload.js" defer></script>
<script>
  // Register Service Worker for upload persistence
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/assets/js/service-worker-upload.js').catch(() => {
      // Silently fail if Service Worker is not available; uploads still work without it
    });
  }
</script>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
