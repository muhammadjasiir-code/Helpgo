<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$orderId = sanitize($_GET['id'] ?? '');
$riderId = (int)$_SESSION['user_id'];

// Fetch order with customer details
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.order_id = '$orderId' AND o.rider_id IS NULL AND o.status = 'pending'
    LIMIT 1
"));

if (!$order) {
    die("<div style='color:#fff; text-align:center; margin-top:50px; font-family:sans-serif;'>Order not available or already taken.</div>");
}

// Accept order
if (isset($_POST['accept'])) {
    // Generate a random 4-digit OTP
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    // Assign rider, update status, and save OTP
    mysqli_query($conn, "UPDATE orders SET rider_id = (SELECT id FROM riders WHERE user_id = $riderId), status = 'accepted', otp = '$otp' WHERE order_id = '$orderId'");
    header("Location: order.php?id=$orderId");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details – HelpGo Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#0f1117; --surface:#1a1d27; --primary:#FF6B35; --text:#fff; --text-secondary:#9aa0b0; --green:#2ED573; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit', sans-serif; background:var(--bg); color:var(--text); padding:20px; }
        .container { max-width:500px; margin:0 auto; }
        .card { background:var(--surface); border-radius:20px; padding:24px; margin-bottom:20px; }
        .back-link { display:inline-flex; align-items:center; gap:8px; color:var(--primary); text-decoration:none; margin-bottom:20px; }
        h2 { font-size:24px; font-weight:700; margin-bottom:16px; }
        .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
        .detail-row span:first-child { color:var(--text-secondary); }
        .btn-accept { width:100%; padding:14px; border:none; border-radius:14px; background:var(--green); color:#000; font-weight:700; font-size:16px; cursor:pointer; transition:0.3s; }
        .btn-accept:hover { background:#25c068; }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>

    <div class="card">
        <h2><?= ucfirst($order['service_type']) ?> Order</h2>
        <div class="detail-row"><span>Customer</span><span><?= htmlspecialchars($order['customer_name']) ?></span></div>
        <div class="detail-row"><span>Phone</span><span><?= htmlspecialchars($order['customer_phone']) ?></span></div>
        <div class="detail-row"><span>Delivery Address</span><span style="text-align:right;"><?= htmlspecialchars($order['drop_address']) ?></span></div>
        <div class="detail-row"><span>Payment</span><span><?= ucfirst($order['payment_method']) ?></span></div>
        <div class="detail-row"><span>Total</span><span>₹<?= number_format($order['total_amount'],2) ?></span></div>
        <?php if ($order['petrol_quantity'] > 0): ?>
            <div class="detail-row"><span>Petrol Quantity</span><span><?= $order['petrol_quantity'] ?>L</span></div>
        <?php endif; ?>
    </div>

    <form method="POST">
        <button type="submit" name="accept" class="btn-accept">Accept Order</button>
    </form>
</div>
</body>
</html>