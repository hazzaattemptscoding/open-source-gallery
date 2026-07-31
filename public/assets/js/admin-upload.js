/**
 * Chunked upload UI: drag-drop or file picker, 2MB chunks with retry,
 * then finalize. Talks to /admin/upload/init, /chunk, /finalize.
 * Integrates with floating progress widget for real-time upload tracking.
 */

const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB
const MAX_RETRIES = 3;
const CSRF_TOKEN = document.getElementById('uploadZone').dataset.csrfToken;

let selectedFiles = [];
let batchId = null;
let sessionId = null;
let progressWidget = null;

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

document.getElementById('chooseFileBtn').addEventListener('click', () => {
  document.getElementById('fileInput').click();
});

document.getElementById('fileInput').addEventListener('change', (e) => {
  handleFiles(Array.from(e.target.files));
});

document.getElementById('startUploadBtn').addEventListener('click', startUpload);

function handleFiles(files) {
  if (!files.length) return;

  selectedFiles = files;
  displayFiles(selectedFiles);
}

function displayFiles(files) {
  const list = document.getElementById('filesList');
  list.innerHTML = '';
  files.forEach((file, idx) => {
    const li = document.createElement('li');
    li.className = 'upload-file-item';
    li.innerHTML = `
      <strong>${escapeHtml(file.name)}</strong> (${formatBytes(file.size)})
      <div class="upload-progress">
        <div class="upload-progress-bar" id="progress-${idx}"></div>
      </div>
      <div class="upload-file-status status-pending" id="status-${idx}">Pending</div>
    `;
    list.appendChild(li);
  });
}

function startUpload() {
  sessionId = parseInt(document.getElementById('session').value, 10);
  if (!sessionId) {
    alert('Please select a session');
    return;
  }

  progressWidget = getProgressWidget('Uploading photos');
  progressWidget.show();
  selectedFiles.forEach((file) => {
    progressWidget.addFile(file.name, file.size);
  });

  initBatch();
}

async function initBatch() {
  const formData = new FormData();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('session_id', sessionId);
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

    uploadFiles(data.accepted);
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
  const chunksReceived = fileInfo.chunks_received || 0;
  const statusEl = document.getElementById(`status-${idx}`);
  const progressEl = document.getElementById(`progress-${idx}`);

  statusEl.textContent = chunksReceived > 0 ? 'Resuming...' : 'Uploading';
  statusEl.className = 'upload-file-status status-uploading';

  // Resume from where we left off, or start from 0 if this is a new upload
  for (let chunkIndex = chunksReceived; chunkIndex < chunksTotal; chunkIndex++) {
    const start = chunkIndex * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    let retries = 0;
    let success = false;
    while (retries < MAX_RETRIES && !success) {
      try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
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
          statusEl.className = 'upload-file-status status-error';
          throw err;
        }
        await sleep(2 ** retries * 1000);
      }
    }

    const totalUploaded = chunkIndex + 1;
    const progress = (totalUploaded / chunksTotal) * 100;
    progressEl.style.width = progress + '%';

    if (progressWidget) {
      progressWidget.updateProgress(idx, progress);
    }
  }

  await finalizeUpload(idx, fileId, statusEl, progressEl);
}

async function finalizeUpload(idx, fileId, statusEl, progressEl) {
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
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
    statusEl.className = 'upload-file-status status-done';
    progressEl.style.width = '100%';
  } catch (err) {
    statusEl.textContent = `Error: ${err.message}`;
    statusEl.className = 'upload-file-status status-error';
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
