// sw.js - Service Worker for Smart School Attendance PWA
const CACHE_NAME = 'smart-att-cache-v1';
const STATIC_ASSETS = [
  './assets/css/style.css',
  './assets/js/main.js',
  './assets/img/logo.svg',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js'
];

// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('Failed to cache some static assets during install:', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event (Network first with cache fallback for dynamic content; Cache first for static assets)
self.addEventListener('fetch', (event) => {
  const requestUrl = new URL(event.request.url);

  // If fetching static assets (css, js, images, webfonts), try cache first
  if (
    requestUrl.pathname.match(/\.(css|js|svg|png|jpg|jpeg|woff2|woff|ttf)$/) ||
    STATIC_ASSETS.includes(event.request.url)
  ) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          // Return cache and update in background
          fetch(event.request)
            .then((networkResponse) => {
              if (networkResponse && networkResponse.status === 200) {
                caches.open(CACHE_NAME).then((cache) => {
                  cache.put(event.request, networkResponse);
                });
              }
            })
            .catch(() => {});
          return cachedResponse;
        }
        return fetch(event.request);
      })
    );
    return;
  }

  // Network-first for PHP and API endpoints
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});
