<?php
// rider/ajax_order_status.php — returns current order status + payment_status as JSON
require_once '../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isRider()) { echo json_encode(['error'=>'unauthorized']); exit; }

$orderId = isset($_GET['order_id']) ? sanitize($_GET['order_id']) : '';

if ($orderId === '') { echo json_encode(['error'=>'missing order_id']); exit; }

$riderId = (int)$_SESSION['user_id'];
$sql = "SELECT status, payment_status, payment_method, bill_image, service_type
        FROM orders
        WHERE order_id = '$orderId'
          AND rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
        LIMIT 1";
$res = mysqli_query($conn, $sql);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) { echo json_encode(['error'=>'not found']); exit; }

echo json_encode([
    'status'         => $row['status'],
    'payment_status' => $row['payment_status'],
    'payment_method' => $row['payment_method'],
    'bill_uploaded'  => !empty($row['bill_image']),
    'service_type'   => $row['service_type'],
]);
