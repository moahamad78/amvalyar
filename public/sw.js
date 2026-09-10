const CACHE = 'amvalyar-shell-v1';
const SHELL = [
  '/branding/amvalyar-mark-original.svg',
  '/branding/amvalyar-logo-original.svg',
  '/fonts/Vazirmatn-variable.woff2'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;
  event.respondWith(fetch(request).catch(() => caches.match(request)));
});
