<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error' => 'auth']); exit; }

$order_id = sanitize($_GET['order_id'] ?? '');
$uid      = (int)$_SESSION['user_id'];

if (empty($order_id)) { echo json_encode(['error' => 'invalid']); exit; }

$order = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM orders WHERE order_id = '$order_id' AND user_id = $uid LIMIT 1"));

if (!$order) { echo json_encode(['error' => 'not_found']); exit; }

$riderLat = null; $riderLng = null; $riderName = null; $riderPhone = null;
if (!empty($order['rider_id'])) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT r.current_lat, r.current_lng, u.full_name, u.phone
         FROM riders r LEFT JOIN users u ON u.id = r.user_id
         WHERE r.id = " . (int)$order['rider_id'] . " LIMIT 1"));
    if ($r) {
        $riderLat   = $r['current_lat'];
        $riderLng   = $r['current_lng'];
        $riderName  = $r['full_name'] ?? null;
        $riderPhone = $r['phone'] ?? null;
    }
}

$productAmount = (float)($order['product_amount'] ?? 0);
$deliveryFare  = (float)($order['delivery_fare']  ?? 0);
$dbTotal       = (float)($order['total_amount']   ?? 0);
$totalAmount   = $productAmount > 0 ? ($productAmount + $deliveryFare) : $dbTotal;

echo json_encode([
    'status'         => strtolower($order['status'] ?? ''),
    'serviceType'    => strtolower($order['service_type'] ?? ''),
    'paymentMethod'  => strtolower($order['payment_method'] ?? ''),
    'paymentStatus'  => strtolower($order['payment_status'] ?? ''),
    'proofSubmitted' => !empty($order['payment_screenshot']),
    'productAmount'  => $productAmount,
    'deliveryFare'   => $deliveryFare,
    'totalAmount'    => $totalAmount,
    'billImage'      => $order['bill_image'] ?? null,
    'otp'            => $order['otp'] ?? null,
    'riderLat'       => $riderLat,
    'riderLng'       => $riderLng,
    'riderName'      => $riderName,
    'riderPhone'     => $riderPhone,
]);
