<?php 
include "includes/db.php"; 

if(isset($_SESSION["user_id"])){
    header("Location: dashboard.php");
    exit();
}

if(isset($_POST["login"])){
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST["username"]]);
    $u = $stmt->fetch();
    
    if($u && password_verify($_POST["password"], $u["password"])){
        $_SESSION["user_id"] = $u["id"]; 
        $_SESSION["role"] = $u["role"]; 
        $_SESSION["name"] = $u["name"];
        header("Location: dashboard.php");
        exit();
    } else { 
        $err = "Username atau Password Salah!"; 
    }
} 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="assets/style.css?v=<?=time()?>">
    <title>Login - BPOS</title>
    
    <style>
        body {
            justify-content: center !important;
            align-items: center !important;
            flex-direction: column !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100vh !important;
        }
        .login-card {
            width: 90% !important; /* KUNCI MOBILE: Kasih sisa ruang 10% biar gak mepet layar */
            max-width: 380px !important; /* KUNCI PC: Jangan kepanjangan */
            padding: 40px 35px !important; /* Padding dalem digedein biar input gak mepet batas kotak */
            text-align: center;
            border-left: none !important; 
            border-top: 5px solid var(--primary) !important; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            border-radius: 12px !important;
            box-sizing: border-box !important;
        }
        .login-card h2 {
            color: var(--primary);
            margin-top: 0;
            margin-bottom: 35px;
            font-size: 2rem;
            letter-spacing: 1px;
        }
        .login-card input {
            width: 100% !important;
            margin-bottom: 20px !important;
            padding: 14px 15px !important; /* Inputnya digemukin dikit biar proporsional */
            font-size: 1rem !important;
            border-radius: 8px !important;
            box-sizing: border-box !important;
        }
        .login-card button {
            width: 100% !important;
            padding: 14px !important;
            font-size: 1.1rem !important;
            margin-top: 10px !important;
            border-radius: 8px !important;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <div class="card login-card">
        <h2>Barber POS</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">LOGIN</button>
        </form>
        <?php if(isset($err)) echo "<p style='color:var(--danger); margin-top:20px; font-weight:bold; font-size:0.9rem;'>$err</p>"; ?>
    </div>

</body>
</html>
