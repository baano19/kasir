<?php
include "includes/db.php";

try {
    // 1. Bikin tabel Cabang
    $db->exec("CREATE TABLE IF NOT EXISTS branches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT
    )");

    // 2. Bikin 1 cabang default (Biar data lama lo masuk ke sini)
    $cek = $db->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    if($cek == 0) {
        $db->exec("INSERT INTO branches (name) VALUES ('Cabang Utama')");
    }

    // 3. Tambahin kolom branch_id ke semua tabel
    // Pake try-catch biar kalau udah pernah di-run nggak error
    try { $db->exec("ALTER TABLE users ADD COLUMN branch_id INTEGER DEFAULT 1"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE services ADD COLUMN branch_id INTEGER DEFAULT 1"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE transactions ADD COLUMN branch_id INTEGER DEFAULT 1"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE expenses ADD COLUMN branch_id INTEGER DEFAULT 1"); } catch(Exception $e) {}

    echo "<h3>Sistem Database Multi-Cabang Berhasil Diaktifkan! 🔥</h3>";
    echo "<p>Semua data lama otomatis masuk ke 'Cabang Utama'. Hapus file <b>upgrade_db.php</b> ini sekarang.</p>";

} catch(Exception $e) {
    echo "Gagal upgrade: " . $e->getMessage();
}
?>