<?php include "includes/db.php"; checkLogin(); 
if($_SESSION["role"] != "admin") { header("Location: dashboard.php"); exit(); }
$tab = $_GET["tab"] ?? "services";

// --- LOGIC LAYANAN ---
if(isset($_POST["add_s"])){ $db->prepare("INSERT INTO services (name, price) VALUES (?,?)")->execute([$_POST["n"], $_POST["p"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["up_s"])){ $db->prepare("UPDATE services SET name=?, price=? WHERE id=?")->execute([$_POST["n"], $_POST["p"], $_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["del_s"])){ $db->prepare("DELETE FROM services WHERE id=?")->execute([$_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }

// --- LOGIC CAPSTER ---
if(isset($_POST["add_c"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("INSERT INTO users (name,username,password,role) VALUES (?,?,'$h','barber')")->execute([$_POST["n"],$_POST["u"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["up_pc"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["del_c"])){ $db->prepare("DELETE FROM users WHERE id=? AND role='barber'")->execute([$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }

// --- LOGIC ADMIN ---
if(isset($_POST["up_adm"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_SESSION["user_id"]]); header("Location: settings.php?tab=profile"); exit(); }

// --- LOGIC UANG MAKAN (DINAMIS) ---
if(isset($_POST["update_meal"])){
    $st = $db->prepare("REPLACE INTO settings (key, value) VALUES ('meal_allowance', ?)");
    $st->execute([$_POST["meal_val"]]);
    header("Location: settings.php?tab=services"); exit();
}

// Ambil nilai uang makan sekarang dari database (Default 30000 kalau kosong)
$meal_allowance = $db->query("SELECT value FROM settings WHERE key='meal_allowance'")->fetchColumn();
if(!$meal_allowance) $meal_allowance = 30000; 

?>
<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>Settings BPOS</title>
</head><body>
<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="transactions.php">Transaksi</a>
    <a href="expenses.php">Pengeluaran</a> <a href="settings.php" class="active">Settings</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>
<div class="content">
    <h1>Settings</h1>
    <div class="tabs"><a href="?tab=services" class="<?=($tab=='services'?'active':'')?>">Layanan</a><a href="?tab=capsters" class="<?=($tab=='capsters'?'active':'')?>">Capster</a><a href="?tab=profile" class="<?=($tab=='profile'?'active':'')?>">Admin</a></div>
    
    <?php if($tab == "services"): ?>
        <div class="card">
            <h3>Tambah Layanan</h3>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="n" placeholder="Nama" style="flex:2; min-width:150px;" required>
                <input type="number" name="p" placeholder="Harga" style="flex:1; min-width:100px;" required>
                <button name="add_s">Tambah</button>
            </form>
        </div>
        
        <div class="table-container">
            <table>
                <thead><tr><th>Nama</th><th>Harga</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php $sv=$db->query("SELECT * FROM services")->fetchAll(); foreach($sv as $s): ?>
                    <tr>
                        <form method="POST">
                        <input type="hidden" name="id" value="<?=$s['id']?>">
                        <td><input type="text" name="n" value="<?=$s['name']?>"></td>
                        <td><input type="number" name="p" value="<?=$s['price']?>"></td>
                        <td style="white-space: nowrap;">
                            <button name="up_s" style="padding: 5px 10px !important;">Save</button> 
                            <button name="del_s" class="del" onclick="return confirm('Hapus?')" style="padding: 5px 10px !important;">Del</button>
                        </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="border-left-color: orange; margin-top: 30px;">
            <h3 style="color: orange; margin-top: 0;">Pengaturan Uang Makan Harian</h3>
            <p style="font-size: 0.8rem; color: #ccc; margin-top: -10px; margin-bottom: 15px;">Nominal ini akan dipotong otomatis saat Capster melakukan klaim.</p>
            <form method="POST" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="font-size: 0.8rem; color: #ccc;">Nominal (Rp)</label>
                    <input type="number" name="meal_val" value="<?= htmlspecialchars($meal_allowance) ?>" required 
                        style="width: 100% !important; margin: 0 !important; height: 38px !important; padding: 0 10px !important; background: #1a1a1a !important; color: white !important; border: 1px solid #444 !important; border-radius: 6px; box-sizing: border-box !important;">
                </div>
                <button name="update_meal" style="background: orange !important; color: white !important; height: 38px !important; padding: 0 20px !important; width: auto !important; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Simpan</button>
            </form>
        </div>
    
    <?php elseif($tab == "capsters"): ?>
        <div class="card">
            <h3>Tambah Capster</h3>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="n" placeholder="Nama" style="flex:1; min-width:120px;" required>
                <input type="text" name="u" placeholder="Username" style="flex:1; min-width:120px;" required>
                <input type="text" name="pw" placeholder="Password" style="flex:1; min-width:120px;" required>
                <button name="add_c">Tambah</button>
            </form>
        </div>
        <div class="table-container">
            <table>
                <thead><tr><th>Nama</th><th>User</th><th>Ganti Pass</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php $cs=$db->query("SELECT * FROM users WHERE role='barber'")->fetchAll(); foreach($cs as $c): ?>
                    <tr>
                        <td><?=$c['name']?></td>
                        <td><?=$c['username']?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="id" value="<?=$c['id']?>">
                                <input type="text" name="pw" placeholder="Pass Baru" required style="width: 100px;">
                                <button name="up_pc" style="padding: 5px 10px !important;">OK</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?=$c['id']?>">
                                <button name="del_c" class="del" onclick="return confirm('Hapus?')" style="padding: 5px 10px !important;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <?php else: ?>
        <div class="card" style="max-width:400px;">
            <h3>Ganti Password Admin</h3>
            <form method="POST">
                <input type="text" name="pw" placeholder="Password Baru" required>
                <button name="up_adm" style="margin-top: 10px;">Update</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<script>
    function toggleMenu(){document.getElementById('sidebar').classList.toggle('active');}
    document.addEventListener('click', function(event) {
        var sidebar = document.getElementById('sidebar');
        var burger = document.querySelector('.burger-btn');
        if (sidebar.classList.contains('active') && !sidebar.contains(event.target) && !burger.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
</script>
</body></html>