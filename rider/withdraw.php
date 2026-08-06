<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];
$walletBalance = getWalletBalance($riderId);
$error = '';
$success = '';

$todayCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM withdrawals WHERE rider_id=$riderId AND DATE(created_at)=CURDATE()"))['cnt'] ?? 0;
$maxDaily = 5;
$minAmount = 50;

if (isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount']);
    $method = sanitize($_POST['method']);

    $details = [];
    if ($method == 'bank') {
        $details['name']    = sanitize($_POST['bank_name'] ?? '');
        $details['account'] = sanitize($_POST['account_no'] ?? '');
        $details['ifsc']    = sanitize($_POST['ifsc_code'] ?? '');
        $details['mobile']  = sanitize($_POST['bank_mobile'] ?? '');
        $required = ['name','account','ifsc','mobile'];
    } else {
        $details['name'] = sanitize($_POST['upi_name'] ?? '');
        $details['upi']  = sanitize($_POST['upi_id'] ?? '');
        $required = ['name','upi'];
    }

    $missing = false;
    foreach ($required as $field) {
        if (empty($details[$field])) $missing = true;
    }

    if ($amount < $minAmount) {
        $error = "Minimum withdrawal amount is ₹$minAmount.";
    } elseif ($amount > $walletBalance) {
        $error = "Insufficient wallet balance.";
    } elseif ($todayCount >= $maxDaily) {
        $error = "You have reached the maximum $maxDaily withdrawals today.";
    } elseif ($missing) {
        $error = "Please fill in all required fields.";
    } else {
        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
        $ins = mysqli_query($conn, "INSERT INTO withdrawals (rider_id, amount, method, details) VALUES ($riderId, $amount, '$method', '$detailsJson')");
        if ($ins) {
            mysqli_query($conn, "UPDATE wallet SET balance = balance - $amount WHERE user_id = $riderId");
            mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, description) VALUES ($riderId, 'debit', $amount, 'Withdrawal request')");
            $success = "Withdrawal request submitted. Your balance has been deducted.";
            $walletBalance = getWalletBalance($riderId);
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    }
}

$withdrawals = mysqli_query($conn, "SELECT * FROM withdrawals WHERE rider_id=$riderId ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Withdraw – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #E55A2B;
            --primary-light: rgba(255,107,53,0.15);
            --bg: #050508;
            --surface: rgba(18,20,30,0.85);
            --surface-hover: rgba(25,28,40,0.9);
            --border: rgba(255,255,255,0.06);
            --text: #ffffff;
            --text-secondary: #8a8f9e;
            --text-muted: #5a5f6e;
            --green: #2ED573;
            --green-light: rgba(46,213,115,0.15);
            --danger: #FF4757;
            --danger-light: rgba(255,71,87,0.15);
            --warning: #FFA502;
            --warning-light: rgba(255,165,2,0.15);
            --radius-sm: 12px;
            --radius-md: 18px;
            --radius-lg: 24px;
            --radius-xl: 30px;
            --shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 24px 16px 40px;
            position: relative;
        }
        /* Animated background mesh */
        .bg-ambient {
            position: fixed; top:0; left:0; width:100%; height:100%;
            z-index:0; pointer-events:none;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(255,107,53,0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(46,213,115,0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 0%, rgba(255,107,53,0.04) 0%, transparent 40%);
            animation: ambientShift 20s ease-in-out infinite alternate;
        }
        @keyframes ambientShift {
            0% { opacity:0.8; transform: scale(1); }
            100% { opacity:1; transform: scale(1.05); }
        }
        .container { position:relative; z-index:2; max-width:480px; margin:0 auto; }

        /* Back button */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--text-secondary); text-decoration: none;
            font-weight: 500; margin-bottom: 28px;
            transition: color 0.2s; font-size: 15px;
        }
        .back-link:hover { color: var(--primary); }
        .back-link i { font-size: 14px; }

        /* Balance hero card */
        .balance-hero {
            background: linear-gradient(145deg, rgba(30,33,45,0.9), rgba(20,22,32,0.95));
            backdrop-filter: blur(30px);
            border-radius: var(--radius-xl);
            padding: 32px 28px;
            text-align: center;
            border: 1px solid var(--border);
            box-shadow: var(--shadow), inset 0 1px 0 rgba(255,255,255,0.04);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .balance-hero::before {
            content: ''; position: absolute; top:-40%; left:-20%;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(255,107,53,0.2) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .balance-hero::after {
            content: ''; position: absolute; bottom:-30%; right:-20%;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(46,213,115,0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .balance-hero .label {
            font-size: 13px; text-transform: uppercase;
            letter-spacing: 2px; color: var(--text-muted);
            margin-bottom: 10px; position: relative; z-index:1;
        }
        .balance-hero .amount {
            font-size: 48px; font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #FFA502);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
            background-clip:text; position: relative; z-index:1;
            line-height: 1.1;
        }
        .balance-hero .subtext {
            font-size: 13px; color: var(--text-secondary);
            margin-top: 8px; position: relative; z-index:1;
        }
        .daily-limit {
            position: relative; z-index:1;
            margin-top: 16px; font-size: 12px; color: var(--text-muted);
            display: flex; align-items:center; justify-content:center; gap: 6px;
        }
        .daily-limit .dot { width:6px; height:6px; border-radius:50%; background: var(--green); }

        /* Method selector */
        .method-selector {
            display: flex; gap: 10px; margin-bottom: 28px;
        }
        .method-btn {
            flex:1; padding: 14px 16px; border-radius: var(--radius-md);
            background: var(--surface); border: 2px solid var(--border);
            color: var(--text-secondary); font-weight: 600;
            font-size: 14px; cursor: pointer; transition: all 0.3s;
            text-align: center; font-family: 'Outfit', sans-serif;
            backdrop-filter: blur(15px);
        }
        .method-btn.active {
            border-color: var(--primary); color: var(--primary);
            background: var(--primary-light); box-shadow: 0 0 25px rgba(255,107,53,0.15);
        }
        .method-btn i { font-size: 18px; margin-right: 6px; }

        /* Form card */
        .form-card {
            background: var(--surface); backdrop-filter: blur(25px);
            border-radius: var(--radius-lg); padding: 24px;
            border: 1px solid var(--border); box-shadow: var(--shadow);
            margin-bottom: 28px;
            transition: all 0.3s ease;
        }
        .form-card .section-title {
            font-size: 18px; font-weight: 700; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .form-card .section-title i { color: var(--primary); }

        .input-group { margin-bottom: 18px; position: relative; }
        .input-group label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .input-group input, .input-group select {
            width: 100%; padding: 14px 16px; border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.04); border: 1.5px solid var(--border);
            color: var(--text); font-size: 15px; outline: none;
            font-family: 'Outfit', sans-serif;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255,107,53,0.1);
            background: rgba(255,255,255,0.06);
        }
        .input-group .input-icon {
            position: absolute; right: 16px; top: 42px;
            color: var(--text-muted); font-size: 16px; pointer-events: none;
        }

        .btn-submit {
            width: 100%; padding: 16px; border: none; border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; font-weight: 700; font-size: 16px;
            cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center;
            gap: 10px; font-family: 'Outfit', sans-serif;
            box-shadow: 0 10px 30px rgba(255,107,53,0.3);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(255,107,53,0.4);
        }
        .btn-submit:active { transform: scale(0.98); }

        .hidden { display: none; }
        .animate-in { animation: fadeSlideIn 0.4s ease; }
        @keyframes fadeSlideIn {
            from { opacity:0; transform: translateY(10px); }
            to { opacity:1; transform: translateY(0); }
        }

        /* Messages */
        .msg {
            padding: 14px 18px; border-radius: var(--radius-sm);
            margin-bottom: 20px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .msg-error { background: var(--danger-light); border:1px solid rgba(255,71,87,0.3); color: var(--danger); }
        .msg-success { background: var(--green-light); border:1px solid rgba(46,213,115,0.3); color: var(--green); }

        /* History */
        .history-section { margin-top: 8px; }
        .history-section .section-title {
            font-size: 18px; font-weight: 700; margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .history-section .section-title i { color: var(--primary); }

        .history-list {
            background: var(--surface); backdrop-filter: blur(20px);
            border-radius: var(--radius-lg); overflow: hidden;
            border: 1px solid var(--border);
        }
        .history-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .history-item:last-child { border-bottom: none; }
        .history-item:hover { background: var(--surface-hover); }
        .history-item .info { flex:1; }
        .history-item .info .amount {
            font-weight: 700; font-size: 16px; margin-bottom: 2px;
        }
        .history-item .info .method {
            font-size: 12px; color: var(--text-muted);
        }
        .history-item .info .detail {
            font-size: 11px; color: var(--text-secondary); margin-top: 2px;
        }
        .status-badge {
            padding: 6px 14px; border-radius: 20px; font-size: 12px;
            font-weight: 600; text-transform: capitalize;
        }
        .status-pending { background: var(--warning-light); color: var(--warning); }
        .status-approved { background: var(--green-light); color: var(--green); }
        .status-rejected { background: var(--danger-light); color: var(--danger); }

        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 36px; margin-bottom: 12px; opacity: 0.5; }
    </style>
</head>
<body>
<div class="bg-ambient"></div>
<div class="container">
    <a href="wallet.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Wallet</a>

    <!-- Balance Hero -->
    <div class="balance-hero">
        <div class="label">Available Balance</div>
        <div class="amount">₹<?= number_format($walletBalance, 2) ?></div>
        <div class="subtext">Min withdrawal: ₹<?= $minAmount ?></div>
        <div class="daily-limit">
            <span class="dot"></span> <?= $todayCount ?>/<?= $maxDaily ?> withdrawals today
        </div>
    </div>

    <!-- Messages -->
    <?php if ($error): ?><div class="msg msg-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg msg-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

    <!-- Method Selector -->
    <div class="method-selector">
        <button type="button" class="method-btn active" id="bankMethodBtn" onclick="switchMethod('bank')">
            <i class="fas fa-university"></i> Bank
        </button>
        <button type="button" class="method-btn" id="upiMethodBtn" onclick="switchMethod('upi')">
            <i class="fas fa-qrcode"></i> UPI
        </button>
    </div>

    <!-- Withdrawal Form -->
    <div class="form-card animate-in" id="withdrawFormCard">
        <div class="section-title"><i class="fas fa-arrow-up"></i> Withdraw Funds</div>
        <form method="POST" id="withdrawForm">
            <div class="input-group">
                <label>Amount (₹)</label>
                <input type="number" name="amount" min="50" step="1" placeholder="Enter amount" required>
                <span class="input-icon"><i class="fas fa-rupee-sign"></i></span>
            </div>

            <input type="hidden" name="method" id="methodInput" value="bank">

            <!-- Bank Fields -->
            <div id="bankFields">
                <div class="input-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_name" placeholder="Full name as per bank records" required>
                </div>
                <div class="input-group">
                    <label>Account Number</label>
                    <input type="text" name="account_no" placeholder="Enter account number" required>
                </div>
                <div class="input-group">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" placeholder="e.g. SBIN0001234" required>
                </div>
                <div class="input-group">
                    <label>Mobile Number</label>
                    <input type="text" name="bank_mobile" placeholder="Linked mobile number" required>
                </div>
            </div>

            <!-- UPI Fields -->
            <div id="upiFields" class="hidden">
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="upi_name" placeholder="Your full name">
                </div>
                <div class="input-group">
                    <label>UPI ID</label>
                    <input type="text" name="upi_id" placeholder="example@upi">
                </div>
            </div>

            <button type="submit" name="withdraw" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Submit Withdrawal
            </button>
        </form>
    </div>

    <!-- Withdrawal History -->
    <div class="history-section">
        <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Recent Withdrawals</div>
        <div class="history-list">
            <?php if (mysqli_num_rows($withdrawals) > 0): ?>
                <?php while ($w = mysqli_fetch_assoc($withdrawals)): ?>
                    <?php $det = json_decode($w['details'], true); ?>
                    <div class="history-item">
                        <div class="info">
                            <div class="amount">₹<?= number_format($w['amount'], 2) ?></div>
                            <div class="method">via <?= ucfirst($w['method']) ?></div>
                            <div class="detail">
                                <?php
                                if ($w['method'] == 'bank') {
                                    echo ($det['name'] ?? 'N/A') . ' • ' . (substr($det['account'] ?? '', -4) ?: 'N/A');
                                } else {
                                    echo $det['upi'] ?? 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No withdrawal requests yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchMethod(method) {
        const bankBtn = document.getElementById('bankMethodBtn');
        const upiBtn = document.getElementById('upiMethodBtn');
        const bankFields = document.getElementById('bankFields');
        const upiFields = document.getElementById('upiFields');
        const methodInput = document.getElementById('methodInput');

        if (method === 'bank') {
            bankBtn.classList.add('active');
            upiBtn.classList.remove('active');
            bankFields.classList.remove('hidden');
            upiFields.classList.add('hidden');
            methodInput.value = 'bank';
            // Make bank inputs required, upi not
            bankFields.querySelectorAll('input').forEach(i => i.required = true);
            upiFields.querySelectorAll('input').forEach(i => i.required = false);
        } else {
            upiBtn.classList.add('active');
            bankBtn.classList.remove('active');
            upiFields.classList.remove('hidden');
            bankFields.classList.add('hidden');
            methodInput.value = 'upi';
            upiFields.querySelectorAll('input').forEach(i => i.required = true);
            bankFields.querySelectorAll('input').forEach(i => i.required = false);
        }

        // Re-trigger animation
        const formCard = document.getElementById('withdrawFormCard');
        formCard.classList.remove('animate-in');
        void formCard.offsetWidth;
        formCard.classList.add('animate-in');
    }
</script>
</body>
</html>