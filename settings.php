<?php include "includes/db.php"; checkLogin(); if($_SESSION["role"] != "admin") { header("Location: dashboard.php"); exit(); }
$tab = $_GET["tab"] ?? "services";

if(isset($_POST["add_s"])){ $db->prepare("INSERT INTO services (name, price) VALUES (?,?)")->execute([$_POST["n"], $_POST["p"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["up_s"])){ $db->prepare("UPDATE services SET name=?, price=? WHERE id=?")->execute([$_POST["n"], $_POST["p"], $_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["del_s"])){ $db->prepare("DELETE FROM services WHERE id=?")->execute([$_POST["id"]]); header("Location: settings.php?tab=services"); exit(); }
if(isset($_POST["add_c"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("INSERT INTO users (name,username,password,role) VALUES (?,?,'$h','barber')")->execute([$_POST["n"],$_POST["u"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["up_pc"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["del_c"])){ $db->prepare("DELETE FROM users WHERE id=? AND role='barber'")->execute([$_POST["id"]]); header("Location: settings.php?tab=capsters"); exit(); }
if(isset($_POST["up_adm"])){ $h=password_hash($_POST["pw"],PASSWORD_DEFAULT); $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h,$_SESSION["user_id"]]); header("Location: settings.php?tab=profile"); exit(); }
?>
<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
</head><body>
<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <a href="dashboard.php">Dashboard</a><a href="transactions.php">Transaksi</a><a href="settings.php" class="active">Settings</a><a href="logout.php" class="logout-link">Logout</a>
</div>
<div class="content">
    <h1>Settings</h1>
    <div class="tabs"><a href="?tab=services" class="<?=($tab=='services'?'active':'')?>">Layanan</a><a href="?tab=capsters" class="<?=($tab=='capsters'?'active':'')?>">Capster</a><a href="?tab=profile" class="<?=($tab=='profile'?'active':'')?>">Admin</a></div>
    
    <?php if($tab == "services"): ?>
        <div class="card"><h3>Tambah Layanan</h3><form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;"><input type="text" name="n" placeholder="Nama" style="flex:2;" required><input type="number" name="p" placeholder="Harga" style="flex:1;" required><button name="add_s">Tambah</button></form></div>
        <div class="table-container"><table><thead><tr><th>Nama</th><th>Harga</th><th>Aksi</th></tr></thead><tbody><?php $sv=$db->query("SELECT * FROM services")->fetchAll(); foreach($sv as $s): ?><tr><form method="POST"><input type="hidden" name="id" value="<?=$s['id']?>"><td><input type="text" name="n" value="<?=$s['name']?>"></td><td><input type="number" name="p" value="<?=$s['price']?>"></td><td><button name="up_s">Save</button> <button name="del_s" class="del" onclick="return confirm('Hapus?')">Del</button></td></form></tr><?php endforeach; ?></tbody></table></div>
    
    <?php elseif($tab == "capsters"): ?>
        <div class="card"><h3>Tambah Capster</h3><form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;"><input type="text" name="n" placeholder="Nama" style="flex:1;" required><input type="text" name="u" placeholder="User" style="flex:1;" required><input type="text" name="pw" placeholder="Pass" style="flex:1;" required><button name="add_c">Tambah</button></form></div>
        <div class="table-container"><table><thead><tr><th>Nama</th><th>User</th><th>Ganti Pass</th><th>Aksi</th></tr></thead><tbody><?php $cs=$db->query("SELECT * FROM users WHERE role='barber'")->fetchAll(); foreach($cs as $c): ?><tr><td><?=$c['name']?></td><td><?=$c['username']?></td><td><form method="POST" style="display:flex; gap:5px;"><input type="hidden" name="id" value="<?=$c['id']?>"><input type="text" name="pw" placeholder="Pass Baru" required><button name="up_pc">OK</button></form></td><td><form method="POST"><input type="hidden" name="id" value="<?=$c['id']?>"><button name="del_c" class="del" onclick="return confirm('Hapus?')">Hapus</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    
    <?php else: ?>
        <div class="card" style="max-width:400px;"><h3>Ganti Password Admin</h3><form method="POST"><input type="text" name="pw" placeholder="Password Baru" required><button name="up_adm">Update</button></form></div>
    <?php endif; ?>
</div>
<script>function toggleMenu(){document.getElementById('sidebar').classList.toggle('active');}</script>
</body></html>
