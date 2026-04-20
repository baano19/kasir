<?php
include "includes/db.php";

echo "<h2>Force System Sync...</h2>";

try {
    $db->beginTransaction();

    // 1. SETTINGS
    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('meal_allowance', '30000')");
    echo "✅ Settings table ready.<br>";

    // 2. BRANCHES
    $db->exec("CREATE TABLE IF NOT EXISTS branches (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, meal_allowance REAL DEFAULT 30000)");
    $cek_b = $db->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    if($cek_b == 0) {
        $db->exec("INSERT INTO branches (name, meal_allowance) VALUES ('Cabang Utama', 30000)");
    }
    echo "✅ Branches table ready.<br>";

    // 3. EXPENSES
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

    // 4. RESET SERVICES (Hapus dan buat baru biar bersih dari error UNIQUE/Column)
    $db->exec("DROP TABLE IF EXISTS services");
    $db->exec("CREATE TABLE services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        price REAL,
        branch_id INTEGER DEFAULT 1
    )");
    echo "✅ Services table RECREATED (Fresh & Clean).<br>";

    // 5. SAFE ALTER UNTUK TABEL LAIN
    $tables = ['users', 'transactions'];
    foreach($tables as $t) {
        // Cek dulu kolomnya udah ada blm biar ga error
        $columns = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('branch_id', $columns)) {
            $db->exec("ALTER TABLE $t ADD COLUMN branch_id INTEGER DEFAULT 1");
            echo "✅ Added branch_id to $t.<br>";
        } else {
            echo "ℹ️ Column branch_id already exists in $t.<br>";
        }
    }

    $db->commit();
    echo "<h3>🚀 SYNC BERHASIL!</h3>";
    echo "<p>Sekarang login ke Admin, masuk ke <b>Settings > Layanan</b>, terus input ulang harga-harganya ya Bos.</p>";

} catch(Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "❌ ERROR LAGI: " . $e->getMessage();
}
?>