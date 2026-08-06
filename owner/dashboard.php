<?php
require_once '../config.php';
if (!isLoggedIn()) { redirect('login.php'); }

$uid = (int)$_SESSION['user_id'];

// Find the store owned by this user
$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE owner_id = $uid LIMIT 1"));
if (!$store) {
    die("You don't own a store yet. Please contact the administrator.");
}
$store_id = $store['id'];

// ---------- Handle UPI / QR update ----------
$updateMsg = '';
if (isset($_POST['update_upi'])) {
    $upiId = trim($_POST['upi_id'] ?? '');
    $upiId = mysqli_real_escape_string($conn, $upiId);

    // Process QR code upload (optional)
    $qrPath = $store['upi_qr'] ?? '';   // keep existing by default
    if (!empty($_FILES['qr_code']['name']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['qr_code']['tmp_name']);
        finfo_close($finfo);
        if (in_array($mime, $allowed)) {
            $dir = '../assets/store_qr/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            $filename = 'qr_' . $store_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $dir . $filename)) {
                $qrPath = 'assets/store_qr/' . $filename;
            } else {
                $updateMsg = '<div class="order-alert" style="background:rgba(220,53,69,0.1); border-color:rgba(220,53,69,0.3);"><i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i><span>Failed to save QR image.</span></div>';
            }
        } else {
            $updateMsg = '<div class="order-alert" style="background:rgba(220,53,69,0.1); border-color:rgba(220,53,69,0.3);"><i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i><span>Invalid image type (only JPG, PNG, WebP, GIF).</span></div>';
        }
    }

    if (empty($updateMsg)) {
        $qrPath = mysqli_real_escape_string($conn, $qrPath);
        mysqli_query($conn, "UPDATE stores SET upi_id = '$upiId', upi_qr = '$qrPath' WHERE id = $store_id");
        $updateMsg = '<div class="order-alert" style="background:rgba(46,213,115,0.1); border-color:rgba(46,213,115,0.3);"><i class="fas fa-check-circle" style="color:#2ED573;"></i><span>UPI details updated successfully!</span></div>';
        // Refresh store data
        $store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE id = $store_id"));
    }
}

// Basic stats
$productCount   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM store_products WHERE store_id = $store_id"))[0];
$categoryCount  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM store_categories WHERE store_id = $store_id"))[0];

// Order stats
$pendingOrders   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE store_id = $store_id AND store_order_status = 'pending'"))[0];
$acceptedOrders  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE store_id = $store_id AND store_order_status = 'accepted'"))[0];
$readyForPayment = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE store_id = $store_id AND store_order_status = 'ready_for_payment'"))[0];
$paymentSubmitted = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE store_id = $store_id AND store_order_status = 'payment_submitted'"))[0];
$totalActive      = $pendingOrders + $acceptedOrders + $readyForPayment + $paymentSubmitted;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Dashboard – <?= htmlspecialchars($store['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#052e24; --surface:#064e3b; --primary:#c9a84c; --text:#f5f0e0; --text-secondary:#9db3a8; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; justify-content:center; align-items:flex-start; min-height:100vh; padding:40px 20px; }
        .container { max-width:500px; width:100%; }
        .card { background:var(--surface); border-radius:20px; padding:25px; margin-bottom:20px; }
        .btn { display:inline-block; padding:10px 24px; border-radius:30px; background:var(--primary); color:#052e24; font-weight:700; text-decoration:none; border:none; cursor:pointer; margin-right:10px; margin-bottom:10px; }
        .logout { background:#dc3545; color:#fff; }
        .stats { display:flex; gap:15px; margin:15px 0; flex-wrap:wrap; }
        .stat { flex:1 1 80px; background:rgba(255,255,255,0.05); padding:15px; border-radius:15px; text-align:center; }
        .stat i { font-size:24px; color:var(--primary); }
        .stat h3 { margin:5px 0 0; font-size:18px; }
        .stat p { font-size:11px; color:var(--text-secondary); }
        .order-alert { background: rgba(255,193,7,0.1); border:1px solid rgba(255,193,7,0.3); border-radius:12px; padding:12px; margin:10px 0; display:flex; align-items:center; gap:10px; }
        .order-alert i { color:#ffc107; font-size:20px; }
        .order-alert span { font-weight:600; font-size:14px; }
        .form-group { margin-bottom:14px; }
        .form-group label { display:block; margin-bottom:4px; font-size:13px; color:var(--text-secondary); }
        .form-group input[type="text"], .form-group input[type="file"] {
            width:100%; padding:10px 14px; border-radius:10px;
            background:var(--bg); border:1px solid rgba(255,255,255,0.1);
            color:var(--text); font-family:inherit; font-size:14px;
        }
        .qr-preview { max-width:150px; border-radius:10px; margin-top:8px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🏪 <?= htmlspecialchars($store['name']) ?></h1>
    <p style="color:var(--text-secondary); margin-bottom:15px;"><?= htmlspecialchars($store['description'] ?? '') ?></p>

    <div class="card">
        <h3 style="margin-bottom:10px;">Store Overview</h3>
        <div class="stats">
            <div class="stat">
                <i class="fas fa-list"></i>
                <h3><?= $categoryCount ?></h3>
                <p>Categories</p>
            </div>
            <div class="stat">
                <i class="fas fa-box"></i>
                <h3><?= $productCount ?></h3>
                <p>Products</p>
            </div>
            <div class="stat">
                <i class="fas fa-shopping-bag"></i>
                <h3><?= $totalActive ?></h3>
                <p>Active Orders</p>
            </div>
        </div>
    </div>

    <!-- Order notification badges -->
    <?php if ($pendingOrders > 0 || $paymentSubmitted > 0): ?>
    <div class="order-alert">
        <i class="fas fa-bell"></i>
        <span>
            <?= $pendingOrders > 0 ? $pendingOrders . ' new order(s) waiting' : '' ?>
            <?= $paymentSubmitted > 0 ? ($pendingOrders > 0 ? ', ' : '') . $paymentSubmitted . ' payment(s) to verify' : '' ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Payment Setup Card (NEW) -->
    <div class="card">
        <h3 style="margin-bottom:10px;">💳 Payment Setup</h3>
        <?= $updateMsg ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>UPI ID (e.g., store@upi)</label>
                <input type="text" name="upi_id" value="<?= htmlspecialchars($store['upi_id'] ?? '') ?>" placeholder="Enter your UPI ID">
            </div>
            <div class="form-group">
                <label>UPI QR Code Image (optional)</label>
                <input type="file" name="qr_code" accept="image/*">
                <?php if (!empty($store['upi_qr'])): ?>
                    <div style="margin-top:8px;">
                        <img src="../<?= htmlspecialchars($store['upi_qr']) ?>" class="qr-preview" alt="Current QR">
                        <small style="color:var(--text-secondary);">(Current QR code)</small>
                    </div>
                <?php endif; ?>
            </div>
            <button type="submit" name="update_upi" class="btn"><i class="fas fa-save"></i> Save Payment Details</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom:10px;">Quick Actions</h3>
        <a href="products.php" class="btn"><i class="fas fa-boxes"></i> Products</a>
        <a href="categories.php" class="btn"><i class="fas fa-tags"></i> Categories</a>
        <a href="orders.php" class="btn" style="margin-top:8px;"><i class="fas fa-clipboard-list"></i> Orders
            <?php if ($totalActive > 0): ?><span style="background:#dc3545; color:#fff; padding:2px 8px; border-radius:20px; font-size:12px; margin-left:6px;"><?= $totalActive ?></span><?php endif; ?>
        </a>
    </div>

    <div class="card">
        <h3 style="margin-bottom:10px;">Payment & Delivery</h3>
        <p style="font-size:13px; color:var(--text-secondary);">Make sure your UPI ID and payment methods are set in the admin panel (contact HelpGo admin if needed).</p>
        <a href="/store/<?= urlencode($store['slug']) ?>" class="btn" style="background:transparent; border:1px solid var(--primary); color:var(--primary);">View Store</a>
    </div>

    <br>
    <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
</body>
</html>