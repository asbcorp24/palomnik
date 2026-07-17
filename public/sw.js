const CACHE_VERSION = 'palomnik-v3';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_CACHE = `${CACHE_VERSION}-offline`;
const STATIC_URLS = [
  '/offline.html',
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

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(request).then(response => response || caches.match('/offline.html'))
      )
    );
    return;
  }

  if (url.pathname.startsWith('/css/')
      || url.pathname.startsWith('/icons/')
      || url.pathname === '/manifest.webmanifest') {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(response => {
        const copy = response.clone();
        caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
        return response;
      }))
    );
  }
});

self.addEventListener('message', event => {
  if (event.data?.type !== 'CACHE_URLS' || !Array.isArray(event.data.urls)) return;

  const safeUrls = event.data.urls.filter(value => {
    try {
      const url = new URL(value, self.location.origin);
      return url.origin === self.location.origin
        && !url.pathname.startsWith('/profile')
        && !url.pathname.startsWith('/admin')
        && !url.pathname.startsWith('/login')
        && !url.pathname.startsWith('/register');
    } catch (error) {
      return false;
    }
  });

  event.waitUntil(
    caches.open(OFFLINE_CACHE).then(cache => Promise.all(
      safeUrls.map(url => cache.add(url).catch(() => null))
    ))
  );
});
