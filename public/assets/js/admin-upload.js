/**
 * Chunked upload UI: drag-drop or file picker, 2MB chunks with retry,
 * then finalize. Talks to /admin/upload/init, /chunk, /finalize.
 * Integrates with floating progress widget for real-time upload tracking.
 */

// Chunking, retries and the transfer itself now live in /upload-worker.js.
// This file only prepares the batch and renders its rows.
const CSRF_TOKEN = document.getElementById('uploadZone').dataset.csrfToken;

let selectedFiles = [];
let batchId = null;
let sessionId = null;
let progressWidget = null;

// file_id -> index in selectedFiles, so worker broadcasts (which know only the
// server-side id) can find the row they belong to.
const fileIndexById = new Map();

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

/**
 * Hand the batch to the Service Worker and stop being responsible for it.
 *
 * The chunk loop used to live here, in page context, which is why navigating
 * away killed the upload: the loop, the File handles and the widget state all
 * belonged to one document. Now the files go into IndexedDB and the worker
 * drives the transfer, so this page is just one of several possible viewers
 * of an upload that is already independent of it.
 */
async function uploadFiles(fileInfo) {
  const records = [];

  for (let idx = 0; idx < selectedFiles.length; idx++) {
    const info = fileInfo[idx];
    if (!info) continue;

    records.push({
      fileId: info.file_id,
      name: selectedFiles[idx].name,
      size: selectedFiles[idx].size,
      blob: selectedFiles[idx],
      chunksTotal: info.chunks_total,
      chunksReceived: info.chunks_received || 0,
    });

    fileIndexById.set(String(info.file_id), idx);

    const statusEl = document.getElementById(`status-${idx}`);
    statusEl.textContent = (info.chunks_received || 0) > 0 ? 'Resuming' : 'Queued';
    statusEl.className = 'upload-file-status status-uploading';
  }

  if (!records.length) return;

  await self.UploadStore.putFiles(batchId, sessionId, CSRF_TOKEN, records);
  await self.UploadStore.setActiveBatch(batchId);

  const worker = navigator.serviceWorker.controller
    || (await navigator.serviceWorker.ready).active;

  if (worker) {
    worker.postMessage({ type: 'start', batchId: batchId });
  } else {
    alert('Upload could not start: the background uploader is unavailable. Reload the page and try again.');
  }
}

/**
 * Mirror worker broadcasts onto this page's per-file rows. The top bar in
 * upload-monitor.js covers every other admin page; this only adds the
 * detail view that makes sense while you are still looking at the picker.
 */
function watchWorkerProgress() {
  let channel;
  try {
    channel = new BroadcastChannel(self.UPLOAD_CHANNEL || 'gallery-upload-progress');
  } catch (err) {
    return; // monitor already warns; per-file detail is a nicety
  }

  channel.addEventListener('message', (event) => {
    const data = event.data || {};
    const idx = fileIndexById.get(String(data.fileId));
    if (idx === undefined) return;

    const statusEl = document.getElementById(`status-${idx}`);
    const progressEl = document.getElementById(`progress-${idx}`);
    if (!statusEl || !progressEl) return;

    if (data.type === 'progress') {
      statusEl.textContent = 'Uploading';
      statusEl.className = 'upload-file-status status-uploading';
      progressEl.style.width = data.percent + '%';
      if (progressWidget) progressWidget.updateProgress(idx, data.percent);
    } else if (data.type === 'file-done') {
      statusEl.textContent = `Done: ${escapeHtml(data.token || '')}`;
      statusEl.className = 'upload-file-status status-done';
      progressEl.style.width = '100%';
      if (progressWidget) progressWidget.updateProgress(idx, 100);
    } else if (data.type === 'file-error') {
      statusEl.textContent = `Error: ${data.message}`;
      statusEl.className = 'upload-file-status status-error';
    }
  });
}

watchWorkerProgress();

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
