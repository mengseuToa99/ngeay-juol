/**
 * ngeay juol — Minimal PWA Service Worker
 *
 * Version 1 strategy:
 *   - Cache static shell assets (CSS, fonts, JS) on install.
 *   - Serve cached assets when available; fall back to network.
 *   - All API / Livewire / dynamic routes go to the network.
 *   - Offline: dynamic routes that fail show an offline message.
 *
 * Financial data (invoices, payments, tenancies) is NEVER cached
 * for offline reads or writes in this version.
 */

const CACHE_NAME = 'ngeay-juol-shell-v1';

/** Static assets safe to cache for the shell. */
const SHELL_ASSETS = [
    '/css/rentwise-admin.css',
    '/Khmer House Key.png',
    '/favicon.ico',
];

// ── Install: pre-cache shell ──────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(SHELL_ASSETS.map(url => new Request(url, { credentials: 'same-origin' })));
        }).catch(() => {
            // Shell pre-cache failure is non-fatal — app still works online.
            console.warn('[SW] Shell pre-cache partially failed; continuing.');
        })
    );
    self.skipWaiting();
});

// ── Activate: delete old caches ───────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names
                    .filter((n) => n !== CACHE_NAME)
                    .map((n) => caches.delete(n))
            )
        )
    );
    self.clients.claim();
});

// ── Fetch: cache-first for shell, network-first for everything else ──
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only handle same-origin GET requests.
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return; // pass through non-GET and cross-origin
    }

    // Financial / dynamic routes — always go online.
    // Livewire uses POST so it passes through above, but guard named patterns just in case.
    const alwaysOnline = [
        '/landlord/invoices',
        '/landlord/monthly-billing',
        '/landlord/payments',
        '/landlord/tenants',
        '/landlord/units',
        '/landlord/rentals',
        '/livewire',
        '/api',
        '/locale',
    ];
    if (alwaysOnline.some((path) => url.pathname.startsWith(path))) {
        return; // let browser handle normally
    }

    // Static assets: cache-first.
    const isStaticAsset = SHELL_ASSETS.some((a) => url.pathname === a)
        || url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/css/')
        || url.pathname.startsWith('/js/');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                return response;
            }))
        );
        return;
    }

    // Everything else: network-first with offline fallback message.
    event.respondWith(
        fetch(event.request).catch(() => {
            return new Response(
                '<!doctype html><html lang="km"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline — ngeay juol</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f0fdf4;color:#1a2e0d}.card{text-align:center;padding:2rem;background:#fff;border-radius:1rem;box-shadow:0 4px 24px #0002;max-width:320px}h1{font-size:1.25rem;margin:0 0 .5rem}p{color:#555;font-size:.9rem}a{color:#059669;font-weight:700}</style></head><body><div class="card"><div style="font-size:2.5rem">📡</div><h1>អ៊ីនធឺណិតត្រូវការ</h1><p>You need an internet connection for billing and payment actions.</p><p style="margin-top:1rem"><a href="/landlord/simple">Try again</a></p></div></body></html>',
                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
        })
    );
});
