const CACHE_VERSION = 'palomnik-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_CACHE = `${CACHE_VERSION}-offline`;
const STATIC_URLS = [
  '/',
  '/offline',
  '/css/pilgrim-site.css',
  '/css/pilgrim-account.css',
  '/manifest.webmanifest',
  '/icons/pilgrim.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_URLS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => !key.startsWith(CACHE_VERSION)).map(key => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/profile') || url.pathname.startsWith('/admin') || url.pathname.startsWith('/login') || url.pathname.startsWith('/register')) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then(response => {
          const copy = response.clone();
          caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then(response => response || caches.match('/offline')))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(cached => cached || fetch(request).then(response => {
      const copy = response.clone();
      caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
      return response;
    }))
  );
});

self.addEventListener('message', event => {
  if (event.data?.type !== 'CACHE_URLS' || !Array.isArray(event.data.urls)) return;
  event.waitUntil(
    caches.open(OFFLINE_CACHE).then(cache => Promise.all(
      event.data.urls.map(url => cache.add(url).catch(() => null))
    ))
  );
});
