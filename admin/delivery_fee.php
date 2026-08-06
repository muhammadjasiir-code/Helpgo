<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$msg = '';
if (isset($_POST['update_fee'])) {
    $fee = (float)$_POST['delivery_fee'];
    mysqli_query($conn, "UPDATE settings SET setting_value = '$fee' WHERE setting_key = 'delivery_fee'");
    $msg = '<div class="alert success">✅ Delivery fee updated to ₹' . number_format($fee, 2) . '</div>';
}

// Get current fee
$feeRes = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'delivery_fee' LIMIT 1");
$currentFee = 25;
if ($feeRes && ($row = mysqli_fetch_assoc($feeRes))) {
    $currentFee = (float)$row['setting_value'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delivery Fee – Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#052e24; --surface:#064e3b; --primary:#c9a84c; --text:#f5f0e0; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; justify-content:center; padding:40px 20px; }
        .container { max-width:400px; width:100%; }
        .card { background:var(--surface); border-radius:20px; padding:25px; }
        .form-group { margin-bottom:16px; }
        label { display:block; margin-bottom:6px; font-size:14px; }
        input { width:100%; padding:12px; border-radius:12px; background:var(--bg); border:1px solid rgba(255,255,255,0.1); color:var(--text); font-size:16px; }
        .btn { background:var(--primary); color:#052e24; border:none; padding:14px 20px; border-radius:30px; font-weight:700; font-size:16px; cursor:pointer; width:100%; }
        .alert { padding:12px; border-radius:12px; margin-bottom:15px; }
        .alert.success { background:rgba(13,122,95,0.4); color:#a3e4bc; }
        a.back { color:var(--primary); font-size:14px; display:inline-block; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2 style="margin-bottom:20px;">🚚 Delivery Fee Settings</h2>
    <?= $msg ?>
    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label>Global Delivery Fee (₹)</label>
                <input type="number" step="0.01" name="delivery_fee" value="<?= $currentFee ?>" required>
            </div>
            <button type="submit" name="update_fee" class="btn">Update Fee</button>
        </form>
    </div>
</div>
</body>
</html>