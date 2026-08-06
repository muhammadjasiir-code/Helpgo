<?php
require_once '../config.php';
if (!isAdmin()) { redirect('../index.php'); }

$msg = '';

// ----- Assign Rider -----
if (isset($_POST['assign_rider'])) {
    $order_id = sanitize($_POST['order_id']);
    $rider_id = (int)$_POST['rider_id'];
    mysqli_query($conn, "UPDATE orders SET rider_id = $rider_id, status = 'accepted' WHERE order_id = '$order_id' AND rider_id IS NULL");
    $msg = "Rider assigned.";
}

// ----- Update Status -----
if (isset($_POST['update_status'])) {
    $order_id = sanitize($_POST['order_id']);
    $newStatus = sanitize($_POST['status']);
    mysqli_query($conn, "UPDATE orders SET status = '$newStatus' WHERE order_id = '$order_id'");
    if ($newStatus == 'delivered' || $newStatus == 'completed') {
        $orderData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT rider_id, delivery_charge FROM orders WHERE order_id='$order_id'"));
        if ($orderData['rider_id']) {
            $commission = $orderData['delivery_charge'] * 0.15;
            mysqli_query($conn, "UPDATE wallet SET balance = balance + $commission WHERE user_id = {$orderData['rider_id']}");
            mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, description) VALUES ({$orderData['rider_id']}, 'credit', $commission, 'Commission for order #$order_id')");
        }
    }
    $msg = "Status updated.";
}

// Fetch all orders
$orders = mysqli_query($conn, "SELECT o.*, u.full_name as customer_name, r.full_name as rider_name 
                               FROM orders o 
                               JOIN users u ON o.user_id = u.id 
                               LEFT JOIN riders rd ON o.rider_id = rd.user_id 
                               LEFT JOIN users r ON rd.user_id = r.id 
                               ORDER BY o.id DESC");

$ridersList = mysqli_query($conn, "SELECT u.id, u.full_name, r.status FROM users u JOIN riders r ON u.id = r.user_id WHERE r.status='online'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Orders – HelpGo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary: #FF6B35; --bg: #0A0A0A; --card: rgba(20,20,20,0.9); --border: rgba(255,255,255,0.06); --text: #fff; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); padding:20px; }
        .container { max-width: 1000px; margin:auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:20px; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:10px; border-bottom:1px solid var(--border); text-align:left; }
        select, input, button { background:rgba(255,255,255,0.05); border:1px solid var(--border); color:#fff; padding:8px; border-radius:8px; }
        button { background:var(--primary); cursor:pointer; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="color:var(--primary);"><i class="fas fa-arrow-left"></i> Back</a>
    <h2 style="margin:20px 0;">All Orders</h2>
    <?php if ($msg): ?><p style="color:var(--primary);"><?= $msg ?></p><?php endif; ?>
    <div class="card">
        <table>
            <tr>
                <th>Order ID</th><th>Customer</th><th>Service</th><th>Status</th><th>Rider</th><th>Actions</th>
            </tr>
            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
            <tr>
                <td><?= $order['order_id'] ?></td>
                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                <td><?= ucfirst($order['service_type']) ?></td>
                <td><?= $order['status'] ?></td>
                <td><?= $order['rider_name'] ?: 'Unassigned' ?></td>
                <td style="display:flex; gap:5px; flex-wrap:wrap;">
                    <?php if (!$order['rider_id'] && $order['status']=='pending'): ?>
                    <form method="POST" style="display:flex; gap:5px;">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <select name="rider_id">
                            <?php mysqli_data_seek($ridersList, 0); while($r = mysqli_fetch_assoc($ridersList)): ?>
                                <option value="<?= $r['id'] ?>"><?= $r['full_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="assign_rider">Assign</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($order['rider_id'] && $order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                    <form method="POST" style="display:flex; gap:5px;">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <select name="status">
                            <option value="accepted" <?= $order['status']=='accepted'?'selected':'' ?>>Accepted</option>
                            <option value="picked_up" <?= $order['status']=='picked_up'?'selected':'' ?>>Picked Up</option>
                            <option value="in_transit" <?= $order['status']=='in_transit'?'selected':'' ?>>On the Way</option>
                            <option value="delivered" <?= $order['status']=='delivered'?'selected':'' ?>>Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button type="submit" name="update_status">Update</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>