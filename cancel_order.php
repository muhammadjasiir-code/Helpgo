<?php
require_once 'config.php';
$order_id = sanitize($_GET['order_id'] ?? '');
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE order_id='$order_id'"));
if ($order && $order['payment_status'] == 'pending' && $order['status'] == 'pending') {
    // Check if 15 minutes passed
    if (!empty($order['bill_uploaded_at']) && time() > strtotime($order['bill_uploaded_at']) + 900) {
        mysqli_query($conn, "UPDATE orders SET status='cancelled', payment_status='failed' WHERE order_id='$order_id'");
    }
}
echo 'ok';