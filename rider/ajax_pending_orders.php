<?php
require_once '../config.php';
if (!isRider()) {
    header('Content-Type: application/json');
    echo json_encode(['orders' => []]);
    exit;
}

$riderId = (int)$_SESSION['user_id'];

$orders = [];
$query = mysqli_query($conn, "
    SELECT o.order_id, o.service_type, o.drop_address, o.delivery_fare
    FROM orders o
    WHERE o.status = 'pending' AND o.rider_id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM order_rejections r 
        WHERE r.order_id = o.order_id AND r.rider_id = $riderId
    )
    ORDER BY o.id DESC
    LIMIT 20
");

while ($row = mysqli_fetch_assoc($query)) {
    $orders[] = [
        'order_id'      => $row['order_id'],
        'service_type'  => $row['service_type'],
        'drop_address'  => $row['drop_address'],
        'delivery_fare' => $row['delivery_fare']
    ];
}

header('Content-Type: application/json');
echo json_encode(['orders' => $orders]);