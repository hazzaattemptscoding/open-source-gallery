/**
 * Service Worker that owns photo uploads.
 *
 * Lives at the site root deliberately: a worker's default scope is the
 * directory it is served from, so the previous one at /assets/js/ controlled
 * no admin page and its handlers never fired. From here the scope is "/".
 *
 * The important difference from the previous attempt is what this does rather
 * than where it sits. That one intercepted fetch events, which cannot help:
 * an interceptor is still driven by the page that started the request, so
 * navigating away killed the upload anyway. This one reads the files out of
 * IndexedDB and posts the chunks itself, so the transfer belongs to the
 * worker and no document is involved.
 *
 * Progress goes out over BroadcastChannel, which every admin page listens to,
 * so the widget can be rebuilt anywhere without the uploading page still
 * being open.
 */

importScripts('/assets/js/upload-store.js');

const CHUNK_SIZE = 2 * 1024 * 1024; // must match CHUNK_SIZE in app/controllers/admin/upload.php
const MAX_RETRIES = 3;

let draining = false;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'start' || data.type === 'resume') {
    // waitUntil keeps the worker alive for the transfer. Browsers may still
    // terminate a long-running worker, which is why every accepted chunk is
    // committed to IndexedDB immediately: a restart resumes from the last
    // committed chunk rather than starting the file again.
    event.waitUntil(drain());
  }
});

function broadcast(message) {
  try {
    const channel = new BroadcastChannel(self.UPLOAD_CHANNEL);
    channel.postMessage(message);
    channel.close();
  } catch (err) {
    // BroadcastChannel is unavailable in a few older engines. The upload still
    // completes; pages fall back to polling /admin/upload/status.
  }
}

async function drain() {
  if (draining) return;
  draining = true;

  try {
    const files = await self.UploadStore.getAllFiles();
    const pending = files.filter((f) => f.status !== 'done' && f.status !== 'error' && f.blob);

    if (!pending.length) {
      broadcast({ type: 'idle' });
      await self.UploadStore.clearActiveBatch();
      return;
    }

    for (const file of pending) {
      await uploadOne(file, files);
    }

    broadcast({ type: 'complete' });
    await self.UploadStore.clearActiveBatch();
  } catch (err) {
    broadcast({ type: 'error', message: String(err && err.message ? err.message : err) });
  } finally {
    draining = false;
  }
}

async function uploadOne(file, allFiles) {
  await self.UploadStore.updateFile(file.fileId, { status: 'uploading' });

  // Chunks go strictly in order. The server tracks progress as a plain
  // chunks_received counter (app/controllers/admin/upload.php), not a record
  // of which indexes arrived, so resuming assumes everything below the count
  // is present. Uploading in parallel would break that assumption silently
  // and assemble a corrupt file.
  for (let index = file.chunksReceived; index < file.chunksTotal; index++) {
    const start = index * CHUNK_SIZE;
    const chunk = file.blob.slice(start, Math.min(start + CHUNK_SIZE, file.size));

    await postChunk(file, index, chunk);

    await self.UploadStore.updateFile(file.fileId, { chunksReceived: index + 1 });
    file.chunksReceived = index + 1;

    broadcast({
      type: 'progress',
      fileId: file.fileId,
      name: file.name,
      chunksReceived: index + 1,
      chunksTotal: file.chunksTotal,
      percent: ((index + 1) / file.chunksTotal) * 100,
      overall: overallPercent(allFiles)
    });
  }

  await finalize(file);
}

async function postChunk(file, index, chunk) {
  let attempt = 0;

  for (;;) {
    try {
      const body = new FormData();
      body.append('csrf_token', file.csrfToken);
      body.append('file_id', file.fileId);
      body.append('chunk_index', index);
      body.append('chunk', chunk);

      const response = await fetch('/admin/upload/chunk', {
        method: 'POST',
        body: body,
        credentials: 'same-origin'
      });

      if (!response.ok) throw new Error('HTTP ' + response.status);
      return;
    } catch (err) {
      attempt++;
      if (attempt >= MAX_RETRIES) {
        await self.UploadStore.updateFile(file.fileId, { status: 'error', error: String(err.message || err) });
        broadcast({ type: 'file-error', fileId: file.fileId, name: file.name, message: String(err.message || err) });
        throw err;
      }
      await sleep(Math.pow(2, attempt) * 1000);
    }
  }
}

async function finalize(file) {
  const body = new FormData();
  body.append('csrf_token', file.csrfToken);
  body.append('file_id', file.fileId);
  body.append('session_id', file.sessionId);

  const response = await fetch('/admin/upload/finalize', {
    method: 'POST',
    body: body,
    credentials: 'same-origin'
  });

  if (!response.ok) {
    let message = 'Finalize failed';
    try {
      const payload = await response.json();
      message = payload.error || message;
    } catch (err) { /* non-JSON error body; keep the generic message */ }

    await self.UploadStore.updateFile(file.fileId, { status: 'error', error: message });
    broadcast({ type: 'file-error', fileId: file.fileId, name: file.name, message: message });
    return;
  }

  const payload = await response.json();
  await self.UploadStore.updateFile(file.fileId, { status: 'done' });
  await self.UploadStore.clearBlob(file.fileId);

  broadcast({ type: 'file-done', fileId: file.fileId, name: file.name, token: payload.public_token });
}

function overallPercent(files) {
  const total = files.reduce((sum, f) => sum + (f.chunksTotal || 0), 0);
  if (!total) return 0;
  const done = files.reduce((sum, f) => sum + (f.chunksReceived || 0), 0);
  return (done / total) * 100;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
