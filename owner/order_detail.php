<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
if (!isLoggedIn()) { redirect('../login.php'); }

$uid = (int)$_SESSION['user_id'];
$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE owner_id = $uid LIMIT 1"));
if (!$store) { die("You don't have a store yet."); }
$store_id = $store['id'];

$oid = isset($_GET['oid']) ? $_GET['oid'] : '';
if (empty($oid)) { die("Order ID missing."); }
$oid_safe = mysqli_real_escape_string($conn, $oid);

// ----- Column existence check -----
$requiredColumns = [
    'order_id', 'user_id', 'store_id', 'service_type', 'drop_address',
    'total_amount', 'payment_method', 'payment_status', 'status',
    'store_order_status', 'product_details', 'payment_proof', 'utr'
];
$missing = [];
foreach ($requiredColumns as $col) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '$col'");
    if (mysqli_num_rows($res) == 0) {
        $missing[] = $col;
    }
}
if (!empty($missing)) {
    die("<div style='background:#fdecea;color:#b71c1c;padding:20px;font-family:Outfit;'>
        <h3>Missing columns in 'orders' table:</h3>
        <p>" . implode(', ', $missing) . "</p>
        <p>Run the required ALTER TABLE statements.</p>
    </div>");
}

// ----- Handle form submissions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = '';

    if (isset($_POST['accept'])) {
        $sql = "UPDATE orders SET store_order_status = 'accepted' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    } elseif (isset($_POST['ready_for_payment'])) {
        $sql = "UPDATE orders SET store_order_status = 'ready_for_payment' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    } elseif (isset($_POST['verify_payment'])) {
        $sql = "UPDATE orders SET store_order_status = 'payment_verified', status = 'ready' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    } elseif (isset($_POST['reject_payment'])) {
        $sql = "UPDATE orders SET store_order_status = 'accepted', payment_proof = '', utr = '' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    } elseif (isset($_POST['reject'])) {
        $sql = "UPDATE orders SET store_order_status = 'rejected', status = 'cancelled' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    } elseif (isset($_POST['ready'])) {
        $sql = "UPDATE orders SET store_order_status = 'ready', status = 'ready' WHERE order_id = '$oid_safe' AND store_id = $store_id";
    }

    if ($sql !== '') {
        if (mysqli_query($conn, $sql)) {
            header("Location: order_detail.php?oid=$oid");
            exit;
        } else {
            die("<div style='background:#fdecea;color:#b71c1c;padding:20px;font-family:Outfit;'>
                <h3>Database Error</h3>
                <p>" . mysqli_error($conn) . "</p>
                <p><b>SQL:</b> {$sql}</p>
                <p><a href='order_detail.php?oid={$oid}'>Go back</a></p>
            </div>");
        }
    }
}

// Fetch order
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.order_id = '$oid_safe' AND o.store_id = $store_id
"));
if (!$order) { die("Order not found."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($oid) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#052e24; --surface:#064e3b; --primary:#c9a84c; --text:#f5f0e0; --muted:#9db3a8; --danger:#dc3545; --success:#28a745; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:20px; max-width:500px; margin:auto; }
        .back-btn { color:var(--primary); display:inline-flex; align-items:center; gap:6px; margin-bottom:20px; text-decoration:none; }
        .card { background:var(--surface); border-radius:12px; padding:16px; margin-bottom:16px; }
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; border:none; cursor:pointer; margin-right:10px; margin-top:10px; }
        .btn-accept { background:var(--primary); color:#052e24; }
        .btn-reject { background:var(--danger); color:#fff; }
        .btn-ready { background:var(--success); color:#fff; }
        .btn-warning { background:#ffc107; color:#000; }
        .badge { display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; text-transform:capitalize; }
        .badge.pending { background:rgba(255,193,7,0.2); color:#ffc107; }
        .badge.accepted { background:rgba(0,119,182,0.2); color:#0077b6; }
        .badge.ready_for_payment { background:rgba(255,107,53,0.2); color:#FF6B35; }
        .badge.payment_submitted { background:rgba(187,134,252,0.2); color:#bb86fc; }
        .badge.payment_verified { background:rgba(46,213,115,0.2); color:#2ED573; }
        .badge.ready { background:rgba(46,213,115,0.2); color:#2ED573; }
        .badge.rejected, .badge.cancelled { background:rgba(220,53,69,0.2); color:var(--danger); }
        .payment-proof-img { max-width:100%; border-radius:8px; margin-top:10px; }
    </style>
</head>
<body>
    <a href="orders.php" class="back-btn"><i class="fas fa-arrow-left"></i> Orders</a>
    <h2>Order #<?= htmlspecialchars($oid) ?></h2>

    <div class="card">
        <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?> (<?= htmlspecialchars($order['customer_phone']) ?>)</p>
        <p><strong>Address:</strong> <?= htmlspecialchars($order['drop_address']) ?></p>
        <p><strong>Items:</strong> <?= htmlspecialchars($order['product_details'] ?? 'N/A') ?></p>
        <p><strong>Total:</strong> ₹<?= number_format($order['total_amount'],2) ?></p>
        <p><strong>Payment Method:</strong> <?= strtoupper($order['payment_method']) ?></p>
        <p><strong>Payment Status:</strong> <?= ucfirst($order['payment_status']) ?></p>
        <p><strong>Order Status:</strong> <span class="badge <?= $order['store_order_status'] ?>"><?= str_replace('_',' ',$order['store_order_status']) ?></span></p>
    </div>

    <?php if (!empty($order['payment_proof'])): ?>
    <div class="card">
        <h3>Payment Proof</h3>
        <p><strong>UTR:</strong> <?= htmlspecialchars($order['utr'] ?? 'N/A') ?></p>
        <img src="../<?= htmlspecialchars($order['payment_proof']) ?>" class="payment-proof-img" alt="Screenshot" onerror="this.style.display='none'">
    </div>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <?php if ($order['store_order_status'] == 'pending'): ?>
            <form method="post" style="display:inline;"><button type="submit" name="accept" class="btn btn-accept">Accept Order</button></form>
            <form method="post" style="display:inline;"><button type="submit" name="reject" class="btn btn-reject">Reject Order</button></form>
        <?php elseif ($order['store_order_status'] == 'accepted'): ?>
            <form method="post" style="display:inline;"><button type="submit" name="ready_for_payment" class="btn btn-warning">Ready for Payment</button></form>
            <form method="post" style="display:inline;"><button type="submit" name="ready" class="btn btn-ready">Mark as Ready (COD / Paid)</button></form>
        <?php elseif ($order['store_order_status'] == 'ready_for_payment'): ?>
            <p style="color:var(--muted);">Waiting for customer to submit payment proof...</p>
        <?php elseif ($order['store_order_status'] == 'payment_submitted'): ?>
            <form method="post" style="display:inline;"><button type="submit" name="verify_payment" class="btn btn-accept">Verify Payment</button></form>
            <form method="post" style="display:inline;"><button type="submit" name="reject_payment" class="btn btn-reject">Reject Payment</button></form>
        <?php elseif ($order['store_order_status'] == 'payment_verified' || $order['store_order_status'] == 'ready'): ?>
            <p style="color:var(--success);">✅ Payment verified – order is now with the rider.</p>
        <?php elseif ($order['store_order_status'] == 'rejected' || $order['store_order_status'] == 'cancelled'): ?>
            <p style="color:var(--danger);">❌ This order has been cancelled/rejected.</p>
        <?php endif; ?>
    </div>
</body>
</html>