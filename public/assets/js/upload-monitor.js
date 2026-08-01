/**
 * Cross-page upload indicator.
 *
 * Loaded on every admin page from the shared footer. Two jobs:
 *
 *  1. Register the upload Service Worker. This is a separate file rather than
 *     an inline <script> because the app sends `default-src 'self'`
 *     (public/index.php), which refuses inline script outright. The previous
 *     registration was inline and so never ran at all.
 *
 *  2. Show a progress bar wherever the admin happens to be. Upload state lives
 *     in IndexedDB and progress arrives over BroadcastChannel, so a page that
 *     was opened halfway through a transfer can still render an accurate bar.
 */

(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) return;

  navigator.serviceWorker.register('/upload-worker.js').then(function () {
    resumeIfUnfinished();
  }).catch(function (err) {
    // Registration can legitimately fail (private browsing, insecure origin).
    // Log it rather than swallowing: the old code's empty catch is why four
    // separate registration bugs went unnoticed.
    console.warn('upload worker registration failed:', err);
  });

  var bar = null;

  function ensureBar() {
    if (bar) return bar;

    bar = document.createElement('div');
    bar.className = 'upload-topbar';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.innerHTML =
      '<div class="upload-topbar-fill"></div>' +
      '<div class="upload-topbar-label"><span class="upload-topbar-text"></span></div>';
    document.body.appendChild(bar);
    return bar;
  }

  function render(percent, text) {
    var el = ensureBar();
    el.classList.add('is-active');
    el.querySelector('.upload-topbar-fill').style.width = Math.max(0, Math.min(100, percent)) + '%';
    el.querySelector('.upload-topbar-text').textContent = text;
  }

  function finish(text) {
    if (!bar) return;
    render(100, text);
    setTimeout(function () {
      if (bar) bar.classList.remove('is-active');
    }, 2500);
  }

  /**
   * On a fresh page load, rebuild state from IndexedDB rather than waiting for
   * the next broadcast, which may be seconds away mid-chunk. Also nudges the
   * worker, since the browser may have terminated it while idle.
   */
  function resumeIfUnfinished() {
    if (!self.UploadStore) return;

    self.UploadStore.getAllFiles().then(function (files) {
      var live = files.filter(function (f) { return f.status !== 'done' && f.status !== 'error'; });
      if (!live.length) return;

      var total = files.reduce(function (sum, f) { return sum + (f.chunksTotal || 0); }, 0);
      var done = files.reduce(function (sum, f) { return sum + (f.chunksReceived || 0); }, 0);
      render(total ? (done / total) * 100 : 0, 'Uploading ' + live.length + ' file' + (live.length === 1 ? '' : 's'));

      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'resume' });
      } else {
        navigator.serviceWorker.ready.then(function (reg) {
          if (reg.active) reg.active.postMessage({ type: 'resume' });
        });
      }
    }).catch(function (err) {
      console.warn('could not restore upload state:', err);
    });
  }

  try {
    var channel = new BroadcastChannel(self.UPLOAD_CHANNEL || 'gallery-upload-progress');
    channel.addEventListener('message', function (event) {
      var data = event.data || {};

      if (data.type === 'progress') {
        render(data.overall || data.percent || 0, 'Uploading ' + data.name);
      } else if (data.type === 'file-done') {
        render(data.overall || 100, 'Uploaded ' + data.name);
      } else if (data.type === 'file-error') {
        render(100, 'Upload failed: ' + data.name);
        if (bar) bar.classList.add('has-error');
      } else if (data.type === 'complete') {
        finish('Upload complete');
      } else if (data.type === 'error') {
        render(100, 'Upload error: ' + data.message);
        if (bar) bar.classList.add('has-error');
      }
    });
  } catch (err) {
    console.warn('BroadcastChannel unavailable; progress will not update live:', err);
  }
})();
