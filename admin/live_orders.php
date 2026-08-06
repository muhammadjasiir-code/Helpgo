<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$activeOrders = mysqli_query($conn, "
    SELECT o.*, 
           u.full_name AS customer_name, 
           u.phone AS customer_phone,
           rd.full_name AS rider_name,
           r.current_latitude AS rider_lat,
           r.current_longitude AS rider_lng
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN riders r ON o.rider_id = r.user_id
    LEFT JOIN users rd ON r.user_id = rd.id
    WHERE o.status IN ('accepted','picked_up','in_transit')
    AND o.drop_latitude IS NOT NULL
    ORDER BY o.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Orders – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px 20px; }
        .container { max-width:1200px; margin:auto; }
        h2 { margin-bottom:20px; }
        #adminMap { width:100%; height:500px; border-radius:18px; border:1px solid var(--border); }
        .order-list { background:var(--card); border-radius:18px; padding:20px; margin-top:20px; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:10px; border-bottom:1px solid var(--border); text-align:left; }
        .back { color:var(--primary); text-decoration:none; margin-bottom:20px; display:inline-block; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>Live Active Orders</h2>
    <div id="adminMap"></div>
    <div class="order-list">
        <table>
            <tr><th>Order</th><th>Customer</th><th>Rider</th><th>Status</th></tr>
            <?php while ($ord = mysqli_fetch_assoc($activeOrders)): ?>
            <tr>
                <td><?= $ord['order_id'] ?></td>
                <td><?= htmlspecialchars($ord['customer_name']) ?> (<?= $ord['customer_phone'] ?>)</td>
                <td><?= $ord['rider_name'] ?: 'Unassigned' ?></td>
                <td><?= ucfirst($ord['status']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('adminMap').setView([10.8505, 76.2711], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const orders = <?php 
        $ordersJson = [];
        mysqli_data_seek($activeOrders, 0);
        while ($o = mysqli_fetch_assoc($activeOrders)) {
            $ordersJson[] = [
                'order_id' => $o['order_id'],
                'customer' => $o['customer_name'],
                'rider' => $o['rider_name'],
                'cust_lat' => $o['drop_latitude'],
                'cust_lng' => $o['drop_longitude'],
                'rider_lat' => $o['rider_lat'],
                'rider_lng' => $o['rider_lng']
            ];
        }
        echo json_encode($ordersJson);
    ?>;

    orders.forEach(order => {
        if (order.cust_lat && order.cust_lng) {
            const custMarker = L.marker([order.cust_lat, order.cust_lng], {
                icon: L.divIcon({ className:'', html:'<i class="fas fa-map-pin" style="font-size:24px; color:#FF6B35;"></i>', iconSize:[24,32] })
            }).addTo(map).bindPopup(`<b>${order.customer}</b><br>Order #${order.order_id}`);
        }
        if (order.rider_lat && order.rider_lng) {
            const riderMarker = L.marker([order.rider_lat, order.rider_lng], {
                icon: L.divIcon({ className:'', html:'<i class="fas fa-motorcycle" style="font-size:24px; color:#2ED573;"></i>', iconSize:[24,28] })
            }).addTo(map).bindPopup(`<b>Rider: ${order.rider}</b><br>Order #${order.order_id}`);
        }
    });

    if (orders.length > 0) {
        const group = new L.featureGroup(orders.flatMap(o => {
            const markers = [];
            if (o.cust_lat && o.cust_lng) markers.push(L.marker([o.cust_lat, o.cust_lng]));
            if (o.rider_lat && o.rider_lng) markers.push(L.marker([o.rider_lat, o.rider_lng]));
            return markers;
        }));
        map.fitBounds(group.getBounds().pad(0.1));
    }
</script>
</body>
</html>