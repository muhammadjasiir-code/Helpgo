<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$orderId = isset($_GET['order_id']) ? $_GET['order_id'] : '';
if (empty($orderId)) { die("Order ID missing."); }
$orderId_safe = mysqli_real_escape_string($conn, $orderId);

// Fetch order – now also get the internal ID (auto-increment) for the tracking page
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, s.upi_id, s.name AS store_name, o.id AS internal_id
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    WHERE o.order_id = '$orderId_safe' AND o.user_id = " . (int)$_SESSION['user_id']
));

if (!$order) {
    die("Order not found or access denied.");
}

$upiId = $order['upi_id'] ?? '';
$internalId = $order['internal_id'];   // used for redirect

// Handle payment proof upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $utr = trim($_POST['utr'] ?? '');
    $file = $_FILES['payment_proof'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '<div class="alert error">Upload error (code ' . $file['error'] . '). Please try again.</div>';
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $msg = '<div class="alert error">Only JPG, PNG, WebP, and GIF images are allowed.</div>';
        } else {
            $uploadDir = 'uploads/payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . $orderId_safe . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $utrSafe = mysqli_real_escape_string($conn, $utr);
                $pathSafe = mysqli_real_escape_string($conn, $destination);
                mysqli_query($conn, "UPDATE orders SET payment_proof = '$pathSafe', utr = '$utrSafe', store_order_status = 'payment_submitted' WHERE order_id = '$orderId_safe'");
                
                // ✅ Redirect to tracking page after successful upload
                header("Location: store_order.php?id=" . $internalId);
                exit;
            } else {
                $msg = '<div class="alert error">Failed to save the file. Please check folder permissions.</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Complete Payment – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold:#F4B400; --ink:#1A1A1A; --muted:#7A7A7A; --bg:#F6F5F1; --card:#fff; --radius:22px; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--ink); display:flex; justify-content:center; min-height:100vh; padding:20px; }
        .app { width:100%; max-width:430px; }
        .back-btn { display:inline-flex; align-items:center; gap:6px; color:var(--ink); font-weight:600; margin-bottom:20px; text-decoration:none; }
        h1 { font-family:'Outfit',sans-serif; font-size:24px; font-weight:800; margin-bottom:20px; }
        .section { background:var(--card); border-radius:var(--radius); padding:20px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,.04); }
        .upi-box { background:#f9f9f9; padding:14px; border-radius:12px; display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .upi-box .upi-id { font-weight:700; font-size:18px; word-break:break-all; }
        .qr-code { width:200px; height:200px; margin:20px auto; display:block; }
        .form-group { margin-bottom:12px; }
        label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
        input, textarea { width:100%; padding:12px; border:1px solid #ddd; border-radius:12px; font-family:inherit; font-size:14px; }
        .btn { display:block; width:100%; padding:16px; background:var(--gold); color:#000; border:none; border-radius:30px; font-weight:800; font-size:16px; cursor:pointer; margin-top:20px; }
        .alert { padding:10px 14px; border-radius:12px; margin-bottom:15px; font-size:14px; }
        .alert.success { background:#e6f7e6; color:#2e7d32; border:1px solid #a5d6a7; }
        .alert.error { background:#fdecea; color:#c62828; border:1px solid #ef9a9a; }
    </style>
</head>
<body>
<div class="app">
    <a href="/orders" class="back-btn"><i class="fas fa-arrow-left"></i> My Orders</a>
    <h1>Complete Payment</h1>
    <?= $msg ?>
    <div class="section">
        <h3 style="margin-bottom:10px;">Pay to <?= htmlspecialchars($order['store_name']) ?></h3>
        <?php if (!empty($upiId)): ?>
            <div class="upi-box">
                <span>UPI ID</span>
                <span class="upi-id"><?= htmlspecialchars($upiId) ?></span>
                <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($upiId) ?>')" style="background:none; border:none; color:var(--gold); cursor:pointer;">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <img class="qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=<?= urlencode($upiId) ?>&pn=HelpGo" alt="UPI QR Code" onerror="this.style.display='none'">
        <?php else: ?>
            <p style="color:var(--muted);">UPI ID not available. Please contact the store.</p>
        <?php endif; ?>
    </div>

    <?php if (empty($msg) || strpos($msg, 'success') === false): ?>
    <div class="section">
        <h3 style="margin-bottom:10px;">Upload Payment Proof</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>UTR Number (12 digits)</label>
                <input type="text" name="utr" placeholder="e.g., 123456789012" required>
            </div>
            <div class="form-group">
                <label>Payment Screenshot</label>
                <input type="file" name="payment_proof" accept="image/*" required>
            </div>
            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Submit Proof</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</body>
</html>