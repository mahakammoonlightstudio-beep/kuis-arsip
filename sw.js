/* Service Worker - Sistem Kuesioner & Evaluasi Kearsipan Diarpus Kukar
   Strategy:
   - Static assets (css/js/img/fonts): cache-first, revalidate in background
   - PHP pages: network-first, offline fallback to cached page
   - Never cache POST requests or /admin/, /auth/ pages
*/
const VERSION = 'v7';
const STATIC_CACHE = `static-${VERSION}`;
const PAGE_CACHE = `pages-${VERSION}`;
const IMG_CACHE = `img-${VERSION}`;

const STATIC_ASSETS = [
  'assets/style.css',
  'assets/app.js',
  'assets/img/logo-kukar.png',
  'manifest.json'
];

const OFFLINE_URL = 'offline.html';

self.addEventListener('install', event => {
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)),
      caches.open(PAGE_CACHE).then(cache => cache.add(OFFLINE_URL).catch(() => {}))
    ]).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => !k.endsWith(VERSION)).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return /\.(css|js|woff2?|ttf)(\?.*)?$/.test(url.pathname) ||
         url.pathname.includes('/assets/');
}

function isImage(url) {
  return /\.(png|jpe?g|gif|svg|webp|ico)(\?.*)?$/.test(url.pathname);
}

function isNeverCache(url) {
  return url.pathname.includes('/admin/') ||
         url.pathname.includes('/auth/') ||
         url.pathname.includes('/api/') ||
         url.pathname.endsWith('.php');
}

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Only handle same-origin GET
  if (request.method !== 'GET' || url.origin !== location.origin) return;

  // Never cache admin/auth/api/PHP pages - always fresh (session data!)
  if (isNeverCache(url)) {
    event.respondWith(
      fetch(request).catch(() =>
        new Response('Koneksi terputus. Halaman ini memerlukan koneksi internet.',
          { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
      )
    );
    return;
  }

  // Images: cache-first, very long-lived
  if (isImage(url)) {
    event.respondWith(
      caches.open(IMG_CACHE).then(async cache => {
        const cached = await cache.match(request);
        if (cached) return cached;
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
      }).catch(() => caches.match(request))
    );
    return;
  }

  // Static assets: stale-while-revalidate (fast + always fresh next time)
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async cache => {
        const cached = await cache.match(request);
        const fetchPromise = fetch(request).then(response => {
          if (response.ok) cache.put(request, response.clone());
          return response;
        }).catch(() => cached);
        return cached || fetchPromise;
      })
    );
    return;
  }

  // Navigation (HTML): network-first with offline fallback.
  // Hanya cache navigasi non-PHP; semua halaman PHP berisi data sesi
  // sehingga tidak boleh disajikan dari cache milik pengguna lain.
  if (request.mode === 'navigate') {
    if (url.pathname.endsWith('.php')) {
      event.respondWith(
        fetch(request).catch(() =>
          caches.match(OFFLINE_URL) ||
          new Response('Offline', { status: 503 })
        )
      );
    } else {
      event.respondWith(
        fetch(request).then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(PAGE_CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        }).catch(async () => {
          const cached = await caches.match(request);
          return cached || caches.match(OFFLINE_URL) ||
            new Response('Offline', { status: 503 });
        })
      );
    }
  }
});

// Allow page to trigger immediate update
self.addEventListener('message', event => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});