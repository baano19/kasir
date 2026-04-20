<?php
include "includes/db.php";

try {
    $db->beginTransaction();

    // 1. Nambahin kolom uang makan per cabang
    try { $db->exec("ALTER TABLE branches ADD COLUMN meal_allowance REAL DEFAULT 30000"); } catch(Exception $e) {}

    // 2. Bikin tabel services sementara (TIDAK UNIQUE)
    $db->exec("CREATE TABLE IF NOT EXISTS services_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        price REAL,
        branch_id INTEGER DEFAULT 1
    )");

    // 3. Pindahin data lama ke tabel baru
    $db->exec("INSERT INTO services_new (id, name, price, branch_id) SELECT id, name, price, branch_id FROM services");

    // 4. Hapus tabel lama & ganti nama tabel baru
    $db->exec("DROP TABLE services");
    $db->exec("ALTER TABLE services_new RENAME TO services");

    $db->commit();
    echo "<h3>Database berhasil diperbaiki, Bos! 🔥</h3>";
    echo "<p>Sekarang lo bisa input nama service yang sama di cabang beda, dan atur uang makan per cabang.</p>";

} catch(Exception $e) {
    $db->rollBack();
    echo "Waduh gagal: " . $e->getMessage();
}
?>