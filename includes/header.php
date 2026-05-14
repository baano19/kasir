<?php
// includes/header.php
include_once "db.php"; // Pastikan koneksi DB ada
checkLogin();

$role = $_SESSION["role"];
$name = $_SESSION["name"];
$uid  = $_SESSION["user_id"]; // TAMBAHIN INI BOS! Biar transaksi & dashboard gampang manggil ID
$active_page = basename($_SERVER['PHP_SELF'], ".php");

// Fungsi Anti-XSS buat keamanan data yang tampil
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>BPOS - <?= ucfirst($active_page) ?></title>
</head>
<body>

<div class="mobile-header">
    <span style="color:var(--primary); font-weight:bold; font-size:1.2rem;">BPOS</span>
    <button class="burger-btn" onclick="toggleMenu()">☰</button>
</div>

<div class="sidebar" id="sidebar">
    <h2>BPOS</h2>
    <a href="dashboard" class="<?= $active_page == 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="transactions" class="<?= $active_page == 'transactions' ? 'active' : '' ?>">Transaksi</a>
    
    <?php if($role == "admin"): ?>
        <a href="expenses" class="<?= $active_page == 'expenses' ? 'active' : '' ?>">Pengeluaran</a>
        <a href="settings" class="<?= $active_page == 'settings' ? 'active' : '' ?>">Settings</a>
    <?php endif; ?>
    
    <a href="logout" class="logout-link">Logout</a>
</div>

<div class="content">
