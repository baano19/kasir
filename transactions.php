<?php 
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include "includes/db.php"; 
date_default_timezone_set('Asia/Jakarta');

include "includes/header.php";

// --- PERBAIKAN QUERY: Ambil Data Uang Makan & Cabang dari Tabel Users (Secure) ---
$stmt_user = $db->prepare("SELECT branch_id, meal_allowance FROM users WHERE id = ?");
$stmt_user->execute([$uid]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
$my_branch = $user_data['branch_id'] ?? 1;
$meal_allowance = $user_data['meal_allowance'] ?? 30000;

$branches_data = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$capsters_data = $db->query("SELECT id, name, branch_id FROM users WHERE role='barber' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$services_data = $db->query("SELECT id, name, price, branch_id FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- LOGIC CLAIM MEAL (BARBER) ---
if(isset($_POST["claim_meal"]) && $role == "barber"){
    $tgl_skrg = date('Y-m-d');
    $cek = $db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND category='Makan' AND DATE(created_at)=?");
    $cek->execute([$uid, $tgl_skrg]);
    if($cek->fetchColumn() == 0){
        $db->prepare("INSERT INTO expenses (user_id, category, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)")
           ->execute([$uid, 'Makan', $meal_allowance, 'Uang Makan Harian', date('Y-m-d H:i:s'), $my_branch]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Uang makan berhasil diambil!'];
        header("Location: transactions"); exit();
    }
}

// --- LOGIC ADD SINGLE ---
if(isset($_POST["add"])){ 
    try {
        $waktu_input = !empty($_POST["custom_time"]) ? date('Y-m-d H:i:s', strtotime($_POST["custom_time"])) : date('Y-m-d H:i:s'); 
        $target_uid = (int)$_POST["target_uid"]; 
        
        $stmt_target = $db->prepare("SELECT branch_id FROM users WHERE id = ?");
        $stmt_target->execute([$target_uid]);
        $t_branch = $stmt_target->fetchColumn() ?: 1;

        $db->prepare("INSERT INTO transactions (user_id, service_name, amount, created_at, branch_id) VALUES (?,?,?,?,?)")
           ->execute([$target_uid, trim($_POST["s_name"]), (int)$_POST["s_price"], $waktu_input, $t_branch]); 
        
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Transaksi berhasil tersimpan!'];
    } catch(Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal menyimpan transaksi.'];
    }
    header("Location: transactions"); exit(); 
}

// --- LOGIC ADD BULK ---
if(isset($_POST["add_bulk"]) && $role == "admin"){
    try {
        $tgl_bulk = $_POST["bulk_date"];
        $target_uid = (int)$_POST["bulk_uid"];
        
        // --- PERBAIKAN QUERY BULK: Ambil dari Target Capster ---
        $stmt_target = $db->prepare("SELECT branch_id, meal_allowance FROM users WHERE id = ?");
        $stmt_target->execute([$target_uid]);
        $target_user_data = $stmt_target->fetch(PDO::FETCH_ASSOC);
        
        $t_branch = $target_user_data['branch_id'] ?? 1;
        $m_allowance = $target_user_data['meal_allowance'] ?? 30000;
        
        $waktu_batch = $tgl_bulk . " " . date('H:i:s');

        if(!empty($_POST['qty'])){
            foreach($_POST['qty'] as $s_id => $qty){
                $qty = (int)$qty;
                if($qty > 0){
                    $srv = $db->prepare("SELECT name, price FROM services WHERE id=?");
                    $srv->execute([(int)$s_id]);
                    $srv_data = $srv->fetch();
                    for($i=0; $i<$qty; $i++){
                        $db->prepare("INSERT INTO transactions (user_id, service_name, amount, created_at, branch_id) VALUES (?,?,?,?,?)")
                           ->execute([$target_uid, $srv_data['name'], $srv_data['price'], $waktu_batch, $t_branch]);
                    }
                }
            }
        }
        
        if(isset($_POST['bulk_makan'])){
            $cek_makan = $db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND category='Makan' AND DATE(created_at)=?");
            $cek_makan->execute([$target_uid, $tgl_bulk]);
            if($cek_makan->fetchColumn() > 0){
                $_SESSION['toast'] = ['type' => 'warning', 'msg' => 'Transaksi tersimpan. Uang makan dilewati (Sudah diambil).'];
            } else {
                $db->prepare("INSERT INTO expenses (user_id, category, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)")
                   ->execute([$target_uid, 'Makan', $m_allowance, 'Uang Makan Harian', $tgl_bulk . " 12:00:00", $t_branch]);
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Data massal & uang makan tersimpan!'];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Data massal berhasil tersimpan!'];
        }
    } catch(Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal menyimpan data massal.'];
    }
    header("Location: transactions"); exit();
}

// --- LOGIC UPDATE & DELETE ---
if(isset($_POST["update_t"]) && $role == "admin"){ 
    if($db->prepare("UPDATE transactions SET service_name=?, amount=? WHERE id=?")->execute([trim($_POST["edit_service"]), (int)$_POST["edit_amount"], (int)$_POST["t_id"]])) {
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Transaksi diperbarui.'];
    }
    header("Location: transactions"); exit(); 
}
if(isset($_POST["delete_t"]) && $role == "admin"){ 
    if($db->prepare("DELETE FROM transactions WHERE id=?")->execute([(int)$_POST["t_id"]])) {
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Transaksi dihapus.'];
    }
    header("Location: transactions"); exit(); 
}

// --- FILTER & PAGINATION LOGIC ---
$limit = 10; 
$page = max(1, (int)($_GET["page"] ?? 1)); 
$off = ($page - 1) * $limit;

$where = []; $p = []; 

$start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

if($role == "barber"){ 
    $where[] = "t.user_id=?"; $p[] = $uid; 
} else { 
    $f_b_id = isset($_GET['f_b_id']) && $_GET['f_b_id'] !== '' ? (int)$_GET['f_b_id'] : '';
    $f_b = isset($_GET['f_b']) && $_GET['f_b'] !== '' ? (int)$_GET['f_b'] : '';
    if($f_b_id !== ''){ $where[] = "t.branch_id=?"; $p[] = $f_b_id; }
    if($f_b !== ''){ $where[] = "t.user_id=?"; $p[] = $f_b; } 
}

if($start_date !== '') { $where[] = "DATE(t.created_at) >= ?"; $p[] = $start_date; }
if($end_date !== '') { $where[] = "DATE(t.created_at) <= ?"; $p[] = $end_date; }

$w_sql = count($where) ? "WHERE ".implode(" AND ", $where) : "";

$total_gross = 0; $total_net = 0; $total_meals = 0;
$admin_capster_summaries = [];

if($role == "barber") {
    $sum_query = $db->prepare("SELECT SUM(amount) FROM transactions t $w_sql");
    $sum_query->execute($p);
    $total_gross = $sum_query->fetchColumn() ?? 0;

    $meals_query = $db->prepare("SELECT SUM(amount) FROM expenses WHERE user_id=? AND DATE(created_at) >= ? AND DATE(created_at) <= ? AND category='Makan'");
    $meals_query->execute([$uid, $start_date, $end_date]);
    $total_meals = $meals_query->fetchColumn() ?? 0;

    $total_net = max(0, ($total_gross - $total_meals) * 0.5);
} else {
    // 1. Ambil Summary Global per Capster
    $sum_sql = "SELECT u.id as capster_id, u.name as capster_name, b.name as branch_name, SUM(t.amount) as total_gross, COUNT(t.id) as total_trx 
                FROM transactions t 
                JOIN users u ON t.user_id = u.id 
                LEFT JOIN branches b ON t.branch_id = b.id 
                $w_sql 
                GROUP BY t.user_id 
                ORDER BY total_gross DESC";
    $sum_stmt = $db->prepare($sum_sql);
    $sum_stmt->execute($p);
    $admin_capster_summaries = $sum_stmt->fetchAll();

    // 2. Siapin Sub-query buat Breakdown Services per Capster
    $bd_sql = "SELECT service_name, COUNT(*) as qty FROM transactions t ";
    if (!empty($w_sql)) {
        $bd_sql .= $w_sql . " AND t.user_id = ? GROUP BY service_name ORDER BY qty DESC";
    } else {
        $bd_sql .= " WHERE t.user_id = ? GROUP BY service_name ORDER BY qty DESC";
    }
    $bd_stmt = $db->prepare($bd_sql);

    foreach($admin_capster_summaries as &$cs) {
        $bd_params = $p; 
        $bd_params[] = $cs['capster_id'];
        $bd_stmt->execute($bd_params);
        $cs['breakdown'] = $bd_stmt->fetchAll();
    }
    unset($cs);
}

$t_rows = $db->prepare("SELECT COUNT(*) FROM transactions t $w_sql"); $t_rows->execute($p); $t_pages = ceil($t_rows->fetchColumn() / $limit);

$logs = $db->prepare("SELECT t.*, u.name as b_name, b.name as c_name FROM transactions t JOIN users u ON t.user_id=u.id LEFT JOIN branches b ON t.branch_id=b.id $w_sql ORDER BY t.created_at DESC, t.id DESC LIMIT $limit OFFSET $off");
$logs->execute($p); 
$list = $logs->fetchAll();

$url_params = "&start_date={$start_date}&end_date={$end_date}";
if($role == "admin"){
    if(isset($f_b_id) && $f_b_id !== '') $url_params .= "&f_b_id={$f_b_id}";
    if(isset($f_b) && $f_b !== '') $url_params .= "&f_b={$f_b}";
}
?>

<?php if(isset($_SESSION['toast'])): ?>
    <div class="toast-container" id="toastContainer">
        <div class="toast toast-<?= $_SESSION['toast']['type'] ?>"><span><?= e($_SESSION['toast']['msg']) ?></span></div>
    </div>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

<div class="dash-header mb-25"><h1 class="dash-title">Data Transaksi</h1></div>

<?php if($role == "barber"): 
    $cek_makan = $db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND category='Makan' AND DATE(created_at)=?");
    $cek_makan->execute([$uid, date('Y-m-d')]); $sdh_makan = $cek_makan->fetchColumn();
    $cek_cust = $db->prepare("SELECT COUNT(id) FROM transactions WHERE user_id=? AND DATE(created_at)=?");
    $cek_cust->execute([$uid, date('Y-m-d')]); $cust_today = $cek_cust->fetchColumn();
?>
<div class="card card-warning flex-between mb-25">
    <span class="fw-bold">Uang Makan Hari Ini: <?= $sdh_makan ? "<span class='text-accent'>✅ Sudah Diambil</span>" : "<span class='text-danger'>❌ Belum Diambil</span>" ?></span>
    <?php if(!$sdh_makan): ?>
        <?php if($cust_today >= 2): ?>
            <form method="POST" class="mb-0"><button name="claim_meal" class="btn-dash btn-warning">Ambil Rp <?= number_format($meal_allowance) ?></button></form>
        <?php else: ?>
            <button type="button" class="btn-dash btn-secondary" disabled>🔒 Syarat: 2 Cust (Baru <?= $cust_today ?>/2)</button>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if($role == "admin"): ?>
<div class="tabs mb-25">
    <button type="button" onclick="switchInput('single')" id="btn-single" class="active">Input Data</button>
    <button type="button" onclick="switchInput('bulk')" id="btn-bulk">Input Data Massal</button>
</div>

<div id="form-single" class="card mb-25">
    <h3 class="mt-0 text-primary mb-25">Input Transaksi Baru</h3>
    <form method="POST">
        <div class="form-grid-2">
            <div class="fg"><input type="datetime-local" name="custom_time" class="auto-date" value="<?= date('Y-m-d\TH:i') ?>" required></div>
            <div class="fg"><select id="add_branch" name="add_branch" onchange="updateAddForm()" required><option value="">-- Pilih Cabang --</option><?php foreach($branches_data as $b) echo "<option value='{$b['id']}'>".e($b['name'])."</option>"; ?></select></div>
            <div class="fg"><select id="add_capster" name="target_uid" required><option value="">-- Pilih Capster --</option></select></div>
            <div class="fg"><select id="add_service" name="s_name" onchange="document.getElementById('pr').value=this.options[this.selectedIndex].getAttribute('data-p')" required><option value="">-- Pilih Layanan --</option></select></div>
            <div class="fg"><input type="number" name="s_price" id="pr" placeholder="Harga" readonly></div>
            <div class="fg"><button name="add" class="btn-dash btn-primary w-100" style="height: 100%;">Simpan Transaksi</button></div>
        </div>
    </form>
</div>

<div id="form-bulk" class="card card-accent mb-25" style="display:none;">
    <h3 class="mt-0 text-accent mb-25">Input Data Massal</h3>
    <form method="POST">
        <div class="form-grid-3">
            <div class="fg"><input type="date" name="bulk_date" class="auto-date-only" value="<?= date('Y-m-d') ?>" required></div>
            <div class="fg"><select id="bulk_branch" onchange="updateBulkForm()" required><option value="">-- Pilih Cabang --</option><?php foreach($branches_data as $b) echo "<option value='{$b['id']}'>".e($b['name'])."</option>"; ?></select></div>
            <div class="fg"><select id="bulk_capster" name="bulk_uid" required><option value="">-- Pilih Capster --</option></select></div>
        </div>
        
        <div id="bulk_services_area" class="mb-25 mt-8">
            <p class="text-muted fs-sm text-center">Pilih cabang untuk menampilkan daftar layanan.</p>
        </div>

        <div class="flex-between">
            <label class="label-checkbox flex-1 mb-0">
                <input type="checkbox" name="bulk_makan" value="1" class="checkbox-inline"> 
                <span class="fw-bold">Input Uang Makan Sekaligus?</span>
            </label>
            <button name="add_bulk" class="btn-dash btn-accent" style="flex:1;">Simpan Semua Data</button>
        </div>
    </form>
</div>

<?php else: // BARBER FORM ?>
<div class="card mb-25">
    <h3 class="mt-0 text-primary mb-25">Input Transaksi Baru</h3>
    <form method="POST">
        <input type="hidden" name="target_uid" value="<?= $uid ?>">
        <div class="form-grid-2">
            <div class="fg"><input type="datetime-local" name="custom_time" class="auto-date" value="<?= date('Y-m-d\TH:i') ?>" required></div>
            <div class="fg">
                <select name="s_name" onchange="document.getElementById('pr').value=this.options[this.selectedIndex].getAttribute('data-p')" required>
                    <option value="">-- Pilih Layanan --</option>
                    <?php foreach($services_data as $s) { if($s['branch_id'] == $my_branch) echo "<option value='".e($s['name'])."' data-p='{$s['price']}'>".e($s['name'])."</option>"; } ?>
                </select>
            </div>
            <div class="fg"><input type="number" name="s_price" id="pr" placeholder="Harga" readonly></div>
            <div class="fg"><button name="add" class="btn-dash btn-primary w-100" style="height: 100%;">Simpan Transaksi</button></div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card <?= $role=='admin' ? 'card-warning' : '' ?> mb-25">
    <form method="GET">
        <?php if($role == "admin"): ?>
            <div class="filter-wrapper">
                <div class="fg mb-0"><select id="filter_branch" name="f_b_id" onchange="updateFilterForm()" class="dash-select"><option value="">Semua Cabang</option><?php foreach($branches_data as $b): ?><option value="<?= $b['id'] ?>" <?= (isset($_GET['f_b_id']) && $_GET['f_b_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
                <div class="fg mb-0"><select id="filter_capster" name="f_b" class="dash-select"><option value="">Semua Capster</option></select></div>
                
                <div class="date-range-group mb-0">
                    <input type="date" name="start_date" value="<?= e($start_date) ?>" class="dash-select flex-1" required>
                    <span class="text-muted fw-bold">-</span>
                    <input type="date" name="end_date" value="<?= e($end_date) ?>" class="dash-select flex-1" required>
                </div>
                
                <div class="action-btns mb-0">
                    <button type="submit" class="btn-dash btn-warning">Tampilkan</button>
                    <a href="transactions" class="btn-dash btn-secondary">Reset</a>
                </div>
            </div>
        <?php else: // Barber Filter Compact ?>
            <div class="filter-wrapper-barber">
                <div class="date-range-group mb-0">
                    <input type="date" name="start_date" value="<?= e($start_date) ?>" class="dash-select flex-1" required>
                    <span class="text-muted fw-bold">-</span>
                    <input type="date" name="end_date" value="<?= e($end_date) ?>" class="dash-select flex-1" required>
                </div>
                <div class="action-btns mb-0">
                    <button type="submit" class="btn-dash btn-primary w-100">Tampilkan</button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if($role == "admin" && count($admin_capster_summaries) > 0): ?>
    <div class="capster-summary-wrapper">
        <?php foreach($admin_capster_summaries as $cs): ?>
            <div class="capster-summary-box">
                <p class="capster-summary-name"><?= e($cs['capster_name']) ?></p>
                <span class="capster-summary-branch"><?= e($cs['branch_name'] ?: 'Umum') ?></span>
                <p class="capster-summary-total">Rp <?= number_format($cs['total_gross']) ?></p>
                <span class="capster-summary-count"><?= $cs['total_trx'] ?> Transaksi</span>
                
                <?php if(!empty($cs['breakdown'])): ?>
                <div class="capster-summary-breakdown">
                    <?php foreach($cs['breakdown'] as $bd): ?>
                        <div class="capster-summary-breakdown-item">
                            <span><?= e($bd['service_name']) ?></span>
                            <span><?= $bd['qty'] ?>x</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif($role == "barber"): ?>
    <div class="kpi-grid mb-25">
        <div class="kpi-card"><h3>Kotor</h3><p class="val">Rp <?= number_format($total_gross) ?></p></div>
        <?php if($total_meals > 0): ?>
            <div class="kpi-card kpi-highlight"><h3 class="text-primary fw-bold">Pendapatan Bersih Akhir</h3><p class="val text-primary">Rp <?= number_format($total_net) ?></p></div>
        <?php else: ?>
            <div class="kpi-card" style="border-left-color: var(--danger); border-left-style: dashed;"><h3 class="text-danger fw-bold">Pendapatan Bersih Akhir</h3><p class="text-danger fw-bold mt-8 mb-0">⚠️ Terkunci</p><small class="text-danger fs-sm mt-8 d-block">*Ambil uang makan terlebih dahulu.</small></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>NO</th>
                    <?php if($role=="admin") echo "<th>CAPSTER</th>"; ?>
                    <th class="col-service">SERVICE</th> 
                    <th class="col-gross num-col">GROSS</th>
                    <th class="col-waktu">WAKTU</th>
                    <?php if($role=="admin") echo "<th class='col-aksi'>AKSI</th>"; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(count($list) == 0): ?>
                    <tr><td colspan="<?= $role=='admin' ? 6 : 4 ?>" align="center"><span class="text-muted mt-8">Tidak ada data transaksi.</span></td></tr>
                <?php else: ?>
                    <?php $no=$off+1; foreach($list as $l): $is_ed=($role=='admin' && ($_GET['edit_id']??'')==$l['id']); ?>
                    <tr>
                        <form method="POST">
                        <input type="hidden" name="t_id" value="<?=$l['id']?>">
                        <td><?=$no++?></td>
                        
                        <?php if($role=="admin"): ?>
                            <td>
                                <span class="fw-bold d-block text-primary"><?= e($l['b_name']) ?></span>
                                <span class="badge-branch mb-0 mt-8" style="font-size:0.65rem; padding: 2px 6px;"><?= e($l['c_name'] ?: 'Umum') ?></span>
                            </td>
                        <?php endif; ?>
                        
                        <td class="col-service">
                            <?= $is_ed ? "<input type='text' name='edit_service' value='".e($l['service_name'])."' class='edit-input input-sm' style='width:150px!important;'>" : e($l['service_name']) ?>
                        </td>
                        
                        <td class="col-gross num-col fw-bold">
                            <?= $is_ed ? "<input type='number' name='edit_amount' value='{$l['amount']}' class='edit-input input-sm'>" : "Rp ".number_format($l['amount']) ?>
                        </td>
                        
                        <td class="col-waktu fs-sm text-muted">
                            <?= date($role=='admin' ? 'd/m, H:i' : 'd M, H:i', strtotime($l['created_at'])) ?>
                        </td>

                        <?php if($role=="admin"): ?>
                            <td class="col-aksi">
                                <div class="action-btns">
                                    <?php if($is_ed): ?>
                                        <button name="update_t" class="btn-dash btn-accent">OK</button> 
                                        <a href="transactions" class="btn-dash btn-secondary">X</a>
                                    <?php else: ?>
                                        <a href="?edit_id=<?=$l['id']?>&page=<?=$page?><?=$url_params?>" class="btn-dash btn-accent">Edit</a>
                                        <button type="button" class="btn-dash btn-danger" onclick="showConfirm('Hapus Transaksi', 'Yakin ingin menghapus transaksi ini permanen?', this.closest('form'), 'delete_t')">Del</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>

                        </form>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if($t_pages > 1): ?>
<div class="pagination">
    <?php if($page > 1): ?><a href="?page=<?= $page-1 ?><?= $url_params ?>">&laquo; Prev</a><?php endif; ?>
    
    <?php
    $start_p = max(1, $page - 1);
    $end_p = min($t_pages, $page + 1);

    if($start_p > 1) {
        echo '<a href="?page=1'.$url_params.'">1</a>';
        if($start_p > 2) echo '<span class="dots">...</span>';
    }

    for($i=$start_p; $i<=$end_p; $i++) {
        $act = ($page == $i) ? 'active' : '';
        echo '<a href="?page='.$i.$url_params.'" class="'.$act.'">'.$i.'</a>';
    }

    if($end_p < $t_pages) {
        if($end_p < $t_pages - 1) echo '<span class="dots">...</span>';
        echo '<a href="?page='.$t_pages.$url_params.'">'.$t_pages.'</a>';
    }
    ?>

    <?php if($page < $t_pages): ?><a href="?page=<?= $page+1 ?><?= $url_params ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<dialog id="customConfirmModal" class="custom-confirm-dialog">
    <div class="confirm-icon">⚠️</div>
    <h3 id="confirmTitle" class="confirm-title">Konfirmasi</h3>
    <p id="confirmDesc" class="confirm-desc">Apakah Anda yakin?</p>
    <div class="confirm-actions">
        <button type="button" class="btn-dash btn-secondary" onclick="document.getElementById('customConfirmModal').close()">Batal</button>
        <button type="button" class="btn-dash btn-primary" id="confirmYesBtn" onclick="executeConfirm()">Ya, Lanjutkan</button>
    </div>
</dialog>

<script>
    // TOAST AUTO-HIDE LOGIC
    document.addEventListener("DOMContentLoaded", function() {
        const toast = document.getElementById('toastContainer');
        if (toast) {
            setTimeout(() => {
                toast.firstElementChild.classList.add('hiding');
                setTimeout(() => toast.remove(), 300); 
            }, 3500);
        }
    });

    // FALLBACK JAM OTOMATIS: Kalo user nge-clear input tanggal, balikin ke jam saat ini
    document.querySelectorAll('.auto-date').forEach(inp => {
        inp.addEventListener('blur', function() {
            if(!this.value) {
                let now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                this.value = now.toISOString().slice(0,16);
            }
        });
    });
    
    document.querySelectorAll('.auto-date-only').forEach(inp => {
        inp.addEventListener('blur', function() {
            if(!this.value) {
                let now = new Date();
                this.value = now.toISOString().slice(0,10);
            }
        });
    });

    // CUSTOM CONFIRMATION LOGIC
    let pendingForm = null;
    let pendingActionName = null;

    function showConfirm(title, desc, form, actionName) {
        if(actionName !== 'delete_t') {
            if(!form.checkValidity()) { form.reportValidity(); return; }
        }
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmDesc').innerText = desc;
        
        const btnYes = document.getElementById('confirmYesBtn');
        btnYes.className = 'btn-dash';
        if(actionName.startsWith('delete_') || actionName.startsWith('del_')) { 
            btnYes.classList.add('btn-danger'); 
        } else { 
            btnYes.classList.add('btn-primary'); 
        }

        pendingForm = form; pendingActionName = actionName;
        document.getElementById('customConfirmModal').showModal();
    }

    function executeConfirm() {
        let input = document.createElement('input');
        input.type = 'hidden'; input.name = pendingActionName; input.value = '1';
        pendingForm.appendChild(input);
        pendingForm.submit();
        document.getElementById('customConfirmModal').close();
    }

    const capsters = <?= json_encode($capsters_data) ?>;
    const services = <?= json_encode($services_data) ?>;
    
    function switchInput(mode) {
        document.getElementById('form-single').style.display = (mode === 'single') ? 'block' : 'none';
        document.getElementById('form-bulk').style.display = (mode === 'bulk') ? 'block' : 'none';

        const btnSingle = document.getElementById('btn-single');
        const btnBulk = document.getElementById('btn-bulk');

        if (mode === 'single') {
            btnSingle.classList.add('active');
            btnBulk.classList.remove('active');
        } else if (mode === 'bulk') {
            btnSingle.classList.remove('active');
            btnBulk.classList.add('active');
        }
    }

    function updateAddForm() {
        const b_id = document.getElementById('add_branch').value;
        const capSelect = document.getElementById('add_capster');
        const srvSelect = document.getElementById('add_service');
        const priceInput = document.getElementById('pr');
        
        capSelect.innerHTML = '<option value="">-- Pilih Capster --</option>';
        srvSelect.innerHTML = '<option value="">-- Pilih Layanan --</option>';
        priceInput.value = '';

        if(b_id) {
            capsters.filter(c => c.branch_id == b_id).forEach(c => capSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`);
            services.filter(s => s.branch_id == b_id).forEach(s => { srvSelect.innerHTML += `<option value="${s.name}" data-p="${s.price}">${s.name}</option>`; });
        }
    }

    function updateBulkForm() {
        const b_id = document.getElementById('bulk_branch').value;
        const capSelect = document.getElementById('bulk_capster');
        const srvArea = document.getElementById('bulk_services_area');
        capSelect.innerHTML = '<option value="">-- Pilih Capster --</option>';
        srvArea.innerHTML = '';
        
        if(b_id) {
            capsters.filter(c => c.branch_id == b_id).forEach(c => capSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`);
            services.filter(s => s.branch_id == b_id).forEach(s => {
                srvArea.innerHTML += `
                    <div class="bulk-row">
                        <span class="fw-bold">${s.name} <small class="text-muted">(Rp ${parseInt(s.price).toLocaleString()})</small></span>
                        <input type="number" name="qty[${s.id}]" value="0" min="0" class="edit-input input-sm">
                    </div>
                `;
            });
        }
    }

    function updateFilterForm() {
        const b_id = document.getElementById('filter_branch')?.value;
        const capSelect = document.getElementById('filter_capster');
        if(!capSelect) return;
        
        const currentCapster = "<?= $_GET['f_b'] ?? '' ?>";
        capSelect.innerHTML = '<option value="">Semua Capster</option>';
        
        const filteredCaps = b_id ? capsters.filter(c => c.branch_id == b_id) : capsters;
        filteredCaps.forEach(c => {
            let sel = (c.id == currentCapster) ? 'selected' : '';
            capSelect.innerHTML += `<option value="${c.id}" ${sel}>${c.name}</option>`;
        });
    }

    document.addEventListener("DOMContentLoaded", function() { if(document.getElementById('filter_branch')) updateFilterForm(); });
</script>

<?php include "includes/footer.php"; ?>
