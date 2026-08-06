<?php
require_once "config.php";
header('Content-Type: application/json');
$id = (int)$_GET['id'];
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_order_status FROM orders WHERE order_id = $id"));
echo json_encode(['status' => $order['store_order_status'] ?? 'pending']);