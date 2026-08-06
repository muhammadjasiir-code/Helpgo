<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$msg = '';

// Approve or Reject
if (isset($_REQUEST['action']) && isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
    $action = $_REQUEST['action'];

    // Fetch the withdrawal to ensure it exists and is pending
    $withdrawal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM withdrawals WHERE id = $id AND status = 'pending'"));
    if ($withdrawal) {
        if ($action === 'approve') {
            mysqli_query($conn, "UPDATE withdrawals SET status = 'approved' WHERE id = $id");
            $msg = "Withdrawal #$id approved.";
        } elseif ($action === 'reject') {
            // Refund the amount to rider wallet
            $riderId = (int)$withdrawal['rider_id'];
            $amount = floatval($withdrawal['amount']);
            mysqli_query($conn, "UPDATE wallet SET balance = balance + $amount WHERE user_id = $riderId");
            mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, description) VALUES ($riderId, 'credit', $amount, 'Refund for rejected withdrawal #$id')");
            mysqli_query($conn, "UPDATE withdrawals SET status = 'rejected' WHERE id = $id");
            $msg = "Withdrawal #$id rejected. Amount ₹$amount refunded to rider wallet.";
        }
    } else {
        $msg = "Withdrawal not found or already processed.";
    }

    // Redirect to refresh
    $redirectUrl = 'withdrawals.php?msg=' . urlencode($msg);
    if (!headers_sent()) {
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        echo '<script>window.location.href="' . $redirectUrl . '";</script>';
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Fetch all pending withdrawals with rider details
$pendingWithdrawals = mysqli_query($conn, "
    SELECT w.*, u.full_name AS rider_name, u.phone AS rider_phone
    FROM withdrawals w
    JOIN users u ON w.rider_id = u.id
    WHERE w.status = 'pending'
    ORDER BY w.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdrawals – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; --danger:#FF4757; --green:#2ED573; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px 20px; }
        .container { max-width:1000px; margin:auto; }
        .card { background:var(--card); backdrop-filter:blur(15px); border:1px solid var(--border); border-radius:18px; padding:20px; margin-bottom:20px; }
        h2 { margin-bottom:20px; }
        .msg { padding:10px 16px; border-radius:10px; margin-bottom:15px; font-weight:500; background:rgba(46,213,115,0.15); color:var(--green); }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:12px; border-bottom:1px solid var(--border); text-align:left; }
        .btn { padding:6px 14px; border-radius:8px; font-weight:600; cursor:pointer; border:none; color:#fff; }
        .btn-approve { background:var(--primary); }
        .btn-reject { background:var(--danger); }
        .back { color:var(--primary); text-decoration:none; display:inline-block; margin-bottom:20px; }
        .action-form { display:inline; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>Pending Withdrawals</h2>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <table>
            <tr>
                <th>ID</th>
                <th>Rider</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Details</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php while ($w = mysqli_fetch_assoc($pendingWithdrawals)):
                $details = json_decode($w['details'], true);
            ?>
            <tr>
                <td><?= $w['id'] ?></td>
                <td><?= htmlspecialchars($w['rider_name']) ?> (<?= $w['rider_phone'] ?>)</td>
                <td>₹<?= number_format($w['amount'],2) ?></td>
                <td><?= ucfirst($w['method']) ?></td>
                <td>
                    <?php if ($w['method'] == 'bank'): ?>
                        Name: <?= htmlspecialchars($details['name'] ?? '') ?><br>
                        Acc: <?= htmlspecialchars($details['account'] ?? '') ?><br>
                        IFSC: <?= htmlspecialchars($details['ifsc'] ?? '') ?><br>
                        Mobile: <?= htmlspecialchars($details['mobile'] ?? '') ?>
                    <?php else: ?>
                        Name: <?= htmlspecialchars($details['name'] ?? '') ?><br>
                        UPI: <?= htmlspecialchars($details['upi'] ?? '') ?>
                    <?php endif; ?>
                </td>
                <td><?= date('d M Y', strtotime($w['created_at'])) ?></td>
                <td>
                    <a href="?action=approve&id=<?= $w['id'] ?>" class="btn btn-approve">Approve</a>
                    <form method="POST" class="action-form" onsubmit="return confirm('Reject this withdrawal? The amount will be refunded to the rider wallet.');">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $w['id'] ?>">
                        <button type="submit" class="btn btn-reject">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php if (mysqli_num_rows($pendingWithdrawals) == 0): ?>
            <p style="text-align:center; padding:20px; color:var(--text-secondary);">No pending withdrawals.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>