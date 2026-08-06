<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];

// Fetch grocery orders assigned to this rider that need a bill
$ordersWithoutBill = mysqli_query($conn, "
    SELECT o.order_id, o.grocery_list, o.drop_address, o.total_amount, u.full_name AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
      AND o.service_type = 'grocery'
      AND o.bill_image IS NULL
      AND o.status NOT IN ('delivered','cancelled')
    ORDER BY o.id DESC
");

$msg = '';

// Handle bill upload for a specific order
if (isset($_POST['upload_bill']) && isset($_POST['order_id'])) {
    $orderId = sanitize($_POST['order_id']);
    $productAmount = floatval($_POST['product_amount'] ?? 0);
    $file = $_FILES['bill_image'] ?? null;

    if ($productAmount <= 0) {
        $msg = "Please enter a valid product amount.";
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = "Please select a bill image.";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (!in_array($ext, $allowed)) {
            $msg = "Only JPG, PNG, PDF files are allowed.";
        } else {
            $filename = time() . '_bill_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = UPLOAD_DIR . 'bills/' . $filename;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Update order: set product amount, recalc total, save bill image
                $orderData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT delivery_fare FROM orders WHERE order_id = '$orderId'"));
                if ($orderData) {
                    $newTotal = $productAmount + floatval($orderData['delivery_fare']);
                    mysqli_query($conn, "UPDATE orders SET product_amount = $productAmount, total_amount = $newTotal, bill_image = '$filename' WHERE order_id = '$orderId'");
                    $msg = "Bill uploaded successfully!";
                    // Refresh the list
                    $ordersWithoutBill = mysqli_query($conn, "
                        SELECT o.order_id, o.grocery_list, o.drop_address, o.total_amount, u.full_name AS customer_name
                        FROM orders o
                        JOIN users u ON o.user_id = u.id
                        WHERE o.rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
                          AND o.service_type = 'grocery'
                          AND o.bill_image IS NULL
                          AND o.status NOT IN ('delivered','cancelled')
                        ORDER BY o.id DESC
                    ");
                }
            } else {
                $msg = "Failed to save the file. Check folder permissions.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bill – HelpGo Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0f1117; --surface:#1a1d27; --border:rgba(255,255,255,0.08); --text:#fff; --text-secondary:#9aa0b0; --green:#2ED573; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:20px; }
        .container { max-width:500px; margin:auto; }
        .back { color:var(--primary); text-decoration:none; display:inline-block; margin-bottom:20px; }
        h2 { margin-bottom:20px; }
        .card { background:var(--surface); border-radius:20px; padding:20px; margin-bottom:20px; border:1px solid var(--border); }
        .order-item { margin-bottom:20px; }
        .order-item h3 { font-size:18px; }
        label { display:block; margin:10px 0 5px; color:var(--text-secondary); }
        input, input[type="file"] { width:100%; padding:10px; border-radius:12px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text); margin-bottom:10px; }
        .btn { background:var(--primary); color:#fff; padding:12px 25px; border:none; border-radius:12px; font-weight:600; cursor:pointer; }
        .msg { padding:10px; border-radius:10px; margin-bottom:15px; background:rgba(46,213,115,0.15); color:var(--green); }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>Upload Bills</h2>
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

    <?php while ($o = mysqli_fetch_assoc($ordersWithoutBill)): ?>
    <div class="card order-item">
        <h3>Order #<?= $o['order_id'] ?></h3>
        <p><strong>Customer:</strong> <?= htmlspecialchars($o['customer_name']) ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($o['drop_address']) ?></p>
        <p><strong>Grocery List:</strong></p>
        <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; white-space:pre-wrap; font-size:14px;"><?= htmlspecialchars($o['grocery_list']) ?></div>
        <form method="POST" enctype="multipart/form-data" style="margin-top:15px;">
            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
            <label>Product Amount (₹)</label>
            <input type="number" name="product_amount" step="0.01" required>
            <label>Bill Image</label>
            <input type="file" name="bill_image" accept="image/*" required>
            <button type="submit" name="upload_bill" class="btn">Upload Bill</button>
        </form>
    </div>
    <?php endwhile; ?>

    <?php if (mysqli_num_rows($ordersWithoutBill) == 0): ?>
        <p style="color:var(--text-secondary);">No pending bills to upload.</p>
    <?php endif; ?>
</div>
</body>
</html>