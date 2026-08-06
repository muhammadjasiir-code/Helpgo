<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE order_id = $orderId AND user_id = " . $_SESSION['user_id']));
if (!$order) { die("Order not found."); }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Preparing your order… – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background:#FFF4E6; font-family:'Outfit',sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; }
        .card { max-width:380px; text-align:center; padding:30px; }
        .loader-pizza { width:80px; height:80px; margin:0 auto 20px; border:6px solid #F4B400; border-top-color:#FF6B35; border-radius:50%; animation:spin 1s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg)} }
        h2 { font-weight:800; }
        p { color:#555; }
    </style>
</head>
<body>
<div class="card">
    <div class="loader-pizza"></div>
    <h2>Preparing your order…</h2>
    <p>We'll notify you once the shop is ready for payment.</p>
    <p style="color:#888; font-size:13px;">Order #<?= $orderId ?></p>
</div>

<script>
const ORDER_ID = <?= $orderId ?>;
function checkStatus() {
    fetch(`/ajax_order_status.php?id=${ORDER_ID}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ready_for_payment') {
    window.location.href = '/payment_page.php?order_id=' + ORDER_ID;
}
        });
}
setInterval(checkStatus, 3000);  // poll every 3s
</script>
</body>
</html>