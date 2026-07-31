<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tag photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/admin-refined.css">
<style>
.driver-visibility-panel {
  margin: 2rem 0;
  padding: 1.5rem;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: 8px;
}

.driver-visibility-panel h3 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1rem;
  font-weight: 600;
}

.driver-visibility-list {
  display: grid;
  gap: 1rem;
}

.driver-visibility-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;
}

.driver-visibility-item strong {
  flex: 1;
  font-family: 'Geist Mono', monospace;
  font-size: 0.9rem;
}

.driver-visibility-item select {
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--border);
  border-radius: 3px;
  font-size: 0.9rem;
}

.driver-visibility-item button {
  padding: 0.4rem 0.8rem;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  font-size: 0.85rem;
}

.driver-visibility-item button:hover {
  opacity: 0.9;
}

.driver-visibility-item button:active {
  transform: scale(0.97);
}

.visibility-info {
  margin-top: 0.5rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}
</style>
</head>
<body>
<div class="dashboard">
  <h1>Tag photos</h1>
  <p><a href="/admin/photos?session=<?= e($sessionId) ?>">← Back to photos</a></p>

  <?php if (!empty($eventEntries)): ?>
  <div class="driver-visibility-panel">
    <h3>Driver visibility</h3>
    <p style="margin-top: 0; font-size: 0.9rem; color: var(--text-muted);">Control how driver names appear in the public gallery. By default, names are hidden for privacy.</p>
    <div class="driver-visibility-list">
      <?php
      $uniqueDrivers = [];
      foreach ($eventEntries as $entry) {
        if (!empty($entry['driver_name'])) {
          $uniqueDrivers[$entry['driver_name']] = $entry['drivers_visibility'] ?? 'hidden';
        }
      }
      foreach ($uniqueDrivers as $driverName => $visibility):
      ?>
        <div class="driver-visibility-item">
          <strong><?= e($driverName) ?></strong>
          <select class="driver-visibility-select" data-driver="<?= e($driverName) ?>" data-event-id="<?= (int)$eventId ?>">
            <option value="hidden" <?= $visibility === 'hidden' ? 'selected' : '' ?>>Hidden</option>
            <option value="initials" <?= $visibility === 'initials' ? 'selected' : '' ?>>Initials (M.P.)</option>
            <option value="full" <?= $visibility === 'full' ? 'selected' : '' ?>>Full name</option>
          </select>
          <button type="button" class="driver-visibility-btn" data-driver="<?= e($driverName) ?>" data-event-id="<?= (int)$eventId ?>">Save</button>
          <div class="visibility-info">
            <?php
              $readableVis = match($visibility) {
                'hidden' => 'Hidden from public view',
                'initials' => 'Shows initials only',
                'full' => 'Shows full name',
                default => 'Unknown'
              };
              echo $readableVis;
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

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
<script>
document.addEventListener('DOMContentLoaded', function() {
  const visibilityButtons = document.querySelectorAll('.driver-visibility-btn');
  visibilityButtons.forEach(button => {
    button.addEventListener('click', async function() {
      const driverName = this.dataset.driver;
      const eventId = parseInt(this.dataset.eventId);
      const select = document.querySelector(`.driver-visibility-select[data-driver="${driverName}"]`);
      const visibility = select.value;

      button.disabled = true;
      button.textContent = 'Saving...';

      try {
        const response = await fetch('/admin/drivers/visibility', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            csrf_token: '<?= e($csrfToken) ?>',
            event_id: eventId,
            driver_name: driverName,
            visibility: visibility
          })
        });

        const data = await response.json();

        if (response.ok && data.success) {
          // Update the info text
          const infoDiv = this.nextElementSibling;
          const readableVis = {
            'hidden': 'Hidden from public view',
            'initials': 'Shows initials only',
            'full': 'Shows full name'
          }[visibility];
          infoDiv.textContent = readableVis;
          button.textContent = 'Saved!';
          setTimeout(() => {
            button.textContent = 'Save';
            button.disabled = false;
          }, 1500);
        } else {
          alert('Error: ' + (data.error || 'Failed to save'));
          button.textContent = 'Save';
          button.disabled = false;
        }
      } catch (error) {
        console.error(error);
        alert('Error saving driver visibility');
        button.textContent = 'Save';
        button.disabled = false;
      }
    });
  });
});
</script>
</body>
</html>
