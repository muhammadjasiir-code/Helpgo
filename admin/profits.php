<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

// Today's profit (15% commission on delivery charges)
$todayProfit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(delivery_charge*0.15),0) as profit FROM orders WHERE DATE(order_date)=CURDATE() AND status='delivered'"))['profit'];
$weekProfit  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(delivery_charge*0.15),0) as profit FROM orders WHERE YEARWEEK(order_date)=YEARWEEK(CURDATE()) AND status='delivered'"))['profit'];
$monthProfit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(delivery_charge*0.15),0) as profit FROM orders WHERE MONTH(order_date)=MONTH(CURDATE()) AND YEAR(order_date)=YEAR(CURDATE()) AND status='delivered'"))['profit'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit Report – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px; }
        .container { max-width:600px; margin:auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:25px; margin-bottom:20px; text-align:center; }
        h2 { margin-bottom:20px; }
        .back { color:var(--primary); text-decoration:none; margin-bottom:20px; display:inline-block; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>Profit Summary (15% commission)</h2>
    <div class="card"><h3>Today</h3><p style="font-size:32px; font-weight:700;">₹<?= number_format($todayProfit,2) ?></p></div>
    <div class="card"><h3>This Week</h3><p style="font-size:32px; font-weight:700;">₹<?= number_format($weekProfit,2) ?></p></div>
    <div class="card"><h3>This Month</h3><p style="font-size:32px; font-weight:700;">₹<?= number_format($monthProfit,2) ?></p></div>
</div>
</body>
</html>