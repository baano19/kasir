<?php 
include "includes/db.php"; 

if(isset($_SESSION["user_id"])){
    header("Location: dashboard");
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
        header("Location: dashboard");
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
</head>
<body class="login-page">

    <div class="card login-card">
        <h2>Barber POS</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">LOGIN</button>
        </form>
        <?php if(isset($err)) echo "<p class='login-error'>$err</p>"; ?>
    </div>

</body>
</html>

