<?php 
include "includes/db.php"; 
date_default_timezone_set('Asia/Jakarta');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["user_id"])) { header("Location: index"); exit(); }

$role = $_SESSION["role"]; 
$id = $_SESSION["user_id"]; 
$user_name = $db->query("SELECT name FROM users WHERE id='$id'")->fetchColumn();

// --- FUNGSI HELPER: SECURITY ANTI DOUBLE CLAIM ---
function checkDoubleClaimMakan($db, $user_id, $date) {
    if(empty($user_id)) return false;
    $sql = "SELECT COUNT(*) FROM expenses WHERE user_id=? AND LOWER(category)='makan' AND DATE(created_at)=DATE(?)";
    $cek = $db->prepare($sql);
    $cek->execute([$user_id, $date]);
    return $cek->fetchColumn() > 0;
}

// =========================================================================
// 1. LOGIKA EXPORT EXCEL & PRINT VIEW
// =========================================================================
$is_print = isset($_GET['view']) && $_GET['view'] == 'print';
$is_excel = isset($_GET['export']) && $_GET['export'] == 'excel';

if ($is_excel || $is_print) {
    if($role != 'admin') exit("Akses Ditolak");
    
    $start = isset($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start']) ? $_GET['start'] : date('Y-m-01');
    $end = isset($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end']) ? $_GET['end'] : date('Y-m-d');
    $ex_b_filter = isset($_GET['b_filter']) && $_GET['b_filter'] !== 'all' ? (int)$_GET['b_filter'] : 'all';

    $branches_query = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
    $start_fmt = date('d M Y', strtotime($start));
    $end_fmt = date('d M Y', strtotime($end));

    if($is_excel) {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=Rekap_BPOS_" . $start . "_sd_" . $end . ".xls");
    }
    
    echo "<html><head><title>Cetak Laporan BPOS</title><style>
        body { font-family: sans-serif; font-size: 11px; color: #333; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; page-break-inside: auto; }
        .table-gabungan { border: 2px solid #000; font-size: 14px; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #000; padding: 6px; text-align: center; white-space: nowrap; }
        .head-table { background-color: #bb86fc; color: black; font-size: 13px; font-weight:bold; }
        .date-row { background-color: #d9ead3; font-weight: bold; text-align: left; font-size: 12px; }
        .total-day-row { background-color: #f2f2f2; font-weight: bold; }
        .rekap-box { background-color: #c9daf8; font-weight: bold; }
        .text-left { text-align: left; }
        .text-right { text-align: right; font-variant-numeric: tabular-nums; }
        .text-danger { color: #cf6679; }
        .title-report { font-size:18px; text-align:left; border:none; color:#bb86fc; padding-bottom:5px; }
        .subtitle-report { font-size:12px; text-align:left; border:none; color:#666; padding-bottom:15px; }
        .rincian-wrapper { width: 500px; page-break-inside: avoid; }
        .bg-teal { background: #03dac6; color: #000; font-weight: bold; font-size: 13px; }
        .bg-teal-large { background: #03dac6; color: #000; font-weight: bold; font-size: 16px; }
        .bg-purple { background:#bb86fc; color:#000; font-size:18px; padding:15px; text-align:center; }
        .bg-gray { background:#eee; }
        .bg-light-gray { background:#f9f9f9; font-weight:bold; }
        .border-none { border: none; }
        .print-btn { padding:10px 20px; background:#bb86fc; border:none; color:black; border-radius:5px; cursor:pointer; margin-bottom:20px; font-weight:bold; }
        .mb-20 { margin-bottom: 20px; }
        @page { size: auto; margin: 10mm; } 
        @media print { .no-print { display: none; } }
    </style></head><body>";

    if($is_print) echo "<div class='no-print'><button onclick='window.print()' class='print-btn'>🖨️ Cetak / Simpan PDF</button><hr class='mb-20'></div>";

    $fmt = function($num) use ($is_excel) {
        if ($is_excel) return $num; 
        return number_format((float)$num, 0, ',', '.');
    };

    function renderReport($db, $title, $branch_id, $start, $end, $fmt, $start_fmt, $end_fmt) {
        $b_sql_s = ($branch_id === 'all') ? "" : "AND branch_id = $branch_id";
        $b_sql_t = ($branch_id === 'all') ? "" : "AND t.branch_id = $branch_id";
        $b_sql_e = ($branch_id === 'all') ? "" : "AND e.branch_id = $branch_id";
        $b_sql_u = ($branch_id === 'all') ? "" : "AND u.branch_id = $branch_id";

        $srv_query = $db->query("SELECT DISTINCT name FROM services WHERE 1=1 $b_sql_s ORDER BY name ASC")->fetchAll();
        $services = []; foreach($srv_query as $s) $services[] = $s['name'];
        $total_cols = count($services) + 5; 

        echo "<table>";
        echo "<tr><th colspan='$total_cols' class='title-report'>LAPORAN PENDAPATAN: $title</th></tr>";
        echo "<tr><th colspan='$total_cols' class='subtitle-report'>Periode: $start_fmt - $end_fmt</th></tr>";
        
        echo "<thead><tr><th class='head-table text-left'>TANGGAL / CAPSTER</th>";
        foreach($services as $srv) echo "<th class='head-table'>$srv</th>";
        echo "<th class='head-table'>CUST</th><th class='head-table text-right'>KOTOR</th><th class='head-table text-right'>MAKAN</th><th class='head-table text-right'>NET (50%)</th></tr></thead>";
        
        $grand_total_gross = 0; $total_cust_all = 0;
        $dates_q = $db->prepare("SELECT DISTINCT date(t.created_at) as tgl FROM transactions t WHERE date(t.created_at) >= ? AND date(t.created_at) <= ? $b_sql_t ORDER BY tgl ASC");
        $dates_q->execute([$start, $end]); $dates = $dates_q->fetchAll();

        if(count($dates) == 0) {
            echo "<tr><td colspan='$total_cols'>Tidak ada data transaksi.</td></tr>";
        } else {
            foreach($dates as $d) {
                $tgl = $d['tgl'];
                $day_cust = 0; $day_gross = 0; $day_exp = 0; $day_net = 0;

                echo "<tr><td colspan='$total_cols' class='date-row'>Tanggal: " . date('d M Y', strtotime($tgl)) . "</td></tr>";
                $capsters = $db->query("SELECT u.id, u.name, b.name as bname FROM users u LEFT JOIN branches b ON u.branch_id=b.id WHERE u.role='barber' $b_sql_u ORDER BY b.id, u.name ASC")->fetchAll();

                foreach($capsters as $c) {
                    $cid = $c['id'];
                    $cek_tr = $db->prepare("SELECT COUNT(id) as cust, SUM(amount) as gross FROM transactions t WHERE t.user_id=? AND date(t.created_at)=? $b_sql_t");
                    $cek_tr->execute([$cid, $tgl]); $tr_data = $cek_tr->fetch();
                    $cust_count = $tr_data['cust'] ?: 0;
                    $gross = $tr_data['gross'] ?: 0;
                    
                    $cek_exp = $db->prepare("SELECT SUM(amount) FROM expenses WHERE user_id=? AND LOWER(category)='makan' AND date(created_at)=?");
                    $cek_exp->execute([$cid, $tgl]); $exp_user = $cek_exp->fetchColumn() ?: 0;

                    $net_user = ($gross - $exp_user) * 0.5;
                    if($net_user < 0) $net_user = 0;

                    if($cust_count > 0 || $exp_user > 0) {
                        $day_cust += $cust_count; $day_gross += $gross; $day_exp += $exp_user; $day_net += $net_user;
                        $grand_total_gross += $gross; $total_cust_all += $cust_count;

                        echo "<tr><td class='text-left'>{$c['name']}</td>";
                        foreach($services as $srv) {
                            $cek_srv = $db->prepare("SELECT COUNT(id) FROM transactions t WHERE t.user_id=? AND t.service_name=? AND date(t.created_at)=? $b_sql_t");
                            $cek_srv->execute([$cid, $srv, $tgl]); $jml_srv = $cek_srv->fetchColumn();
                            echo "<td>" . ($jml_srv ?: "-") . "</td>";
                        }
                        echo "<td>$cust_count</td><td class='text-right'>".$fmt($gross)."</td>";
                        echo "<td class='text-right text-danger'>".($exp_user > 0 ? "-".$fmt($exp_user) : "-")."</td>";
                        echo "<td class='text-right'>".$fmt($net_user)."</td></tr>";
                    }
                }
                echo "<tr class='total-day-row'><td colspan='".(count($services)+1)."' class='text-right'>TOTAL HARIAN (".date('d/m', strtotime($tgl))."):</td>";
                echo "<td>$day_cust</td><td class='text-right'>".$fmt($day_gross)."</td><td class='text-right text-danger'>-".$fmt($day_exp)."</td><td class='text-right'>".$fmt($day_net)."</td></tr>";
            }
        }

        $barber_exp_q = $db->prepare("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE u.role='barber' AND LOWER(e.category)='makan' AND date(e.created_at) >= ? AND date(e.created_at) <= ? $b_sql_e");
        $barber_exp_q->execute([$start, $end]); $barber_exp = $barber_exp_q->fetchColumn() ?: 0;
        
        $admin_exp_q = $db->prepare("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE (u.role='admin' OR LOWER(e.category)!='makan') AND date(e.created_at) >= ? AND date(e.created_at) <= ? $b_sql_e");
        $admin_exp_q->execute([$start, $end]); $admin_exp = $admin_exp_q->fetchColumn() ?: 0;

        $subtotal = $grand_total_gross - $barber_exp;
        $jatah_owner = $subtotal * 0.5;
        $bersih_admin = $jatah_owner - $admin_exp;

        echo "<tr class='rekap-box'><td colspan='".(count($services)+1)."' class='text-left'>Total pendapatan Periode: $start_fmt - $end_fmt</td><td>$total_cust_all</td><td class='text-right'>".$fmt($grand_total_gross)."</td><td class='text-right text-danger'>-".$fmt($barber_exp)."</td><td class='text-right'>".$fmt($jatah_owner)."</td></tr>";
        echo "</table><br>";

        echo "<div class='rincian-wrapper'>";
        echo "<table>";
        echo "<tr><th colspan='2' class='head-table text-left'>RINCIAN PERHITUNGAN PENDAPATAN</th></tr>";
        echo "<tr><td class='text-left'>Total Pendapatan Kotor</td><td class='text-right'>".$fmt($grand_total_gross)."</td></tr>";
        echo "<tr><td class='text-left'>Total Uang Makan Capster</td><td class='text-right text-danger'>- ".$fmt($barber_exp)."</td></tr>";
        echo "<tr class='rekap-box'><td class='text-left'>SUBTOTAL (Kotor - Makan)</td><td class='text-right'>".$fmt($subtotal)."</td></tr>";
        echo "<tr><td class='text-left'>Pendapatan Owner (50% dari Subtotal)</td><td class='text-right'>".$fmt($jatah_owner)."</td></tr>";
        echo "<tr><td class='text-left'>Total Pengeluaran Operasional</td><td class='text-right text-danger'>- ".$fmt($admin_exp)."</td></tr>";
        echo "<tr class='bg-teal'><td class='text-left'>PENDAPATAN BERSIH AKHIR</td><td class='text-right'>".$fmt($bersih_admin)."</td></tr>";
        echo "</table></div><br>";

        echo "<table>";
        echo "<tr><th colspan='6' class='title-report border-none' style='font-size:16px; padding-top:20px;'>Rincian Pengeluaran Operasional (".ucwords(strtolower($title)).")</th></tr>";
        echo "<tr><th class='bg-gray'>Waktu</th><th class='bg-gray'>Cabang</th><th class='bg-gray'>Oleh/Untuk</th><th class='bg-gray'>Kategori</th><th class='bg-gray'>Catatan</th><th class='bg-gray text-right'>Nominal</th></tr>";
        
        $exp_list = $db->prepare("SELECT e.*, b.name as bname, u.name as uname, u.role FROM expenses e LEFT JOIN branches b ON e.branch_id=b.id LEFT JOIN users u ON e.user_id=u.id WHERE date(e.created_at) >= ? AND date(e.created_at) <= ? AND LOWER(e.category) != 'makan' $b_sql_e ORDER BY e.created_at ASC");
        $exp_list->execute([$start, $end]); $expenses = $exp_list->fetchAll();
        
        if(count($expenses) == 0) echo "<tr><td colspan='6'>Tidak ada pengeluaran operasional.</td></tr>";
        else foreach($expenses as $e) {
            $siapa = ($e['role'] == 'admin') ? "Operasional Admin" : "Capster (".$e['uname'].")";
            echo "<tr><td>".date('d/m H:i',strtotime($e['created_at']))."</td><td>{$e['bname']}</td><td>$siapa</td><td>{$e['category']}</td><td class='text-left'>{$e['notes']}</td><td class='text-right'>".$fmt($e['amount'])."</td></tr>";
        }
        echo "</table><br>";

        return [ 'kotor' => $grand_total_gross, 'makan' => $barber_exp, 'sub' => $subtotal, 'owner' => $jatah_owner, 'admin' => $admin_exp, 'bersih' => $bersih_admin ];
    }

    if($ex_b_filter === 'all') {
        $rekap = ['kotor'=>0, 'makan'=>0, 'sub'=>0, 'owner'=>0, 'admin'=>0, 'bersih'=>0, 'list'=>[]];
        foreach($branches_query as $br) {
            $h = renderReport($db, strtoupper($br['name']), $br['id'], $start, $end, $fmt, $start_fmt, $end_fmt);
            $rekap['kotor']+=$h['kotor']; $rekap['makan']+=$h['makan']; $rekap['sub']+=$h['sub'];
            $rekap['owner']+=$h['owner']; $rekap['admin']+=$h['admin']; $rekap['bersih']+=$h['bersih'];
            $rekap['list'][] = ['name'=>$br['name'], 'h'=>$h];
        }
        
        echo "<br><br>";
        echo "<table class='table-gabungan'>";
        echo "<tr><th colspan='2' class='bg-purple'>REKAP TOTAL GABUNGAN SEMUA CABANG</th></tr>";
        
        echo "<tr><th colspan='2' class='text-left bg-gray'>1. RINCIAN PENDAPATAN KOTOR GABUNGAN</th></tr>";
        foreach($rekap['list'] as $l) echo "<tr><td class='text-left'> - Cabang {$l['name']}</td><td class='text-right'>".$fmt($l['h']['kotor'])."</td></tr>";
        echo "<tr class='bg-light-gray'><td class='text-left'>TOTAL KOTOR SELURUH CABANG</td><td class='text-right'>".$fmt($rekap['kotor'])."</td></tr>";
        echo "<tr><th colspan='2' class='border-none' style='height:10px;'></th></tr>";

        echo "<tr><th colspan='2' class='text-left bg-gray'>2. RINCIAN UANG MAKAN CAPSTER GABUNGAN</th></tr>";
        foreach($rekap['list'] as $l) echo "<tr><td class='text-left'> - Cabang {$l['name']}</td><td class='text-right text-danger'>- ".$fmt($l['h']['makan'])."</td></tr>";
        echo "<tr class='bg-light-gray'><td class='text-left'>TOTAL UANG MAKAN SELURUH CABANG</td><td class='text-right text-danger'>- ".$fmt($rekap['makan'])."</td></tr>";
        echo "<tr><th colspan='2' class='border-none' style='height:10px;'></th></tr>";

        echo "<tr class='rekap-box'><td class='text-left'>SUBTOTAL GABUNGAN (Total Kotor - Total Makan)</td><td class='text-right'>".$fmt($rekap['sub'])."</td></tr>";
        echo "<tr><td class='text-left'>PENDAPATAN OWNER (50% DARI SUBTOTAL GABUNGAN)</td><td class='text-right'>".$fmt($rekap['owner'])."</td></tr>";
        echo "<tr><th colspan='2' class='border-none' style='height:10px;'></th></tr>";

        echo "<tr><th colspan='2' class='text-left bg-gray'>3. RINCIAN PENGELUARAN OPERASIONAL GABUNGAN</th></tr>";
        foreach($rekap['list'] as $l) echo "<tr><td class='text-left'> - Cabang {$l['name']}</td><td class='text-right text-danger'>- ".$fmt($l['h']['admin'])."</td></tr>";
        echo "<tr class='bg-light-gray'><td class='text-left'>TOTAL PENGELUARAN OPERASIONAL SELURUH CABANG</td><td class='text-right text-danger'>- ".$fmt($rekap['admin'])."</td></tr>";
        echo "<tr><th colspan='2' class='border-none' style='height:15px;'></th></tr>";

        echo "<tr class='bg-teal-large'><td class='text-left'>PENDAPATAN BERSIH AKHIR GABUNGAN</td><td class='text-right'>".$fmt($rekap['bersih'])."</td></tr>";
        echo "</table>";
    } else {
        $nama_c = $db->query("SELECT name FROM branches WHERE id='$ex_b_filter'")->fetchColumn();
        renderReport($db, strtoupper($nama_c), $ex_b_filter, $start, $end, $fmt, $start_fmt, $end_fmt);
    }
    echo "</body></html>"; exit();
}

// =========================================================================
// 2. DATA PREPARATION DASHBOARD
// =========================================================================
include "includes/header.php";

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$all_c_data = $db->query("SELECT id, name, branch_id FROM users WHERE role='barber' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); 

$b_filter = isset($_GET['b_filter']) && $_GET['b_filter'] !== 'all' ? (int)$_GET['b_filter'] : 'all';

$b_sql_t = ($b_filter === 'all') ? "" : " AND t.branch_id = $b_filter";
$b_sql_e = ($b_filter === 'all') ? "" : " AND e.branch_id = $b_filter"; 
$b_sql_u = ($b_filter === 'all') ? "" : " AND u.branch_id = $b_filter";

// --- LOGIC ADD EXPENSE (QUICK ADD VIA MODAL) ---
if(isset($_POST["add_expense"]) && $role == 'admin'){
    $waktu_exp = !empty($_POST["exp_time"]) ? date('Y-m-d H:i:s', strtotime($_POST["exp_time"])) : date('Y-m-d H:i:s');
    $cat = trim($_POST["exp_cat"]);
    $u_id = !empty($_POST["exp_user_id"]) ? (int)$_POST["exp_user_id"] : null;
    $b_id = (int)$_POST["exp_branch"];
    $amt = (int)$_POST["exp_amount"];
    $note = trim($_POST["exp_note"]);

    if(strtolower($cat) == 'makan' && checkDoubleClaimMakan($db, $u_id, $waktu_exp)) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Ditolak: Karyawan ini sudah mendapat allowance makan hari ini!'];
    } else {
        $db->prepare("INSERT INTO expenses (user_id, category, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)")
           ->execute([$u_id ?: $id, $cat, $amt, $note, $waktu_exp, $b_id]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Pengeluaran berhasil dicatat!'];
    }
    header("Location: dashboard?b_filter=" . $b_id); exit();
}

$filter = $_GET['filter'] ?? 'today'; $today = date("Y-m-d");
if($filter == 'week') { $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = $today; }
elseif($filter == 'month') { $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); }
else { $start_date = $today; $end_date = $today; }

if($role == "admin"){
    $kotor = $db->query("SELECT SUM(amount) FROM transactions t WHERE date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t")->fetchColumn() ?: 0;
    $cust = $db->query("SELECT COUNT(id) FROM transactions t WHERE date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t")->fetchColumn() ?: 0;
    
    $barber_exp = $db->query("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE u.role='barber' AND LOWER(e.category)='makan' AND date(e.created_at) >= '$start_date' AND date(e.created_at) <= '$end_date' $b_sql_e")->fetchColumn() ?: 0;
    $admin_exp = $db->query("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE (u.role='admin' OR LOWER(e.category)!='makan') AND date(e.created_at) >= '$start_date' AND date(e.created_at) <= '$end_date' $b_sql_e")->fetchColumn() ?: 0;
    
    $bersih_admin = (($kotor - $barber_exp) * 0.5) - $admin_exp;

    $capster_stats = $db->query("SELECT u.id, u.name, COUNT(t.id) as total, SUM(t.amount) as gross, 
        (SELECT SUM(amount) FROM expenses e WHERE e.user_id = u.id AND LOWER(e.category)='makan' AND date(e.created_at) >= '$start_date' AND date(e.created_at) <= '$end_date') as exp_user 
        FROM users u 
        LEFT JOIN transactions t ON u.id = t.user_id AND date(t.created_at) >= '$start_date' AND date(t.created_at) <= '$end_date' $b_sql_t 
        WHERE u.role = 'barber' $b_sql_u GROUP BY u.id")->fetchAll();
} else {
    $st1 = $db->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st1->execute([$id, $start_date, $end_date]); $income_gross = $st1->fetchColumn() ?: 0;
    $st2 = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND date(created_at) >= ? AND date(created_at) <= ?"); 
    $st2->execute([$id, $start_date, $end_date]); $cust = $st2->fetchColumn() ?: 0;
    $st3 = $db->prepare("SELECT SUM(amount) FROM expenses WHERE user_id=? AND LOWER(category)='makan' AND date(created_at) >= ? AND date(created_at) <= ?");
    $st3->execute([$id, $start_date, $end_date]); $my_meals = $st3->fetchColumn() ?: 0;
    $gaji_b_capster = max(0, ($income_gross - $my_meals) * 0.5);
}

// =========================================================================
// FIX LOGIKA CHART: KONDISI TERKUNCI & NET PROFIT AKURAT PER HARI
// =========================================================================
$labels = []; $chart_profit = []; $chart_cust = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days")); 
    $labels[] = date("D", strtotime($d));
    
    if($role == 'admin') {
        // Admin: Net Profit Harian = ((Kotor - Makan Capster) * 50%) - Operasional
        $val = $db->query("SELECT SUM(amount) FROM transactions t WHERE date(t.created_at)='$d' $b_sql_t")->fetchColumn() ?: 0; 
        $c_count = $db->query("SELECT COUNT(id) FROM transactions t WHERE date(t.created_at)='$d' $b_sql_t")->fetchColumn() ?: 0;
        
        $b_exp = $db->query("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE u.role='barber' AND LOWER(e.category)='makan' AND date(e.created_at)='$d' $b_sql_e")->fetchColumn() ?: 0;
        $a_exp = $db->query("SELECT SUM(amount) FROM expenses e JOIN users u ON e.user_id=u.id WHERE (u.role='admin' OR LOWER(e.category)!='makan') AND date(e.created_at)='$d' $b_sql_e")->fetchColumn() ?: 0;
        
        $chart_profit[] = (($val - $b_exp) * 0.5) - $a_exp;
        $chart_cust[] = $c_count;
    } else {
        // Barber: Gaji Harian = (Kotor - Makan) * 50%. TAPI jika hari itu belum/nggak ambil makan, grafik di-0-kan (Terkunci)
        $val = $db->query("SELECT SUM(amount) FROM transactions WHERE date(created_at)='$d' AND user_id=$id")->fetchColumn() ?: 0; 
        $c_count = $db->query("SELECT COUNT(id) FROM transactions WHERE date(created_at)='$d' AND user_id=$id")->fetchColumn() ?: 0;
        $makan = $db->query("SELECT SUM(amount) FROM expenses WHERE LOWER(category)='makan' AND date(created_at)='$d' AND user_id=$id")->fetchColumn() ?: 0;
        
        if($makan > 0) {
            $chart_profit[] = max(0, ($val - $makan) * 0.5);
        } else {
            $chart_profit[] = 0; // Kunci ke 0 karena uang makan belum ditarik
        }
        $chart_cust[] = $c_count;
    }
} 
?>

<?php if(isset($_SESSION['toast'])): ?>
    <div class="toast-container" id="toastContainer">
        <div class="toast toast-<?= $_SESSION['toast']['type'] ?>">
            <span><?= e($_SESSION['toast']['msg']) ?></span>
        </div>
    </div>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

<div class="dash-container">
    <div class="dash-header">
        <div>
            <h1 class="dash-title">Halo, <?= e(ucfirst($role == 'admin' ? 'Admin' : $user_name)) ?></h1>
        </div>
        <div class="dash-actions">
            <form method="GET" class="filter-form">
                <?php if($role=="admin"): ?>
                    <select name="b_filter" onchange="this.form.submit()" class="dash-select">
                        <option value="all" <?= $b_filter==='all'?'selected':'' ?>>🌍 Semua Cabang</option>
                        <?php foreach($branches as $b) echo "<option value='{$b['id']}' ".($b_filter==$b['id']?'selected':'').">🏠 ".e($b['name'])."</option>"; ?>
                    </select>
                <?php endif; ?>
                <select name="filter" onchange="this.form.submit()" class="dash-select">
                    <option value="today" <?= $filter=='today'?'selected':'' ?>>Hari Ini</option>
                    <option value="week" <?= $filter=='week'?'selected':'' ?>>Minggu Ini</option>
                    <option value="month" <?= $filter=='month'?'selected':'' ?>>Bulan Ini</option>
                </select>
            </form>
            
            <?php if($role=="admin"): ?>
            <div class="action-btns">
                <button type="button" class="btn-dash btn-primary" onclick="document.getElementById('dlReport').showModal()">📥 Laporan</button>
                <button type="button" class="btn-dash btn-danger" onclick="document.getElementById('dlExp').showModal()">+ Pengeluaran</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($role=="admin"): ?>
    <div class="kpi-grid">
        <div class="kpi-card">
            <h3>Pendapatan Kotor</h3>
            <p class="val">Rp <?= number_format((float)$kotor) ?></p>
        </div>
        <div class="kpi-card kpi-highlight">
            <h3 class="text-primary fw-bold">Pendapatan Bersih</h3>
            <p class="val text-primary">Rp <?= number_format((float)$bersih_admin) ?></p>
        </div>
        <div class="kpi-card">
            <h3>Total Customer</h3>
            <p class="val"><?= $cust ?></p>
        </div>
    </div>

    <div class="kpi-card mb-25">
        <h3>Tren Pendapatan & Customer</h3>
        <div class="chart-container"><canvas id="chart"></canvas></div>
    </div>

    <div class="kpi-card">
        <h3>Performa Capster</h3>
        <div class="table-wrap">
            <table class="dash-table">
                <thead><tr><th>Nama</th><th class="num-col">Cust</th><th class="num-col">Kotor</th><th class="num-col">Makan</th><th class="num-col text-accent">Net (50%)</th></tr></thead>
                <tbody>
                    <?php foreach($capster_stats as $cs){ 
                        $gv = $cs["gross"] ?? 0; $ex = $cs["exp_user"] ?? 0; $net = ($gv - $ex) * 0.5;
                        echo "<tr><td>".e($cs["name"])."</td><td class='num-col'>".($cs["total"] ?: 0)."</td><td class='num-col'>".number_format((float)$gv)."</td><td class='num-col text-danger'>-".number_format((float)$ex)."</td><td class='num-col text-accent fw-bold'>".number_format((float)$net)."</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
    </div>

    <dialog id="dlExp">
        <h2 class="dialog-title text-danger">Input Pengeluaran Cepat</h2>
        <form method="POST">
            <div class="fg">
                <label>Cabang</label>
                <select name="exp_branch" id="exp_branch" onchange="filterExpCapster()" required>
                    <?php foreach($branches as $b) echo "<option value='{$b['id']}' ".($b['id']==$b_filter?'selected':'').">".e($b['name'])."</option>"; ?>
                </select>
            </div>
            <div class="fg">
                <label>Untuk Siapa?</label>
                <select name="exp_user_id" id="exp_user_id">
                    <option value="">-- Operasional Admin --</option>
                </select>
            </div>
            <div class="form-grid-2 mb-0">
                <div class="fg">
                    <label>Kategori</label>
                    <input list="cats" name="exp_cat" placeholder="Ketik/Pilih Kategori" required>
                    <datalist id="cats"><option value="Makan"><option value="Operasional"><option value="Peralatan"><option value="Lainnya"></datalist>
                </div>
                <div class="fg">
                    <label>Jumlah (Rp)</label>
                    <input type="number" name="exp_amount" placeholder="Cth: 50000" required>
                </div>
            </div>
            <div class="fg">
                <label>Catatan (Opsional)</label>
                <input type="text" name="exp_note" placeholder="Rincian pengeluaran...">
            </div>
            <div class="dialog-actions">
                <button type="button" class="btn-dash btn-secondary" onclick="this.closest('dialog').close()">Batal</button>
                <button type="submit" name="add_expense" class="btn-dash btn-danger">Simpan Data</button>
            </div>
        </form>
    </dialog>

    <dialog id="dlReport">
        <h2 class="dialog-title text-primary">Export Laporan Periode</h2>
        <form method="GET" target="_blank">
            <input type="hidden" name="b_filter" value="<?= e($b_filter) ?>">
            <div class="form-grid-2 mb-0">
                <div class="fg"><label>Dari Tanggal</label><input type="date" name="start" value="<?= date('Y-m-01') ?>" required></div>
                <div class="fg"><label>Sampai Tanggal</label><input type="date" name="end" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="dialog-actions">
                <button type="button" class="btn-dash btn-secondary" onclick="this.closest('dialog').close()">Batal</button>
                <button type="submit" name="view" value="print" class="btn-dash btn-primary">PDF / Print</button>
                <button type="submit" name="export" value="excel" class="btn-dash btn-accent">Excel</button>
            </div>
        </form>
    </dialog>

    <?php else: // BARBER VIEW ?>
    <div class="kpi-grid">
        <div class="kpi-card">
            <h3>Total Customer</h3>
            <p class="val"><?= $cust ?></p>
        </div>
        <div class="kpi-card kpi-highlight-accent">
            <h3>Pendapatan</h3>
            <?php if($my_meals > 0): ?>
                <p class="val text-accent">Rp <?= number_format((float)$gaji_b_capster) ?></p>
            <?php else: ?>
                <p class="text-danger fw-bold mt-8 mb-0" style="font-size:1.2rem;">⚠️ Terkunci</p>
                <small class="text-danger d-block mt-8 fs-sm">*Ambil uang makan di menu Transaksi terlebih dahulu.</small>
            <?php endif; ?>
        </div>
    </div>
    <div class="kpi-card mb-25">
        <h3>Tren Performa</h3>
        <div class="chart-container"><canvas id="chart"></canvas></div>
    </div>
    <?php endif; ?>

</div> 

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById('toastContainer');
    if (toast) {
        setTimeout(() => {
            toast.firstElementChild.classList.add('hiding');
            setTimeout(() => toast.remove(), 300); 
        }, 3500);
    }
    filterExpCapster();
});

const allCapsters = <?= json_encode($all_c_data) ?>;
function filterExpCapster() {
    const bId = document.getElementById('exp_branch')?.value;
    const sel = document.getElementById('exp_user_id');
    if(!sel || !bId) return;
    sel.innerHTML = '<option value="">-- Operasional Admin --</option>';
    allCapsters.filter(c => c.branch_id == bId).forEach(c => { sel.innerHTML += `<option value="${c.id}">Capster: ${c.name}</option>`; });
}

new Chart(document.getElementById("chart"), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { label: 'Profit (Rp)', data: <?= json_encode($chart_profit) ?>, borderColor: '#bb86fc', tension: 0.4, fill: true, backgroundColor: 'rgba(187,134,252,0.1)', yAxisID: 'y', pointRadius: 3 },
            { label: 'Customer', data: <?= json_encode($chart_cust) ?>, borderColor: '#03dac6', tension: 0.4, yAxisID: 'y1', pointRadius: 3 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { position: 'left', grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888', maxTicksLimit: 5 } },
            y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { stepSize: 1, color: '#03dac6' } },
            x: { grid: { display: false }, ticks: { color: '#888' } }
        },
        plugins: { legend: { position: 'top', labels: { color: '#eee', boxWidth: 10, usePointStyle: true } } }
    }
});
</script>

<?php include "includes/footer.php"; ?>
