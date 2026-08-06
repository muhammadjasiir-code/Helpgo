<?php
// rider/order.php — Dispatcher: routes to the correct order detail page
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$orderId = sanitize(isset($_GET['id']) ? $_GET['id'] : '');
$riderId = (int)$_SESSION['user_id'];

if ($orderId === '') {
    header("Location: home.php");
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT service_type FROM orders
    WHERE order_id = '$orderId'
      AND rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
    LIMIT 1
"));

if (!$row) {
    die("<div style='color:#fff;text-align:center;margin-top:50px;font-family:sans-serif;background:#0f1117;min-height:100vh;padding-top:80px;'>Order not found or not assigned to you.<br><br><a href='home.php' style='color:#FF6B35;'>Back to Home</a></div>");
}

$service = strtolower($row['service_type']);

if ($service === 'grocery') {
    header("Location: order_grocery.php?id=" . urlencode($orderId));
} elseif ($service === 'store_delivery') {
    header("Location: order_store.php?id=" . urlencode($orderId));
} else {
    // petrol / parcel / etc.
    header("Location: order_petrol.php?id=" . urlencode($orderId));
}
exit;