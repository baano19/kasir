<?php include "includes/db.php"; checkLogin();

// Update Zona Waktu
date_default_timezone_set('Asia/Jakarta');

$role = $_SESSION["role"]; $uid = $_SESSION["user_id"];

// --- 1. LOGIC SIMPAN TRANSAKSI ---
if(isset($_POST["add"])){ 
    $waktu_sekarang = date('Y-m-d H:i:s'); 
    $target_uid = $_POST["target_uid"]; 
    $st = $db->prepare("INSERT INTO transactions (user_id, service_name, amount, created_at) VALUES (?,?,?,?)"); 
    $st->execute([$target_uid, $_POST["s_name"], $_POST["s_price"], $waktu_sekarang]); 
    header("Location: transactions.php"); exit(); 
}

// --- 2. LOGIC UPDATE & DELETE (Admin Only) ---
if(isset($_POST["update_t"]) && $role == "admin"){ 
    $st = $db->prepare("UPDATE transactions SET service_name=?, amount=? WHERE id=?"); 
    $st->execute([$_POST["edit_service"], $_POST["edit_amount"], $_POST["t_id"]]); 
    header("Location: transactions.php"); exit(); 
}
if(isset($_POST["delete_t"]) && $role == "admin"){ 
    $st = $db->prepare("DELETE FROM transactions WHERE id=?"); 
    $st->execute([$_POST["t_id"]]); header("Location: transactions.php"); exit(); 
}

// --- 3. AMBIL DATA CAPSTER ---
$bs = $db->query("SELECT id, name FROM users WHERE role='barber' ORDER BY name ASC")->fetchAll();

// --- 4. LOGIC FILTER & PAGINATION ---
$limit = 10; 
$page = (int)($_GET["page"] ?? 1); 
$off = ($page - 1) * $limit;
$where = []; $p = []; 

$start_date = "";
$end_date = "";
$date_range_val = "";

if($role == "barber"){ 
    // Filter khusus Capster
    $where[] = "t.user_id=?"; $p[] = $uid; 
    
    // Cek parameter date_range (Format dari Flatpickr: "YYYY-MM-DD to YYYY-MM-DD")
    if(isset($_GET['date_range'])) {
        $date_range_val = trim($_GET['date_range']);
        if(!empty($date_range_val)) {
            $dates = explode(" to ", $date_range_val);
            $start_date = $dates[0];
            $end_date = isset($dates[1]) ? $dates[1] : $dates[0]; // Kalau cuma klik 1 tanggal
        }
    } else {
        // Default load: Hari ini
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $date_range_val = $start_date; 
    }

    if(!empty($start_date)) {
        $where[] = "date(t.created_at) >= ?"; $p[] = $start_date;
    }
    if(!empty($end_date)) {
        $where[] = "date(t.created_at) <= ?"; $p[] = $end_date . " 23:59:59"; // Biar full seharian
    }

    // Label buat di kotak ringkasan Gross
    if(empty($start_date) && empty($end_date)) {
        $date_label = "Semua Waktu";
    } else if($start_date == $end_date) {
        $date_label = date('d M Y', strtotime($start_date));
    } else {
        $date_label = date('d M', strtotime($start_date)) . " - " . date('d M', strtotime($end_date));
    }

} else { 
    // Filter Admin: Sesuai yang dipilih di form
    if(!empty($_GET["f_b"])){ $where[] = "t.user_id=?"; $p[] = $_GET["f_b"]; } 
    if(!empty($_GET["f_d"])){ $where[] = "date(t.created_at)=?"; $p[] = $_GET["f_d"]; } 
}
$w_sql = count($where) ? "WHERE ".implode(" AND ", $where) : "";

// Hitung Ringkasan Pendapatan (Khusus Capster)
$total_gross = 0;
$total_net = 0;
if($role == "barber") {
    $sum_query = $db->prepare("SELECT SUM(amount) FROM transactions t $w_sql");
    $sum_query->execute($p);
    $total_gross = $sum_query->fetchColumn() ?? 0;
    $total_net = $total_gross * 0.5;
}

// Hitung Total Halaman
$total_data = $db->prepare("SELECT COUNT(*) FROM transactions t $w_sql");
$total_data->execute($p);
$t_rows = $total_data->fetchColumn();
$t_pages = ceil($t_rows / $limit);

// Ambil List Transaksi
$logs = $db->prepare("SELECT t.*, u.name as b_name FROM transactions t JOIN users u ON t.user_id=u.id $w_sql ORDER BY t.id DESC LIMIT $limit OFFSET $off");
$logs->execute($p); 
$list = $logs->fetchAll();

// Parameter URL untuk Link Pagination
$url_params = "";
if(!empty($_GET['f_b'])) $url_params .= "&f_b=" . $_GET['f_b'];
if(!empty($_GET['f_d'])) $url_params .= "&f_d=" . $_GET['f_d'];
if(isset($_GET['date_range'])) $url_params .= "&date_range=" . urlencode($_GET['date_range']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <title>Transaksi BPOS</title>
</head>
<body>

<div class="mobile-header">
    <span style="color:var(--primary); font-weight:bold;">BPOS</span>
    <button class="burger-btn" onclick="toggleMenu()">☰</button>
</div>

<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="transactions.php" class="active">Transaksi</a>
    <?php if($role=="admin") echo "<a href='settings.php'>Settings</a>"; ?>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <h1>Data Transaksi</h1>
    
    <div class="card" style="border-left-color: var(--primary);">
        <h3 style="margin-top:0;">Input Transaksi Baru</h3>
        <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
            <?php if($role == "admin"): ?>
                <select name="target_uid" required style="width:100%!important; margin:0!important; box-sizing:border-box!important;">
                    <option value="">-- Pilih Capster --</option>
                    <?php foreach($bs as $b) echo "<option value='{$b['id']}'>{$b['name']}</option>"; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="target_uid" value="<?= $uid ?>">
            <?php endif; ?>

            <select name="s_name" onchange="document.getElementById('pr').value=this.options[this.selectedIndex].getAttribute('data-p')" required style="width:100%!important; margin:0!important; box-sizing:border-box!important;">
                <option value="">-- Pilih Layanan --</option>
                <?php $sv=$db->query("SELECT * FROM services")->fetchAll(); foreach($sv as $s) echo "<option value='{$s['name']}' data-p='{$s['price']}'>{$s['name']}</option>"; ?>
            </select>
            <input type="number" name="s_price" id="pr" placeholder="Harga" readonly style="width:100%!important; margin:0!important; box-sizing:border-box!important;">
            <button name="add" style="margin:0!important; padding:10px!important;">Simpan Transaksi</button>
        </form>
    </div>

    <?php if($role == "admin"): ?>
    <div class="card" style="background: #1a1a1a; border-left: 4px solid orange; overflow: hidden; padding: 15px !important;">
        <h3 style="margin-top:0; color: orange; font-size: 1.1rem;">Filter Data</h3>
        <form method="GET" style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
            <div style="width: 100%; position: relative;">
                <select name="f_b" style="width: 100% !important; height: 42px; border-radius: 8px; background: #252525; color: white; border: 1px solid #444; padding: 0 10px;">
                    <option value="">Semua Capster</option>
                    <?php foreach($bs as $b) { 
                        $sel = (isset($_GET['f_b']) && $_GET['f_b'] == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $sel>{$b['name']}</option>"; 
                    } ?>
                </select>
            </div>
            <div style="width: 100%; max-width: 100%; overflow: hidden; display: block;">
                <input type="date" name="f_d" value="<?= $_GET['f_d'] ?? '' ?>" 
                    style="width: 100% !important; max-width: 100% !important; height: 42px !important; box-sizing: border-box !important; display: block !important; -webkit-appearance: none !important; background: #252525 !important; color: white !important; border: 1px solid #444 !important; border-radius: 8px !important; padding: 0 10px !important; margin: 0 !important;">
            </div>
            <div style="width: 100%;">
                <button type="submit" style="background:orange !important; color: white !important; width: 100% !important; height: 42px; font-weight: bold; border-radius: 8px; border: none; cursor: pointer;">Cari Data</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if($role == "barber"): ?>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px;">
        
        <div class="card" style="background: #252525; border-left-color: #4CAF50; margin: 0; padding: 12px 15px;">
            <form method="GET" id="form-filter-capster" style="display: flex; align-items: center; gap: 10px; margin: 0; flex-wrap: wrap;">
                
                <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 220px;">
                    <label style="font-size: 0.85rem; color: #ccc; white-space: nowrap;">Pilih Tanggal:</label>
                    <div style="width: 100%; position: relative;">
                        <input type="text" id="date_range_picker" name="date_range" value="<?= $date_range_val ?>" placeholder="Semua Waktu" 
                            style="width: 100% !important; height: 35px !important; margin: 0 !important; padding: 0 10px !important; background: #1a1a1a !important; color: white !important; border: 1px solid #444 !important; border-radius: 6px; box-sizing: border-box !important; cursor: pointer; text-align: center;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <button type="submit" style="padding: 0 15px; height: 35px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: bold; margin: 0 !important;">Cari</button>
                    
                    <a href="transactions.php" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; height: 35px; background: #444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.8rem; box-sizing: border-box;">Hari Ini</a>
                    
                    <a href="transactions.php?date_range=" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; height: 35px; background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; text-decoration: none; border-radius: 6px; font-size: 0.8rem; box-sizing: border-box;">Clear</a>
                </div>
            </form>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div style="background: #252525; padding: 10px 15px; border-radius: 8px; border: 1px solid #444; flex: 1; min-width: 140px;">
                <span style="font-size: 0.75rem; opacity: 0.7;">Gross (<?= $date_label ?>)</span>
                <div style="font-weight: bold; color: #fff; font-size: 1.1rem;">Rp <?= number_format($total_gross) ?></div>
            </div>
            <div style="background: #252525; padding: 10px 15px; border-radius: 8px; border: 1px solid #4CAF50; flex: 1; min-width: 140px;">
                <span style="font-size: 0.75rem; opacity: 0.7;">Pendapatan Bersih</span>
                <div style="font-weight: bold; color: #4CAF50; font-size: 1.1rem;">Rp <?= number_format($total_net) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <?php if($role=="admin") echo "<th>Barber</th>"; ?>
                    <th>Service</th>
                    <th>Gross</th>
                    <th>Net (50%)</th>
                    <th>Jam</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=$off+1; foreach($list as $l): $is_ed=($role=='admin' && ($_GET['edit_id']??'')==$l['id']); ?>
                <tr>
                    <form method="POST">
                    <input type="hidden" name="t_id" value="<?=$l['id']?>">
                    <td><?=$no++?></td>
                    <?php if($role=="admin") echo "<td>{$l['b_name']}</td>"; ?>
                    <td><?= $is_ed ? "<input type='text' name='edit_service' value='{$l['service_name']}' style='width:80px'>" : $l['service_name'] ?></td>
                    <td><?= $is_ed ? "<input type='number' name='edit_amount' value='{$l['amount']}' style='width:80px'>" : "Rp ".number_format($l['amount']) ?></td>
                    <td style="color:var(--accent); font-weight:bold;">Rp <?=number_format($l['amount']*0.5)?></td>
                    <td>
                        <?php if($role=="admin"): ?>
                            <?php if($is_ed): ?>
                                <button name="update_t" style="background: var(--accent) !important; padding: 5px 10px !important;">OK</button>
                                <a href="transactions.php" style="color: #ccc; margin-left: 5px; text-decoration: none;">X</a>
                            <?php else: ?>
                                <a href="?edit_id=<?=$l['id']?>&page=<?=$page?><?=$url_params?>" style="color:var(--primary); text-decoration: none; font-size: 0.9rem;">Edit</a> 
                                <span style="color: #444;">|</span>
                                <button name="delete_t" onclick="return confirm('Hapus?')" style="background: #ff4d4d !important; color: white !important; border: none !important; padding: 6px 12px !important; border-radius: 6px !important; cursor: pointer !important; margin-left: 5px !important; display: inline-block !important; width: auto !important;">Del</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= substr($l['created_at'],11,5) ?>
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
        <?php if($page > 1): ?>
            <a href="?page=<?= $page-1 ?><?= $url_params ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php for($i=1; $i<=$t_pages; $i++): $act = ($page == $i) ? 'active' : ''; ?>
            <a href="?page=<?= $i . $url_params ?>" class="<?= $act ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if($page < $t_pages): ?>
            <a href="?page=<?= $page+1 ?><?= $url_params ?>">&raquo; Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    if(document.getElementById("date_range_picker")) {
        flatpickr("#date_range_picker", {
            mode: "range",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d M Y", // Biar tampilannya cakep (ex: 20 Apr 2026)
            disableMobile: "true", // WAJIB biar di HP tetep muncul kalender range-nya, bukan kalender bawaan HP
            
            // Script sakti buat nambahin tombol "Ke Hari Ini" di dalem pop-up kalender
            onReady: function(selectedDates, dateStr, instance) {
                const btnToday = document.createElement("button");
                btnToday.innerHTML = "Ke Hari Ini";
                btnToday.style.cssText = "display: block; width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; cursor: pointer; font-weight: bold; font-size: 14px; border-radius: 0 0 5px 5px;";
                btnToday.type = "button";
                
                // Pas tombol diklik, kalender langsung pindah ke hari ini dan nutup
                btnToday.addEventListener("click", function() {
                    instance.setDate(new Date()); // Set ke hari ini
                    instance.close(); // Tutup pop-up
                });
                
                instance.calendarContainer.appendChild(btnToday);
            }
        });
    }

    function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); }
    document.addEventListener('click', function(event) {
        var sidebar = document.getElementById('sidebar');
        var burger = document.querySelector('.burger-btn');
        if (sidebar.classList.contains('active') && !sidebar.contains(event.target) && !burger.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
</script>
</body>
</html>