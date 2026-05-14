<?php 
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include "includes/db.php"; 

// --- HEADER INCLUDE ---
include "includes/header.php";

if($role != "admin") { header("Location: dashboard"); exit(); }

// --- FUNGSI HELPER: CEK DOUBLE CLAIM MAKAN ---
function checkDoubleClaimMakan($db, $user_id, $date, $ignore_expense_id = null) {
    if(empty($user_id)) return false;
    $sql = "SELECT COUNT(*) FROM expenses WHERE user_id=? AND LOWER(category)='makan' AND DATE(created_at)=DATE(?)";
    $params = [$user_id, $date];
    if($ignore_expense_id) {
        $sql .= " AND id != ?";
        $params[] = $ignore_expense_id;
    }
    $cek = $db->prepare($sql);
    $cek->execute($params);
    return $cek->fetchColumn() > 0;
}

// --- LOGIC ADD EXPENSE ---
if(isset($_POST['add_exp'])) {
    $cat = trim($_POST['cat']);
    $amt = (int)$_POST['amt'];
    $b_id = (int)$_POST['b_id'];
    $u_id = !empty($_POST['u_id']) ? (int)$_POST['u_id'] : null;
    $note = trim($_POST['note']);
    $tgl = date('Y-m-d H:i:s');

    if(strtolower($cat) == 'makan' && checkDoubleClaimMakan($db, $u_id, $tgl)) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Ditolak: Karyawan ini sudah mengambil allowance makan hari ini!'];
        header("Location: expenses?tab=makan"); exit();
    }
    
    $db->prepare("INSERT INTO expenses (user_id, branch_id, category, amount, notes, created_at) VALUES (?,?,?,?,?,?)")
       ->execute([$u_id, $b_id, $cat, $amt, $note, $tgl]);
    
    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Pengeluaran baru berhasil dicatat!'];
    $target_tab = (strtolower($cat) == 'makan') ? 'makan' : 'operasional';
    header("Location: expenses?tab=" . $target_tab); exit();
}

// --- LOGIC EDIT EXPENSE ---
if(isset($_POST["edit_exp"])){
    $id = (int)$_POST["id"];
    $cat = trim($_POST["cat"]);
    $amt = (int)$_POST["amt"];
    $note = trim($_POST["note"]);

    if(strtolower($cat) == 'makan') {
        $cek_exp = $db->prepare("SELECT user_id, created_at FROM expenses WHERE id=?");
        $cek_exp->execute([$id]);
        $exp_data = $cek_exp->fetch();
        
        if($exp_data && checkDoubleClaimMakan($db, $exp_data['user_id'], $exp_data['created_at'], $id)) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Ditolak: Karyawan sudah memiliki klaim makan di tanggal tersebut!'];
            header("Location: expenses?tab=makan"); exit();
        }
    }

    $st = $db->prepare("UPDATE expenses SET category=?, amount=?, notes=? WHERE id=?");
    $st->execute([$cat, $amt, $note, $id]);
    
    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Data pengeluaran berhasil diperbarui!'];
    $target_tab = (strtolower($cat) == 'makan') ? 'makan' : 'operasional';
    header("Location: expenses?tab=" . $target_tab); exit();
}

// --- LOGIC DELETE ---
if(isset($_POST["del_exp"])){
    $st = $db->prepare("DELETE FROM expenses WHERE id=?");
    $st->execute([(int)$_POST["id"]]);
    
    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Data pengeluaran dihapus dari sistem!'];
    header("Location: expenses?tab=" . ($_GET['tab'] ?? 'operasional')); exit();
}

// --- FILTER, TABS & PAGINASI LOGIC ---
$limit = 10; 
$page = max(1, (int)($_GET["page"] ?? 1)); 
$offset = ($page - 1) * $limit;

$tab = $_GET['tab'] ?? 'operasional';
$valid_tabs = ['operasional', 'makan', 'semua'];
if(!in_array($tab, $valid_tabs)) $tab = 'operasional';

$where = []; 
$params = []; 

$f_start = $_GET['f_start'] ?? date('Y-m-d');
$f_end = $_GET['f_end'] ?? date('Y-m-d');

$f_b_id = isset($_GET['f_b_id']) && $_GET['f_b_id'] !== '' ? (int)$_GET['f_b_id'] : '';
$f_start_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_start) ? $f_start : date('Y-m-d');
$f_end_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_end) ? $f_end : date('Y-m-d');

if ($f_b_id !== '') { $where[] = "e.branch_id = ?"; $params[] = $f_b_id; }
if ($f_start_safe !== '') { $where[] = "DATE(e.created_at) >= ?"; $params[] = $f_start_safe; }
if ($f_end_safe !== '') { $where[] = "DATE(e.created_at) <= ?"; $params[] = $f_end_safe; }

if ($tab === 'operasional') { $where[] = "LOWER(e.category) != 'makan'"; } 
elseif ($tab === 'makan') { $where[] = "LOWER(e.category) = 'makan'"; }

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$total_stmt = $db->prepare("SELECT COUNT(*) FROM expenses e $where_sql");
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sum_stmt = $db->prepare("SELECT SUM(amount) FROM expenses e $where_sql");
$sum_stmt->execute($params);
$total_expense_filtered = $sum_stmt->fetchColumn() ?: 0;

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$capsters = $db->query("SELECT id, name FROM users WHERE role='barber' ORDER BY name ASC")->fetchAll();

// MENCARI NAMA CABANG UNTUK LABEL SUMMARY
$selected_branch_name = 'Semua Cabang';
if ($f_b_id !== '') {
    foreach($branches as $br) {
        if($br['id'] == $f_b_id) { $selected_branch_name = $br['name']; break; }
    }
}

$query = "SELECT e.*, u.name as user_name, b.name as branch_name 
          FROM expenses e 
          LEFT JOIN users u ON e.user_id = u.id 
          LEFT JOIN branches b ON e.branch_id = b.id 
          $where_sql
          ORDER BY e.created_at DESC 
          LIMIT $limit OFFSET $offset";
$st = $db->prepare($query);
$st->execute($params);
$list = $st->fetchAll();

$url_params_no_tab = "";
if($f_b_id !== '') $url_params_no_tab .= "&f_b_id=" . $f_b_id;
$url_params_no_tab .= "&f_start=" . $f_start_safe . "&f_end=" . $f_end_safe;
$full_url_params = "?tab=" . $tab . $url_params_no_tab;
?>

<?php if(isset($_SESSION['toast'])): ?>
    <div class="toast-container" id="toastContainer">
        <div class="toast toast-<?= $_SESSION['toast']['type'] ?>">
            <span><?= e($_SESSION['toast']['msg']) ?></span>
        </div>
    </div>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

<div class="dash-header mb-25">
    <h1 class="dash-title">Manajemen Pengeluaran</h1>
</div>

<div class="card card-danger mb-25">
    <h3 class="mt-0 text-danger mb-25">Catat Pengeluaran Manual</h3>
    <form method="POST">
        <div class="expense-form-grid">
            <div class="fg mb-0">
                <select name="b_id" required>
                    <option value="">-- Pilih Cabang --</option>
                    <?php foreach($branches as $b) echo "<option value='{$b['id']}'>".e($b['name'])."</option>"; ?>
                </select>
            </div>
            <div class="fg mb-0">
                <select name="cat" required>
                    <option value="">-- Kategori --</option>
                    <option value="Operasional">Operasional</option>
                    <option value="Peralatan">Peralatan</option>
                    <option value="Lainnya">Lainnya</option>
                    <option value="Makan">Uang Makan</option>
                </select>
            </div>
            <div class="fg mb-0">
                <input type="number" name="amt" placeholder="Nominal (Rp)" required>
            </div>
            <div class="fg mb-0">
                <select name="u_id">
                    <option value="">-- Terkait Karyawan? (Opsional) --</option>
                    <?php foreach($capsters as $c) echo "<option value='{$c['id']}'>".e($c['name'])."</option>"; ?>
                </select>
            </div>
        </div>
        <div class="expense-note-row">
            <input type="text" name="note" placeholder="Tuliskan catatan atau rincian pengeluaran..." style="margin:0!important;">
            <button name="add_exp" class="btn-dash btn-danger w-100" style="margin:0!important;">Simpan Pengeluaran</button>
        </div>
    </form>
</div>

<div class="tabs mb-25">
    <a href="?tab=operasional<?= $url_params_no_tab ?>" class="<?= $tab=='operasional' ? 'active' : '' ?>">⚙️ Operasional Toko</a>
    <a href="?tab=makan<?= $url_params_no_tab ?>" class="<?= $tab=='makan' ? 'active' : '' ?>">🍽️ Uang Makan Capster</a>
    <a href="?tab=semua<?= $url_params_no_tab ?>" class="<?= $tab=='semua' ? 'active' : '' ?>">📋 Semua Riwayat</a>
</div>

<div class="card mb-25">
    <form method="GET">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="filter-wrapper">
            <div class="fg mb-0">
                <select name="f_b_id" class="dash-select w-100">
                    <option value="">Semua Cabang</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($f_b_id === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="date-range-group mb-0" style="grid-column: span 2;">
                <input type="date" name="f_start" value="<?= e($f_start_safe) ?>" class="dash-select flex-1">
                <span class="text-muted fw-bold">-</span>
                <input type="date" name="f_end" value="<?= e($f_end_safe) ?>" class="dash-select flex-1">
            </div>
            <div class="action-btns mb-0">
                <button type="submit" class="btn-dash btn-primary">Filter</button>
                <a href="expenses?tab=<?= e($tab) ?>" class="btn-dash btn-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="summary-card">
    <span class="summary-label">Total <?= $tab == 'makan' ? 'Uang Makan' : ($tab == 'operasional' ? 'Operasional' : 'Pengeluaran Gabungan') ?> (<?= e($selected_branch_name) ?>)</span>
    <h2 class="summary-total">Rp <?= number_format($total_expense_filtered) ?></h2>
</div>

<div class="grid-table">
    <div class="grid-thead gt-expenses">
        <div>TANGGAL</div>
        <div>KATEGORI & NOMINAL</div>
        <div>KETERANGAN & LOKASI</div>
        <div>TINDAKAN</div>
    </div>
    
    <?php if(count($list) == 0): ?>
        <div style="padding: 30px; text-align: center; color: #888; font-style: italic;">Tidak ada data pengeluaran di rentang tanggal ini.</div>
    <?php else: ?>
        <?php foreach($list as $e): ?>
        <form method="POST" id="form_exp_<?=$e['id']?>"><input type="hidden" name="id" value="<?=$e['id']?>"></form>
        <div class="grid-tr gt-expenses">
            
            <div class="text-muted fw-bold">
                <?= date('d/m/Y', strtotime($e["created_at"])) ?>
            </div>
            
            <div class="flex-col-gap">
                <div class="input-group-wrapper">
                    <span class="input-group-label" style="width: 55px;">Kategori:</span>
                    <input type="text" name="cat" value="<?= e($e["category"]) ?>" class="ghost-input" form="form_exp_<?=$e['id']?>" required>
                </div>
                <div class="input-group-wrapper" style="border-color: rgba(207, 102, 121, 0.2);">
                    <span class="input-group-label text-danger" style="width: 55px;">Harga: Rp</span>
                    <input type="number" name="amt" value="<?= $e["amount"] ?>" class="ghost-input text-danger fw-bold" form="form_exp_<?=$e['id']?>" required>
                </div>
            </div>

            <div class="flex-col-gap">
                <span class="badge-branch" style="align-self: flex-start;"><?= e($e["branch_name"] ?: 'Umum') ?></span>
                <input type="text" name="note" value="<?= e($e["notes"]) ?>" class="ghost-input" placeholder="Tulis catatan..." form="form_exp_<?=$e['id']?>">
                <span class="text-muted fs-sm">Terkait: <?= e($e["user_name"] ?: 'Sistem/Admin') ?></span>
            </div>

            <div class="td-action-group">
                <button type="submit" name="edit_exp" class="btn-dash btn-accent" form="form_exp_<?=$e['id']?>">Save</button>
                <button type="button" class="btn-dash btn-danger" onclick="showConfirm('Hapus Data', 'Yakin hapus riwayat pengeluaran ini permanen?', document.getElementById('form_exp_<?=$e['id']?>'), 'del_exp')">Del</button>
            </div>
            
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if($total_pages > 1): ?>
<div class="pagination">
    <?php if($page > 1): ?><a href="<?= $full_url_params ?>&page=<?= $page-1 ?>">&laquo; Prev</a><?php endif; ?>
    <?php 
    $start_p = max(1, $page - 1); 
    $end_p = min($total_pages, $page + 1);
    
    if($start_p > 1) { 
        echo '<a href="'.$full_url_params.'&page=1">1</a>'; 
        if($start_p > 2) echo '<span class="dots">...</span>'; 
    }
    for($i=$start_p; $i<=$end_p; $i++) { 
        $act = ($page == $i) ? 'active' : ''; 
        echo '<a href="'.$full_url_params.'&page='.$i.'" class="'.$act.'">'.$i.'</a>'; 
    }
    if($end_p < $total_pages) { 
        if($end_p < $total_pages - 1) echo '<span class="dots">...</span>'; 
        echo '<a href="'.$full_url_params.'&page='.$total_pages.'">'.$total_pages.'</a>'; 
    } 
    ?>
    <?php if($page < $total_pages): ?><a href="<?= $full_url_params ?>&page=<?= $page+1 ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<dialog id="customConfirmModal" class="custom-confirm-dialog">
    <div class="confirm-icon">⚠️</div>
    <h3 id="confirmTitle" class="confirm-title">Otorisasi Diperlukan</h3>
    <p id="confirmDesc" class="confirm-desc">Apakah Anda yakin melanjutkan tindakan ini?</p>
    <div class="confirm-actions">
        <button type="button" class="btn-dash btn-secondary" onclick="document.getElementById('customConfirmModal').close()">Batalkan</button>
        <button type="button" class="btn-dash btn-primary" id="confirmYesBtn" onclick="executeConfirm()">Konfirmasi</button>
    </div>
</dialog>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toast = document.getElementById('toastContainer');
        if (toast) {
            setTimeout(() => {
                toast.firstElementChild.classList.add('hiding');
                setTimeout(() => toast.remove(), 300); 
            }, 3500);
        }
    });

    let pendingForm = null;
    let pendingActionName = null;

    function showConfirm(title, desc, form, actionName) {
        if(actionName !== 'del_exp') {
            if(!form.checkValidity()) { form.reportValidity(); return; }
        }
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmDesc').innerText = desc;
        
        const btnYes = document.getElementById('confirmYesBtn');
        btnYes.className = 'btn-dash'; 
        if(actionName.startsWith('del_')) {
            btnYes.classList.add('btn-danger');
        } else {
            btnYes.classList.add('btn-primary');
        }

        pendingForm = form;
        pendingActionName = actionName;
        document.getElementById('customConfirmModal').showModal();
    }

    function executeConfirm() {
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = pendingActionName;
        input.value = '1';
        pendingForm.appendChild(input);
        pendingForm.submit();
        document.getElementById('customConfirmModal').close();
    }
</script>

<?php include "includes/footer.php"; ?>
