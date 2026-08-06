<?php
require_once '../config.php';

// If already logged in as admin, redirect to dashboard
if (isAdmin()) {
    redirect('dashboard.php');
}

$message = "";

if (isset($_POST['login'])) {
    $phone    = sanitize($_POST['phone']);
    $password = $_POST['password'];

    if (empty($phone) || empty($password)) {
        $message = "Please fill all fields.";
    } else {
        $query = mysqli_query($conn, "SELECT id, full_name, password FROM users WHERE phone='$phone' AND user_type='admin' LIMIT 1");
        if ($query && mysqli_num_rows($query) == 1) {
            $admin = mysqli_fetch_assoc($query);
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id']   = $admin['id'];
                $_SESSION['user_name'] = $admin['full_name'];
                $_SESSION['user_type'] = 'admin';
                redirect('dashboard.php');
            } else {
                $message = "Invalid password!";
            }
        } else {
            $message = "No admin account found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login – HelpGo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #E55A2B;
            --bg: #0A0A0A;
            --card: rgba(22,22,22,0.8);
            --border: rgba(255,255,255,0.08);
            --text: #fff;
            --text-secondary: #B0B0B0;
            --text-muted: #808080;
            --danger: #FF4757;
            --radius-md: 16px;
            --radius-lg: 24px;
            --shadow: 0 30px 60px rgba(0,0,0,0.6);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; overflow:hidden; }
        body {
            font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif;
            display:flex; justify-content:center; align-items:center;
            background: var(--bg);
            position:relative;
        }
        .bg-animation { position:fixed; top:0; left:0; width:100%; height:100%; z-index:0; pointer-events:none; }
        .bg-orb {
            position:absolute; border-radius:50%; filter:blur(100px); opacity:0.12;
            animation: float 20s infinite ease-in-out;
        }
        .bg-orb:nth-child(1) { width:500px; height:500px; background:var(--primary); top:-200px; right:-150px; }
        .bg-orb:nth-child(2) { width:400px; height:400px; background:#004E89; bottom:-150px; left:-150px; animation-delay:-7s; }
        .bg-orb:nth-child(3) { width:300px; height:300px; background:var(--primary); top:50%; left:50%; transform:translate(-50%,-50%); animation-delay:-14s; opacity:0.05; }
        @keyframes float {
            0%,100%{ transform:translate(0,0) scale(1); }
            25%{ transform:translate(50px,-25px) scale(1.1); }
            50%{ transform:translate(-25px,35px) scale(0.9); }
            75%{ transform:translate(-35px,-20px) scale(1.05); }
        }
        .grid-pattern {
            position:fixed; top:0; left:0; width:100%; height:100%;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size:50px 50px; z-index:0; pointer-events:none;
        }
        .login-container {
            width:100%; max-width:400px; position:relative; z-index:2;
            margin:0 20px; animation: slideUp 0.8s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes slideUp { from{ opacity:0; transform:translateY(40px); } to{ opacity:1; transform:translateY(0); } }
        .login-card {
            background: var(--card); backdrop-filter:blur(25px);
            border-radius: var(--radius-lg); padding:30px 28px;
            border:1px solid var(--border); box-shadow: var(--shadow);
            position:relative; overflow:hidden;
        }
        .login-card::after {
            content:''; position:absolute; top:-2px; left:-2px; right:-2px; bottom:-2px;
            border-radius:inherit; background: linear-gradient(45deg, rgba(255,107,53,0.2), transparent 40%, transparent 60%, rgba(0,78,137,0.2));
            z-index:-1; animation: borderGlow 4s linear infinite;
        }
        @keyframes borderGlow { 0%,100%{ opacity:0.4; } 50%{ opacity:1; } }
        .logo-section { text-align:center; margin-bottom:25px; }
        .logo-circle {
            width:70px; height:70px; background:linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius:20px; display:inline-flex; align-items:center; justify-content:center;
            font-size:32px; color:#fff; margin-bottom:15px;
            box-shadow:0 15px 35px rgba(255,107,53,0.4);
        }
        .brand-name { font-size:30px; font-weight:800; color:var(--text); letter-spacing:-1px; }
        .brand-name span { color:var(--primary); }
        .input-group { margin-bottom:18px; }
        .input-wrapper {
            position:relative; background:rgba(255,255,255,0.05); border-radius:var(--radius-md);
            border:1px solid var(--border); transition:0.25s;
        }
        .input-wrapper:focus-within { border-color:var(--primary); box-shadow:0 0 0 4px rgba(255,107,53,0.25); }
        .input-wrapper i { position:absolute; left:16px; top:50%; transform:translateY(-50%); color:var(--text-muted); }
        .input-wrapper input {
            width:100%; padding:14px 16px 14px 48px; background:transparent;
            border:none; color:var(--text); font-size:15px; outline:none;
            font-family:'Plus Jakarta Sans', sans-serif; font-weight:500;
        }
        .input-wrapper input::placeholder { color:var(--text-muted); }
        .toggle-password {
            position:absolute; right:14px; top:50%; transform:translateY(-50%);
            background:none; border:none; color:var(--text-muted); cursor:pointer;
            font-size:14px; padding:6px; z-index:2;
        }
        .btn-login {
            width:100%; padding:14px; background:linear-gradient(135deg, var(--primary), var(--primary-dark));
            border:none; border-radius:var(--radius-md); color:#fff; font-size:16px;
            font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center;
            gap:10px; transition:0.3s; margin-top:10px;
        }
        .btn-login:hover { transform:translateY(-2px); box-shadow:0 15px 35px rgba(255,107,53,0.4); }
        .alert-error {
            background:rgba(255,71,87,0.1); border:1px solid rgba(255,71,87,0.3);
            color:var(--danger); padding:12px 16px; border-radius:10px;
            margin-top:15px; font-size:14px; display:flex; align-items:center; gap:8px;
        }
    </style>
</head>
<body>
<div class="bg-animation">
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>
</div>
<div class="grid-pattern"></div>

<div class="login-container">
    <div class="login-card">
        <div class="logo-section">
            <div class="logo-circle"><i class="fa-solid fa-shield-haltered"></i></div>
            <h1 class="brand-name">Admin <span>Panel</span></h1>
        </div>

        <form method="POST">
            <div class="input-group">
                <div class="input-wrapper">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <input type="tel" name="phone" placeholder="Admin phone number" maxlength="10" required>
                </div>
            </div>
            <div class="input-group">
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <button type="button" class="toggle-password" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
            <?php if ($message): ?>
                <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    document.querySelector('.toggle-password').addEventListener('click', function(){
        const pass = document.getElementById('password');
        const icon = this.querySelector('i');
        if(pass.type === 'password'){
            pass.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            pass.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    });
</script>
</body>
</html>