<?php
include "includes/db.php";

echo "<h2>Starting System Sync...</h2>";

try {
    $db->beginTransaction();

    // 1. BUAT TABEL SETTINGS (Jika belum ada)
    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('meal_allowance', '30000')");
    echo "✅ Settings table ready.<br>";

    // 2. BUAT TABEL BRANCHES (Jika belum ada)
    $db->exec("CREATE TABLE IF NOT EXISTS branches (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, meal_allowance REAL DEFAULT 30000)");
    $cek_b = $db->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    if($cek_b == 0) {
        $db->exec("INSERT INTO branches (name, meal_allowance) VALUES ('Cabang Utama', 30000)");
    }
    echo "✅ Branches table ready.<br>";

    // 3. BUAT TABEL EXPENSES DENGAN STRUKTUR BARU
    $db->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        branch_id INTEGER DEFAULT 1,
        category TEXT, 
        amount REAL,
        notes TEXT,
        created_at DATETIME
    )");
    echo "✅ Expenses table ready.<br>";

    // 4. FIX STRUCTURE: CABUT UNIQUE CONSTRAINT PADA SERVICE NAME
    // Kita cek dulu apakah tabel services sudah benar
    $db->exec("CREATE TABLE IF NOT EXISTS services_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        price REAL,
        branch_id INTEGER DEFAULT 1
    )");
    
    // Pindahkan data jika tabel lama masih ada
    $check_old = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='services'")->fetch();
    if($check_old) {
        $db->exec("INSERT INTO services_new (id, name, price, branch_id) SELECT id, name, price, IFNULL(branch_id, 1) FROM services");
        $db->exec("DROP TABLE services");
    }
    $db->exec("ALTER TABLE services_new RENAME TO services");
    echo "✅ Services structure fixed (Multi-branch support).<br>";

    // 5. TAMBAH KOLOM BRANCH_ID KE TABEL LAIN (Safe Alter)
    $tables = ['users', 'transactions'];
    foreach($tables as $t) {
        try {
            $db->exec("ALTER TABLE $t ADD COLUMN branch_id INTEGER DEFAULT 1");
            echo "✅ Added branch_id to $t.<br>";
        } catch(Exception $e) {
            echo "ℹ️ Column branch_id already exists in $t.<br>";
        }
    }

    $db->commit();
    echo "<h3>🚀 SEMUA BERES! Database lo udah versi paling update.</h3>";
    echo "<p style='color:red;'>Wajib hapus file <b>master_init.php</b> ini sekarang!</p>";

} catch(Exception $e) {
    $db->rollBack();
    echo "❌ ERROR: " . $e->getMessage();
}
?>