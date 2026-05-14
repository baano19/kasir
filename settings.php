<?php 
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include "includes/db.php"; 

// --- AUTO-PATCHING SQLITE ---
try { $db->exec("ALTER TABLE branches ADD COLUMN address VARCHAR(255) DEFAULT ''"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE users ADD COLUMN meal_allowance INTEGER DEFAULT 30000"); } catch(Exception $e) {}

include "includes/header.php";

if($role != "admin") { header("Location: dashboard"); exit(); }
$tab = $_GET["tab"] ?? "branches";

function handleDbAction($callback, $successMsg) {
    try {
        $callback();
        $_SESSION['toast'] = ['type' => 'success', 'msg' => $successMsg];
    } catch(Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Sistem Error: Cek input Anda!'];
    }
}

// --- LOGIC CABANG ---
if(isset($_POST["add_b"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("INSERT INTO branches (name, address) VALUES (?, ?)")->execute([trim($_POST["n"]), trim($_POST["addr"])]); 
    }, 'Cabang baru berhasil ditambahkan!');
    header("Location: settings?tab=branches"); exit(); 
}
if(isset($_POST["up_b"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("UPDATE branches SET name=?, address=? WHERE id=?")->execute([trim($_POST["n"]), trim($_POST["addr"]), (int)$_POST["id"]]); 
    }, 'Data cabang diperbarui!');
    header("Location: settings?tab=branches"); exit(); 
}
if(isset($_POST["del_b"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("DELETE FROM branches WHERE id=?")->execute([(int)$_POST["id"]]); 
    }, 'Cabang dihapus!');
    header("Location: settings?tab=branches"); exit(); 
}

// --- LOGIC LAYANAN ---
if(isset($_POST["add_s"])){ 
    $nama = trim($_POST["n"]); 
    $branch_id = (int)$_POST["b_id"];
    $cek = $db->prepare("SELECT COUNT(*) FROM services WHERE name = ? AND branch_id = ?");
    $cek->execute([$nama, $branch_id]);
    
    if($cek->fetchColumn() == 0){
        handleDbAction(function() use ($db, $nama, $branch_id) {
            $db->prepare("INSERT INTO services (name, price, branch_id) VALUES (?,?,?)")->execute([$nama, (int)$_POST["p"], $branch_id]);
        }, 'Layanan baru ditambahkan!');
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal: Layanan sudah ada di cabang ini!'];
    }
    header("Location: settings?tab=services"); exit(); 
}
if(isset($_POST["up_s"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("UPDATE services SET name=?, price=?, branch_id=? WHERE id=?")->execute([trim($_POST["n"]), (int)$_POST["p"], (int)$_POST["b_id"], (int)$_POST["id"]]); 
    }, 'Layanan diperbarui!');
    header("Location: settings?tab=services"); exit(); 
}
if(isset($_POST["del_s"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("DELETE FROM services WHERE id=?")->execute([(int)$_POST["id"]]); 
    }, 'Layanan dihapus!');
    header("Location: settings?tab=services"); exit(); 
}

// --- LOGIC CAPSTER ---
if(isset($_POST["add_c"])){ 
    handleDbAction(function() use ($db) {
        $h = password_hash($_POST["pw"], PASSWORD_DEFAULT); 
        $db->prepare("INSERT INTO users (name,username,password,role,branch_id, meal_allowance) VALUES (?,?,'$h','barber',?,?)")
           ->execute([trim($_POST["n"]), trim($_POST["u"]), (int)$_POST["b_id"], (int)$_POST["meal"]]); 
    }, 'Karyawan baru ditambahkan!');
    header("Location: settings?tab=capsters"); exit(); 
}
if(isset($_POST["up_c_info"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("UPDATE users SET branch_id=?, meal_allowance=? WHERE id=?")->execute([(int)$_POST["b_id"], (int)$_POST["meal"], (int)$_POST["id"]]); 
    }, 'Data penugasan diperbarui!');
    header("Location: settings?tab=capsters"); exit(); 
}
if(isset($_POST["up_pc"])){ 
    handleDbAction(function() use ($db) {
        $h = password_hash($_POST["pw"], PASSWORD_DEFAULT); 
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h, (int)$_POST["id"]]); 
    }, 'Kredensial diperbarui!');
    header("Location: settings?tab=capsters"); exit(); 
}
if(isset($_POST["del_c"])){ 
    handleDbAction(function() use ($db) {
        $db->prepare("DELETE FROM users WHERE id=? AND role='barber'")->execute([(int)$_POST["id"]]); 
    }, 'Karyawan dihapus dari sistem!');
    header("Location: settings?tab=capsters"); exit(); 
}

// --- LOGIC ADMIN ---
if(isset($_POST["up_adm"])){ 
    handleDbAction(function() use ($db, $uid) {
        $h = password_hash($_POST["pw"], PASSWORD_DEFAULT); 
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h, $uid]); 
    }, 'Kredensial Admin berhasil diperbarui!');
    header("Location: settings?tab=profile"); exit(); 
}

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>

<?php if(isset($_SESSION['toast'])): ?>
    <div class="toast-container" id="toastContainer">
        <div class="toast toast-<?= $_SESSION['toast']['type'] ?>"><span><?= e($_SESSION['toast']['msg']) ?></span></div>
    </div>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

<div class="dash-header mb-25"><h1 class="dash-title">Settings</h1></div>

<div class="tabs mb-25">
    <a href="?tab=branches" class="<?=($tab=='branches'?'active':'')?>">Cabang</a>
    <a href="?tab=services" class="<?=($tab=='services'?'active':'')?>">Layanan</a>
    <a href="?tab=capsters" class="<?=($tab=='capsters'?'active':'')?>">Karyawan</a>
    <a href="?tab=profile" class="<?=($tab=='profile'?'active':'')?>">Sistem</a>
</div>

<?php if($tab == "branches"): ?>
    <div class="card card-accent mb-25">
        <h3 class="mt-0 text-accent mb-25">Registrasi Cabang Baru</h3>
        <form method="POST">
            <div class="compact-form">
                <div class="fg flex-2 mb-0"><input type="text" name="n" placeholder="Nama Cabang (Contoh: Cabang Bekasi)" required></div>
                <div class="action-btns flex-1 mb-0"><button name="add_b" class="btn-dash btn-accent w-100">Simpan Cabang</button></div>
            </div>
        </form>
    </div>

    <div class="grid-table">
        <div class="grid-thead gt-cabang">
            <div>ID</div>
            <div>NAMA CABANG</div>
            <div>ALAMAT / KETERANGAN</div>
            <div>TINDAKAN</div>
        </div>
        <?php foreach($branches as $b): ?>
        <form method="POST" class="grid-tr gt-cabang">
            <input type="hidden" name="id" value="<?=$b['id']?>">
            <div class="fw-bold text-muted">#<?=$b['id']?></div>
            <div><input type="text" name="n" value="<?=e($b['name'])?>" class="ghost-input" required></div>
            <div><input type="text" name="addr" value="<?=e($b['address'] ?? '')?>" class="ghost-input" placeholder="Isi alamat di sini..."></div>
            <div class="td-action-group">
                <button type="submit" name="up_b" class="btn-dash btn-accent">Save</button> 
                <button type="button" class="btn-dash btn-danger" onclick="showConfirm('Hapus Cabang', 'Yakin ingin menghapus cabang ini secara permanen?', this.closest('form'), 'del_b')">Del</button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>

<?php elseif($tab == "services"): ?>
    <div class="card card-primary mb-25">
        <h3 class="mt-0 text-primary mb-25">Tambah Layanan Baru</h3>
        <form method="POST" class="form-grid-auto">
            <div class="fg mb-0"><select name="b_id" required><option value="">-- Pilih Cabang --</option><?php foreach($branches as $b) echo "<option value='{$b['id']}'>".e($b['name'])."</option>"; ?></select></div>
            <div class="fg mb-0"><input type="text" name="n" placeholder="Nama Layanan" required></div>
            <div class="fg mb-0"><input type="number" name="p" placeholder="Harga (Rp)" required></div>
            <div class="fg mb-0"><button name="add_s" class="btn-dash btn-primary w-100">Tambah Layanan</button></div>
        </form>
    </div>

    <?php 
    $sv_all = $db->query("SELECT * FROM services ORDER BY name ASC")->fetchAll(); 
    foreach($branches as $b): 
        $branch_services = array_filter($sv_all, function($s) use ($b) { return $s['branch_id'] == $b['id']; });
    ?>
        <div class="branch-group-header"><span>📍</span> Layanan <?= e($b['name']) ?></div>
        <div class="grid-table grid-table-grouped">
            <div class="grid-thead gt-layanan-grouped">
                <div>NAMA LAYANAN</div>
                <div>HARGA</div>
                <div>TINDAKAN</div>
            </div>
            <?php if(count($branch_services) == 0): ?>
                <div style="padding: 20px; text-align: center; color: #888; font-style: italic;">Belum ada layanan di cabang ini.</div>
            <?php else: ?>
                <?php foreach($branch_services as $s): ?>
                <form method="POST" class="grid-tr gt-layanan-grouped">
                    <input type="hidden" name="id" value="<?=$s['id']?>">
                    <input type="hidden" name="b_id" value="<?=$s['branch_id']?>">
                    <div><input type="text" name="n" value="<?=e($s['name'])?>" class="ghost-input" required></div>
                    <div><input type="number" name="p" value="<?=$s['price']?>" class="ghost-input" required></div>
                    <div class="td-action-group">
                        <button type="submit" name="up_s" class="btn-dash btn-accent">Save</button> 
                        <button type="button" class="btn-dash btn-danger" onclick="showConfirm('Hapus Layanan', 'Yakin ingin menghapus layanan ini?', this.closest('form'), 'del_s')">Del</button>
                    </div>
                </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php elseif($tab == "capsters"): ?>
    <div class="card card-warning mb-25">
        <h3 class="mt-0 text-warning mb-25">Registrasi Karyawan Baru</h3>
        <form method="POST" class="form-grid-3">
            <div class="fg mb-0"><select name="b_id" required><option value="">-- Penugasan Cabang --</option><?php foreach($branches as $b) echo "<option value='{$b['id']}'>".e($b['name'])."</option>"; ?></select></div>
            <div class="fg mb-0"><input type="text" name="n" placeholder="Nama Lengkap" required></div>
            <div class="fg mb-0"><input type="text" name="u" placeholder="Username Sistem" required></div>
            <div class="fg mb-0"><input type="number" name="meal" placeholder="Allowance Makan (Rp)" required></div>
            <div class="fg mb-0"><input type="text" name="pw" placeholder="Kredensial (Password)" required></div>
            <div class="fg mb-0"><button name="add_c" class="btn-dash btn-warning w-100" style="height:100%;">Registrasi</button></div>
        </form>
    </div>

    <?php 
    $cs_all = $db->query("SELECT * FROM users WHERE role='barber' ORDER BY name ASC")->fetchAll(); 
    foreach($branches as $b): 
        $branch_capsters = array_filter($cs_all, function($c) use ($b) { return $c['branch_id'] == $b['id']; });
    ?>
        <div class="branch-group-header"><span>👥</span> Tim <?= e($b['name']) ?></div>
        <div class="grid-table grid-table-grouped">
            <div class="grid-thead gt-capster">
                <div>IDENTITAS KARYAWAN</div>
                <div>PENUGASAN & ALLOWANCE</div>
                <div>SIMPAN DATA</div>
                <div>MANAJEMEN AKUN</div>
            </div>
            
            <?php if(count($branch_capsters) == 0): ?>
                <div style="padding: 20px; text-align: center; color: #888; font-style: italic;">Belum ada karyawan yang ditugaskan di cabang ini.</div>
            <?php else: ?>
                <?php foreach($branch_capsters as $c): ?>
                <form method="POST" id="form_c_info_<?=$c['id']?>"><input type="hidden" name="id" value="<?=$c['id']?>"></form>
                <form method="POST" id="form_c_pass_<?=$c['id']?>"><input type="hidden" name="id" value="<?=$c['id']?>"></form>
                
                <div class="grid-tr gt-capster">
                    <div>
                        <span class="fw-bold d-block text-primary" style="font-size: 1.1rem;"><?=e($c['name'])?></span>
                        <span class="text-muted fs-sm">@<?=e($c['username'])?></span>
                    </div>
                    
                    <div class="flex-col-gap">
                        <div class="input-group-wrapper">
                            <span class="input-group-label">Cabang:</span>
                            <select name="b_id" class="ghost-input" form="form_c_info_<?=$c['id']?>">
                                <?php foreach($branches as $br){ $sel=($br['id']==$c['branch_id'])?'selected':''; echo "<option value='{$br['id']}' $sel>".e($br['name'])."</option>"; } ?>
                            </select>
                        </div>
                        <div class="input-group-wrapper">
                            <span class="input-group-label">Makan: Rp</span>
                            <input type="number" name="meal" value="<?=$c['meal_allowance'] ?? 30000 ?>" class="ghost-input" form="form_c_info_<?=$c['id']?>" required>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" name="up_c_info" class="btn-dash btn-accent w-100" style="height: 100%;" form="form_c_info_<?=$c['id']?>">Simpan Info</button>
                    </div>
                    
                    <div class="td-action-group">
                        <input type="text" name="pw" placeholder="Pass Baru" class="ghost-input" style="flex:1; min-width:90px; border: 1px solid #333!important;" form="form_c_pass_<?=$c['id']?>" required>
                        <button type="submit" name="up_pc" class="btn-dash btn-warning" form="form_c_pass_<?=$c['id']?>">Ubah</button>
                        <button type="button" class="btn-dash btn-danger" onclick="showConfirm('Hapus Karyawan', 'Yakin hapus karyawan ini permanen dari sistem?', document.getElementById('form_c_pass_<?=$c['id']?>'), 'del_c')">Del</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php else: ?>
    <div class="card" style="max-width:500px; border-left-color: var(--primary);">
        <h3 class="mt-0 text-primary mb-25">Manajemen Kredensial Administrator</h3>
        <form method="POST" id="form_admin_pass">
            <div class="fg">
                <label>Password Baru</label>
                <input type="text" name="pw" placeholder="Masukkan password baru..." required>
            </div>
            <button type="button" class="btn-dash btn-primary w-100 mt-8" onclick="showConfirm('Peringatan Sistem', 'Yakin ingin memperbarui kredensial Administrator?', document.getElementById('form_admin_pass'), 'up_adm')">Perbarui Kredensial</button>
        </form>
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
        if(actionName !== 'del_b' && actionName !== 'del_s' && actionName !== 'del_c') {
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
