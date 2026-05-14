<?php
// offline.php — Halaman fallback saat benar-benar tidak ada cache
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BPOS — Offline</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #121212; color: #e0e0e0;
      font-family: 'Segoe UI', system-ui, sans-serif;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      min-height: 100vh; text-align: center; padding: 24px;
    }
    .icon { font-size: 5rem; margin-bottom: 24px; animation: float 3s ease-in-out infinite; }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-12px); }
    }
    h1 { font-size: 1.8rem; color: #bb86fc; margin-bottom: 12px; font-weight: 700; }
    p { color: #888; font-size: 0.95rem; line-height: 1.7; max-width: 300px; margin-bottom: 24px; }
    .status-dot {
      display: inline-block; width: 8px; height: 8px;
      background: #cf6679; border-radius: 50%;
      margin-right: 8px; animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(1.4); }
    }
    .badge {
      padding: 8px 20px; background: #1e1e1e;
      border: 1px solid #333; border-radius: 999px;
      font-size: 0.82rem; color: #03dac6;
      display: inline-flex; align-items: center;
      margin-bottom: 24px;
    }
    button {
      padding: 13px 32px; background: #bb86fc; color: #000;
      border: none; border-radius: 10px; font-weight: 700;
      font-size: 1rem; cursor: pointer; transition: opacity 0.2s;
    }
    button:hover { opacity: 0.85; }
    .hint { margin-top: 16px; font-size: 0.78rem; color: #555; }
  </style>
</head>
<body>
  <div class="icon">✂️</div>
  <h1>Tidak Ada Koneksi</h1>
  <p>Internet kamu terputus dan halaman ini belum pernah dikunjungi sebelumnya.</p>

  <div class="badge">
    <span class="status-dot"></span>
    Menunggu koneksi internet...
  </div>

  <button onclick="window.location.reload()">🔄 Muat Ulang</button>
  <p class="hint">Halaman akan otomatis reload saat internet tersambung kembali.</p>

  <script>
    // Auto-reload saat kembali online
    window.addEventListener('online', () => {
      window.location.href = '/dashboard';
    });
  </script>
</body>
</html>
