<?php
// parcel.php – Parcel Delivery (Coming Soon with WhatsApp notify option)
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Parcel Delivery – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --bg-start: #0B2E2A;
            --bg-end: #1A4A44;
            --glass-bg: rgba(255,255,255,0.06);
            --glass-border: rgba(255,255,255,0.10);
            --gold: #E8B84A;
            --gold-dark: #C99A2E;
            --white: #FFFFFF;
            --gray-soft: #A8B2AE;
            --gray-muted: #6B7A73;
            --radius-card: 28px;
            --font: 'Outfit', sans-serif;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        html { height: 100%; overflow-y: auto; }
        body {
            font-family: var(--font);
            background: linear-gradient(145deg, var(--bg-start), var(--bg-end));
            color: var(--white);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation: float 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width: 400px; height: 400px; background: var(--gold); top: -150px; right: -100px; }
        .bg-orb:nth-child(2) { width: 300px; height: 300px; background: #2ED573; bottom: -100px; left: -100px; }
        .bg-orb:nth-child(3) { width: 200px; height: 200px; background: var(--gold); top: 40%; left: 10%; }
        @keyframes float { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(30px, -20px) scale(1.05); } }

        .container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 2;
            text-align: center;
            animation: fadeUp 0.8s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gold);
            text-decoration: none;
            margin-bottom: 48px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .back-btn:hover { gap: 12px; }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            margin-bottom: 32px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            font-size: 42px;
            color: var(--gold);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(232,184,74,0.3); }
            70% { box-shadow: 0 0 0 25px rgba(232,184,74,0); }
            100% { box-shadow: 0 0 0 0 rgba(232,184,74,0); }
        }

        h2 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--white);
        }
        h2 span { color: var(--gold); }

        p {
            font-size: 16px;
            color: var(--gray-soft);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 30px 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .notify-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
        .input-group {
            text-align: left;
        }
        .input-group label {
            display: block;
            font-size: 12px;
            color: var(--gray-soft);
            margin-bottom: 6px;
        }
        .input-group input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--white);
            font-family: var(--font);
            font-size: 14px;
            outline: none;
        }
        .input-group input:focus { border-color: var(--gold); }

        .notify-form button {
            padding: 14px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: #0B2E2A;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 4px;
        }
        .notify-form button:hover { box-shadow: 0 8px 25px rgba(232,184,74,0.4); }

        .success-msg {
            margin-top: 16px;
            font-size: 14px;
            color: #2ED573;
            display: none;
        }

        .small-note {
            font-size: 12px;
            color: var(--gray-muted);
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="bg-orb"></div><div class="bg-orb"></div><div class="bg-orb"></div>

    <div class="container">
        <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>

        <div class="glass-card">
            <div class="icon-wrapper">
                <i class="fas fa-box-open"></i>
            </div>

            <h2><span>Parcel</span> Delivery</h2>
            <p>
                We're preparing a fast, secure and reliable parcel service for you.<br>
                <strong>Coming very soon!</strong>
            </p>

            <div style="margin-top: 12px; color: var(--gray-muted);">
                Leave your email or WhatsApp number to be the first to know.
            </div>

            <form id="notifyForm" class="notify-form" onsubmit="submitNotify(event)">
                <div class="input-group">
                    <label>Email Address (optional)</label>
                    <input type="email" id="notifyEmail" placeholder="you@example.com">
                </div>
                <div class="input-group">
                    <label>WhatsApp Number (optional)</label>
                    <input type="tel" id="notifyPhone" placeholder="+91 98765 43210">
                </div>
                <button type="submit">Notify Me</button>
                <p class="small-note">We'll send a message when the service launches.</p>
            </form>
            <div id="notifySuccess" class="success-msg">
                <i class="fas fa-check-circle"></i> You'll be notified! 🎉
            </div>
        </div>
    </div>

    <script>
        function submitNotify(e) {
            e.preventDefault();
            const email = document.getElementById('notifyEmail').value.trim();
            const phone = document.getElementById('notifyPhone').value.trim();
            if (!email && !phone) {
                alert('Please enter at least one contact method.');
                return;
            }
            // In production, send this data to your backend.
            document.getElementById('notifyForm').style.display = 'none';
            document.getElementById('notifySuccess').style.display = 'block';
        }
    </script>
</body>
</html>