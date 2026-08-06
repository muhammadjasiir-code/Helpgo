<?php
// settings.php – Customer Settings (Emerald Prestige theme)
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$uid = (int)$_SESSION['user_id'];
$user = getUserData($uid);
$success = '';
$error = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } else {
        $upd = mysqli_query($conn, "UPDATE users SET full_name='$name', email='$email', phone='$phone' WHERE id=$uid");
        if ($upd) {
            $success = "Profile updated successfully.";
            $user = getUserData($uid); // refresh
        } else {
            $error = "Update failed: " . mysqli_error($conn);
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "All password fields are required.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = mysqli_query($conn, "UPDATE users SET password='$hash' WHERE id=$uid");
        if ($upd) {
            $success = "Password changed successfully.";
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33; --emerald-light: #0E5548; --emerald-dark: #04261F;
            --gold: #D4AF37; --gold-light: #E8C84A; --gold-dark: #B8962E;
            --white: #FFFFFF; --gray-soft: #AEB8B2; --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.6); --glass-border: rgba(212, 175, 55, 0.2);
            --shadow-glass: 0 20px 50px rgba(0,0,0,0.4); --radius-card: 28px;
            --radius-input: 16px; --radius-btn: 20px;
            --font: 'Poppins', sans-serif; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font);
            background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
            color: var(--white);
            display: flex; justify-content: center; min-height: 100vh; padding: 20px 16px 60px;
            overflow-x: hidden; position: relative;
        }
        .bg-orb {
            position: fixed; border-radius:50%; filter:blur(130px); opacity:0.1; pointer-events:none; z-index:0;
            animation: orbFloat 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width:500px; height:500px; background:var(--gold); top:-200px; right:-150px; }
        .bg-orb:nth-child(2) { width:350px; height:350px; background:var(--gold); bottom:-100px; left:-120px; animation-delay:-10s; }
        @keyframes orbFloat { 0%{ transform:translate(0,0) scale(1); } 100%{ transform:translate(40px,-30px) scale(1.1); } }
        .container { width:100%; max-width:500px; position:relative; z-index:2; }
        .back-link { display:inline-flex; align-items:center; gap:8px; color:var(--gold); text-decoration:none; margin-bottom:24px; font-weight:500; }
        .back-link:hover { gap:12px; }
        h2 { font-size:28px; font-weight:800; margin-bottom:24px; color:var(--white); }
        h2 span { color:var(--gold); }

        .card {
            background: var(--glass-bg); backdrop-filter: blur(20px);
            border:1px solid var(--glass-border); border-radius:var(--radius-card);
            padding:24px; margin-bottom:24px; box-shadow:var(--shadow-glass);
        }
        .card-title { font-size:18px; font-weight:700; margin-bottom:20px; color:var(--gold); display:flex; align-items:center; gap:8px; }

        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:13px; color:var(--gray-soft); margin-bottom:6px; font-weight:500; }
        .form-group input {
            width:100%; padding:14px 16px; border-radius:var(--radius-input);
            background:rgba(255,255,255,0.05); border:1px solid var(--glass-border);
            color:var(--white); font-family:var(--font); font-size:15px; outline:none;
            transition:var(--transition);
        }
        .form-group input:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(212,175,55,0.15); }

        .btn-primary {
            width:100%; padding:14px; border:none; border-radius:var(--radius-btn);
            background:linear-gradient(145deg, var(--gold), var(--gold-dark));
            color:var(--emerald-dark); font-weight:700; font-size:16px; cursor:pointer;
            transition:var(--transition); box-shadow:0 8px 30px rgba(212,175,55,0.3);
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 14px 40px rgba(212,175,55,0.5); }

        .btn-outline {
            width:100%; padding:14px; border:1px solid var(--gold); border-radius:var(--radius-btn);
            background:transparent; color:var(--gold); font-weight:600; font-size:16px; cursor:pointer;
            transition:var(--transition); display:flex; align-items:center; justify-content:center; gap:8px;
            margin-top:12px;
        }
        .btn-outline:hover { background:rgba(212,175,55,0.1); }

        .btn-danger {
            width:100%; padding:14px; border:none; border-radius:var(--radius-btn);
            background:rgba(255,71,87,0.15); color:#FF4757; font-weight:600; font-size:16px;
            cursor:pointer; transition:var(--transition); display:flex; align-items:center; justify-content:center; gap:8px;
            margin-top:24px;
        }
        .btn-danger:hover { background:rgba(255,71,87,0.25); }

        .message { padding:12px 16px; border-radius:12px; margin-bottom:20px; font-size:14px; font-weight:500; }
        .message.success { background:rgba(46,213,115,0.15); color:#2ED573; border:1px solid rgba(46,213,115,0.2); }
        .message.error { background:rgba(255,71,87,0.15); color:#FF4757; border:1px solid rgba(255,71,87,0.2); }

        .divider { border-top:1px solid var(--glass-border); margin:24px 0; }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <a href="home.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        <h2>Account <span>Settings</span></h2>

        <?php if ($success): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-user-circle"></i> Profile Information</div>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>

        <!-- Change Password Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-lock"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn-outline"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>

        <!-- Logout / Deactivate -->
        <a href="logout.php" class="btn-outline" style="text-align:center; text-decoration:none;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</body>
</html>