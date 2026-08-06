<?php
// wallet.php – Customer Wallet (Emerald Prestige theme)
require_once __DIR__ . '/config.php';

if (!isLoggedIn()) { redirect(SITE_URL . 'login.php'); }
$uid = (int)$_SESSION['user_id'];

// Helper function (if not already available)
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Ensure wallet row exists for the user
$check = mysqli_query($conn, "SELECT id FROM wallet WHERE user_id = $uid LIMIT 1");
if ($check && mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "INSERT INTO wallet (user_id, balance) VALUES ($uid, 0)");
}

// Fetch current balance
$balance = 0.00;
$walletRes = mysqli_query($conn, "SELECT balance FROM wallet WHERE user_id = $uid LIMIT 1");
if ($walletRes && $row = mysqli_fetch_assoc($walletRes)) {
    $balance = floatval($row['balance']);
}

// Fetch recent transactions (if table exists, otherwise empty)
$transactions = [];
$tableExists = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_transactions'");
if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    $txnRes = mysqli_query($conn, "
        SELECT id, type, amount, description, status, created_at
        FROM wallet_transactions
        WHERE user_id = $uid
        ORDER BY id DESC
        LIMIT 20
    ");
    if ($txnRes) {
        while ($row = mysqli_fetch_assoc($txnRes)) {
            $transactions[] = $row;
        }
    }
}

// Handle withdrawal request
$withdrawMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = floatval($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        $withdrawMsg = 'Please enter a valid amount.';
    } elseif ($amount > $balance) {
        $withdrawMsg = 'Insufficient balance.';
    } else {
        // Insert pending withdrawal transaction
        $desc = "Withdrawal request of ₹" . number_format($amount, 2);
        $insert = mysqli_query($conn, "
            INSERT INTO wallet_transactions (user_id, type, amount, description, status, created_at)
            VALUES ($uid, 'debit', $amount, '$desc', 'pending', NOW())
        ");
        if ($insert) {
            $withdrawMsg = 'Withdrawal request submitted. It will be processed shortly.';
            // Refresh transactions
            $txnRes = mysqli_query($conn, "
                SELECT id, type, amount, description, status, created_at
                FROM wallet_transactions
                WHERE user_id = $uid
                ORDER BY id DESC
                LIMIT 20
            ");
            $transactions = [];
            if ($txnRes) {
                while ($row = mysqli_fetch_assoc($txnRes)) {
                    $transactions[] = $row;
                }
            }
        } else {
            $withdrawMsg = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wallet – HelpGo</title>
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
            min-height: 100vh;
            padding: 20px 16px 60px;
            overflow-x: hidden;
            position: relative;
        }

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

        .container { width: 100%; max-width: 500px; position: relative; z-index: 2; }

        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .back-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .back-btn:hover {
            background: rgba(212,175,55,0.15);
            border-color: var(--gold);
        }
        .header h1 { font-size: 28px; font-weight: 800; color: var(--white); }
        .header h1 span { color: var(--gold); }

        .balance-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 32px 24px;
            margin-bottom: 24px;
            text-align: center;
            box-shadow: var(--shadow-glass);
            position: relative;
            overflow: hidden;
        }
        .balance-card::after {
            content: '';
            position: absolute;
            right: -30px; top: -30px;
            width: 150px; height: 150px;
            background: radial-gradient(circle, rgba(212,175,55,0.2) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .wallet-icon {
            font-size: 48px;
            color: var(--gold);
            margin-bottom: 12px;
            filter: drop-shadow(0 0 15px rgba(212,175,55,0.5));
        }
        .balance-label {
            font-size: 14px;
            color: var(--gray-soft);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .balance-amount {
            font-size: 48px;
            font-weight: 800;
            color: var(--gold);
            line-height: 1.2;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
        }
        .action-btn {
            flex: 1;
            padding: 14px 0;
            border-radius: var(--radius-btn);
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            color: var(--white);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .action-btn:hover {
            border-color: var(--gold);
            box-shadow: 0 0 20px rgba(212,175,55,0.3);
            transform: translateY(-2px);
        }
        .action-btn.withdraw {
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            border: none;
            font-weight: 700;
        }

        .withdraw-form {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 20px;
            margin-bottom: 24px;
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .withdraw-form.show { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .withdraw-form h3 { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: var(--gold); }
        .input-group { margin-bottom: 14px; }
        .input-group label { display: block; font-size: 13px; color: var(--gray-soft); margin-bottom: 6px; }
        .input-group input {
            width: 100%; padding: 12px 16px; border-radius: 16px;
            background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);
            color: var(--white); font-family: var(--font); font-size: 15px; outline: none;
            transition: var(--transition);
        }
        .input-group input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212,175,55,0.15); }
        .submit-btn {
            width: 100%; padding: 14px; border: none; border-radius: var(--radius-btn);
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark); font-weight: 700; font-size: 16px; cursor: pointer;
            transition: var(--transition);
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(212,175,55,0.5); }
        .message {
            padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; font-weight: 500;
        }
        .message.success { background: rgba(46,213,115,0.15); color: #2ED573; }
        .message.error { background: rgba(255,71,87,0.15); color: #FF4757; }

        .section-title {
            font-size: 18px; font-weight: 700; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: var(--gold); }

        .transaction-list { display: flex; flex-direction: column; gap: 12px; }
        .txn-card {
            background: var(--glass-bg); backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border); border-radius: 20px;
            padding: 16px; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: var(--transition);
        }
        .txn-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .txn-left { display: flex; align-items: center; gap: 14px; }
        .txn-icon {
            width: 44px; height: 44px; border-radius: 14px;
            background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: var(--gold);
        }
        .txn-details .txn-desc { font-weight: 600; font-size: 15px; }
        .txn-details .txn-date { font-size: 12px; color: var(--gray-muted); }
        .txn-amount { font-weight: 700; font-size: 18px; }
        .txn-amount.credit { color: var(--gold); }
        .txn-amount.debit { color: #FF4757; }

        .empty-state {
            text-align: center; padding: 40px 20px;
            background: var(--glass-bg); backdrop-filter: blur(20px);
            border-radius: var(--radius-card); border: 1px solid var(--glass-border);
        }
        .empty-state i { font-size: 50px; color: var(--gold); opacity: 0.5; margin-bottom: 12px; }
        .empty-state p { color: var(--gray-soft); }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1>My <span>Wallet</span></h1>
        </div>

        <!-- Balance Card -->
        <div class="balance-card">
            <div class="wallet-icon"><i class="fas fa-wallet"></i></div>
            <div class="balance-label">Available Balance</div>
            <div class="balance-amount">₹<?= number_format($balance, 2) ?></div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <div class="action-btn" id="withdrawBtn">
                <i class="fas fa-arrow-up"></i> Withdraw
            </div>
            <a href="contact.php" class="action-btn">
                <i class="fas fa-headset"></i> Help
            </a>
        </div>

        <!-- Withdraw Form -->
        <div class="withdraw-form" id="withdrawForm">
            <h3>Request Withdrawal</h3>
            <?php if ($withdrawMsg): ?>
                <div class="message <?= strpos($withdrawMsg, 'submitted') !== false || strpos($withdrawMsg, 'processed') !== false ? 'success' : 'error' ?>">
                    <?= h($withdrawMsg) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="withdraw">
                <div class="input-group">
                    <label>Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" min="0" max="<?= $balance ?>" placeholder="Enter amount" required>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>

        <!-- Recent Transactions -->
        <div class="section-title">
            <i class="fas fa-clock-rotate-left"></i> Recent Transactions
        </div>

        <div class="transaction-list">
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No transactions yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                    <?php
                        $isCredit = strtolower($txn['type']) === 'credit';
                        $amountClass = $isCredit ? 'credit' : 'debit';
                        $prefix = $isCredit ? '+' : '-';
                        $icon = $isCredit ? 'fa-arrow-down' : 'fa-arrow-up';
                    ?>
                    <div class="txn-card">
                        <div class="txn-left">
                            <div class="txn-icon"><i class="fas <?= $icon ?>"></i></div>
                            <div class="txn-details">
                                <div class="txn-desc"><?= h($txn['description']) ?></div>
                                <div class="txn-date"><?= date('d M, h:i A', strtotime($txn['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="txn-amount <?= $amountClass ?>"><?= $prefix ?>₹<?= number_format($txn['amount'], 2) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const withdrawBtn = document.getElementById('withdrawBtn');
        const withdrawForm = document.getElementById('withdrawForm');
        let formVisible = false;
        withdrawBtn.addEventListener('click', function() {
            formVisible = !formVisible;
            if (formVisible) {
                withdrawForm.classList.add('show');
                withdrawBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            } else {
                withdrawForm.classList.remove('show');
                withdrawBtn.innerHTML = '<i class="fas fa-arrow-up"></i> Withdraw';
            }
        });
    </script>
</body>
</html>