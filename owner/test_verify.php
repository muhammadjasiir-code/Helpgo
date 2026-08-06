<?php
require_once '../config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$orderId = '1785070645';    // <-- put the real order ID here

// Try the exact same UPDATE that the Verify Payment button runs
$sql = "UPDATE orders SET store_order_status = 'payment_verified', status = 'ready' WHERE order_id = '$orderId'";

if (mysqli_query($conn, $sql)) {
    echo "✅ Payment verified successfully!";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}