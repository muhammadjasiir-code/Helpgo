<?php
require_once 'config.php';
header('Content-Type: application/json');

$order_id = sanitize($_GET['order_id'] ?? '');
if (empty($order_id)) {
    echo json_encode(['success'=>false, 'message'=>'Order ID required']);
    exit;
}
$location = mysqli_fetch_assoc(mysqli_query($conn, "SELECT latitude, longitude, heading, speed, updated_at FROM rider_locations WHERE order_id='$order_id' ORDER BY updated_at DESC LIMIT 1"));
if ($location) {
    echo json_encode([
        'success' => true,
        'lat'     => $location['latitude'],
        'lng'     => $location['longitude'],
        'heading' => $location['heading'] ?? 0,
        'speed'   => $location['speed'] ?? 0,
        'updated_at' => $location['updated_at']
    ]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Rider location not available']);
}