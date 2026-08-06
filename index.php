<?php
require_once 'config.php';
if (isLoggedIn()) { redirect('home.php'); }

$error = '';
$phone = ''; // ensure $phone is always defined

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim input (do not rely on an undefined sanitize function)
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        $error = 'Please enter your mobile number and password.';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $error = 'Please enter a valid 10-digit Indian mobile number.';
    } else {
        // ---------- FIX: Use a prepared statement to prevent SQL injection ----------
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, password, user_type FROM users WHERE phone = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $phone);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) === 1) {
            mysqli_stmt_bind_result($stmt, $id, $full_name, $hashed_password, $user_type);
            mysqli_stmt_fetch($stmt);
            
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id']   = $id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_type'] = $user_type;
                if ($user_type === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user_type === 'rider') {
                    redirect('rider/home.php');
                } else {
                    redirect('home.php');
                }
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'No account found with that number.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>HelpGo – Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ===== ROOT VARIABLES (matching register.php) ===== */
        :root {
            --emerald: #083C33;
            --emerald-light: #0E5548;
            --emerald-dark: #04261F;
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8962E;
            --gold-glow: rgba(212, 175, 55, 0.35);
            --gold-glow-soft: rgba(212, 175, 55, 0.12);
            --white: #FFFFFF;
            --gray-soft: #AEB8B2;
            --gray-muted: #6B7A73;
            --bg-glass: rgba(8, 60, 51, 0.65);
            --border-glass: rgba(212, 175, 55, 0.15);
            --border-glass-focus: rgba(212, 175, 55, 0.5);
            --shadow-glass: 0 30px 80px rgba(0, 0, 0, 0.5);
            --radius-card: 28px;
            --radius-input: 16px;
            --radius-btn: 16px;
            --radius-icon: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-primary: 'Plus Jakarta Sans', 'Outfit', sans-serif;
            --font-display: 'Outfit', 'Plus Jakarta Sans', sans-serif;
        }

        /* ===== RESET ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }
        body {
            font-family: var(--font-primary);
            background: var(--emerald);
            color: var(--white);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 24px 16px 40px;
            position: relative;
        }

        /* ===== BACKGROUND ===== */
        .bg-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
            background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
        }
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.25;
            animation: orbFloat 25s infinite ease-in-out;
        }
        .bg-orb:nth-child(1) {
            width: 600px;
            height: 600px;
            background: var(--gold);
            top: -200px;
            right: -250px;
            opacity: 0.08;
        }
        .bg-orb:nth-child(2) {
            width: 500px;
            height: 500px;
            background: var(--gold);
            bottom: -200px;
            left: -200px;
            opacity: 0.06;
            animation-delay: -10s;
        }
        .bg-orb:nth-child(3) {
            width: 300px;
            height: 300px;
            background: var(--gold);
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.04;
            animation-delay: -18s;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(40px, -30px) scale(1.1); }
            50% { transform: translate(-30px, 40px) scale(0.9); }
            75% { transform: translate(-40px, -20px) scale(1.05); }
        }

        .dot-pattern {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 180px;
            height: 180px;
            z-index: 0;
            pointer-events: none;
            opacity: 0.12;
            background-image: radial-gradient(circle, var(--gold) 1.5px, transparent 1.5px);
            background-size: 20px 20px;
            mask-image: radial-gradient(ellipse at 100% 0%, black 30%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at 100% 0%, black 30%, transparent 70%);
        }

        .curve-deco {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 200px;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at 0% 100%, rgba(212, 175, 55, 0.04) 0%, transparent 60%);
        }
        .curve-deco::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 200'%3E%3Cpath d='M0 200 Q300 60 600 120 T1200 40 L1200 200 Z' fill='rgba(212,175,55,0.03)'/%3E%3C/svg%3E") no-repeat bottom / 100% 100%;
        }

        /* ===== CONTAINER ===== */
        .login-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
            animation: fadeSlideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }
        @keyframes fadeSlideUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== BRANDING ===== */
        .brand-section {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(145deg, var(--emerald-light), var(--emerald-dark));
            border-radius: var(--radius-icon);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: var(--gold);
            box-shadow: 0 0 40px var(--gold-glow), inset 0 1px 0 rgba(212, 175, 55, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.15);
            position: relative;
            margin-bottom: 14px;
            transition: var(--transition);
        }
        .brand-icon:hover {
            transform: scale(1.04);
            box-shadow: 0 0 60px var(--gold-glow);
        }
        .brand-icon .ring-pulse {
            position: absolute;
            top: -6px;
            left: -6px;
            right: -6px;
            bottom: -6px;
            border: 1.5px solid rgba(212, 175, 55, 0.2);
            border-radius: 22px;
            animation: ringPulse 3s ease-in-out infinite;
        }
        @keyframes ringPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.12); opacity: 0.1; }
        }

        .brand-name {
            font-family: var(--font-display);
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .brand-name .help { color: var(--white); }
        .brand-name .go { color: var(--gold); }

        .brand-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin: 6px auto 4px;
            max-width: 200px;
        }
        .brand-divider .line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0.4;
        }
        .brand-divider .diamond {
            color: var(--gold);
            font-size: 6px;
            opacity: 0.6;
        }
        .brand-tagline {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-soft);
            letter-spacing: 0.3px;
            margin-top: 2px;
        }

        /* ===== GLASS CARD ===== */
        .glass-card {
            background: var(--bg-glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-glass);
            box-shadow: var(--shadow-glass), inset 0 1px 0 rgba(255, 255, 255, 0.04);
            padding: 28px 24px 26px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        .glass-card::before {
            content: '';
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: radial-gradient(circle at 30% 20%, rgba(212, 175, 55, 0.04) 0%, transparent 60%);
            pointer-events: none;
        }
        .glass-card .card-glow {
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.12), transparent 40%, transparent 60%, rgba(212, 175, 55, 0.06));
            z-index: -1;
            animation: borderGlow 6s ease-in-out infinite alternate;
        }
        @keyframes borderGlow {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .card-header {
            text-align: center;
            margin-bottom: 24px;
            position: relative;
        }
        .card-header h2 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            color: var(--white);
            letter-spacing: -0.3px;
        }
        .card-header p {
            font-size: 13px;
            color: var(--gray-soft);
            font-weight: 400;
            margin-top: 4px;
            letter-spacing: 0.2px;
        }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 14px;
            position: relative;
        }

        .input-wrap {
            position: relative;
            background: rgba(255, 255, 255, 0.04);
            border-radius: var(--radius-input);
            border: 1.5px solid rgba(212, 175, 55, 0.08);
            transition: var(--transition);
            overflow: hidden;
        }
        .input-wrap:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 5px var(--gold-glow-soft), inset 0 0 20px rgba(212, 175, 55, 0.02);
            background: rgba(255, 255, 255, 0.06);
        }
        .input-wrap.error {
            border-color: #FF6B6B;
            box-shadow: 0 0 0 5px rgba(255, 107, 107, 0.12);
        }
        .input-wrap .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-muted);
            font-size: 15px;
            transition: var(--transition);
            z-index: 2;
            pointer-events: none;
        }
        .input-wrap:focus-within .input-icon {
            color: var(--gold);
        }
        .input-wrap input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: transparent;
            border: none;
            color: var(--white);
            font-size: 15px;
            font-family: var(--font-primary);
            font-weight: 500;
            outline: none;
            letter-spacing: 0.2px;
            min-height: 54px;
        }
        .input-wrap input::placeholder {
            color: var(--gray-muted);
            font-weight: 400;
            font-size: 14px;
            letter-spacing: 0px;
        }
        .input-wrap input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(8, 60, 51, 0.9) inset !important;
            -webkit-text-fill-color: var(--white) !important;
            border-radius: var(--radius-input);
        }

        .phone-prefix {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-soft);
            font-weight: 600;
            font-size: 14px;
            z-index: 2;
            pointer-events: none;
            letter-spacing: 0.3px;
        }
        .phone-prefix .flag {
            margin-right: 4px;
            opacity: 0.7;
        }
        .phone-input {
            padding-left: 68px !important;
        }

        .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-muted);
            cursor: pointer;
            padding: 8px;
            border-radius: 10px;
            transition: var(--transition);
            font-size: 15px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-pass:hover {
            color: var(--gold);
            background: rgba(212, 175, 55, 0.08);
        }
        .toggle-pass:active {
            transform: translateY(-50%) scale(0.92);
        }

        /* ===== OPTIONS ROW ===== */
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 6px 0 20px;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-soft);
            font-size: 13px;
            cursor: pointer;
        }
        .remember-me input[type="checkbox"] {
            accent-color: var(--gold);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .forgot-link {
            color: var(--gold);
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        .forgot-link:hover {
            color: var(--gold-light);
            text-decoration: underline;
        }

        /* ===== BUTTON ===== */
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            border: none;
            border-radius: var(--radius-btn);
            color: var(--emerald-dark);
            font-size: 16px;
            font-weight: 700;
            font-family: var(--font-primary);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 32px rgba(212, 175, 55, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 48px rgba(212, 175, 55, 0.4);
        }
        .btn-primary:active {
            transform: translateY(0) scale(0.98);
        }
        .btn-primary .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: rippleAnim 0.6s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim {
            to { transform: scale(4); opacity: 0; }
        }
        .btn-primary i {
            font-size: 16px;
            color: var(--emerald-dark);
        }
        .btn-primary .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: var(--emerald-dark);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        .btn-primary.loading .spinner { display: block; }
        .btn-primary.loading .btn-text { display: none; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* ===== ALERT ===== */
        .alert-error {
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            animation: shake 0.5s ease;
        }
        .alert-error i {
            font-size: 16px;
            flex-shrink: 0;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* ===== SIGNUP CARD ===== */
        .signup-card {
            margin-top: 16px;
            padding: 18px 24px;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-glass);
            box-shadow: var(--shadow-glass), inset 0 1px 0 rgba(255, 255, 255, 0.03);
            text-align: center;
            transition: var(--transition);
        }
        .signup-card p {
            font-size: 14px;
            color: var(--gray-soft);
            font-weight: 400;
        }
        .signup-card a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            letter-spacing: 0.2px;
        }
        .signup-card a:hover {
            color: var(--gold-light);
            text-shadow: 0 0 20px var(--gold-glow);
        }
        .signup-card .arrow {
            display: inline-block;
            transition: var(--transition);
        }
        .signup-card a:hover .arrow {
            transform: translateX(4px);
        }

        /* ===== TRUST SECTION ===== */
        .trust-section {
            display: flex;
            justify-content: space-around;
            margin-top: 24px;
            gap: 8px;
        }
        .trust-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex: 1;
        }
        .trust-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.06);
            border: 1px solid rgba(212, 175, 55, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--gold);
            transition: var(--transition);
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.04);
            position: relative;
        }
        .trust-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            border: 1px solid rgba(212, 175, 55, 0.06);
            animation: ringPulse 4s ease-in-out infinite;
        }
        .trust-item:hover .trust-icon {
            transform: translateY(-3px);
            box-shadow: 0 0 40px var(--gold-glow-soft);
            border-color: rgba(212, 175, 55, 0.2);
        }
        .trust-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--gray-soft);
            text-align: center;
            letter-spacing: 0.2px;
            line-height: 1.3;
        }
        .trust-label span {
            display: block;
            font-weight: 400;
            font-size: 9px;
            color: var(--gray-muted);
            margin-top: 1px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 420px) {
            body { padding: 16px 12px 30px; }
            .glass-card { padding: 22px 18px 22px; }
            .signup-card { padding: 14px 18px; }
            .brand-name { font-size: 28px; }
            .brand-icon { width: 62px; height: 62px; font-size: 28px; }
            .card-header h2 { font-size: 20px; }
            .input-wrap input { font-size: 14px; padding: 12px 14px 12px 44px; min-height: 48px; }
            .input-wrap .input-icon { left: 14px; font-size: 14px; }
            .phone-input { padding-left: 60px !important; }
            .phone-prefix { left: 14px; font-size: 13px; }
            .btn-primary { padding: 14px; font-size: 15px; }
            .trust-icon { width: 44px; height: 44px; font-size: 15px; }
            .trust-label { font-size: 9px; }
            .trust-label span { font-size: 8px; }
        }

        @media (min-width: 600px) {
            .login-container { padding: 20px 0; }
        }
        @media (min-width: 900px) {
            body { align-items: center; padding: 40px; }
            .login-container { max-width: 420px; }
        }

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }
    </style>
</head>
<body>

    <!-- ===== BACKGROUND LAYERS ===== -->
    <div class="bg-layer">
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
    </div>
    <div class="dot-pattern"></div>
    <div class="curve-deco"></div>

    <!-- ===== MAIN CONTAINER ===== -->
    <div class="login-container">

        <!-- ===== BRANDING ===== -->
        <div class="brand-section">
            <div class="brand-icon">
                <div class="ring-pulse"></div>
                <i class="fa-solid fa-handshake-angle"></i>
            </div>
            <div class="brand-name">
                <span class="help">Help</span><span class="go">Go</span>
            </div>
            <div class="brand-divider">
                <span class="line"></span>
                <span class="diamond"><i class="fa-solid fa-gem"></i></span>
                <span class="line"></span>
            </div>
            <p class="brand-tagline">തൃക്കരിപ്പൂരിന്റെ സ്വന്തം ഡെലിവറി സേവനം</p>
        </div>

        <!-- ===== LOGIN CARD ===== -->
        <div class="glass-card">
            <div class="card-glow"></div>

            <div class="card-header">
                <h2>Welcome Back!</h2>
                <p>Login to continue</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">

                <!-- Mobile Number -->
                <div class="form-group">
                    <div class="input-wrap" id="phoneWrap">
                        <span class="phone-prefix"><span class="flag">🇮🇳</span> +91</span>
                        <input type="tel" name="phone" id="phone" class="phone-input" placeholder="Mobile number" maxlength="10" autocomplete="tel" value="<?= htmlspecialchars($phone) ?>" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div class="input-wrap" id="passWrap">
                        <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                        <button type="button" class="toggle-pass" tabindex="-1" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Options -->
                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <!-- Button -->
                <button type="submit" class="btn-primary" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fa-solid fa-arrow-right"></i> Sign In</span>
                </button>

            </form>
        </div>

        <!-- ===== SIGNUP CARD ===== -->
        <div class="signup-card">
            <p>
                Don't have an account?
                <a href="register.php">
                    Create Account <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </p>
        </div>

        <!-- ===== TRUST SECTION ===== -->
        <div class="trust-section">
            <div class="trust-item">
                <div class="trust-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <span class="trust-label">Secure <span>Your data is protected</span></span>
            </div>
            <div class="trust-item">
                <div class="trust-icon"><i class="fa-solid fa-bolt"></i></div>
                <span class="trust-label">Fast <span>Account ready instantly</span></span>
            </div>
            <div class="trust-item">
                <div class="trust-icon"><i class="fa-solid fa-headset"></i></div>
                <span class="trust-label">24/7 Support <span>We're always here</span></span>
            </div>
        </div>

    </div>

    <!-- ===== SCRIPTS ===== -->
    <script>
        (function() {
            'use strict';

            // ---- Toggle password visibility ----
            document.querySelectorAll('.toggle-pass').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const wrap = this.closest('.input-wrap');
                    const input = wrap.querySelector('input');
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fa-regular fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fa-regular fa-eye';
                    }
                });
            });

            // ---- Phone: digits only, max 10 ----
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });

            // ---- Form submission: loading state & ripple ----
            const form = document.getElementById('loginForm');
            const btn = document.getElementById('loginBtn');

            form.addEventListener('submit', function(e) {
                const phone = phoneInput.value.trim();
                const password = document.getElementById('password').value.trim();
                if (!phone || !password) {
                    if (!phone) document.getElementById('phoneWrap').classList.add('error');
                    if (!password) document.getElementById('passWrap').classList.add('error');
                    e.preventDefault();
                    return;
                }
                document.querySelectorAll('.input-wrap').forEach(w => w.classList.remove('error'));

                btn.classList.add('loading');
                btn.disabled = true;
            });

            // ---- Ripple effect ----
            btn.addEventListener('click', function(e) {
                if (btn.classList.contains('loading')) return;
                const rect = btn.getBoundingClientRect();
                const x = (e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : rect.left + rect.width / 2)) - rect.left;
                const y = (e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : rect.top + rect.height / 2)) - rect.top;
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                const size = Math.max(rect.width, rect.height) * 1.2;
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (x - size / 2) + 'px';
                ripple.style.top = (y - size / 2) + 'px';
                btn.appendChild(ripple);
                setTimeout(() => ripple.remove(), 700);
            });

            // ---- Auto-dismiss error ----
            const alertEl = document.querySelector('.alert-error');
            if (alertEl) {
                setTimeout(() => {
                    alertEl.style.transition = 'opacity 0.6s, transform 0.6s';
                    alertEl.style.opacity = '0';
                    alertEl.style.transform = 'translateY(-10px)';
                    setTimeout(() => alertEl.style.display = 'none', 600);
                }, 5000);
            }

            // ---- Remove error class on focus ----
            document.querySelectorAll('.input-wrap input').forEach(inp => {
                inp.addEventListener('focus', function() {
                    this.closest('.input-wrap').classList.remove('error');
                });
            });

        })();
    </script>

</body>
</html>