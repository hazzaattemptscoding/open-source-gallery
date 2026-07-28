<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tag photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.photos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; margin: 2rem 0; }
.photo-thumb { position: relative; aspect-ratio: 1; border: 3px solid transparent; border-radius: 4px; overflow: hidden; cursor: pointer; transition: border-color 0.2s; }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-thumb.selected { border-color: var(--gold); }
.photo-thumb input[type="checkbox"] { position: absolute; top: 4px; left: 4px; cursor: pointer; }

.bulk-panel { background: rgba(109, 40, 217, 0.1); border: 1px solid var(--purple); border-radius: 4px; padding: 1.5rem; margin: 2rem 0; }
.bulk-panel h3 { margin-top: 0; }
.tag-input-group { margin: 1rem 0; }
.tag-input-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
.tag-input-group input, .tag-input-group select { padding: 0.5rem; border: 1px solid var(--purple); border-radius: 4px; width: 100%; max-width: 300px; box-sizing: border-box; }
.tag-list { margin: 1rem 0; padding: 0; list-style: none; }
.tag-list li { background: white; border: 1px solid var(--purple-deep); padding: 0.75rem; margin: 0.5rem 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
.tag-list li code { font-family: monospace; }
.tag-list button { padding: 0.25rem 0.75rem; background: red; color: white; border: none; border-radius: 2px; cursor: pointer; font-size: 0.875rem; }
.tag-list button:hover { opacity: 0.8; }
button.apply { padding: 0.75rem 1.5rem; background: var(--gold); color: var(--ink); border: none; border-radius: 4px; cursor: pointer; margin-top: 1rem; }
button.apply:hover { opacity: 0.9; }
button.apply:disabled { opacity: 0.5; cursor: not-allowed; }
.hint { font-size: 0.875rem; color: #666; margin-top: 0.5rem; }
</style>
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
      <div id="kartLookup" class="hint" style="display: none;"></div>
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

    <button type="button" onclick="addTag()" class="hint" style="background: var(--purple); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">+ Add tag</button>

    <button type="button" id="applyBtn" class="apply" disabled>Apply to <?php echo count($selectedPhotos ?? []) > 0 ? count($selectedPhotos) : '0'; ?> photo(s)</button>
  </div>

  <h3>Photos in this session</h3>
  <div class="photos-grid" id="photosGrid">
    <?php foreach ($photos as $photo): ?>
      <div class="photo-thumb" onclick="togglePhoto(this, <?= (int)$photo['id'] ?>)">
        <img src="/media/d/<?= e($photo['public_token']) ?>-400.jpg" alt="">
        <input type="checkbox" value="<?= (int)$photo['id'] ?>">
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const eventEntries = <?= json_encode($eventEntries ?? []) ?>;
let selectedPhotos = new Set();
let pendingTags = [];

function togglePhoto(el, photoId) {
  el.classList.toggle('selected');
  const checkbox = el.querySelector('input[type="checkbox"]');
  checkbox.checked = !checkbox.checked;

  if (checkbox.checked) {
    selectedPhotos.add(photoId);
  } else {
    selectedPhotos.delete(photoId);
  }

  updateApplyButton();
}

function updateApplyButton() {
  const btn = document.getElementById('applyBtn');
  btn.disabled = selectedPhotos.size === 0;
  btn.textContent = 'Apply to ' + selectedPhotos.size + ' photo(s)';
}

function addTag() {
  const kart = document.getElementById('kart').value.trim();
  const driver = document.getElementById('driver').value.trim();
  const cls = document.getElementById('class').value.trim();

  if (!kart && !driver && !cls) {
    alert('Enter at least one field');
    return;
  }

  pendingTags.push({ kart, driver, class: cls });
  renderPendingTags();
  clearTagInputs();
}

function removeTag(idx) {
  pendingTags.splice(idx, 1);
  renderPendingTags();
}

function renderPendingTags() {
  const list = document.getElementById('pendingTags');
  list.innerHTML = '';
  pendingTags.forEach((tag, idx) => {
    const li = document.createElement('li');
    const parts = [];
    if (tag.kart) parts.push('Kart ' + tag.kart);
    if (tag.driver) parts.push('Driver: ' + tag.driver);
    if (tag.class) parts.push('Class: ' + tag.class);
    li.innerHTML = `<code>${parts.join(' | ')}</code> <button type="button" onclick="removeTag(${idx})">Remove</button>`;
    list.appendChild(li);
  });
}

function clearTagInputs() {
  document.getElementById('kart').value = '';
  document.getElementById('driver').value = '';
  document.getElementById('class').value = '';
}

document.getElementById('kart').addEventListener('change', (e) => {
  const kart = e.target.value.trim();
  const lookup = eventEntries.find(en => en.kart_number === kart);
  const kartLookupDiv = document.getElementById('kartLookup');

  if (lookup) {
    if (document.getElementById('driver').value === '') {
      document.getElementById('driver').value = lookup.driver_name;
    }
    if (document.getElementById('class').value === '') {
      document.getElementById('class').value = lookup.class;
    }
    kartLookupDiv.textContent = 'Found: ' + lookup.driver_name + ' (' + lookup.class + ')';
    kartLookupDiv.style.display = 'block';
  } else if (kart) {
    kartLookupDiv.textContent = 'Not found in entry list';
    kartLookupDiv.style.display = 'block';
  } else {
    kartLookupDiv.style.display = 'none';
  }
});

async function applyTags() {
  if (selectedPhotos.size === 0 || pendingTags.length === 0) {
    alert('Select photos and add tags');
    return;
  }

  const btn = document.getElementById('applyBtn');
  btn.disabled = true;

  try {
    const response = await fetch('/admin/photos/tags/bulk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        photo_ids: Array.from(selectedPhotos),
        tags: pendingTags,
      }),
    });

    if (!response.ok) {
      const err = await response.json();
      throw new Error(err.error || 'Failed to apply tags');
    }

    alert('Tags applied!');
    selectedPhotos.clear();
    pendingTags = [];
    clearTagInputs();
    renderPendingTags();
    updateApplyButton();
    location.reload();
  } catch (err) {
    alert('Error: ' + err.message);
    btn.disabled = false;
  }
}

document.getElementById('applyBtn').addEventListener('click', applyTags);
</script>
</body>
</html>
