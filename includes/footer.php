</div>

<!-- PWA Install Banner -->
<div id="pwa-install-banner" style="display:none;">
    <div class="pwa-banner-content">
        <img src="/assets/icons/icon-192.png" alt="BPOS" class="pwa-banner-icon">
        <div class="pwa-banner-text">
            <strong>Install BPOS</strong>
            <span>Tambahkan ke Home Screen untuk akses lebih cepat</span>
        </div>
        <div class="pwa-banner-actions">
            <button id="pwa-install-btn" class="pwa-btn-install">Install</button>
            <button id="pwa-dismiss-btn" class="pwa-btn-dismiss">✕</button>
        </div>
    </div>
</div>

<script>
    // ---- Sidebar Toggle ----
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
    }
    document.addEventListener('click', function(event) {
        var sidebar = document.getElementById('sidebar');
        var burger  = document.querySelector('.burger-btn');
        if (sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !burger.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // ---- PWA Install Prompt ----
    let deferredPrompt = null;
    const banner      = document.getElementById('pwa-install-banner');
    const installBtn  = document.getElementById('pwa-install-btn');
    const dismissBtn  = document.getElementById('pwa-dismiss-btn');

    // Jangan tampilkan lagi kalau user sudah dismiss sebelumnya
    const dismissed = localStorage.getItem('pwa-banner-dismissed');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // Tampilkan banner hanya jika belum pernah di-dismiss
        if (!dismissed) {
            setTimeout(() => { banner.style.display = 'flex'; }, 1500);
        }
    });

    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            banner.style.display = 'none';
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('[BPOS] Install outcome:', outcome);
            deferredPrompt = null;
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            banner.style.display = 'none';
            localStorage.setItem('pwa-banner-dismissed', '1');
        });
    }

    // Sembunyikan banner jika sudah terinstall
    window.addEventListener('appinstalled', () => {
        banner.style.display = 'none';
        deferredPrompt = null;
        console.log('[BPOS] App berhasil diinstall!');
    });

    // ---- Online/Offline indicator (Ping-based, bukan navigator.onLine) ----
    // navigator.onLine tidak reliable di Android/mode pesawat
    // Solusi: fetch request kecil ke server setiap 10 detik
    let isActuallyOnline = true;

    async function pingServer() {
        const indicator = document.getElementById('online-indicator');
        try {
            // Fetch favicon/manifest dengan cache-bust biar nggak kena cache SW
            const res = await fetch('/manifest.json?_ping=' + Date.now(), {
                method: 'HEAD',
                cache: 'no-store',
                signal: AbortSignal.timeout(4000) // timeout 4 detik
            });
            if (res.ok) {
                isActuallyOnline = true;
                if (indicator) {
                    indicator.textContent = '🟢 Online';
                    indicator.style.color = '#03dac6';
                }
            } else { throw new Error('Bad response'); }
        } catch {
            isActuallyOnline = false;
            if (indicator) {
                indicator.textContent = '🔴 Offline';
                indicator.style.color = '#cf6679';
            }
        }
    }

    // Ping saat halaman load & setiap 10 detik
    document.addEventListener('DOMContentLoaded', pingServer);
    setInterval(pingServer, 10000);

    // ---- Guard: Blokir submit form saat offline ----
    // Mencegah data hilang diam-diam saat user input pas offline
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!isActuallyOnline) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    showOfflineWarning();
                    return false;
                }
            }, true); // capture phase biar jalan sebelum handler lain
        });
    });

    function showOfflineWarning() {
        // Hapus warning lama kalau ada
        const existing = document.getElementById('offline-warning-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'offline-warning-toast';
        toast.innerHTML = `
            <div style="font-size:1.5rem; margin-bottom:8px;">📡</div>
            <strong style="display:block; margin-bottom:6px; color:#fff;">Kamu Sedang Offline!</strong>
            <span style="font-size:0.85rem; color:#aaa; line-height:1.5;">
                Data tidak bisa disimpan saat tidak ada koneksi.<br>
                Sambungkan internet lalu coba lagi.
            </span>
            <button onclick="this.closest('#offline-warning-toast').remove()"
                style="margin-top:14px; width:100%; padding:10px;
                background:#bb86fc; color:#000; border:none;
                border-radius:8px; font-weight:bold; cursor:pointer; font-size:0.9rem;">
                Oke, Mengerti
            </button>`;
        toast.style.cssText = `
            position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
            z-index:99999; background:#1e1e1e;
            border:1px solid #cf6679; border-radius:14px;
            padding:20px 24px; width:calc(100% - 48px); max-width:340px;
            text-align:center; box-shadow:0 10px 40px rgba(0,0,0,0.7);
            animation:slideUpBanner 0.3s ease forwards;`;
        document.body.appendChild(toast);

        // Auto-hilang setelah 6 detik
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 6000);
    }
</script>
</body>
</html>
