<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
try {
    // DB dipindah ke folder database agar lebih rapi
    $db = new PDO("sqlite:".__DIR__."/../database/barbershop.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) { die("Koneksi Gagal: " . $e->getMessage()); }

function checkLogin() {
    if (!isset($_SESSION["user_id"])) { header("Location: index.php"); exit(); }
} ?>
