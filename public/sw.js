/* NightLifeBD service worker: offline SOS shell + cached emergency data */
const VERSION = 'nl-v1';
const PRECACHE = ['/offline', '/js/sos.js', '/js/nl-map.js', '/manifest.webmanifest', '/icons/icon-192.png'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(VERSION).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

async function networkFirst(req) {
    try {
        const res = await fetch(req);
        if (res && res.ok) { const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy)); }
        return res;
    } catch (e) {
        return (await caches.match(req)) || (await caches.match('/offline'));
    }
}

async function staleWhileRevalidate(req) {
    const cache = await caches.open(VERSION);
    const cached = await cache.match(req);
    const fetching = fetch(req).then(res => { if (res && res.ok) cache.put(req, res.clone()); return res; }).catch(() => cached);
    return cached || fetching;
}

self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname === '/api/emergency-data' || url.pathname === '/sos') {
        event.respondWith(networkFirst(req));
    } else if (/^\/(build|js|icons)\//.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(req));
    } else if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    }
});
