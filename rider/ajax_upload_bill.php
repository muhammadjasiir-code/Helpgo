<?php
require_once '../config.php';

// Detect if this is an AJAX (XHR / fetch) request
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

function respond($ok, $payload = [], $redirectOrderId = null)
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $ok
        ], $payload));
        exit;
    }

    if ($ok) {
        $_SESSION['bill_success'] = true;
    } else {
        $_SESSION['bill_error'] = $payload['error'] ?? 'Upload failed';
    }

    header("Location: order_grocery.php?id=" . urlencode($redirectOrderId));
    exit;
}

if (!isRider()) { respond(false, ['error' => 'Unauthorized']); }

echo "METHOD: " . $_SERVER['REQUEST_METHOD'];
echo "<br><br>";

echo "<pre>";
print_r($_POST);

echo "<pre>";
print_r($_FILES);

exit;
if (empty($orderId)) { respond(false, ['error' => 'Order ID missing']); }

$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM orders
    WHERE order_id = '$orderId'
      AND rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
      AND service_type = 'grocery'
"));
if (!$order) { respond(false, ['error' => 'Order not found or not assigned to you'], $orderId); }

$productAmount = floatval(isset($_POST['product_amount']) ? $_POST['product_amount'] : 0);
if ($productAmount <= 0) { respond(false, ['error' => 'Please enter a valid product amount'], $orderId); }

if (empty($_FILES['bill_image']['name'])) {
    respond(false, ['error' => 'Please select a bill image'], $orderId);
}

$ext = strtolower(pathinfo($_FILES['bill_image']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','pdf'];
if (!in_array($ext, $allowed)) {
    respond(false, ['error' => 'Only JPG, PNG, PDF files allowed'], $orderId);
}

$filename = time() . '_bill_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = UPLOAD_DIR . 'bills/' . $filename;
if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);

if (!move_uploaded_file($_FILES['bill_image']['tmp_name'], $dest)) {
    respond(false, ['error' => 'Failed to save file'], $orderId);
}

// Generate 4-digit OTP for delivery verification
$otp = rand(1000, 9999);
$newTotal = $productAmount + floatval($order['delivery_fare']);

mysqli_query($conn, "UPDATE orders
    SET product_amount = $productAmount,
        total_amount = $newTotal,
        bill_image = '$filename',
        bill_uploaded_at = NOW(),
        otp = '$otp'
    WHERE order_id = '$orderId'");

// Build bill HTML (for AJAX callers)
$billHtml = '
<div class="detail-row"><span>Bill Image</span><a href="'. UPLOAD_URL . 'bills/' . $filename . '" target="_blank" style="color:var(--primary);">View Bill</a></div>
<div class="detail-row"><span>Product Amount</span><span>&#8377;' . number_format($productAmount,2) . '</span></div>
<div class="detail-row"><span>Service Charge</span><span>&#8377;' . number_format($order['delivery_fare'],2) . '</span></div>
<div class="detail-row"><span>Total to Pay</span><span style="color:var(--primary); font-weight:600;">&#8377;' . number_format($newTotal,2) . '</span></div>
';

$actionsHtml = '';
if (!in_array($order['status'], ['delivered','cancelled'])) {
    if ($order['payment_method'] == 'upi' && $order['payment_status'] != 'paid') {
        $actionsHtml = '<div class="card" style="text-align:center; color:var(--text-secondary);"><i class="fas fa-clock"></i> Waiting for customer payment...</div>';
    } else {
        if ($order['status'] == 'accepted') {
            $actionsHtml = '<form method="POST"><input type="hidden" name="status" value="picked_up"><button type="submit" name="update_status" class="btn">&#128230; Mark as Picked Up</button></form>';
        } elseif ($order['status'] == 'picked_up') {
            $actionsHtml = '<form method="POST"><input type="hidden" name="status" value="in_transit"><button type="submit" name="update_status" class="btn">&#127949; Start Delivery (On the Way)</button></form>';
        } elseif ($order['status'] == 'in_transit') {
            $actionsHtml = '<div class="otp-section"><p style="color:var(--text-secondary);">Enter the 4-digit OTP provided by the customer</p><form method="POST" id="otpForm"><input type="text" name="otp" class="otp-input" maxlength="4" inputmode="numeric" placeholder="0000" required><div class="slide-container" id="slideContainer"><div class="slide-track"><div class="slide-progress" id="slideProgress"></div><span class="slide-text" id="slideText">Slide to Deliver</span><div class="slide-thumb" id="slideThumb"><i class="fas fa-chevron-right"></i></div></div></div><input type="hidden" name="verify_otp" value="1"></form></div>';
        }
    }
    $actionsHtml .= '<button class="btn btn-navigate" onclick="openNavigation()"><i class="fas fa-map-marked-alt"></i> Navigate to Customer</button>';
}

respond(true, [
    'billHtml'    => $billHtml,
    'actionsHtml' => $actionsHtml,
    'otp'         => $otp,
], $orderId);
