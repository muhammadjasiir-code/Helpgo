<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$msg = '';

// Handle approve / reject
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $oid    = mysqli_real_escape_string($conn, trim($_GET['order_id']));
    $action = $_GET['action'];

    if ($action === 'approve') {
        mysqli_query($conn, "UPDATE orders
                             SET payment_status = 'paid'
                             WHERE order_id = '$oid'
                               AND payment_method = 'upi'
                             LIMIT 1");
        if (mysqli_affected_rows($conn) > 0) {
            $msg = "Payment for order #$oid has been approved.";
        } else {
            $msg = "No row updated for #$oid. " . mysqli_error($conn);
        }
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE orders
                             SET payment_status = 'failed', status = 'cancelled'
                             WHERE order_id = '$oid' LIMIT 1");
        if (mysqli_affected_rows($conn) > 0) {
            $msg = "Payment for order #$oid has been rejected. Order cancelled.";
        } else {
            $msg = "Error rejecting payment: " . mysqli_error($conn);
        }
    }

    $redirectUrl = 'payments.php?msg=' . urlencode($msg);
    if (!headers_sent()) { header('Location: ' . $redirectUrl); exit; }
    echo '<script>window.location.href="' . $redirectUrl . '";</script>'; exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; }

// Fetch pending UPI payments where screenshot has been uploaded
$pendingPayments = mysqli_query($conn, "
    SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.payment_method = 'upi'
      AND o.payment_status = 'pending'
      AND o.payment_screenshot IS NOT NULL
      AND o.payment_screenshot != ''
    ORDER BY o.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UPI Payments – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; --danger:#FF4757; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px 20px; }
        .container { max-width:1100px; margin:auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:20px; margin-bottom:20px; backdrop-filter:blur(15px); }
        h2 { margin-bottom:20px; }
        .msg { padding:10px 16px; border-radius:10px; margin-bottom:15px; font-weight:500; background:rgba(46,213,115,0.15); color:#2ED573; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:12px; border-bottom:1px solid var(--border); text-align:left; }
        .btn { padding:6px 14px; border-radius:8px; font-weight:600; cursor:pointer; border:none; color:#fff; text-decoration:none; display:inline-block; }
        .btn-approve { background:var(--primary); }
        .btn-reject { background:var(--danger); }
        .screenshot-preview { max-width:80px; max-height:80px; border-radius:8px; border:1px solid var(--border); cursor:pointer; transition:transform 0.2s; }
        .screenshot-preview:hover { transform:scale(1.1); }
        .view-link { color:var(--primary); text-decoration:none; margin-left:5px; font-size:12px; }
        .back { color:var(--primary); text-decoration:none; display:inline-block; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>Pending UPI Payments</h2>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <table>
            <tr>
                <th>Order ID</th><th>Customer</th><th>Type</th><th>Amount</th>
                <th>UTR</th><th>Screenshot</th><th>Actions</th>
            </tr>
            <?php while ($p = mysqli_fetch_assoc($pendingPayments)): ?>
            <tr>
                <td><?= htmlspecialchars($p['order_id']) ?></td>
                <td><?= htmlspecialchars($p['customer_name']) ?> (<?= htmlspecialchars($p['customer_phone']) ?>)</td>
                <td><?= ucfirst($p['service_type']) ?></td>
                <td>₹<?= number_format($p['total_amount'],2) ?></td>
                <td><?= htmlspecialchars($p['payment_utr'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($p['payment_screenshot'])): ?>
                        <a href="../<?= htmlspecialchars($p['payment_screenshot']) ?>" target="_blank">
                            <img src="../<?= htmlspecialchars($p['payment_screenshot']) ?>" alt="Screenshot" class="screenshot-preview" onerror="this.style.display='none'">
                        </a>
                        <a href="../<?= htmlspecialchars($p['payment_screenshot']) ?>" target="_blank" class="view-link">View full</a>
                    <?php else: ?> — <?php endif; ?>
                </td>
                <td>
                    <a href="?action=approve&order_id=<?= urlencode($p['order_id']) ?>" class="btn btn-approve">Approve</a>
                    <a href="?action=reject&order_id=<?= urlencode($p['order_id']) ?>" class="btn btn-reject" onclick="return confirm('Reject this payment? The order will be cancelled.');">Reject</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php if (mysqli_num_rows($pendingPayments) == 0): ?>
            <p style="text-align:center; padding:20px; color:#9aa0b0;">No pending payments.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>