<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

// Stats (unchanged)
$totalUsers    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type='customer'"))['cnt'] ?? 0;
$totalRiders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type='rider'"))['cnt'] ?? 0;
$totalOrders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders"))['cnt'] ?? 0;
$pendingOrders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE status='pending'"))['cnt'] ?? 0;
$todayProfit   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(delivery_charge*0.15),0) as profit FROM orders WHERE DATE(order_date)=CURDATE() AND status='delivered'"))['profit'] ?? 0;

// Pending UPI payments count
$pendingPayments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE payment_method='upi' AND payment_status='pending' AND payment_screenshot IS NOT NULL"))['cnt'] ?? 0;

// Pending withdrawals count
$pendingWithdrawals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM withdrawals WHERE status='pending'"))['cnt'] ?? 0;

// Total stores count
$totalStores = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stores"))['cnt'] ?? 0;

// Delivery fee from settings
$deliveryFee = 25;
$feeRes = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'delivery_fee' LIMIT 1");
if ($feeRes && ($row = mysqli_fetch_assoc($feeRes))) {
    $deliveryFee = (float)$row['setting_value'];
}

// Pending shop applications count
$pendingShopApps = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM shop_applications WHERE status = 'pending'"))['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.95); --border:rgba(255,255,255,0.08); --text:#fff; --text-secondary:#B0B0B0; --sidebar-width:260px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; overflow-x:hidden; }
        .sidebar { position:fixed; top:0; left:0; height:100%; width:var(--sidebar-width); background:var(--card); border-right:1px solid var(--border); padding:30px 20px; display:flex; flex-direction:column; gap:4px; z-index:1000; transform:translateX(-100%); transition:transform 0.3s ease; }
        .sidebar.open { transform:translateX(0); }
        .sidebar h2 { font-size:22px; margin-bottom:20px; color:var(--primary); }
        .sidebar a { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:10px; color:var(--text-secondary); text-decoration:none; font-weight:500; transition:0.2s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.05); color:#fff; }
        .sidebar a i { width:20px; color:var(--primary); }
        .badge { background:var(--primary); color:#fff; padding:2px 8px; border-radius:20px; font-size:12px; margin-left:auto; }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; opacity:0; pointer-events:none; transition:opacity 0.3s; }
        .sidebar-overlay.show { opacity:1; pointer-events:auto; }

        .main { flex:1; padding:30px; margin-left:0; transition:margin-left 0.3s; }
        .top-bar { display:flex; align-items:center; gap:15px; margin-bottom:30px; }
        .hamburger { width:42px; height:42px; border-radius:12px; background:var(--card); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text); font-size:20px; cursor:pointer; }
        .hamburger:hover { background:rgba(255,255,255,0.05); }
        .page-title { font-size:24px; font-weight:700; }

        .cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:20px; margin-bottom:30px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:22px; text-align:center; backdrop-filter:blur(15px); }
        .card i { font-size:28px; color:var(--primary); margin-bottom:10px; }
        .card h2 { font-size:30px; font-weight:800; }
        .card p { font-size:13px; color:var(--text-secondary); }
        .quick-links { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:15px; }
        .quick-link { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; color:var(--text); text-decoration:none; transition:0.2s; display:flex; align-items:center; gap:12px; }
        .quick-link:hover { border-color:var(--primary); }
        .quick-link i { font-size:22px; color:var(--primary); }

        @media (min-width: 768px) {
            .sidebar { transform:translateX(0); }
            .hamburger { display:none; }
            .main { margin-left:var(--sidebar-width); }
            .sidebar-overlay { display:none; }
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <h2>HelpGo</h2>
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="riders.php"><i class="fas fa-user-plus"></i> Manage Riders</a>
    <a href="verification.php"><i class="fas fa-id-card"></i> Verification</a>
    <a href="orders.php"><i class="fas fa-list-check"></i> Orders</a>
    <a href="users.php"><i class="fas fa-users"></i> Customers</a>
    <a href="payments.php"><i class="fas fa-credit-card"></i> Payments <?php if ($pendingPayments > 0): ?><span class="badge"><?= $pendingPayments ?></span><?php endif; ?></a>
    <a href="withdrawals.php"><i class="fas fa-money-bill-transfer"></i> Withdrawals <?php if ($pendingWithdrawals > 0): ?><span class="badge"><?= $pendingWithdrawals ?></span><?php endif; ?></a>
    <a href="live_orders.php"><i class="fas fa-map-marked-alt"></i> Live Orders</a>
    <a href="profits.php"><i class="fas fa-chart-line"></i> Profits</a>
    <a href="coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a href="admin_stores.php"><i class="fas fa-store"></i> Stores</a>
    <a href="delivery_fee.php"><i class="fas fa-truck"></i> Delivery Fee</a>
    <a href="shop_applications.php"><i class="fas fa-clipboard-check"></i> Shop Applications <?php if ($pendingShopApps > 0): ?><span class="badge"><?= $pendingShopApps ?></span><?php endif; ?></a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
    <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a>
    <a href="reviews.php"><i class="fas fa-star"></i> Reviews</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="../logout.php" style="margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<!-- Main Content -->
<div class="main">
    <div class="top-bar">
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
        <h1 class="page-title">Dashboard Overview</h1>
    </div>

    <!-- Stats Cards -->
    <div class="cards">
        <div class="card"><i class="fas fa-users"></i><h2><?= $totalUsers ?></h2><p>Customers</p></div>
        <div class="card"><i class="fas fa-motorcycle"></i><h2><?= $totalRiders ?></h2><p>Riders</p></div>
        <div class="card"><i class="fas fa-clipboard-list"></i><h2><?= $totalOrders ?></h2><p>Total Orders</p></div>
        <div class="card"><i class="fas fa-clock"></i><h2><?= $pendingOrders ?></h2><p>Pending</p></div>
        <div class="card"><i class="fas fa-coins"></i><h2>₹<?= number_format($todayProfit,2) ?></h2><p>Today's Profit</p></div>
        <div class="card"><i class="fas fa-credit-card"></i><h2><?= $pendingPayments ?></h2><p>UPI to Verify</p></div>
        <div class="card"><i class="fas fa-money-bill-transfer"></i><h2><?= $pendingWithdrawals ?></h2><p>Withdrawals</p></div>
        <div class="card"><i class="fas fa-store"></i><h2><?= $totalStores ?></h2><p>Stores</p></div>
        <div class="card"><i class="fas fa-truck"></i><h2>₹<?= number_format($deliveryFee,2) ?></h2><p>Delivery Fee</p></div>
        <div class="card"><i class="fas fa-clipboard-check"></i><h2><?= $pendingShopApps ?></h2><p>Shop Apps</p></div>
    </div>

    <!-- Quick Actions -->
    <h2 style="margin:20px 0 15px;">Quick Actions</h2>
    <div class="quick-links">
        <a href="payments.php" class="quick-link"><i class="fas fa-check-circle"></i> Verify UPI Payments</a>
        <a href="withdrawals.php" class="quick-link"><i class="fas fa-check-double"></i> Approve Withdrawals</a>
        <a href="live_orders.php" class="quick-link"><i class="fas fa-map-marked-alt"></i> Live Tracking</a>
        <a href="profits.php" class="quick-link"><i class="fas fa-calculator"></i> Profit Report</a>
        <a href="verification.php" class="quick-link"><i class="fas fa-user-check"></i> Verify Riders</a>
        <a href="settings.php" class="quick-link"><i class="fas fa-gas-pump"></i> Change Petrol Price</a>
        <a href="notifications.php" class="quick-link"><i class="fas fa-paper-plane"></i> Send Notification</a>
        <a href="complaints.php" class="quick-link"><i class="fas fa-envelope"></i> Complaints</a>
        <a href="reviews.php" class="quick-link"><i class="fas fa-star-half-alt"></i> Rider Reviews</a>
        <a href="coupons.php" class="quick-link"><i class="fas fa-tags"></i> Create Coupon</a>
        <a href="admin_stores.php" class="quick-link"><i class="fas fa-store-alt"></i> Manage Stores</a>
        <a href="delivery_fee.php" class="quick-link"><i class="fas fa-truck"></i> Set Delivery Fee</a>
        <a href="shop_applications.php" class="quick-link"><i class="fas fa-clipboard-check"></i> Shop Applications</a>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
}

// ★ Automatically close sidebar when a link is clicked (mobile behavior)
document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        // Only close if the sidebar is currently open (mobile)
        if (sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    });
});
</script>
</body>
</html>