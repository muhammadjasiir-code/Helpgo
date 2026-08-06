<?php
require_once '../config.php';
if (!isRider()) { die(json_encode(['success'=>false])); }

$riderId = (int)$_SESSION['user_id'];
$orderId = sanitize($_POST['order_id'] ?? '');

// Verify order is still available
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM orders 
    WHERE order_id = '$orderId' AND rider_id IS NULL AND status = 'pending'
    LIMIT 1
"));
if (!$order) {
    echo json_encode(['success'=>false, 'message'=>'Order no longer available']);
    exit;
}

// Generate OTP and accept order
$otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
mysqli_query($conn, "UPDATE orders SET rider_id = (SELECT id FROM riders WHERE user_id = $riderId), status = 'accepted', otp = '$otp' WHERE order_id = '$orderId'");

echo json_encode(['success'=>true, 'order_id'=>$orderId]);
