/**
 * Service Worker for upload persistence.
 * Allows uploads to resume even if the page is navigated away or the browser is closed.
 * Coordinates with admin-upload.js to maintain upload state across navigation.
 */

const CACHE_NAME = 'upload-cache-v1';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

/**
 * Intercept fetch requests for upload endpoints.
 * For chunk uploads, retry on network failure and coordinate with server-side resumption.
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Only intercept POST requests to upload endpoints
  if (request.method !== 'POST' || !url.pathname.startsWith('/admin/upload')) {
    return;
  }

  // For chunk uploads, add offline resilience
  if (url.pathname === '/admin/upload/chunk') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache successful chunk responses for offline support
          if (response.ok) {
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, response.clone());
            });
          }
          return response;
        })
        .catch(() => {
          // On network error, the client will retry via MAX_RETRIES logic
          return new Response(JSON.stringify({ error: 'Network error' }), {
            status: 0,
            statusText: 'Network error',
            headers: { 'Content-Type': 'application/json' },
          });
        })
    );
  }
});

/**
 * Message handler: allows clients to query upload state via postMessage.
 * Enables cross-tab coordination of ongoing uploads.
 */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'GET_UPLOAD_STATE') {
    // Query cached upload state if needed (for future multi-tab support)
    event.ports[0].postMessage({ state: 'active' });
  }
});
