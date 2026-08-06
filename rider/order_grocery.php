<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$orderId = sanitize(isset($_GET['id']) ? $_GET['id'] : '');
$riderId = (int)$_SESSION['user_id'];

$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.order_id = '$orderId'
      AND o.service_type = 'grocery'
      AND o.rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
    LIMIT 1
"));

if (!$order) {
    die("<div style='color:#fff;text-align:center;margin-top:50px;font-family:sans-serif;background:#0f1117;min-height:100vh;padding-top:80px;'>Grocery order not found or not assigned to you.<br><br><a href='home.php' style='color:#FF6B35;'>Back to Home</a></div>");
}

$otp_error = '';
$bill_error = $_SESSION['bill_error'] ?? '';
$bill_success = $_SESSION['bill_success'] ?? false;
unset($_SESSION['bill_error'], $_SESSION['bill_success']);
if (isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['status']);
    if (in_array($newStatus, array('accepted', 'picked_up', 'in_transit'))) {
        mysqli_query($conn, "UPDATE orders SET status = '$newStatus' WHERE order_id = '$orderId'");
        header("Location: order_grocery.php?id=$orderId");
        exit;
    }
}

if (isset($_POST['verify_otp'])) {
    $entered_otp = sanitize(isset($_POST['otp']) ? $_POST['otp'] : '');
    if ($entered_otp === $order['otp']) {
        mysqli_query($conn, "UPDATE orders SET status = 'delivered' WHERE order_id = '$orderId'");
        $platformFee = 5.00;
        $deliveryFare = floatval($order['delivery_fare']);
        $productAmount = floatval($order['product_amount']);
        if ($order['payment_method'] == 'upi') {
            $riderEarning = $productAmount + $deliveryFare - $platformFee;
            mysqli_query($conn, "UPDATE wallet SET balance = balance + $riderEarning WHERE user_id = $riderId");
            mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, description) VALUES ($riderId, 'credit', $riderEarning, 'Earnings for order #$orderId (UPI)')");
        } else {
            mysqli_query($conn, "UPDATE wallet SET balance = balance - $platformFee WHERE user_id = $riderId");
            mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, description) VALUES ($riderId, 'debit', $platformFee, 'Platform fee for order #$orderId (COD)')");
        }
        header("Location: order_grocery.php?id=$orderId");
        exit;
    } else {
        $otp_error = "Invalid OTP. Please try again.";
    }
}

$billNotUploaded = empty($order['bill_image']);
$isTerminal = ($order['status'] == 'delivered' || $order['status'] == 'cancelled');
// Only meta-refresh when waiting for UPI approval AND bill already uploaded (never while typing bill amount)
$allowMetaRefresh = (!$billNotUploaded && $order['payment_method']=='upi' && $order['payment_status']!='paid' && !$isTerminal);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php if ($allowMetaRefresh): ?>
    <meta http-equiv="refresh" content="2">
    <?php endif; ?>
    <title>Grocery Order #<?= $orderId ?> – HelpGo Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary:#FF6B35; --primary-dark:#E55A2B; --bg:#0f1117; --surface:#1a1d27; --border:rgba(255,255,255,0.08); --text:#fff; --text-secondary:#9aa0b0; --text-muted:#6b7280; --green:#2ED573; --danger:#FF4757; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:20px 16px 40px; }
        .container { max-width:500px; margin:0 auto; }
        .back-link { display:inline-flex; align-items:center; gap:8px; color:var(--primary); text-decoration:none; margin-bottom:20px; }
        .card { background:var(--surface); border-radius:20px; padding:24px; margin-bottom:20px; }
        .order-header h2 { font-size:22px; }
        .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:600; text-transform:capitalize; }
        .status-pending { background:rgba(255,165,2,0.2); color:#FFA502; }
        .status-accepted { background:rgba(0,78,137,0.2); color:#0077b6; }
        .status-picked_up,.status-in_transit { background:rgba(138,43,226,0.2); color:#bb86fc; }
        .status-delivered,.status-completed { background:rgba(46,213,115,0.2); color:var(--green); }
        .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
        .detail-row span:first-child { color:var(--text-secondary); }
        .btn { width:100%; padding:14px; border:none; border-radius:14px; font-weight:600; font-size:16px; cursor:pointer; margin-bottom:10px; color:#fff; background:var(--primary); }
        .btn-navigate { background:#007bff; margin-top:15px; }
        .otp-input { width:100%; padding:14px; border-radius:14px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text); font-size:24px; text-align:center; letter-spacing:10px; }
        .otp-error { color:var(--danger); font-size:13px; margin-top:8px; }
        .grocery-list { background:rgba(255,255,255,0.03); padding:14px; border-radius:12px; margin:10px 0; white-space:pre-wrap; font-size:14px; }
        .bill-upload label { display:block; color:var(--text-secondary); font-size:13px; margin-top:8px; }
        .bill-upload input[type=number] { width:100%; padding:14px; border-radius:14px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text); font-size:18px; margin-bottom:10px; }
        .small-map { width:100%; height:200px; border-radius:14px; margin-top:10px; border:1px solid var(--border); }
        .slide-container { position:relative; width:100%; height:64px; background:rgba(255,255,255,0.05); border-radius:40px; border:1px solid var(--border); overflow:hidden; margin-top:20px; touch-action:none; user-select:none; }
        .slide-track { width:100%; height:100%; position:relative; display:flex; align-items:center; }
        .slide-text { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); font-weight:600; font-size:15px; color:var(--text-muted); pointer-events:none; white-space:nowrap; }
        .slide-thumb { position:absolute; left:6px; top:50%; width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); display:flex; align-items:center; justify-content:center; z-index:2; box-shadow:0 8px 20px rgba(255,107,53,0.5); transform:translateY(-50%); }
        .slide-thumb i { color:#fff; font-size:22px; }
        .slide-progress { position:absolute; top:0; left:0; height:100%; background:linear-gradient(90deg,transparent,rgba(255,107,53,0.15)); width:0%; }
        .snap-back { transition: transform 0.4s cubic-bezier(0.25,0.46,0.45,0.94); }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>

    <div class="card">
        <div class="order-header">
            <h2>🛒 Grocery Order #<?= $orderId ?></h2>
            <?php $statusClass = 'status-' . strtolower($order['status']); ?>
            <span class="status-badge <?= $statusClass ?>"><?= ucfirst($order['status']) ?></span>
        </div>
        <div class="detail-row"><span>Customer</span><span><?= htmlspecialchars($order['customer_name']) ?></span></div>
        <div class="detail-row"><span>Phone</span><span><?= htmlspecialchars($order['customer_phone']) ?></span></div>
        <div class="detail-row"><span>Delivery Address</span><span style="text-align:right;"><?= htmlspecialchars($order['drop_address']) ?></span></div>
        <div class="detail-row"><span>Payment</span><span>
            <?= $order['payment_method'] == 'upi' ? ($order['payment_status']=='paid' ? 'Paid online (UPI) ✅' : 'UPI — awaiting payment') : 'Cash on delivery' ?>
        </span></div>

        <?php if (!empty($order['shop_name'])): ?>
            <div class="detail-row" style="color:var(--primary);"><span>🏪 Shop</span><span><?= htmlspecialchars($order['shop_name']) ?></span></div>
        <?php endif; ?>
        <div class="detail-row"><span>Grocery List</span></div>
        <div class="grocery-list"><?= htmlspecialchars($order['grocery_list']) ?></div>

        <?php if (!$billNotUploaded): ?> 
            <div class="detail-row"><span>Bill Image</span><a href="<?= UPLOAD_URL . 'bills/' . $order['bill_image'] ?>" target="_blank" style="color:var(--primary);">View Bill</a></div>
            <div class="detail-row"><span>Product Amount</span><span>₹<?= number_format($order['product_amount'],2) ?></span></div>
            <div class="detail-row"><span>Service Charge</span><span>₹<?= number_format($order['delivery_fare'],2) ?></span></div>
            <?php if ($order['payment_method'] == 'cash'): ?>
                <div class="detail-row"><span>Total to Collect</span><span style="color:var(--primary);font-weight:600;">₹<?= number_format($order['total_amount'],2) ?></span></div>
            <?php else: ?>
            <?php if ($bill_error): ?>
    <div class="err" style="background:rgba(255,71,87,0.15); color:#ff4757; padding:10px; border-radius:10px; margin-bottom:14px;"><?= htmlspecialchars($bill_error) ?></div>
<?php endif; ?>
<?php if ($bill_success): ?>
    <div class="success" style="background:rgba(46,213,115,0.15); color:#2ed573; padding:10px; border-radius:10px; margin-bottom:14px;">Bill uploaded successfully!</div>
<?php endif; ?>
                <div class="detail-row"><span>Your Reimbursement</span><span style="color:var(--green);">₹<?= number_format($order['product_amount'] + $order['delivery_fare'] - 5, 2) ?></span></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="actionsContainer">
        <?php if (!$isTerminal): ?>
            <?php if ($billNotUploaded): ?>
                <div class="card bill-upload">
                    <h3>Upload Bill</h3>
                    <p style="color:var(--text-secondary);font-size:13px;">After purchasing items, upload the bill and enter the total product cost.</p>
                    <?php
echo "Order ID = " . $order['order_id'];
?>
                    <form id="billForm"
      method="POST"
      action="upload_bill.php"
      enctype="multipart/form-data">

    <input type="hidden"
           name="order_id"
           value="<?= $order['order_id']; ?>">

    <label>Product Amount (₹)</label>

    <input type="number"
           name="product_amount"
           step="0.01"
           min="1"
           required>

    <label>Bill Image</label>

    <input type="file"
           name="bill_image"
           accept="image/*"
           required>

    <input type="submit"
           value="Upload Bill"
           class="btn">

</form>
                </div>
            <?php else: ?>
                <?php if ($order['payment_method'] == 'upi' && $order['payment_status'] != 'paid'): ?>
                    <div class="card" style="text-align:center;color:var(--text-secondary);padding:20px;">
                        <i class="fas fa-hourglass-half" style="font-size:36px;margin-bottom:12px;color:var(--primary);"></i>
                        <p>Waiting for customer payment</p>
                        <p style="font-size:13px;margin-top:5px;">This page will update automatically once admin confirms payment.</p>
                    </div>
                <?php else: ?>
                    <?php if ($order['status'] == 'accepted'): ?>
                        <form method="POST"><input type="hidden" name="status" value="picked_up"><button type="submit" name="update_status" class="btn">📦 Mark as Picked Up</button></form>
                    <?php elseif ($order['status'] == 'picked_up'): ?>
                        <form method="POST"><input type="hidden" name="status" value="in_transit"><button type="submit" name="update_status" class="btn">🏍️ Start Delivery (On the Way)</button></form>
                    <?php elseif ($order['status'] == 'in_transit'): ?>
                        <div class="otp-section">
                            <p style="color:var(--text-secondary);margin-bottom:10px;">Enter the 4‑digit OTP provided by the customer</p>
                            <form method="POST" id="otpForm">
                                <input type="text" name="otp" class="otp-input" maxlength="4" inputmode="numeric" placeholder="0000" required>
                                <?php if ($otp_error): ?><p class="otp-error"><?= $otp_error ?></p><?php endif; ?>
                                <div class="slide-container" id="slideContainer">
                                    <div class="slide-track">
                                        <div class="slide-progress" id="slideProgress"></div>
                                        <span class="slide-text" id="slideText">Slide to Deliver</span>
                                        <div class="slide-thumb" id="slideThumb"><i class="fas fa-chevron-right"></i></div>
                                    </div>
                                </div>
                                <input type="hidden" name="verify_otp" value="1">
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <button class="btn btn-navigate" onclick="openNavigation()"><i class="fas fa-map-marked-alt"></i> Navigate to Customer</button>
        <?php endif; ?>
    </div>

    <?php if (!empty($order['drop_latitude']) && !empty($order['drop_longitude'])): ?>
        <div class="small-map" id="custMap"></div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const riderStatus = "<?= $order['status'] ?>";
    const orderId = "<?= $orderId ?>";
    const initialPaymentStatus = "<?= $order['payment_status'] ?>";
    const paymentMethod = "<?= $order['payment_method'] ?>";
    const billNotUploaded = <?= $billNotUploaded ? 'true' : 'false' ?>;

    if (riderStatus !== 'delivered' && riderStatus !== 'cancelled') {
        setInterval(() => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    fetch('update_location.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:`order_id=${orderId}&lat=${pos.coords.latitude}&lng=${pos.coords.longitude}&heading=${(pos.coords.heading||0)}&speed=${(pos.coords.speed||0)}`
                    }).catch(()=>{});
                }, ()=>{}, {enableHighAccuracy:true, maximumAge:1000, timeout:8000});
            }
        }, 2000);
    }

    function openNavigation() {
        const destLat = <?= $order['drop_latitude'] ? $order['drop_latitude'] : 'null' ?>;
        const destLng = <?= $order['drop_longitude'] ? $order['drop_longitude'] : 'null' ?>;
        const destAddress = "<?= addslashes($order['drop_address']) ?>";
        if (destLat !== null && destLng !== null) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}`;
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => window.open(`https://www.google.com/maps/dir/?api=1&origin=${pos.coords.latitude},${pos.coords.longitude}&destination=${destLat},${destLng}&travelmode=driving`, '_blank'),
                    () => window.open(url, '_blank')
                );
            } else { window.open(url, '_blank'); }
        } else {
            window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(destAddress)}`, '_blank');
        }
    }

    <?php if (!empty($order['drop_latitude']) && !empty($order['drop_longitude'])): ?>
    const custMap = L.map('custMap').setView([<?= $order['drop_latitude'] ?>, <?= $order['drop_longitude'] ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(custMap);
    L.marker([<?= $order['drop_latitude'] ?>, <?= $order['drop_longitude'] ?>]).addTo(custMap).bindPopup('Customer Location').openPopup();
    <?php endif; ?>

    // Poll — but do NOT reload while rider is filling the bill form
    let lastPaymentStatus = initialPaymentStatus;
    let lastOrderStatus = riderStatus;
    async function pollStatus() {
        if (billNotUploaded) return; // never disturb bill-entry form
        try {
            const res = await fetch('ajax_order_status.php?order_id=' + encodeURIComponent(orderId) + '&_=' + Date.now(), {cache:'no-store'});
            const data = await res.json();
            if (!data) return;
            const ps = (data.payment_status || '').toLowerCase();
            const os = (data.status || '').toLowerCase();
            if (paymentMethod === 'upi' && lastPaymentStatus !== 'paid' && ps === 'paid') {
                try { const ctx = new (window.AudioContext||window.webkitAudioContext)(); const osc = ctx.createOscillator(); const gain = ctx.createGain(); osc.type='sine'; osc.frequency.value=880; gain.gain.setValueAtTime(0.3,ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.3); osc.connect(gain); gain.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime+0.3);} catch(e){}
                location.reload(); return;
            }
            if (os && os !== lastOrderStatus) { location.reload(); return; }
        } catch(e){}
    }
    setInterval(pollStatus, 2000);

    function initSmoothSlider() {
        const container = document.getElementById('slideContainer');
        if (!container) return;
        const thumb = document.getElementById('slideThumb');
        const progress = document.getElementById('slideProgress');
        const text = document.getElementById('slideText');
        const otpInput = document.querySelector('input[name="otp"]');
        const form = document.getElementById('otpForm');
        const maxTranslate = container.offsetWidth - thumb.offsetWidth - 12;
        let startX = 0, currentTranslate = 0, dragging = false;
        function updateUI(t) {
            t = Math.min(maxTranslate, Math.max(0,t));
            currentTranslate = t;
            thumb.style.transform = `translate(${t}px, -50%)`;
            const pct = (t / maxTranslate) * 100;
            progress.style.width = pct + '%';
            text.textContent = pct >= 90 ? 'Release to Deliver' : 'Slide to Deliver';
            thumb.style.background = pct >= 90 ? 'linear-gradient(135deg,#2ED573,#1a8f42)' : 'linear-gradient(135deg,var(--primary),var(--primary-dark))';
        }
        function snapBack() { thumb.classList.add('snap-back'); updateUI(0); setTimeout(()=>thumb.classList.remove('snap-back'),400); }
        thumb.addEventListener('pointerdown', e => { e.preventDefault(); dragging=true; startX=e.clientX-currentTranslate; thumb.setPointerCapture(e.pointerId); });
        window.addEventListener('pointermove', e => { if(!dragging) return; e.preventDefault(); updateUI(e.clientX-startX); });
        window.addEventListener('pointerup', () => {
            if (!dragging) return;
            dragging = false;
            if (currentTranslate >= maxTranslate * 0.9) {
                if (!otpInput.value || otpInput.value.length !== 4) { alert('Please enter the 4-digit OTP.'); snapBack(); return; }
                form.submit();
            } else { snapBack(); }
        });
        updateUI(0);
    }
    initSmoothSlider();
</script>
</body>
</html>