/**
 * IndexedDB store for in-flight uploads.
 *
 * This exists so the file data outlives the page that selected it. Previously
 * the File handles lived only in a module-level array in admin-upload.js, so
 * navigating away destroyed them along with the chunk loop and the progress
 * widget's state. Files are written here first, then the Service Worker reads
 * them back and drives the upload independently of any document.
 *
 * Loaded by both sides: a <script> tag on admin pages and importScripts() in
 * the worker, hence the attachment to `self` rather than `window`.
 */

(function (self) {
  'use strict';

  var DB_NAME = 'gallery-uploads';
  var DB_VERSION = 1;
  var STORE_FILES = 'files';
  var STORE_META = 'meta';

  function open() {
    return new Promise(function (resolve, reject) {
      var request = self.indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = function (event) {
        var db = event.target.result;
        if (!db.objectStoreNames.contains(STORE_FILES)) {
          var files = db.createObjectStore(STORE_FILES, { keyPath: 'fileId' });
          files.createIndex('batchId', 'batchId', { unique: false });
        }
        if (!db.objectStoreNames.contains(STORE_META)) {
          db.createObjectStore(STORE_META, { keyPath: 'key' });
        }
      };

      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error); };
    });
  }

  function tx(storeName, mode, fn) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var transaction = db.transaction(storeName, mode);
        var store = transaction.objectStore(storeName);
        var result = fn(store);

        transaction.oncomplete = function () {
          db.close();
          resolve(result && result.__request ? result.__request.result : result);
        };
        transaction.onerror = function () { db.close(); reject(transaction.error); };
        transaction.onabort = function () { db.close(); reject(transaction.error); };
      });
    });
  }

  function wrap(request) {
    return { __request: request };
  }

  var UploadStore = {
    /**
     * Record a batch's files. `blob` is the File itself: File is structured
     * cloneable, so IndexedDB stores the actual bytes rather than a handle
     * that would go stale when the page unloads.
     */
    putFiles: function (batchId, sessionId, csrfToken, files) {
      return tx(STORE_FILES, 'readwrite', function (store) {
        files.forEach(function (file) {
          store.put({
            fileId: file.fileId,
            batchId: batchId,
            sessionId: sessionId,
            csrfToken: csrfToken,
            name: file.name,
            size: file.size,
            blob: file.blob,
            chunksTotal: file.chunksTotal,
            chunksReceived: file.chunksReceived || 0,
            status: 'pending',
            error: null
          });
        });
      });
    },

    getBatchFiles: function (batchId) {
      return tx(STORE_FILES, 'readonly', function (store) {
        return wrap(store.index('batchId').getAll(batchId));
      });
    },

    getAllFiles: function () {
      return tx(STORE_FILES, 'readonly', function (store) {
        return wrap(store.getAll());
      });
    },

    updateFile: function (fileId, changes) {
      return tx(STORE_FILES, 'readwrite', function (store) {
        var request = store.get(fileId);
        request.onsuccess = function () {
          var record = request.result;
          if (!record) return;
          Object.keys(changes).forEach(function (key) { record[key] = changes[key]; });
          store.put(record);
        };
      });
    },

    /**
     * Drop a finished file's bytes but keep its row, so the UI can still show
     * the completed entry without holding a full-size image in the database.
     */
    clearBlob: function (fileId) {
      return UploadStore.updateFile(fileId, { blob: null });
    },

    deleteBatch: function (batchId) {
      return UploadStore.getBatchFiles(batchId).then(function (files) {
        return tx(STORE_FILES, 'readwrite', function (store) {
          files.forEach(function (file) { store.delete(file.fileId); });
        });
      });
    },

    setActiveBatch: function (batchId) {
      return tx(STORE_META, 'readwrite', function (store) {
        store.put({ key: 'active', batchId: batchId, at: Date.now() });
      });
    },

    getActiveBatch: function () {
      return tx(STORE_META, 'readonly', function (store) {
        return wrap(store.get('active'));
      });
    },

    clearActiveBatch: function () {
      return tx(STORE_META, 'readwrite', function (store) {
        store.delete('active');
      });
    }
  };

  self.UploadStore = UploadStore;
  self.UPLOAD_CHANNEL = 'gallery-upload-progress';
})(typeof self !== 'undefined' ? self : this);
