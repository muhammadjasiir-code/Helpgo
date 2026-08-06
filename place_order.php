<?php
ob_start();
try {
    require_once "config.php";
    header('Content-Type: application/json');

    if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Login required.']); exit; }

    $uid        = (int)$_SESSION['user_id'];
    $storeId    = (int)($_POST['store_id'] ?? 0);
    $address    = trim($_POST['address'] ?? $_POST['delivery_address'] ?? '');
    $pincode    = trim($_POST['pincode'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'upi';
    $cartItems  = json_decode($_POST['cart_items'] ?? '[]', true);
    $lat = !empty($_POST['lat']) ? (float)$_POST['lat'] : 0;
    $lng = !empty($_POST['lng']) ? (float)$_POST['lng'] : 0;

    // Validate
    if (empty($address)) {
        echo json_encode(['success'=>false,'message'=>'Address required.']);
        exit;
    }
    if (empty($pincode) || !preg_match('/^\d{6}$/', $pincode)) {
        echo json_encode(['success'=>false,'message'=>'Valid 6-digit pincode required.']);
        exit;
    }
    if (empty($cartItems)) { echo json_encode(['success'=>false,'message'=>'Cart empty.']); exit; }

    $feeRes = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key='delivery_fee'");
    $deliveryFee = 25;
    if ($feeRes && ($row = mysqli_fetch_assoc($feeRes))) $deliveryFee = (float)$row['setting_value'];

    $subtotal = 0; $itemsList = [];
    foreach ($cartItems as $i) { $subtotal += $i['price'] * $i['qty']; $itemsList[] = $i['name'] . ' x' . $i['qty']; }
    $total = $subtotal + $deliveryFee;
    $products = implode(', ', $itemsList);

    $orderId = time() . mt_rand(100, 999);

    $address = mysqli_real_escape_string($conn, $address);
    $pincode = mysqli_real_escape_string($conn, $pincode);
    $products = mysqli_real_escape_string($conn, $products);

    $sql = "INSERT INTO orders (order_id, user_id, store_id, service_type, drop_address, drop_latitude, drop_longitude, total_amount, payment_method, payment_status, status, store_order_status, product_details)
            VALUES ('$orderId', $uid, $storeId, 'store_delivery', '$address', $lat, $lng, $total, '$paymentMethod', 'pending', 'pending', 'pending', '$products')";

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success'=>false,'message'=>'DB error: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success'=>true, 'order_id'=>$orderId, 'redirect'=>'/waiting.php?order_id='.$orderId]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Exception: '.$e->getMessage()]);
}