// ============================================================
// BPOS Service Worker — Offline-First Strategy
// Versi: 1.0.0
// Update cache version setiap kali ada perubahan aset statis
// ============================================================

const CACHE_VERSION = 'bpos-v1';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;

// Aset statis yang selalu di-cache saat install
const STATIC_ASSETS = [
  '/',
  '/dashboard',
  '/transactions',
  '/assets/style.css',
  '/manifest.json',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  'https://cdn.jsdelivr.net/npm/chart.js',
];

// Halaman fallback saat offline & halaman tidak ada di cache
const OFFLINE_FALLBACK = '/offline';

// URL yang TIDAK boleh di-cache (selalu fetch langsung ke network)
const BYPASS_CACHE = [
  '/logout',
  '/reset_data',
  '/api/',
  '_ping=',   // Request ping untuk cek status online — HARUS ke network beneran
];

// ============================================================
// INSTALL — Pre-cache aset statis
// ============================================================
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      console.log('[SW] Pre-caching static assets...');
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('[SW] Beberapa aset gagal di-cache:', err);
      });
    })
  );
  // Langsung aktif tanpa nunggu tab lama ditutup
  self.skipWaiting();
});

// ============================================================
// ACTIVATE — Bersihkan cache lama
// ============================================================
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter((key) => key.startsWith('bpos-') && key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
          .map((key) => {
            console.log('[SW] Menghapus cache lama:', key);
            return caches.delete(key);
          })
      );
    })
  );
  // Ambil kontrol semua halaman yang sudah terbuka
  self.clients.claim();
});

// ============================================================
// FETCH — Strategi: Network First untuk halaman PHP,
//         Cache First untuk aset statis
// ============================================================
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Bypass: jangan cache POST requests & URL tertentu (termasuk ping)
  if (
    event.request.method !== 'GET' ||
    BYPASS_CACHE.some((pattern) => url.href.includes(pattern))
  ) {
    // Langsung ke network, jangan lewat cache sama sekali
    event.respondWith(fetch(event.request).catch(() => new Response('', { status: 503 })));
    return;
  }

  // Strategi: Cache First untuk aset statis (CSS, JS, gambar)
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirst(event.request));
    return;
  }

  // Strategi: Network First untuk halaman PHP (dashboard, transactions, dst)
  event.respondWith(networkFirst(event.request));
});

// ============================================================
// STRATEGI: Cache First (untuk aset statis)
// Baca dari cache → jika tidak ada, fetch & simpan ke cache
// ============================================================
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Aset tidak tersedia offline.', { status: 503 });
  }
}

// ============================================================
// STRATEGI: Network First (untuk halaman PHP)
// Coba fetch dari network → jika gagal (offline), baca cache
// ============================================================
async function networkFirst(request) {
  const cache = await caches.open(DYNAMIC_CACHE);

  // Fungsi timeout biar gak nunggu internet kelamaan (3 detik)
  const timeout = (ms) => new Promise((_, reject) => 
    setTimeout(() => reject(new Error('Timeout')), ms)
  );

  try {
    // Balapan: fetch vs timeout 3 detik
    const response = await Promise.race([
      fetch(request),
      timeout(3000)
    ]);

    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    // Jika internet mati ATAU lemot (> 3 detik), ambil dari cache
    const cached = await cache.match(request);
    if (cached) {
      console.log('[SW] Melayani dari cache (offline/lemot):', request.url);
      return cached;
    }

    // Jika benar-benar tidak ada di cache sama sekali
    const staticCached = await caches.match(OFFLINE_FALLBACK);
    return staticCached || new Response(
      buildOfflinePage(),
      { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
    );
  }
}

// ============================================================
// HELPER: Cek apakah URL adalah aset statis
// ============================================================
function isStaticAsset(pathname) {
  return pathname.startsWith('/assets/') ||
         pathname.includes('.css') ||
         pathname.includes('.js') ||
         pathname.includes('.png') ||
         pathname.includes('.jpg') ||
         pathname.includes('.ico') ||
         pathname.includes('.woff');
}

// ============================================================
// HELPER: Halaman offline inline (fallback jika /offline tidak ada)
// ============================================================
function buildOfflinePage() {
  return `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BPOS — Offline</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #121212; color: #e0e0e0;
      font-family: 'Segoe UI', sans-serif;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      min-height: 100vh; text-align: center; padding: 24px;
    }
    .icon { font-size: 4rem; margin-bottom: 20px; }
    h1 { font-size: 1.6rem; color: #bb86fc; margin-bottom: 12px; }
    p { color: #888; font-size: 0.95rem; line-height: 1.6; max-width: 320px; }
    .badge {
      margin-top: 24px; padding: 6px 16px;
      background: #1e1e1e; border: 1px solid #333;
      border-radius: 999px; font-size: 0.8rem; color: #03dac6;
    }
    button {
      margin-top: 20px; padding: 12px 28px;
      background: #bb86fc; color: #000;
      border: none; border-radius: 8px;
      font-weight: bold; font-size: 1rem; cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="icon">✂️</div>
  <h1>Kamu Sedang Offline</h1>
  <p>Koneksi internet tidak terdeteksi.<br>Halaman ini belum tersimpan di cache.</p>
  <div class="badge">📡 Menunggu koneksi...</div>
  <button onclick="window.location.reload()">🔄 Coba Lagi</button>
  <script>
    // Auto-reload saat online kembali
    window.addEventListener('online', () => window.location.reload());
  </script>
</body>
</html>`;
}

// ============================================================
// BACKGROUND SYNC — Kirim data pending saat online kembali
// ============================================================
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-pending-transactions') {
    console.log('[SW] Background sync triggered: pending transactions');
    // Mekanisme ini akan diperluas jika pakai IndexedDB queue
    event.waitUntil(syncPendingData());
  }
});

async function syncPendingData() {
  // Kirim pesan ke semua tab/window yang aktif
  const clients = await self.clients.matchAll({ type: 'window' });
  clients.forEach((client) => {
    client.postMessage({ type: 'SYNC_AVAILABLE' });
  });
}

// ============================================================
// PUSH NOTIFICATIONS (opsional — siap pakai)
// ============================================================
self.addEventListener('push', (event) => {
  if (!event.data) return;
  const data = event.data.json();
  event.waitUntil(
    self.registration.showNotification(data.title || 'BPOS', {
      body: data.body || 'Ada notifikasi baru.',
      icon: '/assets/icons/icon-192.png',
      badge: '/assets/icons/icon-192.png',
      vibrate: [200, 100, 200],
      data: { url: data.url || '/dashboard' }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url || '/dashboard')
  );
});
