<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];

// Accept order and immediately redirect to track it
if (isset($_GET['accept']) && isset($_GET['id'])) {
    $orderId = sanitize($_GET['id']);
    $check = mysqli_query($conn, "SELECT id FROM orders WHERE order_id = '$orderId' AND status = 'ready' AND rider_id IS NULL");
    if (mysqli_num_rows($check) > 0) {
        $riderInternalId = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM riders WHERE user_id = $riderId"))['id'];
        mysqli_query($conn, "UPDATE orders SET rider_id = $riderInternalId, status = 'accepted', store_order_status = 'accepted_by_rider' WHERE order_id = '$orderId'");
        // Redirect to the order tracking page (existing rider order detail page)
        header("Location: order.php?id=$orderId");
        exit;
    } else {
        $error = "Order already taken or no longer available.";
    }
}

// Fetch ready shop orders not yet assigned
$ordersQuery = mysqli_query($conn, "
    SELECT o.*, s.name AS store_name, s.location AS store_location,
           u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    JOIN users u ON o.user_id = u.id
    WHERE o.service_type = 'store_delivery'
      AND o.status = 'ready'
      AND o.rider_id IS NULL
    ORDER BY o.id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Shop Orders – Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#0f1117; --surface:#1a1d27; --primary:#FF6B35; --green:#2ED573; --text:#fff; --text-secondary:#9aa0b0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:20px 16px 100px; }
        .container { max-width:500px; margin:0 auto; }
        .back-btn { display:inline-flex; align-items:center; gap:6px; color:var(--text-secondary); text-decoration:none; margin-bottom:20px; font-size:14px; }
        .back-btn:hover { color:var(--primary); }
        h1 { font-size:24px; font-weight:700; margin-bottom:24px; }
        .error-msg { background:rgba(255,71,87,0.15); color:var(--danger); padding:12px; border-radius:12px; margin-bottom:15px; }
        .order-card { background:var(--surface); border-radius:18px; padding:16px; margin-bottom:14px; }
        .order-card .top-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .store-name { font-weight:700; font-size:16px; }
        .order-id { color:var(--text-secondary); font-size:12px; }
        .customer-info, .address { font-size:13px; color:var(--text-secondary); margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .items { font-size:13px; color:var(--text); margin-bottom:8px; }
        .amount { font-weight:700; color:var(--primary); font-size:18px; margin-bottom:12px; }
        .btn { display:inline-block; padding:10px 20px; border-radius:12px; font-weight:600; font-size:14px; text-decoration:none; transition:0.2s; }
        .btn-accept { background:var(--green); color:#000; }
        .btn-accept:hover { opacity:0.9; }
        .empty-state { text-align:center; padding:30px; color:var(--text-secondary); }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h1>🏪 Ready Shop Orders</h1>

    <?php if (isset($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($ordersQuery) > 0): ?>
        <?php while ($ord = mysqli_fetch_assoc($ordersQuery)): ?>
            <div class="order-card">
                <div class="top-row">
                    <span class="store-name"><?= htmlspecialchars($ord['store_name']) ?></span>
                    <span class="order-id">#<?= htmlspecialchars($ord['order_id']) ?></span>
                </div>
                <div class="customer-info">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($ord['customer_name']) ?> · <?= htmlspecialchars($ord['customer_phone']) ?>
                </div>
                <div class="address">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($ord['drop_address']) ?>
                </div>
                <div class="items">
                    <i class="fas fa-shopping-basket"></i>
                    <?= htmlspecialchars($ord['product_details'] ?? 'Items not listed') ?>
                </div>
                <div class="amount">₹<?= number_format($ord['total_amount'], 2) ?></div>
                <a href="shop-order.php?accept=1&id=<?= urlencode($ord['order_id']) ?>" class="btn btn-accept">
                    <i class="fas fa-check"></i> Accept Order
                </a>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open" style="font-size:32px; margin-bottom:10px; opacity:0.4;"></i>
            <p>No shop orders ready for pickup.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>