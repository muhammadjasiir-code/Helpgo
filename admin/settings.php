<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$msg = '';

if (isset($_POST['update_settings'])) {
    $fields = [
        'petrol_price_per_litre'   => $_POST['petrol_price_per_litre'],
        'petrol_delivery_charge'   => $_POST['petrol_delivery_charge'],
        'grocery_delivery_charge'  => $_POST['grocery_delivery_charge'],
        'parcel_night_charge'      => $_POST['parcel_night_charge'],
        'night_charge_start_time'  => $_POST['night_charge_start_time'],
        'night_charge_end_time'    => $_POST['night_charge_end_time'],
        'rider_fare_per_km'        => $_POST['rider_fare_per_km'] ?? 5,
        'commission_percentage'    => $_POST['commission_percentage'] ?? 15,
        'fare_0_3km'               => $_POST['fare_0_3km'],
        'fare_3_6km'               => $_POST['fare_3_6km'],
        'fare_6_10km'              => $_POST['fare_6_10km'],
        'platform_fee'             => $_POST['platform_fee'],
    ];

    foreach ($fields as $key => $value) {
        $value = sanitize($value);
        $sql = "INSERT INTO site_settings (setting_key, setting_value) 
                VALUES ('$key', '$value') 
                ON DUPLICATE KEY UPDATE setting_value = '$value'";
        mysqli_query($conn, $sql);
    }
    $msg = "✅ Settings updated successfully!";
}

// Reload settings from DB
$settings = [];
$res = mysqli_query($conn, "SELECT * FROM site_settings");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; --text-secondary:#B0B0B0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px 20px; }
        .container { max-width:700px; margin:auto; }
        .card { background:var(--card); backdrop-filter:blur(15px); border:1px solid var(--border); border-radius:18px; padding:25px; margin-bottom:20px; }
        h2 { margin-bottom:20px; }
        label { display:block; margin:15px 0 5px; color:var(--text-secondary); font-size:14px; }
        input, select { width:100%; padding:12px; border-radius:10px; border:1px solid var(--border); background:rgba(255,255,255,0.05); color:#fff; font-size:15px; }
        .btn { background:var(--primary); color:#fff; padding:14px 30px; border:none; border-radius:10px; cursor:pointer; font-weight:600; margin-top:20px; }
        .msg { color:var(--primary); margin-bottom:15px; }
        .back { color:var(--primary); text-decoration:none; display:inline-block; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>⚙️ Site Settings</h2>
    <?php if ($msg): ?><p class="msg"><?= $msg ?></p><?php endif; ?>
    <form method="POST">
        <div class="card">
            <h3>⛽ Petrol & Delivery</h3>
            <label>Petrol Price per Litre (₹)</label>
            <input type="number" step="0.01" name="petrol_price_per_litre" value="<?= $settings['petrol_price_per_litre'] ?? 114 ?>">
            <label>Petrol Delivery Charge (₹)</label>
            <input type="number" step="0.01" name="petrol_delivery_charge" value="<?= $settings['petrol_delivery_charge'] ?? 30 ?>">
            <label>Grocery Delivery Charge (₹)</label>
            <input type="number" step="0.01" name="grocery_delivery_charge" value="<?= $settings['grocery_delivery_charge'] ?? 30 ?>">
            <label>Parcel Night Charge (₹)</label>
            <input type="number" step="0.01" name="parcel_night_charge" value="<?= $settings['parcel_night_charge'] ?? 80 ?>">
        </div>
        <div class="card">
            <h3>🛵 Rider Earnings</h3>
            <label>Rider Fare per KM (₹)</label>
            <input type="number" step="0.01" name="rider_fare_per_km" value="<?= $settings['rider_fare_per_km'] ?? 5 ?>">
            <label>Commission Percentage (%)</label>
            <input type="number" step="0.01" name="commission_percentage" value="<?= $settings['commission_percentage'] ?? 15 ?>">
        </div>
        <div class="card">
            <h3>📏 Distance Fare</h3>
            <label>0–3 km Fare (₹)</label>
            <input type="number" step="0.01" name="fare_0_3km" value="<?= $settings['fare_0_3km'] ?? 40 ?>">
            <label>3–6 km Fare (₹)</label>
            <input type="number" step="0.01" name="fare_3_6km" value="<?= $settings['fare_3_6km'] ?? 60 ?>">
            <label>6–10 km Fare (₹)</label>
            <input type="number" step="0.01" name="fare_6_10km" value="<?= $settings['fare_6_10km'] ?? 80 ?>">
            <label>Platform Fee (₹)</label>
            <input type="number" step="0.01" name="platform_fee" value="<?= $settings['platform_fee'] ?? 5 ?>">
        </div>
        <div class="card">
            <h3>🌙 Night Schedule</h3>
            <label>Night Start Time</label>
            <input type="time" name="night_charge_start_time" value="<?= $settings['night_charge_start_time'] ?? '20:00' ?>">
            <label>Night End Time</label>
            <input type="time" name="night_charge_end_time" value="<?= $settings['night_charge_end_time'] ?? '06:00' ?>">
        </div>
        <button type="submit" name="update_settings" class="btn">💾 Save All Settings</button>
    </form>
</div>
</body>
</html>