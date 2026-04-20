<?php include "includes/db.php"; checkLogin();

date_default_timezone_set('Asia/Jakarta');
$role = $_SESSION["role"]; $uid = $_SESSION["user_id"];

// Ambil info cabang dan jatah uang makan capster yang login
$my_branch = $db->query("SELECT branch_id FROM users WHERE id=$uid")->fetchColumn() ?: 1;
$meal_allowance = $db->query("SELECT meal_allowance FROM branches WHERE id='$my_branch'")->fetchColumn() ?: 30000;

// --- LOGIC KLAIM UANG MAKAN ---
if(isset($_POST["claim_meal"]) && $role == "barber"){
    $tgl_skrg = date('Y-m-d');
    $cek = $db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND category='Makan' AND date(created_at)=?");
    $cek->execute([$uid, $tgl_skrg]);
    if($cek->fetchColumn() == 0){
        // Uang makan masuk ke tabel expenses dengan tag branch_id capster tersebut
        $st = $db->prepare("INSERT INTO expenses (user_id, category, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)");
        $st->execute([$uid, 'Makan', $meal_allowance, 'Uang Makan Harian', date('Y-m-d H:i:s'), $my_branch]);
        header("Location: transactions.php"); exit();
    }
}

// --- LOGIC SIMPAN TRANSAKSI ---
if(isset($_POST["add"])){ 
    $waktu_sekarang = date('Y-m-d H:i:s'); 
    $target_uid = $_POST["target_uid"]; 
    // Transaksi masuk ke branch_id capster yang dituju
    $t_branch = $db->query("SELECT branch_id FROM users WHERE id='$target_uid'")->fetchColumn() ?: 1;
    
    $st = $db->prepare("INSERT INTO transactions (user_id, service_name, amount, created_at, branch_id) VALUES (?,?,?,?,?)"); 
    $st->execute([$target_uid, $_POST["s_name"], $_POST["s_price"], $waktu_sekarang, $t_branch]); 
    header("Location: transactions.php"); exit(); 
}

// --- LOGIC UPDATE & DELETE ---
if(isset($_POST["update_t"]) && $role == "admin"){ 
    $st = $db->prepare("UPDATE transactions SET service_name=?, amount=? WHERE id=?"); 
    $st->execute([$_POST["edit_service"], $_POST["edit_amount"], $_POST["t_id"]]); 
    header("Location: transactions.php"); exit(); 
}
if(isset($_POST["delete_t"]) && $role == "admin"){ 
    $st = $db->prepare("DELETE FROM transactions WHERE id=?"); 
    $st->execute([$_POST["t_id"]]); header("Location: transactions.php"); exit(); 
}

// Data Capster (Khusus Admin filter by nama)
$bs = $db->query("SELECT u.id, u.name, b.name as bname FROM users u LEFT JOIN branches b ON u.branch_id=b.id WHERE u.role='barber' ORDER BY b.id, u.name ASC")->fetchAll();

$limit = 10; $page = (int)($_GET["page"] ?? 1); $off = ($page - 1) * $limit;
$where = []; $p = []; 

$start_date = ""; $end_date = "";

if($role == "barber"){ 
    $where[] = "t.user_id=?"; $p[] = $uid; 
    if(!empty($_GET['start_date'])) { $start_date = $_GET["start_date"]; $end_date = $_GET["end_date"]; } 
    else { $start_date = date('Y-m-d'); $end_date = date('Y-m-d'); }
    
    if(!empty($start_date)) { $where[] = "date(t.created_at) >= ?"; $p[] = $start_date; }
    if(!empty($end_date)) { $where[] = "date(t.created_at) <= ?"; $p[] = $end_date . " 23:59:59"; }
    $date_label = ($start_date == $end_date) ? date('d M Y', strtotime($start_date)) : date('d M', strtotime($start_date)) . " - " . date('d M', strtotime($end_date));
} else { 
    if(!empty($_GET["f_b"])){ $where[] = "t.user_id=?"; $p[] = $_GET["f_b"]; } 
    if(!empty($_GET["f_d"])){ $where[] = "date(t.created_at)=?"; $p[] = $_GET["f_d"]; } 
}
$w_sql = count($where) ? "WHERE ".implode(" AND ", $where) : "";

$total_gross = 0; $total_net = 0;
if($role == "barber") {
    $sum_query = $db->prepare("SELECT SUM(amount) FROM transactions t $w_sql");
    $sum_query->execute($p);
    $total_gross = $sum_query->fetchColumn() ?? 0;
    $total_net = $total_gross * 0.5;
}

$t_rows = $db->prepare("SELECT COUNT(*) FROM transactions t $w_sql"); $t_rows->execute($p); $t_pages = ceil($t_rows->fetchColumn() / $limit);

$logs = $db->prepare("SELECT t.*, u.name as b_name, b.name as c_name FROM transactions t JOIN users u ON t.user_id=u.id LEFT JOIN branches b ON t.branch_id=b.id $w_sql ORDER BY t.id DESC LIMIT $limit OFFSET $off");
$logs->execute($p); 
$list = $logs->fetchAll();

$url_params = "";
if(!empty($_GET['f_b'])) $url_params .= "&f_b=" . $_GET['f_b'];
if(!empty($_GET['f_d'])) $url_params .= "&f_d=" . $_GET['f_d'];
if(!empty($_GET['start_date'])) { $url_params .= "&start_date=" . $_GET['start_date'] . "&end_date=" . $_GET['end_date']; }
?>

<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>Transaksi BPOS</title>
</head><body>

<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2><a href="dashboard.php">Dashboard</a><a href="transactions.php" class="active">Transaksi</a>
    <?php if($role=="admin"): ?><a href="expenses.php">Pengeluaran</a><a href="settings.php">Settings</a><?php endif; ?>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <h1>Data Transaksi</h1>
    
    <?php if($role == "barber"): 
        $cek_makan = $db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND category='Makan' AND date(created_at)=?");
        $cek_makan->execute([$uid, date('Y-m-d')]); $sdh_makan = $cek_makan->fetchColumn();
    ?>
    <div class="card" style="border-left-color: orange; background: #252525; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <span style="font-size: 0.9rem;">Uang Makan Hari Ini: <b><?= $sdh_makan ? "✅ Sudah Diambil" : "❌ Belum Diambil" ?></b></span>
            <?php if(!$sdh_makan): ?>
                <form method="POST"><button name="claim_meal" style="background: orange !important; width: auto !important; padding: 5px 15px !important; font-size: 0.8rem; margin: 0 !important; color: white !important;">Ambil Rp <?= number_format($meal_allowance) ?></button></form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="border-left-color: var(--primary);">
        <h3 style="margin-top:0;">Input Transaksi Baru</h3>
        <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
            <?php if($role == "admin"): ?>
                <select name="target_uid" required style="width:100%!important; margin:0!important;">
                    <option value="">-- Pilih Capster --</option>
                    <?php foreach($bs as $b) echo "<option value='{$b['id']}'>[{$b['bname']}] {$b['name']}</option>"; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="target_uid" value="<?= $uid ?>">
            <?php endif; ?>

            <select name="s_name" onchange="document.getElementById('pr').value=this.options[this.selectedIndex].getAttribute('data-p')" required style="width:100%!important; margin:0!important;">
                <option value="">-- Pilih Layanan --</option>
                <?php 
                if($role == "admin"){
                    $sv=$db->query("SELECT s.*, b.name as bname FROM services s JOIN branches b ON s.branch_id=b.id ORDER BY b.id, s.name")->fetchAll(); 
                    foreach($sv as $s) echo "<option value='{$s['name']}' data-p='{$s['price']}'>[{$s['bname']}] {$s['name']}</option>";
                } else {
                    $sv=$db->query("SELECT * FROM services WHERE branch_id='$my_branch' ORDER BY name")->fetchAll(); 
                    foreach($sv as $s) echo "<option value='{$s['name']}' data-p='{$s['price']}'>{$s['name']}</option>";
                }
                ?>
            </select>
            <input type="number" name="s_price" id="pr" placeholder="Harga" readonly style="width:100%!important; margin:0!important;">
            <button name="add" style="margin:0!important; padding:10px!important;">Simpan Transaksi</button>
        </form>
    </div>

    <?php if($role == "admin"): ?>
    <div class="card" style="background: #1a1a1a; border-left: 4px solid orange; overflow: hidden; padding: 15px !important;">
        <h3 style="margin-top:0; color: orange; font-size: 1.1rem;">Filter Data</h3>
        <form method="GET" style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
            <select name="f_b" style="width: 100% !important; height: 42px; border-radius: 8px; background: #252525; color: white; border: 1px solid #444; padding: 0 10px;">
                <option value="">Semua Capster</option>
                <?php foreach($bs as $b) { $sel = (isset($_GET['f_b']) && $_GET['f_b'] == $b['id']) ? 'selected' : ''; echo "<option value='{$b['id']}' $sel>[{$b['bname']}] {$b['name']}</option>"; } ?>
            </select>
            <input type="date" name="f_d" value="<?= $_GET['f_d'] ?? '' ?>" style="width: 100% !important; height: 42px !important; background: #252525 !important; color: white !important; border: 1px solid #444 !important; border-radius: 8px !important; padding: 0 10px !important; margin: 0 !important; box-sizing: border-box !important; -webkit-appearance: none !important;">
            <button type="submit" style="background:orange !important; color: white !important; height: 42px; font-weight: bold; border-radius: 8px; border: none;">Cari Data</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if($role == "barber"): ?>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px;">
        <div class="card" style="background: #252525; border-left-color: var(--primary); margin: 0; padding: 12px;">
            <form method="GET" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 0;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px;">
                    <input type="date" name="start_date" value="<?= $start_date ?>" required style="flex:1; min-width:0; height:36px!important; margin:0!important; background:#1a1a1a!important; color:white!important; border:1px solid #444!important; border-radius:6px; padding:0 8px!important; -webkit-appearance:none!important;">
                    <span style="color: #888; font-weight: bold;">-</span>
                    <input type="date" name="end_date" value="<?= $end_date ?>" required style="flex:1; min-width:0; height:36px!important; margin:0!important; background:#1a1a1a!important; color:white!important; border:1px solid #444!important; border-radius:6px; padding:0 8px!important; -webkit-appearance:none!important;">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" style="height: 36px; padding: 0 15px; background: var(--primary); color: white; border: none; border-radius: 6px; font-weight: bold;">Tampilkan</button>
                    <a href="transactions.php" style="display: flex; align-items: center; justify-content: center; height: 36px; padding: 0 15px; background: #444; color: white; text-decoration: none; border-radius: 6px;">Reset</a>
                </div>
            </form>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div style="background: #252525; padding: 10px 15px; border-radius: 8px; border: 1px solid #444; flex: 1; min-width: 140px;">
                <span style="font-size: 0.75rem; opacity: 0.7;">Gross (<?= $date_label ?>)</span>
                <div style="font-weight: bold; color: #fff; font-size: 1.1rem;">Rp <?= number_format($total_gross) ?></div>
            </div>
            <div style="background: #252525; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--primary); flex: 1; min-width: 140px;">
                <span style="font-size: 0.75rem; opacity: 0.7;">Pendapatan Bersih</span>
                <div style="font-weight: bold; color: var(--primary); font-size: 1.1rem;">Rp <?= number_format($total_net) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <?php if($role=="admin") echo "<th>Barber (Cabang)</th>"; ?>
                    <th>Service</th>
                    <th>Gross</th>
                    <th>Net (50%)</th>
                    <th style="white-space: nowrap;"><?= $role == "admin" ? "Aksi" : "Waktu" ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $no=$off+1; foreach($list as $l): $is_ed=($role=='admin' && ($_GET['edit_id']??'')==$l['id']); ?>
                <tr>
                    <form method="POST">
                    <input type="hidden" name="t_id" value="<?=$l['id']?>">
                    <td><?=$no++?></td>
                    <?php if($role=="admin") echo "<td><b>{$l['b_name']}</b><br><span style='font-size:0.7rem; color:#888;'>{$l['c_name']}</span></td>"; ?>
                    <td><?= $is_ed ? "<input type='text' name='edit_service' value='{$l['service_name']}' style='width:80px'>" : $l['service_name'] ?></td>
                    <td><?= $is_ed ? "<input type='number' name='edit_amount' value='{$l['amount']}' style='width:80px'>" : "Rp ".number_format($l['amount']) ?></td>
                    <td style="color:var(--accent); font-weight:bold;">Rp <?=number_format($l['amount']*0.5)?></td>
                    <td style="font-size: 0.8rem; white-space: nowrap;">
                        <?php if($role=="admin"): ?>
                            <?php if($is_ed): ?>
                                <button name="update_t" style="background: var(--accent) !important; padding: 5px 10px !important;">OK</button>
                                <a href="transactions.php" style="color: #ccc; margin-left: 5px; text-decoration: none;">X</a>
                            <?php else: ?>
                                <a href="?edit_id=<?=$l['id']?>&page=<?=$page?><?=$url_params?>" style="color:var(--primary); text-decoration: none;">Edit</a> | 
                                <button name="delete_t" onclick="return confirm('Hapus?')" style="background: #ff4d4d !important; color: white !important; border: none !important; padding: 6px 10px !important; border-radius: 6px !important;">Del</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= date('d M, h:i A', strtotime($l['created_at'])) ?>
                        <?php endif; ?>
                    </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if($t_pages > 1): ?>
    <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 5px; margin-top: 20px;">
        <?php if($page > 1): ?><a href="?page=<?= $page-1 ?><?= $url_params ?>">&laquo; Prev</a><?php endif; ?>
        <?php for($i=1; $i<=$t_pages; $i++): $act = ($page == $i) ? 'active' : ''; ?><a href="?page=<?= $i . $url_params ?>" class="<?= $act ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page < $t_pages): ?><a href="?page=<?= $page+1 ?><?= $url_params ?>">&raquo; Next</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script> function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); } </script>
</body></html>