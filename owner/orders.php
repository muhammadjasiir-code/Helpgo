<?php
require_once '../config.php';
if (!isLoggedIn()) { redirect('../login.php'); }

$uid = (int)$_SESSION['user_id'];
$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE owner_id = $uid LIMIT 1"));
if (!$store) { die("You don't have a store yet."); }
$store_id = $store['id'];

// Fetch orders for this store
$orders = mysqli_query($conn, "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone 
    FROM orders o JOIN users u ON o.user_id = u.id 
    WHERE o.store_id = $store_id AND o.service_type = 'store_delivery' 
    ORDER BY o.id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders – <?= htmlspecialchars($store['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --bg:#052e24; --surface:#064e3b; --primary:#c9a84c; --text:#f5f0e0; }
        body { font-family:Outfit,sans-serif; background:var(--bg); color:var(--text); padding:20px; max-width:600px; margin:auto; }
        .back-btn { color:var(--primary); display:inline-flex; align-items:center; gap:6px; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; background:var(--surface); border-radius:12px; overflow:hidden; }
        th, td { padding:12px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.1); }
        .btn { padding:6px 12px; border-radius:6px; background:var(--primary); color:#052e24; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2>📦 Orders</h2>
    <table>
        <tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Action</th></tr>
        <?php while($o = mysqli_fetch_assoc($orders)): ?>
        <tr>
            <td>#<?= $o['order_id'] ?></td>
            <td><?= htmlspecialchars($o['customer_name']) ?></td>
            <td>₹<?= number_format($o['total_amount'],2) ?></td>
            <td><?= ucfirst($o['store_order_status']) ?></td>
            <td><a href="order_detail.php?oid=<?= $o['order_id'] ?>" class="btn">View</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>