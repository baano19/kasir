<?php
include "includes/db.php";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        category TEXT, 
        amount REAL,
        notes TEXT,
        created_at DATETIME
    )");
    echo "Tabel pengeluaran sukses dibuat, Bos! Silakan hapus file ini.";
} catch(Exception $e) {
    echo "Gagal bikin tabel: " . $e->getMessage();
}
?>