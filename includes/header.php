<?php
// includes/header.php
include_once "db.php"; // Pastikan koneksi DB ada
checkLogin();

$role = $_SESSION["role"];
$name = $_SESSION["name"];
$uid  = $_SESSION["user_id"]; // TAMBAHIN INI BOS! Biar transaksi & dashboard gampang manggil ID
$active_page = basename($_SERVER['PHP_SELF'], ".php");

// Fungsi Anti-XSS buat keamanan data yang tampil
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- PWA: Manifest & Theme -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#bb86fc">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BPOS">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>BPOS - <?= ucfirst($active_page) ?></title>

    <!-- PWA: Register Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => {
                        console.log('[BPOS] Service Worker terdaftar:', reg.scope);

                        // Cek update SW setiap kali halaman dimuat
                        reg.update();

                        // Notifikasi user jika ada versi baru
                        reg.addEventListener('updatefound', () => {
                            const newWorker = reg.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    showUpdateBanner();
                                }
                            });
                        });
                    })
                    .catch(err => console.warn('[BPOS] SW gagal daftar:', err));
            });

            // Terima pesan dari SW (misal: sync available)
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'SYNC_AVAILABLE') {
                    console.log('[BPOS] Background sync tersedia, reload data...');
                }
            });
        }

        function showUpdateBanner() {
            const banner = document.createElement('div');
            banner.id = 'pwa-update-banner';
            banner.innerHTML = `
                <span>🚀 Versi baru tersedia!</span>
                <button onclick="window.location.reload(true)" 
                    style="background:#bb86fc;color:#000;border:none;padding:6px 14px;border-radius:6px;font-weight:bold;cursor:pointer;margin-left:12px;">
                    Update Sekarang
                </button>`;
            banner.style.cssText = `
                position:fixed;bottom:0;left:0;right:0;z-index:9999;
                background:#1e1e1e;color:#e0e0e0;
                padding:12px 20px;display:flex;align-items:center;
                justify-content:center;font-size:0.9rem;
                border-top:1px solid #333;`;
            document.body.appendChild(banner);
        }
    </script>
</head>
<body>

<div class="mobile-header">
    <span style="color:var(--primary); font-weight:bold; font-size:1.2rem;">BPOS</span>
    <button class="burger-btn" onclick="toggleMenu()">☰</button>
</div>

<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <span id="online-indicator">🟢 Online</span>
    <a href="dashboard" class="<?= $active_page == 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="transactions" class="<?= $active_page == 'transactions' ? 'active' : '' ?>">Transaksi</a>
    
    <?php if($role == "admin"): ?>
        <a href="expenses" class="<?= $active_page == 'expenses' ? 'active' : '' ?>">Pengeluaran</a>
        <a href="settings" class="<?= $active_page == 'settings' ? 'active' : '' ?>">Settings</a>
    <?php endif; ?>
    
    <a href="logout" class="logout-link">Logout</a>
</div>

<div class="content">
