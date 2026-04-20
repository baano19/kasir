<?php include "includes/db.php"; checkLogin();
if($_SESSION["role"] != "admin") { header("Location: dashboard.php"); exit(); }

// --- LOGIC DELETE & EDIT ---
if(isset($_GET["del"])){ $db->prepare("DELETE FROM expenses WHERE id=?")->execute([$_GET["del"]]); header("Location: expenses.php"); exit(); }
if(isset($_POST["edit_exp"])){ $db->prepare("UPDATE expenses SET category=?, amount=?, notes=? WHERE id=?")->execute([$_POST["cat"], $_POST["amt"], $_POST["note"], $_POST["id"]]); header("Location: expenses.php"); exit(); }

// Query diperbarui buat ngambil nama cabang juga
$list = $db->query("SELECT e.*, u.name, b.name as bname FROM expenses e LEFT JOIN users u ON e.user_id = u.id LEFT JOIN branches b ON e.branch_id = b.id ORDER BY e.created_at DESC")->fetchAll();
?>

<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>Log Pengeluaran</title>
</head><body>
<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2><a href="dashboard.php">Dashboard</a><a href="transactions.php">Transaksi</a><a href="expenses.php" class="active">Pengeluaran</a><a href="settings.php">Settings</a><a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <h1>Riwayat Pengeluaran</h1>
    <div class="table-container">
        <table>
            <thead><tr><th>Tgl</th><th>Cabang</th><th>Kategori</th><th>Jumlah</th><th>Keterangan / Oleh</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach($list as $e): $is_ed = ($_GET["edit"]??0) == $e["id"]; ?>
                <tr>
                    <form method="POST"><input type="hidden" name="id" value="<?= $e["id"] ?>">
                    <td><?= date('d/m/y', strtotime($e["created_at"])) ?></td>
                    <td><span style="font-size:0.8rem; color:#888;"><?= $e["bname"] ?: 'Umum' ?></span></td>
                    <td><?= $is_ed ? "<input type='text' name='cat' value='{$e["category"]}' style='width:90px'>" : $e["category"] ?></td>
                    <td style="color:red;"><b><?= $is_ed ? "<input type='number' name='amt' value='{$e["amount"]}' style='width:80px'>" : "- Rp " . number_format($e["amount"]) ?></b></td>
                    <td>
                        <?php if($is_ed): ?>
                            <input type='text' name='note' value='<?=$e["notes"]?>' style='width:120px'>
                        <?php else: ?>
                            <?= $e["notes"] ?> <br><span style="font-size:0.75rem; color:#ccc;">(Oleh: <?= $e["name"] ?: 'Admin' ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if($is_ed): ?>
                            <button name="edit_exp" style="background:var(--accent)!important; padding:4px 8px!important;">OK</button>
                        <?php else: ?>
                            <a href="?edit=<?= $e["id"] ?>" style="color:var(--primary); text-decoration:none;">Edit</a> | 
                            <a href="?del=<?= $e["id"] ?>" onclick="return confirm('Hapus pengeluaran ini?')" style="color:red; text-decoration:none;">Del</a>
                        <?php endif; ?>
                    </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script> function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); } </script>
</body></html>