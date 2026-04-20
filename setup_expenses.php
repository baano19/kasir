<?php
include "includes/db.php";

try {
    // 1. Bikin tabel expenses (pengeluaran)
    $db->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        category TEXT, 
        amount REAL,
        notes TEXT,
        created_at DATETIME
    )");

    // 2. Bikin tabel settings (pengaturan dinamis)
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY, 
        value TEXT
    )");

    // 3. Masukkan default nominal uang makan (Rp 30.000) kalau belum ada
    $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('meal_allowance', '30000')");

    echo "<h3>Tabel Pengeluaran & Settings Sukses Dibuat, Bos! 🔥</h3>";
    echo "<p>Silakan hapus file <b>setup_expenses.php</b> ini demi keamanan.</p>";
    echo "<a href='settings.php'>Kembali ke Settings</a>";

} catch(Exception $e) {
    echo "Waduh, gagal bikin tabel: " . $e->getMessage();
}
?>