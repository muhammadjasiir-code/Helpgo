<?php
// forgot-password.php – HelpGo (Emerald Prestige theme)
require_once __DIR__ . '/config.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address or phone number.';
    } else {
        // Check if user exists by email or phone
        $userQuery = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE email = '$email' OR phone = '$email' LIMIT 1");
        if ($userQuery && mysqli_num_rows($userQuery) == 1) {
            $user = mysqli_fetch_assoc($userQuery);
            $userId = (int)$user['id'];

            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in password_resets table
            $insert = mysqli_query($conn, "INSERT INTO password_resets (user_id, token, expires_at) VALUES ($userId, '$token', '$expiry')");

            if ($insert) {
                // Build reset link
                $resetLink = SITE_URL . "reset-password.php?token=" . $token;

                // Send email (simulated – replace with actual mail() or an email API)
                $subject = "HelpGo - Password Reset Request";
                $body    = "Hello " . h($user['full_name']) . ",\n\n"
                         . "You requested a password reset. Click the link below to choose a new password:\n"
                         . $resetLink . "\n\n"
                         . "This link expires in 1 hour.\n\n"
                         . "If you did not request this, please ignore this email.\n\n"
                         . "– HelpGo Team";
                $headers = "From: no-reply@yourdomain.com\r\n";

                // Attempt to send the email (if mail() is configured)
                $mailSent = @mail($user['email'], $subject, $body, $headers);

                // For demonstration, we always show success (since mail might be blocked locally)
                $message = "If an account with that email/phone exists, a password reset link has been sent.";
            } else {
                $error = "Could not process your request. Please try again.";
            }
        } else {
            // Don't reveal whether the user exists – show the same success message
            $message = "If an account with that email/phone exists, a password reset link has been sent.";
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33;
            --emerald-light: #0E5548;
            --emerald-dark: #04261F;
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8962E;
            --white: #FFFFFF;
            --gray-soft: #AEB8B2;
            --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.6);
            --glass-border: rgba(212, 175, 55, 0.2);
            --shadow-glass: 0 20px 50px rgba(0, 0, 0, 0.4);
            --radius-card: 28px;
            --radius-btn: 20px;
            --font: 'Poppins', sans-serif;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
            color: var(--white);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Floating background orbs */
        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(130px);
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width: 500px; height: 500px; background: var(--gold); top: -200px; right: -150px; }
        .bg-orb:nth-child(2) { width: 350px; height: 350px; background: var(--gold); bottom: -100px; left: -120px; animation-delay: -10s; }
        @keyframes orbFloat { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(40px, -30px) scale(1.1); } }

        .container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gold);
            text-decoration: none;
            margin-bottom: 24px;
            font-weight: 500;
            transition: var(--transition);
        }
        .back-link:hover { gap: 12px; }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 32px 24px;
            box-shadow: var(--shadow-glass);
        }

        .card h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--white);
        }
        .card h2 span { color: var(--gold); }
        .card .subtitle {
            font-size: 14px;
            color: var(--gray-soft);
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            color: var(--gray-soft);
            margin-bottom: 6px;
            font-weight: 500;
        }
        .input-group input {
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--radius-btn);
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--white);
            font-family: var(--font);
            font-size: 15px;
            outline: none;
            transition: var(--transition);
        }
        .input-group input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
            background: rgba(255,255,255,0.08);
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: var(--radius-btn);
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            font-weight: 700;
            font-size: 17px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 30px rgba(212,175,55,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(212,175,55,0.5);
        }

        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            animation: fadeInUp 0.4s ease;
        }
        .message.success {
            background: rgba(46,213,115,0.15);
            color: #2ED573;
            border: 1px solid rgba(46,213,115,0.2);
        }
        .message.error {
            background: rgba(255,71,87,0.15);
            color: #FF4757;
            border: 1px solid rgba(255,71,87,0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--gray-muted);
        }
        .footer-text a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>

        <div class="card">
            <h2>Forgot <span>Password?</span></h2>
            <p class="subtitle">Enter your email or phone number and we'll send you a link to reset your password.</p>

            <?php if ($message): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?= h($message) ?></div>
            <?php elseif ($error): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label>Email or Phone Number</label>
                    <input type="text" name="email" placeholder="you@example.com or +919876543210" required>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
        </div>

        <div class="footer-text">
            Remember your password? <a href="index.php">Sign In</a>
        </div>
    </div>
</body>
</html>