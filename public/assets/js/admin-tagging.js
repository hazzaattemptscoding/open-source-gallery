/**
 * Bulk photo tagging: click thumbnails to select, build a list of
 * kart/driver/class tags, apply them all to every selected photo in
 * one request. Event entries (for kart-number lookup) come from a
 * data-event-entries attribute on #photosGrid, set server-side.
 */

const photosGrid = document.getElementById('photosGrid');
const eventEntries = JSON.parse(photosGrid.dataset.eventEntries || '[]');
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

photosGrid.addEventListener('click', (e) => {
  const thumb = e.target.closest('.tag-photo-thumb');
  if (thumb) {
    togglePhoto(thumb, parseInt(thumb.dataset.photoId, 10));
  }
});

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

document.getElementById('addTagBtn').addEventListener('click', addTag);

function removeTag(idx) {
  pendingTags.splice(idx, 1);
  renderPendingTags();
}

function renderPendingTags() {
  const list = document.getElementById('pendingTags');
  list.innerHTML = '';
  pendingTags.forEach((tag, idx) => {
    const li = document.createElement('li');
    li.className = 'tag-list-item';
    const parts = [];
    if (tag.kart) parts.push('Kart ' + tag.kart);
    if (tag.driver) parts.push('Driver: ' + tag.driver);
    if (tag.class) parts.push('Class: ' + tag.class);

    const code = document.createElement('code');
    code.textContent = parts.join(' | ');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tag-remove-btn';
    btn.dataset.idx = idx;
    btn.textContent = 'Remove';

    li.appendChild(code);
    li.appendChild(document.createTextNode(' '));
    li.appendChild(btn);
    list.appendChild(li);
  });
}

document.getElementById('pendingTags').addEventListener('click', (e) => {
  const btn = e.target.closest('.tag-remove-btn');
  if (btn) {
    removeTag(parseInt(btn.dataset.idx, 10));
  }
});

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
