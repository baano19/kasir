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

    // ---- Online/Offline indicator ----
    function updateOnlineStatus() {
        const indicator = document.getElementById('online-indicator');
        if (!indicator) return;
        if (navigator.onLine) {
            indicator.textContent = '🟢 Online';
            indicator.style.color = '#03dac6';
        } else {
            indicator.textContent = '🔴 Offline';
            indicator.style.color = '#cf6679';
        }
    }
    window.addEventListener('online',  updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    document.addEventListener('DOMContentLoaded', updateOnlineStatus);
</script>
</body>
</html>
