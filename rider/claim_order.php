<?php
require_once '../config.php';
if (!isRider()) { die(json_encode(['success'=>false])); }

$riderId = (int)$_SESSION['user_id'];
$orderId = sanitize($_POST['order_id'] ?? '');

// Get rider's primary key (from riders table, not user_id)
$riderRecord = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM riders WHERE user_id = $riderId"));
$riderRecordId = $riderRecord['id'];

// Clean up any expired claims instantly (on-the-fly cleanup)
mysqli_query($conn, "UPDATE orders SET rider_id = NULL, claim_expiry = NULL WHERE claim_expiry < UNIX_TIMESTAMP() AND status = 'pending'");

// Check if order is still available (free or already claimed by me)
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM orders 
    WHERE order_id = '$orderId' AND status = 'pending'
    AND (
        (rider_id IS NULL AND claim_expiry IS NULL) OR 
        (rider_id = $riderRecordId AND claim_expiry IS NOT NULL)
    )
    LIMIT 1
"));
if (!$order) {
    echo json_encode(['success'=>false]);
    exit;
}

// Claim for 10 seconds
$expiry = time() + 10;
mysqli_query($conn, "UPDATE orders SET rider_id = $riderRecordId, claim_expiry = $expiry WHERE order_id = '$orderId'");

echo json_encode(['success'=>true, 'order_id'=>$orderId]);