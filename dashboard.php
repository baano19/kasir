<?php include "includes/db.php"; checkLogin();

$role = $_SESSION["role"]; $id = $_SESSION["user_id"]; 

// --- 1. LOGIKA EXPORT EXCEL (KHUSUS ADMIN) ---
if (isset($_GET['export']) && $_GET['export'] == 'excel' && $role == 'admin') {
    $start = $_GET['start']; 
    $end = $_GET['end'];

    // Ambil rekap harian per capster
    $q = $db->prepare("
        SELECT date(t.created_at) as tgl, u.name, COUNT(t.id) as cust, SUM(t.amount) as gross 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.role = 'barber' 
          AND date(t.created_at) >= ? 
          AND date(t.created_at) <= ? 
        GROUP BY date(t.created_at), u.id 
        ORDER BY date(t.created_at) DESC, u.name ASC
    ");
    $q->execute([$start, $end]);
    $data = $q->fetchAll();

    // Paksa browser download file CSV (Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Rekap_Capster_' . $start . '_sd_' . $end . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Header kolom
    fputcsv($output, array('Tanggal', 'Nama Capster', 'Total Customer', 'Pendapatan Kotor (Rp)', 'Pendapatan Bersih 50% (Rp)'));

    // Isi datanya
    foreach ($data as $row) {
        $bersih = ($row['gross'] ?: 0) * 0.5;
        fputcsv($output, array($row['tgl'], $row['name'], $row['cust'], $row['gross'] ?: 0, $bersih));
    }
    fclose($output);
    exit(); // Stop proses biar HTML gak ikut ke-download
}
// ---------------------------------------------


// --- 2. LOGIKA FILTER WAKTU (UI DASHBOARD) ---
$filter = $_GET['filter'] ?? 'today';
$today = date("Y-m-d");

if($filter == 'week') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = $today;
    $label_waktu = "7 Hari Terakhir";
} elseif($filter == 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $label_waktu = "Bulan Ini";
} else {
    $start_date = $today;
    $end_date = $today;
    $label_waktu = "Hari Ini";
}
// ---------------------------------------------

// --- 3. AMBIL DATA SESUAI FILTER ---
if($role == "admin"){
    $kotor = $db->query("SELECT SUM(amount) FROM transactions WHERE date(created_at) >= '$start_date' AND date(created_at) <= '$end_date'")->fetchColumn();
    $bersih = ($kotor ?: 0) * 0.5; 
    $cust = $db->query("SELECT COUNT(id) FROM transactions WHERE date(created_at) >= '$start_date' AND date(created_at) <= '$end_date'")->fetchColumn();
    
    $capster_stats = $db->query("SELECT u.name, COUNT(t.id) as total, SUM(t.amount) as gross FROM users u LEFT JOIN transactions t ON u.id = t.user_id AND date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' WHERE u.role = 'barber' GROUP BY u.id")->fetchAll();
} else {
    $st1 = $db->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st1->execute([$id, $start_date, $end_date]); $income_gross = $st1->fetchColumn();
    $income_net = ($income_gross ?: 0) * 0.5; 
    
    $st2 = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st2->execute([$id, $start_date, $end_date]); $cust = $st2->fetchColumn();
}

// --- 4. DATA GRAFIK (Fix 7 Hari) ---
$labels = []; $chart_profit = []; $chart_cust = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days")); $labels[] = date("D", strtotime($d));
    if($role == "admin") { 
        $val = $db->query("SELECT SUM(amount) FROM transactions WHERE date(created_at)='$d'")->fetchColumn(); 
        $c_count = $db->query("SELECT COUNT(id) FROM transactions WHERE date(created_at)='$d'")->fetchColumn();
    } else { 
        $st = $db->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND date(created_at)=?"); 
        $st->execute([$id, $d]); $val = $st->fetchColumn(); 
        $st_c = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND date(created_at)=?"); 
        $st_c->execute([$id, $d]); $c_count = $st_c->fetchColumn();
    }
    $chart_profit[] = ($val ?: 0) * 0.5;
    $chart_cust[] = $c_count ?: 0;
} 
?>

<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Dashboard</title>
</head><body>

<div class="mobile-header">
    <span style="color:var(--primary); font-weight:bold; font-size:1.2rem;">BPOS</span>
    <button class="burger-btn" onclick="toggleMenu()">☰</button>
</div>

<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="transactions.php">Transaksi</a>
    <?php if($role=="admin") echo "<a href=\"settings.php\">Settings</a>"; ?>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; align-items:center; margin-bottom:20px;">
        <h1>Halo, <?= $_SESSION["name"] ?></h1>
        
        <form method="GET" style="display:flex; gap:10px; align-items:center;">
            <select name="filter" onchange="this.form.submit()" style="padding:8px 12px; border-radius:8px; background:#252525; color:#fff; border:1px solid #444; margin:0;">
                <option value="today" <?= $filter=='today'?'selected':'' ?>>Hari Ini</option>
                <option value="week" <?= $filter=='week'?'selected':'' ?>>7 Hari Terakhir</option>
                <option value="month" <?= $filter=='month'?'selected':'' ?>>Bulan Ini</option>
            </select>
        </form>
    </div>

    <?php if($role=="admin"): ?>
    
    <div class="card" style="border-left-color:#4CAF50; margin-bottom: 30px; overflow: hidden;">
        <h3 style="margin-top:0; color:#4CAF50;">Export Rekap (Excel / CSV)</h3>
        
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; align-items: end;">
            <div style="width: 100%;">
                <label style="font-size:0.75rem; opacity:0.8; display:block; margin-bottom:5px;">Dari Tanggal</label>
                <input type="date" name="start" value="<?= date('Y-m-01') ?>" required 
                    style="width: 100% !important; height: 40px; margin: 0 !important; padding: 8px !important; box-sizing: border-box !important; -webkit-appearance: none; border: 1px solid #444; background: #252525; color: #fff; border-radius: 8px;">
            </div>
            <div style="width: 100%;">
                <label style="font-size:0.75rem; opacity:0.8; display:block; margin-bottom:5px;">Sampai Tanggal</label>
                <input type="date" name="end" value="<?= date('Y-m-d') ?>" required 
                    style="width: 100% !important; height: 40px; margin: 0 !important; padding: 8px !important; box-sizing: border-box !important; -webkit-appearance: none; border: 1px solid #444; background: #252525; color: #fff; border-radius: 8px;">
            </div>
            <div style="width: 100%;">
                <button type="submit" name="export" value="excel" 
                    style="background:#4CAF50 !important; color:#fff !important; width:100% !important; height: 40px; margin:0 !important; padding:0 !important; box-sizing: border-box !important; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    📥 Download
                </button>
            </div>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
        <div class="card">
            <h3>Total Customer (<?= $label_waktu ?>)</h3>
            <p style="font-size: 2rem; font-weight:bold; margin: 10px 0;"><?= $cust ?: 0 ?></p>
        </div>
        <div class="card" style="border-left-color:var(--accent)">
            <h3>Pendapatan (<?= $label_waktu ?>)</h3>
            <p style="font-size: 1.5rem; font-weight:bold; color:var(--accent); margin: 5px 0;">Bersih: Rp <?= number_format($bersih ?: 0) ?></p>
            <p style="font-size: 1rem; opacity:0.8; margin: 0;">Kotor: Rp <?= number_format($kotor ?: 0) ?></p>
        </div>
    </div>
    
    <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));gap:20px;">
        <div class="card" style="overflow:hidden;">
            <h3>Transaksi 7 Hari Terakhir</h3>
            <div style="position:relative; width:100%; height:250px;">
                <canvas id="chart"></canvas>
            </div>
        </div>
        <div class="card" style="overflow-x:auto;">
            <h3>Detail Capster (<?= $label_waktu ?>)</h3>
            <table style="font-size:0.85rem; width:100%; min-width:400px;">
                <thead><tr><th>Nama</th><th>Customer</th><th>Kotor</th><th>Bersih</th></tr></thead>
                <tbody>
                    <?php foreach($capster_stats as $cs) echo "<tr><td>{$cs["name"]}</td><td>".($cs["total"]?:0)."</td><td>".number_format($cs["gross"]?:0)."</td><td style='color:var(--accent); font-weight:bold;'>".number_format(($cs["gross"]?:0)*0.5)."</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));gap:20px;margin-bottom:30px;">
        <div class="card">
            <h3>Customer (<?= $label_waktu ?>)</h3>
            <p style="font-size: 2rem; font-weight:bold; margin: 10px 0;"><?= $cust ?: 0 ?></p>
        </div>
        <div class="card" style="border-left-color:var(--primary)">
            <h3>Pendapatan Anda (<?= $label_waktu ?>)</h3>
            <p style="font-size: 1.5rem; font-weight:bold; color:var(--primary); margin: 5px 0;">Bersih: Rp <?= number_format($income_net ?: 0) ?></p>
            <p style="font-size: 1rem; opacity:0.8; margin: 0;">Kotor: Rp <?= number_format($income_gross ?: 0) ?></p>
        </div>
    </div>
    <div class="card" style="overflow:hidden;">
        <h3>Performa Anda (7 Hari Terakhir)</h3>
        <div style="position:relative; width:100%; height:250px;">
            <canvas id="chart"></canvas>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
new Chart(document.getElementById("chart"),{
    type:"line",
    data:{
        labels:<?= json_encode($labels) ?>,
        datasets:[
            { label: "Profit Bersih (Rp)", data: <?= json_encode($chart_profit) ?>, borderColor: "#bb86fc", backgroundColor: "rgba(187,134,252,0.1)", fill: true, tension: 0.3, yAxisID: 'y' },
            { label: "Customer", data: <?= json_encode($chart_cust) ?>, borderColor: "#03dac6", backgroundColor: "rgba(3,218,198,0.1)", fill: true, tension: 0.3, yAxisID: 'y1' }
        ]
    },
    options:{
        responsive: true, maintainAspectRatio: false, plugins:{legend:{labels:{color:"white"}}},
        scales:{
            x:{ticks:{color:"white"}},
            y:{ type: 'linear', display: true, position: 'left', ticks:{color:"#bb86fc"}, grid:{color: '#333'} },
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

