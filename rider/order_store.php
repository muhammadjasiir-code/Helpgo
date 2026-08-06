<?php
// rider/order_store.php – Delivery actions for store orders
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];
$oid = isset($_GET['id']) ? sanitize($_GET['id']) : '';
if (empty($oid)) { die("Order ID missing."); }

$riderRec = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM riders WHERE user_id = $riderId"));
if (!$riderRec) { die("Rider not found."); }
$riderInternalId = $riderRec['id'];

// Fetch the order – ensure it belongs to this rider
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, 
           u.full_name AS customer_name, u.phone AS customer_phone,
           s.name AS store_name, s.location AS store_location
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN stores s ON o.store_id = s.id
    WHERE o.order_id = '$oid' 
      AND o.service_type = 'store_delivery'
      AND o.rider_id = $riderInternalId
"));
if (!$order) {
    die("Store order not found or not assigned to you.");
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pickup'])) {
        mysqli_query($conn, "UPDATE orders SET status = 'picked_up' WHERE order_id = '$oid'");
    } elseif (isset($_POST['start'])) {
        mysqli_query($conn, "UPDATE orders SET status = 'in_transit' WHERE order_id = '$oid'");
    } elseif (isset($_POST['deliver'])) {
        mysqli_query($conn, "UPDATE orders SET status = 'delivered' WHERE order_id = '$oid'");
    }
    header("Location: order_store.php?id=$oid");
    exit;
}

// Re‑fetch after possible update
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, 
           u.full_name AS customer_name, u.phone AS customer_phone,
           s.name AS store_name, s.location AS store_location
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN stores s ON o.store_id = s.id
    WHERE o.order_id = '$oid' 
      AND o.service_type = 'store_delivery'
      AND o.rider_id = $riderInternalId
"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Store Order #<?= htmlspecialchars($oid) ?> – Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#0f1117; --surface:#1a1d27; --primary:#FF6B35; --green:#2ED573; --text:#fff; --text-secondary:#9aa0b0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:20px 16px 100px; }
        .container { max-width:500px; margin:0 auto; }
        .back-btn { display:inline-flex; align-items:center; gap:6px; color:var(--text-secondary); text-decoration:none; margin-bottom:20px; font-size:14px; }
        .back-btn:hover { color:var(--primary); }
        .card { background:var(--surface); border-radius:18px; padding:16px; margin-bottom:14px; }
        .card h3 { margin-bottom:8px; font-size:16px; display:flex; align-items:center; gap:8px; }
        .row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
        .label { color:var(--text-secondary); font-size:13px; }
        .value { font-weight:600; font-size:14px; }
        .btn { display:inline-block; padding:12px 24px; border-radius:12px; font-weight:600; font-size:14px; border:none; cursor:pointer; text-decoration:none; transition:0.2s; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-green { background:var(--green); color:#000; }
        .btn-outline { background:transparent; border:1px solid var(--text-secondary); color:var(--text-secondary); }
        .btn:disabled { opacity:0.5; cursor:not-allowed; }
        .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; text-transform:capitalize; }
        .status-accepted { background:rgba(0,119,182,0.2); color:#0077b6; }
        .status-picked_up { background:rgba(187,134,252,0.2); color:#bb86fc; }
        .status-in_transit { background:rgba(255,193,7,0.2); color:#ffc107; }
        .status-delivered { background:rgba(46,213,115,0.2); color:#2ED573; }
        .status-ready { background:rgba(46,213,115,0.2); color:#2ED573; }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2 style="margin-bottom:16px;">Store Order #<?= htmlspecialchars($oid) ?></h2>

    <div class="card">
        <h3><i class="fas fa-store"></i> Shop Details</h3>
        <div class="row"><span class="label">Shop</span><span class="value"><?= htmlspecialchars($order['store_name'] ?? 'N/A') ?></span></div>
        <div class="row"><span class="label">Shop Address</span><span class="value"><?= htmlspecialchars($order['store_location'] ?? 'N/A') ?></span></div>
    </div>

    <div class="card">
        <h3><i class="fas fa-user"></i> Customer</h3>
        <div class="row"><span class="label">Name</span><span class="value"><?= htmlspecialchars($order['customer_name']) ?></span></div>
        <div class="row"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($order['customer_phone']) ?></span></div>
        <div class="row"><span class="label">Delivery Address</span><span class="value"><?= htmlspecialchars($order['drop_address']) ?></span></div>
    </div>

    <div class="card">
        <h3><i class="fas fa-shopping-basket"></i> Order Items</h3>
        <p><?= htmlspecialchars($order['product_details'] ?? 'No details') ?></p>
        <div class="row" style="margin-top:10px;">
            <span class="label">Total Amount</span>
            <span class="value" style="color:var(--primary);">₹<?= number_format($order['total_amount'],2) ?></span>
        </div>
        <div class="row">
            <span class="label">Payment</span>
            <span class="value"><?= strtoupper($order['payment_method']) ?> · <?= ucfirst($order['payment_status']) ?></span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="value"><span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span></span>
        </div>
    </div>

    <div class="card">
        <h3><i class="fas fa-tasks"></i> Delivery Actions</h3>
        <?php
        $currentStatus = $order['status'];
        $actionHtml = '';
        
        // Show "Pickup" for both 'ready' and 'accepted' (the two possible initial states)
        if ($currentStatus == 'accepted' || $currentStatus == 'ready') {
            $actionHtml = '<form method="post"><button type="submit" name="pickup" class="btn btn-primary">Mark as Picked Up</button></form>';
        } elseif ($currentStatus == 'picked_up') {
            $actionHtml = '<form method="post"><button type="submit" name="start" class="btn btn-primary">Start Delivery</button></form>';
        } elseif ($currentStatus == 'in_transit') {
            $actionHtml = '<form method="post"><button type="submit" name="deliver" class="btn btn-green">Mark as Delivered</button></form>';
        } elseif ($currentStatus == 'delivered') {
            $actionHtml = '<p style="color:var(--green);">✅ Delivered successfully.</p>';
        } else {
            $actionHtml = '<p style="color:var(--text-secondary);">No action available. Current status: <b>' . htmlspecialchars($currentStatus) . '</b></p>';
        }
        echo $actionHtml;
        ?>
    </div>
</div>
</body>
</html>