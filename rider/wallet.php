<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];
$walletBalance = getWalletBalance($riderId);
$transactions = mysqli_query($conn, "SELECT * FROM wallet_transactions WHERE user_id = $riderId ORDER BY id DESC LIMIT 30");

// Today's withdrawal count (for display only, actual limit enforced in withdraw.php)
$todayWithdrawals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM withdrawals WHERE rider_id=$riderId AND DATE(created_at)=CURDATE()"))['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wallet – HelpGo Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --primary: #FF6B35;
            --green: #2ED573;
            --danger: #FF4757;
            --text: #ffffff;
            --text-secondary: #9aa0b0;
            --text-muted: #6b7280;
            --border: rgba(255,255,255,0.04);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px 16px 40px;
        }
        .container { max-width: 480px; margin: 0 auto; }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--primary); text-decoration: none;
            font-weight: 500; margin-bottom: 24px;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 0.8; }

        /* Balance Card */
        .balance-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 28px 24px;
            text-align: center;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .balance-card .label {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }
        .balance-card .amount {
            font-size: 40px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .balance-card .subtext {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-withdraw {
            background: var(--primary);
            color: #fff;
            border: none;
        }
        .btn-withdraw:hover { background: #e55a2b; }
        .btn-history {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        /* Stats row */
        .stats-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-box {
            flex: 1;
            background: var(--surface);
            border-radius: 18px;
            padding: 18px 14px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .stat-box i {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 8px;
        }
        .stat-box h4 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-box p {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Transaction list */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i { color: var(--primary); }

        .transaction-list {
            background: var(--surface);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-item:hover { background: rgba(255,255,255,0.02); }
        .transaction-icon {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-right: 12px; font-size: 16px; flex-shrink: 0;
        }
        .credit-icon { background: rgba(46,213,115,0.15); color: var(--green); }
        .debit-icon { background: rgba(255,71,87,0.15); color: var(--danger); }
        .transaction-info { flex: 1; min-width: 0; }
        .transaction-info .desc { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .transaction-info .date { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .transaction-amount { font-weight: 700; font-size: 15px; margin-left: 12px; white-space: nowrap; }
        .credit-amount { color: var(--green); }
        .debit-amount { color: var(--danger); }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        .empty-state i { font-size: 36px; color: var(--text-muted); margin-bottom: 12px; }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: var(--surface); border-top: 1px solid rgba(255,255,255,0.05);
            display: flex; justify-content: space-around; padding: 12px 0;
            z-index: 999;
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center;
            color: var(--text-secondary); text-decoration: none; font-size: 11px;
        }
        .nav-item i { font-size: 20px; margin-bottom: 2px; }
        .nav-item.active { color: var(--primary); }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <!-- Balance Card -->
    <div class="balance-card">
        <p class="label">Wallet Balance</p>
        <p class="amount">₹<?= number_format($walletBalance, 2) ?></p>
        <p class="subtext">Available for withdrawal</p>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="withdraw.php" class="btn btn-withdraw">
            <i class="fas fa-wallet"></i> Withdraw
        </a>
        <a href="home.php" class="btn btn-history">
            <i class="fas fa-home"></i> Home
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <i class="fas fa-shopping-bag"></i>
            <h4>
                <?php
                $deliveryCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE rider_id = (SELECT id FROM riders WHERE user_id = $riderId) AND status = 'delivered'"))['cnt'] ?? 0;
                echo $deliveryCount;
                ?>
            </h4>
            <p>Deliveries</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-star"></i>
            <h4>
                <?php
                $avgRating = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ROUND(AVG(rating),1) as avg FROM ratings WHERE rider_id = (SELECT id FROM riders WHERE user_id = $riderId)"))['avg'] ?? 'N/A';
                echo $avgRating ?: '—';
                ?>
            </h4>
            <p>Rating</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-clock"></i>
            <h4>
                <?php
                $thisMonth = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount*0.85),0) as earn FROM orders WHERE rider_id = (SELECT id FROM riders WHERE user_id = $riderId) AND status = 'delivered' AND MONTH(order_date) = MONTH(CURDATE())"))['earn'] ?? 0;
                echo '₹' . number_format($thisMonth, 0);
                ?>
            </h4>
            <p>This Month</p>
        </div>
    </div>

    <!-- Transactions -->
    <div class="section-title">
        <i class="fas fa-receipt"></i> Recent Transactions
    </div>

    <div class="transaction-list">
        <?php if ($transactions && mysqli_num_rows($transactions) > 0): ?>
            <?php while ($t = mysqli_fetch_assoc($transactions)): ?>
                <div class="transaction-item">
                    <div class="transaction-icon <?= $t['transaction_type'] == 'credit' ? 'credit-icon' : 'debit-icon' ?>">
                        <i class="fas <?= $t['transaction_type'] == 'credit' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                    </div>
                    <div class="transaction-info">
                        <div class="desc"><?= htmlspecialchars($t['description']) ?></div>
                        <div class="date"><?= date('d M Y, h:i A', strtotime($t['transaction_date'])) ?></div>
                    </div>
                    <div class="transaction-amount <?= $t['transaction_type'] == 'credit' ? 'credit-amount' : 'debit-amount' ?>">
                        <?= $t['transaction_type'] == 'credit' ? '+' : '-' ?>₹<?= number_format($t['amount'], 2) ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No transactions yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
    <a href="home.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="wallet.php" class="nav-item active"><i class="fas fa-wallet"></i><span>Wallet</span></a>
    <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
</nav>
</body>
</html>