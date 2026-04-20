<?php include "includes/db.php"; checkLogin();

$role = $_SESSION["role"]; $id = $_SESSION["user_id"]; 

// Ambil List Cabang buat Filter
$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$b_filter = $_GET['b_filter'] ?? 'all'; // Default: Semua Cabang

// Parameter SQL buat memisahkan data tiap cabang
$b_sql_t = ($b_filter == 'all') ? "" : " AND t.branch_id = '$b_filter'";
$b_sql_e = ($b_filter == 'all') ? "" : " AND branch_id = '$b_filter'";
$b_sql_u = ($b_filter == 'all') ? "" : " AND u.branch_id = '$b_filter'";

// --- LOGIC INPUT PENGELUARAN (ADMIN ONLY) ---
if(isset($_POST["add_expense"]) && $role == 'admin'){
    $st = $db->prepare("INSERT INTO expenses (user_id, category, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)");
    $st->execute([$id, $_POST["exp_cat"], $_POST["exp_amount"], $_POST["exp_note"], date('Y-m-d H:i:s'), $_POST["exp_branch"]]);
    header("Location: dashboard.php"); exit();
}

// --- 1. LOGIKA EXPORT EXCEL (CLEAN & CLEAR) MULTI-CABANG ---
if (isset($_GET['export']) && $_GET['export'] == 'excel' && $role == 'admin') {
    $start = $_GET['start']; $end = $_GET['end'];
    $ex_b_filter = $_GET['b_filter'] ?? 'all';
    
    $ex_sql_t = ($ex_b_filter == 'all') ? "" : " AND t.branch_id = '$ex_b_filter'";
    $ex_sql_s = ($ex_b_filter == 'all') ? "" : " WHERE branch_id = '$ex_b_filter'";
    $ex_sql_e = ($ex_b_filter == 'all') ? "" : " AND branch_id = '$ex_b_filter'";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_BPOS_" . $start . "_sd_" . $end . ".xls");
    header("Pragma: no-cache"); header("Expires: 0");

    // Cari nama cabang buat di header Excel
    $branch_label = "SEMUA CABANG";
    if($ex_b_filter != 'all') {
        $branch_label = strtoupper($db->query("SELECT name FROM branches WHERE id='$ex_b_filter'")->fetchColumn());
    }

    $srv_query = $db->query("SELECT DISTINCT name FROM services $ex_sql_s ORDER BY name ASC")->fetchAll();
    $services = []; foreach($srv_query as $s) $services[] = $s['name'];

    echo "<table border='1' cellpadding='5'>";
    $total_cols = count($services) + 4;
    
    echo "<tr><th colspan='$total_cols' style='font-size:16px; background-color:#eeeeee; padding:10px;'>LAPORAN BPOS - $branch_label (" . date('d M Y', strtotime($start)) . " - " . date('d M Y', strtotime($end)) . ")</th></tr>";
    echo "<tr><td colspan='$total_cols' style='border:none; height:10px;'></td></tr>";

    $grand_total_gross = 0; $total_cust_all = 0;

    $dates_query = $db->prepare("SELECT DISTINCT date(t.created_at) as tgl FROM transactions t WHERE date(t.created_at) >= ? AND date(t.created_at) <= ? $ex_sql_t ORDER BY tgl ASC");
    $dates_query->execute([$start, $end]); $dates = $dates_query->fetchAll();

    if(count($dates) == 0) {
        echo "<tr><td colspan='$total_cols' align='center'>Tidak ada data transaksi di periode/cabang ini.</td></tr>";
    } else {
        foreach($dates as $d) {
            $tgl = $d['tgl'];
            echo "<tr style='background-color:#d9ead3;'><td colspan='$total_cols'><b>Tanggal: " . date('d M Y', strtotime($tgl)) . "</b></td></tr>";
            
            echo "<tr style='font-weight:bold; background-color:#f2f2f2; text-align:center;'>
                    <td>Nama Capster</td>";
            foreach($services as $srv) echo "<td>$srv</td>";
            echo "<td>Total Cust</td><td>Kotor (Rp)</td><td>Bersih 50% (Rp)</td></tr>";

            // Loop Capster sesuai cabang yg difilter
            $cap_sql = ($ex_b_filter == 'all') ? "" : " AND u.branch_id = '$ex_b_filter'";
            $capsters = $db->query("SELECT id, name FROM users u WHERE role='barber' $cap_sql ORDER BY name ASC")->fetchAll();
            
            foreach($capsters as $c) {
                $cid = $c['id'];
                $cek_tr = $db->prepare("SELECT COUNT(id) as cust, SUM(amount) as gross FROM transactions t WHERE t.user_id=? AND date(t.created_at)=? $ex_sql_t");
                $cek_tr->execute([$cid, $tgl]); $tr_data = $cek_tr->fetch();
                
                if($tr_data['cust'] > 0) {
                    $gross = $tr_data['gross']; $grand_total_gross += $gross; $total_cust_all += $tr_data['cust'];
                    
                    echo "<tr style='text-align:center;'><td style='text-align:left;'>{$c['name']}</td>";
                    foreach($services as $srv) {
                        $cek_srv = $db->prepare("SELECT COUNT(id) FROM transactions t WHERE t.user_id=? AND t.service_name=? AND date(t.created_at)=? $ex_sql_t");
                        $cek_srv->execute([$cid, $srv, $tgl]); $jml_srv = $cek_srv->fetchColumn();
                        echo "<td>" . ($jml_srv > 0 ? $jml_srv : "-") . "</td>";
                    }
                    echo "<td>{$tr_data['cust']}</td><td align='right'>" . number_format($gross) . "</td><td align='right' style='color:#0000ff;'>" . number_format($gross * 0.5) . "</td></tr>";
                }
            }
            echo "<tr><td colspan='$total_cols' style='border:none; height:15px;'></td></tr>"; 
        }
    }

    // --- BAGIAN REKAPITULASI (TOTAL) ---
    $exp_q = $db->prepare("SELECT SUM(amount) FROM expenses WHERE date(created_at) >= ? AND date(created_at) <= ? $ex_sql_e");
    $exp_q->execute([$start, $end]); $total_exp = $exp_q->fetchColumn() ?: 0;

    $kotor_admin = $grand_total_gross * 0.5; $bersih_admin = $kotor_admin - $total_exp;

    echo "<tr><td colspan='$total_cols' style='border-top:2px solid #000;'></td></tr>";
    echo "<tr><td colspan='2' style='font-weight:bold;'>TOTAL TRANSAKSI ($branch_label)</td><td colspan='" . (count($services)) . "'></td><td align='center'><b>$total_cust_all Cust</b></td><td align='right'><b>" . number_format($grand_total_gross) . "</b></td><td align='right' style='color:#0000ff;'><b>" . number_format($kotor_admin) . "</b></td></tr>";
    echo "<tr><td colspan='$total_cols' style='border:none; height:20px;'></td></tr>";
    
    echo "<tr><td colspan='2' rowspan='4' valign='top' style='font-size:16px;'><b>REKAP OWNER:</b></td><td colspan='2' style='font-weight:bold;'>Total Jatah Kotor Admin (50%)</td><td align='right'>Rp " . number_format($kotor_admin) . "</td></tr>";
    echo "<tr><td colspan='2' style='font-weight:bold;'>Total Pengeluaran (Makan + Operasional)</td><td align='right' style='color:red;'>- Rp " . number_format($total_exp) . "</td></tr>";
    echo "<tr><td colspan='2' style='font-weight:bold; font-size:14px; background-color:#c9daf8;'>PENDAPATAN BERSIH OWNER</td><td align='right' style='font-weight:bold; font-size:14px; background-color:#c9daf8;'>Rp " . number_format($bersih_admin) . "</td></tr>";
    echo "</table>"; exit(); 
}

// --- 2. LOGIKA FILTER WAKTU ---
$filter = $_GET['filter'] ?? 'today'; $today = date("Y-m-d");
if($filter == 'week') { $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = $today; $label_waktu = "7 Hari Terakhir"; }
elseif($filter == 'month') { $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); $label_waktu = "Bulan Ini"; }
else { $start_date = $today; $end_date = $today; $label_waktu = "Hari Ini"; }

// --- 3. AMBIL DATA SESUAI FILTER CABANG & WAKTU ---
if($role == "admin"){
    $kotor = $db->query("SELECT SUM(amount) FROM transactions t WHERE date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t")->fetchColumn() ?: 0;
    $cust = $db->query("SELECT COUNT(id) FROM transactions t WHERE date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t")->fetchColumn() ?: 0;
    $total_exp = $db->query("SELECT SUM(amount) FROM expenses WHERE date(created_at) >= '$start_date' AND date(created_at) <= '$end_date' $b_sql_e")->fetchColumn() ?: 0;
    $bersih_admin = ($kotor * 0.5) - $total_exp;

    $capster_stats = $db->query("SELECT u.name, COUNT(t.id) as total, SUM(t.amount) as gross FROM users u LEFT JOIN transactions t ON u.id = t.user_id AND date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t WHERE u.role = 'barber' $b_sql_u GROUP BY u.id")->fetchAll();
} else {
    $st1 = $db->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st1->execute([$id, $start_date, $end_date]); $income_gross = $st1->fetchColumn() ?: 0;
    $st2 = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st2->execute([$id, $start_date, $end_date]); $cust = $st2->fetchColumn() ?: 0;
    
    $st3 = $db->prepare("SELECT SUM(amount) FROM expenses WHERE user_id=? AND category='Makan' AND date(created_at) >= ? AND date(created_at) <= ?");
    $st3->execute([$id, $start_date, $end_date]); $my_meals = $st3->fetchColumn() ?: 0;
}

// --- 4. DATA GRAFIK ---
$labels = []; $chart_profit = []; $chart_cust = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days")); $labels[] = date("D", strtotime($d));
    if($role == "admin") { 
        $val = $db->query("SELECT SUM(amount) FROM transactions t WHERE date(t.created_at)='$d' $b_sql_t")->fetchColumn() ?: 0; 
        $c_count = $db->query("SELECT COUNT(id) FROM transactions t WHERE date(t.created_at)='$d' $b_sql_t")->fetchColumn() ?: 0;
    } else { 
        $st = $db->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND date(created_at)=?"); 
        $st->execute([$id, $d]); $val = $st->fetchColumn() ?: 0; 
        $st_c = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND date(created_at)=?"); 
        $st_c->execute([$id, $d]); $c_count = $st_c->fetchColumn() ?: 0;
    }
    $chart_profit[] = $val * 0.5; $chart_cust[] = $c_count;
} 
?>
<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Dashboard</title>
</head><body>

<div class="mobile-header"><span>BPOS</span><button class="burger-btn" onclick="toggleMenu()">☰</button></div>
<div class="sidebar" id="sidebar">
    <h2>BPOS</h2><a href="dashboard.php" class="active">Dashboard</a><a href="transactions.php">Transaksi</a>
    <?php if($role=="admin"): ?><a href="expenses.php">Pengeluaran</a><a href="settings.php">Settings</a><?php endif; ?>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; align-items:center; margin-bottom:20px; gap:10px;">
        <h1>Halo, <?= $_SESSION["name"] ?></h1>
        <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <?php if($role=="admin"): ?>
            <select name="b_filter" onchange="this.form.submit()" style="padding:8px 12px; border-radius:8px; background:#4CAF50; color:#fff; border:none; margin:0; font-weight:bold;">
                <option value="all" <?= $b_filter=='all'?'selected':'' ?>>🌍 Semua Cabang</option>
                <?php foreach($branches as $b): ?>
                    <option value="<?=$b['id']?>" <?= $b_filter==$b['id']?'selected':'' ?>>🏠 <?=$b['name']?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <select name="filter" onchange="this.form.submit()" style="padding:8px 12px; border-radius:8px; background:#252525; color:#fff; border:1px solid #444; margin:0;">
                <option value="today" <?= $filter=='today'?'selected':'' ?>>Hari Ini</option>
                <option value="week" <?= $filter=='week'?'selected':'' ?>>7 Hari Terakhir</option>
                <option value="month" <?= $filter=='month'?'selected':'' ?>>Bulan Ini</option>
            </select>
        </form>
    </div>

    <?php if($role=="admin"): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
        <div class="card" style="border-left-color: #4CAF50;">
            <h3>Cuan Bersih Owner (<?= $label_waktu ?>)</h3>
            <p style="font-size: 1.8rem; font-weight:bold; color: #4CAF50; margin: 10px 0;">Rp <?= number_format($bersih_admin) ?></p>
            <p style="font-size: 0.85rem; opacity:0.8;">Setelah dipotong pengeluaran Rp <?= number_format($total_exp) ?></p>
        </div>
        <div class="card">
            <h3>Total Customer</h3>
            <p style="font-size: 1.8rem; font-weight:bold; margin: 10px 0;"><?= $cust ?></p>
        </div>
    </div>

    <div class="card" style="border-left-color: red; margin-bottom: 30px;">
        <h3 style="color: red; margin-top: 0;">Input Pengeluaran Operasional</h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; align-items: end;">
            <div>
                <label style="font-size:0.75rem; color:#ccc;">Pilih Cabang</label>
                <select name="exp_branch" required style="width:100%!important; margin:0!important; height:35px!important; padding:0 10px!important; border-radius:6px; background:#1a1a1a; color:white; border:1px solid #444;">
                    <?php foreach($branches as $b) { $sl=($b['id']==$b_filter)?'selected':''; echo "<option value='{$b['id']}' $sl>{$b['name']}</option>"; } ?>
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color: #ccc;">Kategori</label>
                <input list="cat_options" name="exp_cat" placeholder="Listrik, Wifi..." required style="width:100%!important; margin:0!important; height:35px!important; padding:0 10px!important; border-radius:6px; background:#1a1a1a; color:white; border:1px solid #444;">
                <datalist id="cat_options"><option value="Listrik"><option value="Wifi"><option value="Alat Cukur"><option value="Iuran RT"></datalist>
            </div>
            <div>
                <label style="font-size:0.75rem; color: #ccc;">Jumlah (Rp)</label>
                <input type="number" name="exp_amount" required style="width:100%!important; margin:0!important; height:35px!important; padding:0 10px!important; border-radius:6px; background:#1a1a1a; color:white; border:1px solid #444;">
            </div>
            <div>
                <label style="font-size:0.75rem; color: #ccc;">Catatan</label>
                <input type="text" name="exp_note" placeholder="Ket..." style="width:100%!important; margin:0!important; height:35px!important; padding:0 10px!important; border-radius:6px; background:#1a1a1a; color:white; border:1px solid #444;">
            </div>
            <button name="add_expense" style="background: red !important; height: 35px !important; padding: 0 !important; border-radius: 6px; font-weight: bold; color: white;">Simpan</button>
        </form>
    </div>

    <div class="card" style="border-left-color:#4CAF50; margin-bottom: 30px; overflow: hidden;">
        <h3 style="margin-top:0; color:#4CAF50;">Export Rekap Excel <?= ($b_filter=='all')?'':'(Per Cabang)' ?></h3>
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; align-items: end;">
            <input type="hidden" name="export" value="excel">
            <input type="hidden" name="b_filter" value="<?= $b_filter ?>">
            <div style="width: 100%;">
                <label style="font-size:0.75rem; opacity:0.8;">Dari Tanggal</label>
                <input type="date" name="start" value="<?= date('Y-m-01') ?>" required style="width: 100% !important; height: 40px; border-radius: 8px;">
            </div>
            <div style="width: 100%;">
                <label style="font-size:0.75rem; opacity:0.8;">Sampai Tanggal</label>
                <input type="date" name="end" value="<?= date('Y-m-d') ?>" required style="width: 100% !important; height: 40px; border-radius: 8px;">
            </div>
            <button type="submit" style="background:#4CAF50 !important; height: 40px; font-weight: bold; border-radius: 8px; border:none; color:white;">📥 Download</button>
        </form>
    </div>
    
    <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));gap:20px;">
        <div class="card" style="overflow:hidden;"><canvas id="chart"></canvas></div>
        <div class="card" style="overflow-x:auto;">
            <h3>Detail Capster</h3>
            <table style="font-size:0.85rem; width:100%;">
                <thead><tr><th>Nama</th><th>Cust</th><th>Kotor</th><th>Bersih</th></tr></thead>
                <tbody>
                    <?php foreach($capster_stats as $cs) echo "<tr><td>{$cs["name"]}</td><td>".($cs["total"])."</td><td>".number_format($cs["gross"])."</td><td style='color:var(--accent); font-weight:bold;'>".number_format($cs["gross"]*0.5)."</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));gap:20px;margin-bottom:30px;">
        <div class="card"><h3>Customer</h3><p style="font-size: 2rem; font-weight:bold; margin:10px 0;"><?= $cust ?></p></div>
        <div class="card" style="border-left-color:var(--primary)">
            <h3>Gaji Anda (Bersih 50%)</h3>
            <p style="font-size: 1.5rem; font-weight:bold; color:var(--primary); margin: 5px 0;">Rp <?= number_format(($income_gross ?: 0) * 0.5) ?></p>
            <p style="font-size: 0.85rem; opacity:0.8;">Uang Makan yang sudah diambil: Rp <?= number_format($my_meals) ?></p>
        </div>
    </div>
    <div class="card" style="overflow:hidden;"><canvas id="chart"></canvas></div>
    <?php endif; ?>
</div>

<script>
new Chart(document.getElementById("chart"),{
    type:"line",
    data:{
        labels:<?= json_encode($labels) ?>,
        datasets:[
            { label: "Profit (Rp)", data: <?= json_encode($chart_profit) ?>, borderColor: "#bb86fc", backgroundColor: "rgba(187,134,252,0.1)", fill: true, tension: 0.3, yAxisID: 'y' },
            { label: "Customer", data: <?= json_encode($chart_cust) ?>, borderColor: "#03dac6", backgroundColor: "rgba(3,218,198,0.1)", fill: true, tension: 0.3, yAxisID: 'y1' }
        ]
    },
    options:{
        responsive: true, maintainAspectRatio: false, plugins:{legend:{labels:{color:"white"}}},
        scales:{
            x:{ticks:{color:"white"}},
            y:{ type: 'linear', display: true, position: 'left', ticks:{color:"#bb86fc"} },
            y1:{ type: 'linear', display: true, position: 'right', ticks:{color:"#03dac6", stepSize: 1}, grid:{drawOnChartArea: false} }
        }
    }
});
function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); }
document.addEventListener('click', function(event) {
    var sidebar = document.getElementById('sidebar');
    var burger = document.querySelector('.burger-btn');
    if (sidebar.classList.contains('active') && !sidebar.contains(event.target) && !burger.contains(event.target)) {
        sidebar.classList.remove('active');
    }
});
</script>
</body></html>