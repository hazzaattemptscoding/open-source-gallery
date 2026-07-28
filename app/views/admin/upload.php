<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload photos — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.upload-zone { border: 2px dashed var(--purple); border-radius: 8px; padding: 3rem; text-align: center; margin: 2rem 0; }
.upload-zone.dragover { background: rgba(109, 40, 217, 0.1); }
.upload-zone input[type="file"] { display: none; }
.upload-zone button { padding: 1rem 2rem; background: var(--gold); color: var(--ink); border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
.upload-zone button:hover { opacity: 0.9; }
.files-list { list-style: none; padding: 0; margin: 2rem 0; }
.file-item { background: rgba(109, 40, 217, 0.05); border: 1px solid var(--dark-purple); border-radius: 4px; padding: 1rem; margin: 0.5rem 0; }
.file-progress { margin: 0.5rem 0; background: #e0e0e0; height: 20px; border-radius: 2px; overflow: hidden; }
.file-progress-bar { background: var(--purple); height: 100%; transition: width 0.2s; }
.file-status { font-size: 0.875rem; margin-top: 0.5rem; }
.status-pending { color: var(--purple); }
.status-uploading { color: orange; }
.status-done { color: green; }
.status-error { color: red; }
.hint { font-size: 0.875rem; color: #666; margin-top: 1rem; }
</style>
</head>
<body>
<div class="dashboard">
  <h1>Upload photos</h1>
  <p><a href="/admin/events">← Back to events</a></p>

  <div class="upload-zone" id="uploadZone">
    <p>Drag photos here or <button type="button" onclick="document.getElementById('fileInput').click()">choose from folder</button></p>
    <p class="hint">JPEG or PNG. Recommended: 2000×2000 px minimum. Max 16384×16384 px.</p>
    <input type="file" id="fileInput" multiple accept="image/jpeg,image/png">
  </div>

  <div id="sessionSelect" style="margin: 2rem 0; display: none;">
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
    <button type="button" onclick="startUpload()" style="margin-left: 1rem; padding: 0.75rem 1.5rem; background: var(--gold); color: var(--ink); border: none; border-radius: 4px; cursor: pointer;">Start upload</button>
  </div>

  <ul class="files-list" id="filesList"></ul>

  <p id="jobDrain" style="display: none; color: var(--purple); margin-top: 2rem;">
    <strong>Generating derivatives…</strong> Keeping this tab open speeds up processing.
  </p>
</div>

<script>
const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB
const MAX_RETRIES = 3;

let selectedFiles = [];
let batchId = null;
let sessionId = null;

// Drag and drop
const uploadZone = document.getElementById('uploadZone');
uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', () => {
  uploadZone.classList.remove('dragover');
});
uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  handleFiles(Array.from(e.dataTransfer.files));
});

document.getElementById('fileInput').addEventListener('change', (e) => {
  handleFiles(Array.from(e.target.files));
});

function handleFiles(files) {
  if (!files.length) return;

  selectedFiles = files;
  document.getElementById('sessionSelect').style.display = 'block';
  displayFiles(selectedFiles);
}

function displayFiles(files) {
  const list = document.getElementById('filesList');
  list.innerHTML = '';
  files.forEach((file, idx) => {
    const li = document.createElement('li');
    li.className = 'file-item';
    li.innerHTML = `
      <strong>${escapeHtml(file.name)}</strong> (${formatBytes(file.size)})
      <div class="file-progress">
        <div class="file-progress-bar" id="progress-${idx}" style="width: 0%"></div>
      </div>
      <div class="file-status status-pending" id="status-${idx}">Pending</div>
    `;
    list.appendChild(li);
  });
}

function startUpload() {
  sessionId = parseInt(document.getElementById('session').value);
  if (!sessionId) {
    alert('Please select a session');
    return;
  }

  document.getElementById('sessionSelect').style.display = 'none';
  initBatch();
}

async function initBatch() {
  const formData = new FormData();
  selectedFiles.forEach(file => {
    formData.append('files[]', JSON.stringify({
      name: file.name,
      size: file.size,
    }));
  });

  try {
    const response = await fetch('/admin/upload/init', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) throw new Error('Init failed');
    const data = await response.json();
    batchId = data.batch_id;

    uploadFiles(data.files);
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

async function uploadFiles(fileInfo) {
  for (let idx = 0; idx < selectedFiles.length; idx++) {
    const file = selectedFiles[idx];
    const info = fileInfo[idx];
    if (!info) continue;

    await uploadFile(idx, file, info);
  }
}

async function uploadFile(idx, file, fileInfo) {
  const fileId = fileInfo.file_id;
  const chunksTotal = fileInfo.chunks_total;
  const statusEl = document.getElementById(`status-${idx}`);
  const progressEl = document.getElementById(`progress-${idx}`);

  statusEl.textContent = 'Uploading';
  statusEl.className = 'file-status status-uploading';

  for (let chunkIndex = 0; chunkIndex < chunksTotal; chunkIndex++) {
    const start = chunkIndex * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    let retries = 0;
    let success = false;
    while (retries < MAX_RETRIES && !success) {
      try {
        const fd = new FormData();
        fd.append('file_id', fileId);
        fd.append('chunk_index', chunkIndex);
        fd.append('chunk', chunk);

        const response = await fetch('/admin/upload/chunk', {
          method: 'POST',
          body: fd,
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        success = true;
      } catch (err) {
        retries++;
        if (retries >= MAX_RETRIES) {
          statusEl.textContent = `Error: ${err.message}`;
          statusEl.className = 'file-status status-error';
          throw err;
        }
        await sleep(Math.pow(2, retries) * 1000);
      }
    }

    const progress = ((chunkIndex + 1) / chunksTotal) * 100;
    progressEl.style.width = progress + '%';
  }

  await finalizeUpload(idx, fileId, statusEl, progressEl);
}

async function finalizeUpload(idx, fileId, statusEl, progressEl) {
  try {
    const fd = new FormData();
    fd.append('file_id', fileId);
    fd.append('session_id', sessionId);

    const response = await fetch('/admin/upload/finalize', {
      method: 'POST',
      body: fd,
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Finalize failed');
    }

    const data = await response.json();
    statusEl.textContent = `Done: ${escapeHtml(data.public_token)}`;
    statusEl.className = 'file-status status-done';
    progressEl.style.width = '100%';
  } catch (err) {
    statusEl.textContent = `Error: ${err.message}`;
    statusEl.className = 'file-status status-error';
    throw err;
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}
</script>
</body>
</html>
