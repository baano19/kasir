<?php
include "includes/db.php";
try {
    // Tambah kolom uang makan di tabel users
    $db->exec("ALTER TABLE users ADD COLUMN meal_allowance INTEGER DEFAULT 30000");
    echo "<h1>MANTAB BOS!</h1><p>Kolom Uang Makan berhasil ditambahkan ke tabel Capster.</p>";
} catch (Exception $e) {
    echo "<h1>INFO:</h1><p>Gak perlu panik, ini error wajar kalo kolomnya udah ada: " . $e->getMessage() . "</p>";
}
echo "<a href='settings'>Balik ke Settings</a>";
?>
