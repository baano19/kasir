<?php include "includes/db.php"; checkLogin(); 
if($_SESSION["role"] != "admin") { header("Location: dashboard.php"); exit(); }
$tab = $_GET["tab"] ?? "branches";

// --- LOGIC CABANG ---
if(isset($_POST["add_b"])){ $db->prepare("INSERT INTO branches (name, meal_allowance) VALUES (?, ?)")->execute([$_POST["n"], $_POST["m"]]); header("Location: settings.php?tab=branches"); exit(); }
if(isset($_POST["up_b"])){ $db->prepare("UPDATE branches SET name=?, meal_allowance=? WHERE id=?")->execute([$_POST["n"], $_POST["m"], $_POST["id"]]); header("Location: settings.php?tab=branches"); exit(); }
if(isset($_POST["del_b"])){ $db->prepare("DELETE FROM branches WHERE id=?")->execute([$_POST["id"]]); header("Location: settings.php?tab=branches"); exit(); }

// --- LOGIC LAYANAN ---
if(isset($_POST["add_s"])){ $db->prepare("INSERT INTO services (name, price, branch_id) VALUES (?,?,?)")->execute([$_POST["n"], $_POST["p"], $_POST["b_id"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["up_s"])){ $db->prepare("UPDATE services SET name=?, price=?, branch_id=? WHERE id=?")->execute([$_POST["n"], $_POST["p"], $_POST["b_id"], $_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["del_s"])){ $db->prepare("DELETE FROM services WHERE id=?")->execute([$_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }

// --- LOGIC CAPSTER ---
if(isset($_POST["add_c"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("INSERT INTO users (name,username,password,role,branch_id) VALUES (?,?,'$h','barber',?)")->execute([$_POST["n"],$_POST["u"],$_POST["b_id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["up_pc"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["up_bc"])){ $db->prepare("UPDATE users SET branch_id=? WHERE id=?")->execute([$_POST["b_id"],$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["del_c"])){ $db->prepare("DELETE FROM users WHERE id=? AND role='barber'")->execute([$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }

// --- LOGIC ADMIN ---
if(isset($_POST["up_adm"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_SESSION["user_id"]]); header("Location: settings.php?tab=profile"); exit(); }

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>Settings BPOS</title>
</head><body>
<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2><a href="dashboard.php">Dashboard</a><a href="transactions.php">Transaksi</a><a href="expenses.php">Pengeluaran</a>
    <a href="settings.php" class="active">Settings</a><a href="logout.php" class="logout-link">Logout</a>
</div>
<div class="content">
    <h1>Settings Multi-Cabang</h1>
    <div class="tabs">
        <a href="?tab=branches" class="<?=($tab=='branches'?'active':'')?>">Cabang</a>
        <a href="?tab=services" class="<?=($tab=='services'?'active':'')?>">Layanan</a>
        <a href="?tab=capsters" class="<?=($tab=='capsters'?'active':'')?>">Capster</a>
        <a href="?tab=profile" class="<?=($tab=='profile'?'active':'')?>">Sistem</a>
    </div>
    
    <?php if($tab == "branches"): ?>
        <div class="card">
            <h3>Tambah Cabang & Uang Makan</h3>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="n" placeholder="Nama Cabang" style="flex:2; min-width:150px;" required>
                <input type="number" name="m" placeholder="Uang Makan (Rp)" style="flex:1; min-width:120px;" required>
                <button name="add_b">Tambah</button>
            </form>
        </div>
        <div class="table-container"><table><thead><tr><th>ID</th><th>Nama Cabang</th><th>Uang Makan/Hari</th><th>Aksi</th></tr></thead><tbody>
            <?php foreach($branches as $b): ?>
            <tr><form method="POST"><input type="hidden" name="id" value="<?=$b['id']?>"><td><?=$b['id']?></td>
                <td><input type="text" name="n" value="<?=$b['name']?>"></td>
                <td><input type="number" name="m" value="<?=$b['meal_allowance']?>"></td>
                <td style="white-space:nowrap;"><button name="up_b" style="padding:5px 10px!important;">Save</button> <button name="del_b" class="del" onclick="return confirm('Hapus cabang?')" style="padding:5px 10px!important;">Del</button></td>
            </form></tr>
            <?php endforeach; ?>
        </tbody></table></div>

    <?php elseif($tab == "services"): ?>
        <div class="card"><h3>Tambah Layanan</h3><form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
            <select name="b_id" required style="flex:1; min-width:150px;"><option value="">-- Pilih Cabang --</option><?php foreach($branches as $b) echo "<option value='{$b['id']}'>{$b['name']}</option>"; ?></select>
            <input type="text" name="n" placeholder="Nama Layanan" style="flex:2; min-width:150px;" required>
            <input type="number" name="p" placeholder="Harga" style="flex:1; min-width:100px;" required><button name="add_s">Tambah</button>
        </form></div>
        <div class="table-container"><table><thead><tr><th>Cabang</th><th>Nama Layanan</th><th>Harga</th><th>Aksi</th></tr></thead><tbody>
            <?php $sv=$db->query("SELECT s.*, b.name as bname FROM services s LEFT JOIN branches b ON s.branch_id=b.id ORDER BY s.branch_id ASC, s.name ASC")->fetchAll(); foreach($sv as $s): ?>
            <tr><form method="POST"><input type="hidden" name="id" value="<?=$s['id']?>">
                <td><select name="b_id"><?php foreach($branches as $b){ $sel=($b['id']==$s['branch_id'])?'selected':''; echo "<option value='{$b['id']}' $sel>{$b['name']}</option>"; } ?></select></td>
                <td><input type="text" name="n" value="<?=$s['name']?>"></td><td><input type="number" name="p" value="<?=$s['price']?>"></td>
                <td style="white-space:nowrap;"><button name="up_s" style="padding:5px 10px!important;">Save</button> <button name="del_s" class="del" onclick="return confirm('Hapus?')" style="padding:5px 10px!important;">Del</button></td>
            </form></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    
    <?php elseif($tab == "capsters"): ?>
        <?php else: ?>
        <div class="card" style="max-width:400px; margin-bottom:20px;"><h3>Ganti Password Admin</h3><form method="POST"><input type="text" name="pw" placeholder="Password Baru" required><button name="up_adm" style="margin-top:10px;">Update</button></form></div>
    <?php endif; ?>
</div>
<script>function toggleMenu(){document.getElementById('sidebar').classList.toggle('active');}</script>
</body></html>