<?php
require_once 'config.php';
if (isLoggedIn()) {
    redirect('home.php');
}

$message = "";
if (isset($_POST['register'])) {
    $full_name = sanitize($_POST['full_name']);
    $phone     = sanitize($_POST['phone']);
    $password  = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $agree     = isset($_POST['agree']) ? true : false;

    if (empty($full_name) || empty($phone) || empty($password) || empty($cpassword)) {
        $message = "All fields are required!";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $message = "Enter a valid 10‑digit Indian mobile number.";
    } elseif (!$agree) {
        $message = "You must agree to the Privacy Policy & Terms of Service.";
    } elseif ($password !== $cpassword) {
        $message = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE phone = '$phone'");
        if (mysqli_num_rows($check) > 0) {
            $message = "This mobile number is already registered!";
        } else {
            $email = $phone . '@helpgo.local';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = mysqli_query($conn,
                "INSERT INTO users (full_name, phone, email, password)
                 VALUES ('$full_name', '$phone', '$email', '$hashed_password')"
            );

            if ($insert) {
                $user_id = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO wallet (user_id, balance) VALUES ($user_id, 0)");

                $_SESSION['user_id']   = $user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_type'] = 'customer';
                redirect('home.php');
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HelpGo – Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ===== ROOT VARIABLES ===== */
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html,
        body {
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
            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }
            25% {
                transform: translate(40px, -30px) scale(1.1);
            }
            50% {
                transform: translate(-30px, 40px) scale(0.9);
            }
            75% {
                transform: translate(-40px, -20px) scale(1.05);
            }
        }

        /* Dotted pattern - top right */
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

        /* Abstract curves */
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
        .register-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
            animation: fadeSlideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }
        @keyframes fadeSlideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            0%,
            100% {
                transform: scale(1);
                opacity: 0.6;
            }
            50% {
                transform: scale(1.12);
                opacity: 0.1;
            }
        }

        .brand-name {
            font-family: var(--font-display);
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .brand-name .help {
            color: var(--white);
        }
        .brand-name .go {
            color: var(--gold);
        }
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
            0% {
                opacity: 0.5;
            }
            100% {
                opacity: 1;
            }
        }

        /* ===== CARD HEADING ===== */
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
        .form-group:last-of-type {
            margin-bottom: 6px;
        }

        /* Floating input wrapper */
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

        /* Phone prefix */
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

        /* Toggle password */
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

        /* ===== PASSWORD STRENGTH ===== */
        .strength-wrap {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .strength-bar {
            flex: 1;
            height: 4px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1), background 0.4s;
        }
        .strength-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-muted);
            min-width: 52px;
            text-align: right;
            letter-spacing: 0.2px;
            transition: var(--transition);
        }

        /* ===== PASSWORDS MATCH ===== */
        .match-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-muted);
            margin-top: 4px;
            padding-left: 4px;
            transition: var(--transition);
            min-height: 22px;
        }
        .match-indicator .icon {
            font-size: 13px;
            transition: var(--transition);
        }
        .match-indicator.match {
            color: #2ED573;
        }
        .match-indicator.match .icon {
            color: #2ED573;
        }

        /* ===== TERMS ===== */
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 18px;
            margin-bottom: 20px;
        }
        .terms-group input[type="checkbox"] {
            display: none;
        }
        .check-box {
            width: 22px;
            height: 22px;
            min-width: 22px;
            border: 2px solid rgba(212, 175, 55, 0.25);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            cursor: pointer;
            background: rgba(255, 255, 255, 0.03);
            margin-top: 1px;
        }
        .check-box i {
            color: var(--emerald);
            font-size: 11px;
            opacity: 0;
            transform: scale(0.5);
            transition: var(--transition);
        }
        .terms-group input:checked+.check-box {
            background: var(--gold);
            border-color: var(--gold);
            box-shadow: 0 0 20px var(--gold-glow-soft);
        }
        .terms-group input:checked+.check-box i {
            opacity: 1;
            transform: scale(1);
        }
        .terms-text {
            font-size: 13px;
            color: var(--gray-soft);
            font-weight: 400;
            line-height: 1.5;
            letter-spacing: 0.1px;
        }
        .terms-text a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border-bottom: 1px solid transparent;
        }
        .terms-text a:hover {
            border-bottom-color: var(--gold);
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
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        .btn-primary i {
            font-size: 16px;
            color: var(--emerald-dark);
        }

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
            margin-top: 16px;
            animation: shake 0.5s ease;
        }
        .alert-error i {
            font-size: 16px;
            flex-shrink: 0;
        }
        @keyframes shake {
            0%,
            100% {
                transform: translateX(0);
            }
            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }
            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        /* ===== LOGIN CARD (separate) ===== */
        .login-card {
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
        .login-card p {
            font-size: 14px;
            color: var(--gray-soft);
            font-weight: 400;
        }
        .login-card a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            letter-spacing: 0.2px;
        }
        .login-card a:hover {
            color: var(--gold-light);
            text-shadow: 0 0 20px var(--gold-glow);
        }
        .login-card .arrow {
            display: inline-block;
            transition: var(--transition);
        }
        .login-card a:hover .arrow {
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
            body {
                padding: 16px 12px 30px;
            }
            .glass-card {
                padding: 22px 18px 22px;
            }
            .login-card {
                padding: 14px 18px;
            }
            .brand-name {
                font-size: 28px;
            }
            .brand-icon {
                width: 62px;
                height: 62px;
                font-size: 28px;
            }
            .card-header h2 {
                font-size: 20px;
            }
            .input-wrap input {
                font-size: 14px;
                padding: 12px 14px 12px 44px;
                min-height: 48px;
            }
            .input-wrap .input-icon {
                left: 14px;
                font-size: 14px;
            }
            .phone-input {
                padding-left: 60px !important;
            }
            .phone-prefix {
                left: 14px;
                font-size: 13px;
            }
            .btn-primary {
                padding: 14px;
                font-size: 15px;
            }
            .trust-icon {
                width: 44px;
                height: 44px;
                font-size: 15px;
            }
            .trust-label {
                font-size: 9px;
            }
            .trust-label span {
                font-size: 8px;
            }
            .terms-text {
                font-size: 12px;
            }
        }

        @media (min-width: 600px) {
            .register-container {
                padding: 20px 0;
            }
        }
        @media (min-width: 900px) {
            body {
                align-items: center;
                padding: 40px;
            }
            .register-container {
                max-width: 420px;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--gold);
            border-radius: 10px;
        }
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
    <div class="register-container">

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
            <p class="brand-tagline">Join the Instant Help Network</p>
        </div>

        <!-- ===== REGISTRATION CARD ===== -->
        <div class="glass-card">
            <div class="card-glow"></div>

            <div class="card-header">
                <h2>Create Your Account</h2>
                <p>Start using HelpGo in less than a minute</p>
            </div>

            <form method="POST" id="registerForm">

                <!-- Full Name -->
                <div class="form-group">
                    <div class="input-wrap" id="nameWrap">
                        <span class="input-icon"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="full_name" id="full_name" placeholder="Full name" autocomplete="name" required>
                    </div>
                </div>

                <!-- Mobile Number -->
                <div class="form-group">
                    <div class="input-wrap" id="phoneWrap">
                        <span class="phone-prefix"><span class="flag">🇮🇳</span> +91</span>
                        <input type="tel" name="phone" id="phone" class="phone-input" placeholder="Mobile number" maxlength="10" autocomplete="tel" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div class="input-wrap" id="passWrap">
                        <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" placeholder="Password (min 6 characters)" autocomplete="new-password" minlength="6" required>
                        <button type="button" class="toggle-pass" tabindex="-1" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span class="strength-label" id="strengthLabel"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <div class="input-wrap" id="cpassWrap">
                        <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="cpassword" id="cpassword" placeholder="Confirm password" autocomplete="new-password" required>
                        <button type="button" class="toggle-pass" tabindex="-1" aria-label="Toggle confirm password visibility">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="match-indicator" id="matchIndicator">
                        <span class="icon"><i class="fa-regular fa-circle-check"></i></span>
                        <span class="text">Passwords match</span>
                    </div>
                </div>

                <!-- Terms -->
                <div class="terms-group">
                    <input type="checkbox" name="agree" id="agree" required>
                    <label for="agree" class="check-box">
                        <i class="fa-solid fa-check"></i>
                    </label>
                    <span class="terms-text">
                        I agree to the <a href="privacy.php">Privacy Policy</a> &amp; <a href="terms.php">Terms of Service</a>
                    </span>
                </div>

                <!-- Button -->
                <button type="submit" name="register" class="btn-primary" id="registerBtn">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </button>

                <?php if (!empty($message)): ?>
                    <div class="alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

            </form>
        </div>

        <!-- ===== LOGIN CARD ===== -->
        <div class="login-card">
            <p>
                Already have an account?
                <a href="index.php">
                    Sign In <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
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
            var phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });

            // ---- Password strength ----
            var passInput = document.getElementById('password');
            var strengthFill = document.getElementById('strengthFill');
            var strengthLabel = document.getElementById('strengthLabel');

            function updateStrength(val) {
                var score = 0;
                if (val.length >= 6) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;

                var width = 0,
                    color = '#6B7A73',
                    label = '';
                switch (score) {
                    case 0:
                        width = 0;
                        color = '#6B7A73';
                        label = '';
                        break;
                    case 1:
                        width = 25;
                        color = '#FF6B6B';
                        label = 'Weak';
                        break;
                    case 2:
                        width = 50;
                        color = '#FFA94D';
                        label = 'Medium';
                        break;
                    case 3:
                        width = 75;
                        color = '#D4AF37';
                        label = 'Strong';
                        break;
                    case 4:
                        width = 100;
                        color = '#2ED573';
                        label = 'Very Strong';
                        break;
                }
                strengthFill.style.width = width + '%';
                strengthFill.style.background = color;
                strengthLabel.textContent = label;
                strengthLabel.style.color = (val.length > 0) ? color : '#6B7A73';
            }

            passInput.addEventListener('input', function() {
                updateStrength(this.value);
                checkMatch();
            });

            // ---- Passwords match ----
            var cpassInput = document.getElementById('cpassword');
            var matchIndicator = document.getElementById('matchIndicator');

            function checkMatch() {
                var pass = passInput.value;
                var cpass = cpassInput.value;
                if (cpass.length === 0) {
                    matchIndicator.className = 'match-indicator';
                    matchIndicator.querySelector('.text').textContent = 'Confirm your password';
                    matchIndicator.querySelector('.icon').innerHTML = '<i class="fa-regular fa-circle-check"></i>';
                    return;
                }
                if (pass === cpass && pass.length > 0) {
                    matchIndicator.className = 'match-indicator match';
                    matchIndicator.querySelector('.text').textContent = '✔ Passwords match';
                    matchIndicator.querySelector('.icon').innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                } else {
                    matchIndicator.className = 'match-indicator';
                    matchIndicator.querySelector('.text').textContent = 'Passwords do not match';
                    matchIndicator.querySelector('.icon').innerHTML = '<i class="fa-regular fa-circle-xmark"></i>';
                    matchIndicator.style.color = '#FF6B6B';
                }
                // reset color if match
                if (pass === cpass && pass.length > 0) {
                    matchIndicator.style.color = '#2ED573';
                } else if (cpass.length > 0) {
                    matchIndicator.style.color = '#FF6B6B';
                } else {
                    matchIndicator.style.color = '#6B7A73';
                }
            }

            cpassInput.addEventListener('input', checkMatch);
            passInput.addEventListener('input', checkMatch);

            // ---- Form validation + Ripple ----
            var form = document.getElementById('registerForm');
            var btn = document.getElementById('registerBtn');

            form.addEventListener('submit', function(e) {
                var hasError = false;
                var name = document.getElementById('full_name').value.trim();
                var phone = phoneInput.value.trim();
                var password = passInput.value;
                var cpassword = cpassInput.value;
                var agree = document.getElementById('agree').checked;

                // Reset errors
                document.querySelectorAll('.input-wrap').forEach(function(w) {
                    w.classList.remove('error');
                });

                if (!name) {
                    document.getElementById('nameWrap').classList.add('error');
                    hasError = true;
                }
                if (!phone || phone.length !== 10 || !/^[6-9]/.test(phone)) {
                    document.getElementById('phoneWrap').classList.add('error');
                    hasError = true;
                }
                if (!password || password.length < 6) {
                    document.getElementById('passWrap').classList.add('error');
                    hasError = true;
                }
                if (password !== cpassword || cpassword.length === 0) {
                    document.getElementById('cpassWrap').classList.add('error');
                    hasError = true;
                }
                if (!agree) {
                    e.preventDefault();
                    alert('You must agree to the Privacy Policy & Terms of Service.');
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();
                    // Shake button
                    btn.style.animation = 'none';
                    void btn.offsetHeight;
                    btn.style.animation = 'shake 0.5s ease';
                } else {
                    // Ripple effect on button
                    createRipple(e);
                }
            });

            // ---- Ripple effect ----
            function createRipple(e) {
                var rect = btn.getBoundingClientRect();
                var x = (e.clientX || e.touches?.[0]?.clientX || rect.left + rect.width / 2) - rect.left;
                var y = (e.clientY || e.touches?.[0]?.clientY || rect.top + rect.height / 2) - rect.top;
                var ripple = document.createElement('span');
                ripple.className = 'ripple';
                var size = Math.max(rect.width, rect.height) * 1.2;
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (x - size / 2) + 'px';
                ripple.style.top = (y - size / 2) + 'px';
                btn.appendChild(ripple);
                setTimeout(function() {
                    ripple.remove();
                }, 700);
            }

            // ---- Trigger ripple on click even if validation fails? only on valid submit ----
            btn.addEventListener('click', function(e) {
                // If form is valid, ripple is created in submit handler.
                // But we also want ripple on click if form is valid.
                // We'll let submit handle it.
            });

            // ---- Auto-dismiss alert after 5s ----
            var alertEl = document.querySelector('.alert-error');
            if (alertEl) {
                setTimeout(function() {
                    alertEl.style.transition = 'opacity 0.6s, transform 0.6s';
                    alertEl.style.opacity = '0';
                    alertEl.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alertEl.style.display = 'none';
                    }, 600);
                }, 5000);
            }

            // ---- Initialize strength on page load (if password has value) ----
            if (passInput.value.length > 0) {
                updateStrength(passInput.value);
            }
            if (cpassInput.value.length > 0) {
                checkMatch();
            }

        })();
    </script>

</body>
</html>