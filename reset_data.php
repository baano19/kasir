<?php
include "includes/db.php";

try {
    // 1. Hapus semua data transaksi
    $db->exec("DELETE FROM transactions");
    
    // 2. Reset urutan ID (biar No. balik lagi ke 1)
    $db->exec("DELETE FROM sqlite_sequence WHERE name='transactions'");
    
    echo "<h1>Data Transaksi Berhasil Dibersihkan!</h1>";
    echo "<p>Sekarang database lo udah bersih, siap buat gas transaksi asli.</p>";
    echo "<a href='dashboard.php'>Balik ke Dashboard</a>";
} catch(Exception $e) {
    echo "Waduh gagal reset: " . $e->getMessage();
}
?>
