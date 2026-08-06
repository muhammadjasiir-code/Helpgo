<?php
// petrol_orders_upi.php - UPI Petrol Order Tracking with Payment Verification
// Emerald Prestige Theme + 2s AJAX polling + QR payment flow

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ---------- Load config ----------
$configLoaded = false;
$candidates = array(
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php',
    __DIR__ . '/db.php',
    dirname(__DIR__) . '/db.php',
);
foreach ($candidates as $c) {
    if (file_exists($c)) { require_once $c; $configLoaded = true; break; }
}
if (!$configLoaded || !isset($conn)) {
    die('<div style="font-family:sans-serif;padding:24px;color:#b91c1c;">Configuration error. Unable to connect to database.</div>');
}

if (!defined('SITE_NAME'))       define('SITE_NAME', 'HelpGo');
if (!defined('SITE_URL'))        define('SITE_URL', '');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');

// ---------- Auth ----------
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = intval($_SESSION['user_id']);

// ---------- Helpers ----------
function first_value($row, $keys, $default = '') {
    if (!is_array($row)) return $default;
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $row[$k];
    }
    return $default;
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v) {
    $n = (float)$v;
    return CURRENCY_SYMBOL . number_format($n, 2);
}

// ---------- Resolve order ----------
$orderIdParam = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
if ($orderIdParam === '') { header('Location: orders.php'); exit; }

$order = null;
if (ctype_digit($orderIdParam)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $orderIdParam);
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    $stmt->bind_param('s', $orderIdParam);
}
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    die('<div style="font-family:sans-serif;padding:24px;">Order not found.</div>');
}
if (intval($order['user_id']) !== $userId) {
    die('<div style="font-family:sans-serif;padding:24px;">Access denied.</div>');
}

$orderCode = first_value($order, array('order_id','code','order_code'), $order['id']);
$orderPk   = intval($order['id']);

// ---------- Handle payment proof upload ----------
$uploadMsg = '';
$uploadErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof'])) {
    $utr = isset($_POST['utr']) ? trim($_POST['utr']) : '';
    if ($utr === '' || strlen($utr) < 6) {
        $uploadErr = 'Please enter a valid UTR / transaction ID.';
    } elseif (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $uploadErr = 'Please select a screenshot to upload.';
    } else {
        $f = $_FILES['proof'];
        $allowed = array('image/jpeg','image/png','image/webp');
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
        if (!in_array($mime, $allowed)) {
            $uploadErr = 'Only JPG, PNG, or WEBP images are allowed.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $uploadErr = 'File too large. Max 5 MB.';
        } else {
            $dir = __DIR__ . '/uploads/payments';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $fname = 'pay_' . $orderPk . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $rel = 'uploads/payments/' . $fname;
                $up = $conn->prepare("UPDATE orders SET payment_proof = ?, payment_utr = ?, payment_status = 'pending', proof_submitted_at = NOW() WHERE id = ?");
                $up->bind_param('ssi', $rel, $utr, $orderPk);
                if ($up->execute()) {
                    $uploadMsg = 'Payment proof uploaded. Awaiting admin approval.';
                    // refresh order
                    $order['payment_proof'] = $rel;
                    $order['payment_utr'] = $utr;
                    $order['payment_status'] = 'pending';
                } else {
                    $uploadErr = 'Database error while saving proof.';
                }
                $up->close();
            } else {
                $uploadErr = 'Upload failed. Please try again.';
            }
        }
    }
}

// ---------- Extract fields ----------
$status         = strtolower(first_value($order, array('status'), 'placed'));
$paymentMethod  = strtolower(first_value($order, array('payment_method','payment_mode'), 'upi'));
$paymentStatus  = strtolower(first_value($order, array('payment_status'), 'unpaid'));
$paymentProof   = first_value($order, array('payment_proof'), '');
$paymentUtr     = first_value($order, array('payment_utr','utr'), '');
$fuelType       = first_value($order, array('fuel_type','petrol_type','product_name'), 'Petrol');
$quantity       = first_value($order, array('quantity','liters','litres'), '');
$address        = first_value($order, array('delivery_address','address','user_address'), '');
$productAmount  = (float)first_value($order, array('product_amount','amount','price'), 0);
$deliveryFare   = (float)first_value($order, array('delivery_fare','delivery_fee','delivery_charge'), 0);
$totalAmount    = (float)first_value($order, array('total_amount','grand_total'), $productAmount + $deliveryFare);
$otp            = first_value($order, array('otp','delivery_otp','start_otp'), '');
$riderName      = first_value($order, array('rider_name','delivery_partner_name'), '');
$riderPhone     = first_value($order, array('rider_phone','delivery_partner_phone'), '');
$customerPhone  = first_value($order, array('phone','user_phone','customer_phone'), '');

// Step mapping
function status_step($s) {
    $s = strtolower($s);
    if (in_array($s, array('placed','pending','new'))) return 1;
    if (in_array($s, array('accepted','confirmed','assigned'))) return 2;
    if (in_array($s, array('picked_up','picked','pickup'))) return 3;
    if (in_array($s, array('in_transit','transit','on_the_way','out_for_delivery'))) return 4;
    if (in_array($s, array('delivered','completed'))) return 5;
    if (in_array($s, array('cancelled','canceled'))) return 0;
    return 1;
}
$step = status_step($status);
$isDelivered = ($status === 'delivered' || $status === 'completed');
$isCancelled = ($status === 'cancelled' || $status === 'canceled');

// Payment gating: OTP shows only if paid
$paymentVerified = ($paymentStatus === 'paid' || $paymentStatus === 'approved' || $paymentStatus === 'verified');

// Determine which OTP label to show
$showStartOtp    = ($paymentVerified && ($step === 2 || $step === 3));
$showDeliveryOtp = ($paymentVerified && $step === 4);

$qrPath = 'assets/upi_qr.png';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>UPI Petrol Order · <?php echo h(SITE_NAME); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#062b1f; --bg2:#083a2a; --card:#0e4a37; --card2:#0b3d2d;
    --line:rgba(255,255,255,.08); --text:#eaf7f0; --muted:#a9c9b8;
    --gold:#d4a94a; --gold2:#f0c869; --emerald:#12b981; --danger:#ef4444;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:linear-gradient(180deg,#052018 0%,#062b1f 100%);color:var(--text);font-family:'Manrope',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
  .wrap{max-width:520px;margin:0 auto;padding:16px 16px 120px}
  h1,h2,h3{font-family:'Sora',sans-serif;margin:0}
  a{color:inherit;text-decoration:none}

  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
  .chip{width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.06);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:18px}
  .oid{font-family:'Sora';color:var(--gold);font-weight:700;letter-spacing:.5px;font-size:14px}

  .hero{background:linear-gradient(180deg,var(--card) 0%,var(--card2) 100%);border:1px solid var(--line);border-radius:22px;padding:18px 16px;text-align:center;position:relative;overflow:hidden}
  .hero h1{font-size:22px;font-weight:700}
  .hero .sub{color:var(--muted);font-size:13px;margin-top:4px}
  .live{display:inline-flex;align-items:center;gap:6px;background:rgba(18,185,129,.14);border:1px solid rgba(18,185,129,.4);color:#7defb6;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;margin-top:10px}
  .live .dot{width:6px;height:6px;background:#12b981;border-radius:50%;animation:pulse 1.4s infinite}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:16px;margin-top:14px}
  .card h3{font-size:14px;color:var(--gold);letter-spacing:.5px;text-transform:uppercase;margin-bottom:10px;font-weight:700}
  .row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line);font-size:14px}
  .row:last-child{border-bottom:0}
  .row .k{color:var(--muted)}
  .row .v{font-weight:600;text-align:right;max-width:60%;word-break:break-word}

  .progress{display:flex;justify-content:space-between;align-items:flex-start;position:relative;margin:8px 4px 4px}
  .progress::before{content:"";position:absolute;top:14px;left:8%;right:8%;height:2px;background:rgba(255,255,255,.1);z-index:0}
  .pstep{position:relative;z-index:1;flex:1;text-align:center}
  .pstep .cir{width:30px;height:30px;border-radius:50%;background:#0a3323;border:2px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:13px;color:#7a9689}
  .pstep.done .cir{background:var(--gold);border-color:var(--gold);color:#0a3323;font-weight:800}
  .pstep.active .cir{background:transparent;border-color:var(--gold);color:var(--gold);box-shadow:0 0 0 4px rgba(212,169,74,.15)}
  .pstep .lbl{font-size:10px;color:var(--muted);margin-top:6px;line-height:1.2}
  .pstep.done .lbl,.pstep.active .lbl{color:var(--text)}

  .qrbox{text-align:center}
  .qrbox img{width:220px;height:220px;border-radius:14px;background:#fff;padding:8px;display:block;margin:8px auto}
  .qrhint{color:var(--muted);font-size:12px;margin-top:6px}

  .upload{margin-top:10px}
  .upload label{display:block;font-size:12px;color:var(--muted);margin:8px 0 4px}
  .upload input[type=text],.upload input[type=file]{width:100%;background:#0a3323;border:1px solid var(--line);color:var(--text);border-radius:10px;padding:10px 12px;font:inherit}
  .btn{display:inline-block;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#0a3323;border:0;border-radius:12px;padding:12px 18px;font-weight:700;font-family:'Sora';cursor:pointer;width:100%;margin-top:12px}
  .btn:disabled{opacity:.6}

  .banner{border-radius:14px;padding:12px 14px;font-size:13px;text-align:center;margin-top:10px;font-weight:600}
  .banner.wait{background:rgba(212,169,74,.14);border:1px solid rgba(212,169,74,.4);color:var(--gold2);animation:pulse 2s infinite}
  .banner.ok{background:rgba(18,185,129,.14);border:1px solid rgba(18,185,129,.4);color:#7defb6}
  .banner.err{background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.4);color:#fca5a5}

  .otp{background:linear-gradient(135deg,#0f5a42,#0a3d2d);border:1px solid rgba(212,169,74,.35);border-radius:16px;padding:14px;text-align:center;margin-top:12px}
  .otp .lbl{font-size:11px;color:var(--gold);letter-spacing:2px;text-transform:uppercase}
  .otp .code{font-family:'Sora';font-size:34px;font-weight:800;letter-spacing:8px;color:var(--gold2);margin-top:4px}

  .callbar{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:var(--gold);color:#0a3323;padding:12px 22px;border-radius:999px;font-weight:700;font-family:'Sora';box-shadow:0 8px 24px rgba(0,0,0,.35);z-index:20}
  .thanks{text-align:center;padding:18px;color:var(--muted);font-size:13px}
  .thanks .seal{width:64px;height:64px;border-radius:50%;background:linear-gradient(180deg,var(--gold2),var(--gold));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;color:#0a3323;font-size:32px;font-weight:800}
  .hidden{display:none!important}
  .proofimg{max-width:100%;border-radius:10px;margin-top:8px;border:1px solid var(--line)}
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <a class="chip" href="orders.php">‹</a>
    <div class="oid">#<?php echo h($orderCode); ?></div>
    <?php if (!empty($riderPhone) && !$isDelivered): ?>
      <a class="chip" href="tel:<?php echo h($riderPhone); ?>" title="Call rider">📞</a>
    <?php else: ?>
      <span class="chip">⛽</span>
    <?php endif; ?>
  </div>

  <div class="hero">
    <h1 id="heroTitle"><?php echo $isDelivered ? 'Delivered' : ($isCancelled ? 'Cancelled' : 'UPI Petrol Order'); ?></h1>
    <div class="sub" id="heroSub"><?php echo h($fuelType); ?> · <?php echo h($quantity); ?> L</div>
    <div class="live"><span class="dot"></span> LIVE TRACKING</div>
  </div>

  <div class="card">
    <h3>Order Progress</h3>
    <div class="progress" id="progressBar">
      <?php
      $labels = array('Placed','Accepted','Picked Up','In Transit','Delivered');
      for ($i=1;$i<=5;$i++):
        $cls = $isDelivered ? 'done' : ($i < $step ? 'done' : ($i === $step ? 'active' : ''));
        $icon = ($isDelivered || $i < $step) ? '✓' : $i;
      ?>
      <div class="pstep <?php echo $cls; ?>" data-step="<?php echo $i; ?>">
        <div class="cir"><?php echo $icon; ?></div>
        <div class="lbl"><?php echo $labels[$i-1]; ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Payment Section -->
  <div class="card" id="paymentCard">
    <h3>UPI Payment</h3>

    <?php if ($uploadMsg): ?><div class="banner ok"><?php echo h($uploadMsg); ?></div><?php endif; ?>
    <?php if ($uploadErr): ?><div class="banner err"><?php echo h($uploadErr); ?></div><?php endif; ?>

    <!-- State 1: Not paid, no proof yet -> show QR + upload -->
    <div id="stateUnpaid" class="<?php echo ($paymentVerified || $paymentProof) ? 'hidden' : ''; ?>">
      <div class="qrbox">
        <img src="<?php echo h($qrPath); ?>" alt="UPI QR" onerror="this.style.display='none'">
        <div class="qrhint">Scan the QR and pay <strong><?php echo money($totalAmount); ?></strong>. Then upload the screenshot below.</div>
      </div>
      <form class="upload" method="post" enctype="multipart/form-data">
        <label>UTR / Transaction ID</label>
        <input type="text" name="utr" placeholder="e.g. 458920123456" required>
        <label>Payment Screenshot</label>
        <input type="file" name="proof" accept="image/*" required>
        <button class="btn" type="submit" name="upload_proof" value="1">Submit Payment Proof</button>
      </form>
    </div>

    <!-- State 2: Proof submitted, awaiting approval -->
    <div id="stateAwaiting" class="<?php echo ($paymentProof && !$paymentVerified) ? '' : 'hidden'; ?>">
      <div class="banner wait">⏳ Waiting for admin approval…</div>
      <?php if ($paymentProof): ?>
        <div class="row"><span class="k">UTR</span><span class="v"><?php echo h($paymentUtr); ?></span></div>
        <img class="proofimg" src="<?php echo h($paymentProof); ?>" alt="Proof">
      <?php endif; ?>
    </div>

    <!-- State 3: Verified -->
    <div id="statePaid" class="<?php echo $paymentVerified ? '' : 'hidden'; ?>">
      <div class="banner ok">✓ Payment Verified</div>
      <?php if ($paymentUtr): ?><div class="row"><span class="k">UTR</span><span class="v"><?php echo h($paymentUtr); ?></span></div><?php endif; ?>
    </div>
  </div>

  <!-- OTP cards (only after payment verified) -->
  <div class="otp <?php echo $showStartOtp ? '' : 'hidden'; ?>" id="startOtpCard">
    <div class="lbl">Start OTP</div>
    <div class="code" id="startOtpCode"><?php echo h($otp); ?></div>
  </div>
  <div class="otp <?php echo $showDeliveryOtp ? '' : 'hidden'; ?>" id="deliveryOtpCard">
    <div class="lbl">Delivery OTP</div>
    <div class="code" id="deliveryOtpCode"><?php echo h($otp); ?></div>
  </div>

  <div class="card">
    <h3>Delivery Details</h3>
    <div class="row"><span class="k">Fuel</span><span class="v" id="fuelType"><?php echo h($fuelType); ?></span></div>
    <div class="row"><span class="k">Quantity</span><span class="v" id="quantity"><?php echo h($quantity); ?> L</span></div>
    <div class="row"><span class="k">Deliver To</span><span class="v" id="address"><?php echo h($address); ?></span></div>
  </div>

  <div class="card">
    <h3>Payment Summary</h3>
    <div class="row"><span class="k">Fuel Amount</span><span class="v" id="productAmount"><?php echo money($productAmount); ?></span></div>
    <div class="row"><span class="k">Delivery Fare</span><span class="v" id="deliveryFare"><?php echo money($deliveryFare); ?></span></div>
    <div class="row"><span class="k">Total</span><span class="v" id="totalAmount" style="color:var(--gold2);font-size:16px"><?php echo money($totalAmount); ?></span></div>
    <div class="row"><span class="k">Method</span><span class="v">UPI</span></div>
    <div class="row"><span class="k">Status</span><span class="v" id="payStatus"><?php echo h(ucfirst($paymentStatus)); ?></span></div>
  </div>

  <div class="card" id="riderCard" style="<?php echo (!$riderName || $isDelivered) ? 'display:none' : ''; ?>">
    <h3>Your Rider</h3>
    <div class="row"><span class="k">Name</span><span class="v" id="riderName"><?php echo h($riderName); ?></span></div>
    <div class="row"><span class="k">Phone</span><span class="v" id="riderPhone"><?php echo h($riderPhone); ?></span></div>
  </div>

  <?php if ($isDelivered): ?>
  <div class="thanks">
    <div class="seal">✓</div>
    <div style="font-family:'Sora';font-size:16px;color:var(--text);font-weight:700">Thank you for choosing <?php echo h(SITE_NAME); ?></div>
    <div style="margin-top:4px">We hope to serve you again soon.</div>
  </div>
  <?php endif; ?>

  <?php if (!empty($riderPhone) && !$isDelivered): ?>
    <a class="callbar" href="tel:<?php echo h($riderPhone); ?>">📞 Call Rider</a>
  <?php endif; ?>

</div>

<script>
(function(){
  var ORDER_CODE = <?php echo json_encode($orderCode); ?>;
  var CURRENT_STATUS = <?php echo json_encode($status); ?>;
  var CURRENT_PAY    = <?php echo json_encode($paymentStatus); ?>;
  var POLL_MS = 2000;
  var inFlight = false;

  function isTerminal(s){ s=(s||'').toLowerCase(); return s==='delivered'||s==='completed'||s==='cancelled'||s==='canceled'; }

  function stepFromStatus(s){
    s=(s||'').toLowerCase();
    if(['placed','pending','new'].indexOf(s)>=0) return 1;
    if(['accepted','confirmed','assigned'].indexOf(s)>=0) return 2;
    if(['picked_up','picked','pickup'].indexOf(s)>=0) return 3;
    if(['in_transit','transit','on_the_way','out_for_delivery'].indexOf(s)>=0) return 4;
    if(['delivered','completed'].indexOf(s)>=0) return 5;
    return 1;
  }

  function applyStep(status){
    var step = stepFromStatus(status);
    var delivered = (status==='delivered'||status==='completed');
    var nodes = document.querySelectorAll('#progressBar .pstep');
    nodes.forEach(function(n){
      var i = parseInt(n.getAttribute('data-step'),10);
      n.classList.remove('done','active');
      var cir = n.querySelector('.cir');
      if(delivered || i < step){ n.classList.add('done'); cir.textContent='✓'; }
      else if(i === step){ n.classList.add('active'); cir.textContent = i; }
      else { cir.textContent = i; }
    });
  }

  function money(v){
    var n = Number(v||0);
    return '<?php echo addslashes(CURRENCY_SYMBOL); ?>' + n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  }

  function setText(id,v){ var el=document.getElementById(id); if(el && v!=null) el.textContent=v; }
  function show(id,on){ var el=document.getElementById(id); if(!el) return; el.classList[on?'remove':'add']('hidden'); }

  function updatePaymentUI(paymentStatus, hasProof){
    var verified = (paymentStatus==='paid'||paymentStatus==='approved'||paymentStatus==='verified');
    show('stateUnpaid',   !verified && !hasProof);
    show('stateAwaiting', !verified && hasProof);
    show('statePaid',     verified);
    return verified;
  }

  function poll(){
    if(inFlight) return;
    inFlight = true;
    fetch('get_order_status.php?order_id=' + encodeURIComponent(ORDER_CODE) + '&t=' + Date.now(), {cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d || !d.status) return;
        var newStatus = (d.status||'').toLowerCase();
        var newPay    = (d.paymentStatus||d.payment_status||'').toLowerCase();
        var hasProof  = !!(d.paymentProof || d.payment_proof || d.proofSubmitted);

        // Payment state
        var verified = updatePaymentUI(newPay, hasProof);
        setText('payStatus', newPay ? newPay.charAt(0).toUpperCase()+newPay.slice(1) : 'Unpaid');

        // Progress
        applyStep(newStatus);

        // OTP visibility (only if payment verified)
        var step = stepFromStatus(newStatus);
        show('startOtpCard',    verified && (step===2 || step===3));
        show('deliveryOtpCard', verified && step===4);
        if(d.otp){
          setText('startOtpCode', d.otp);
          setText('deliveryOtpCode', d.otp);
        }

        // Rider
        if(d.riderName){ setText('riderName', d.riderName); }
        if(d.riderPhone){ setText('riderPhone', d.riderPhone); }
        var riderCard = document.getElementById('riderCard');
        if(riderCard && d.riderName && !isTerminal(newStatus)) riderCard.style.display='';

        // Amounts
        if(d.productAmount!=null) setText('productAmount', money(d.productAmount));
        if(d.deliveryFare!=null)  setText('deliveryFare',  money(d.deliveryFare));
        if(d.totalAmount!=null)   setText('totalAmount',   money(d.totalAmount));

        // Full reload on terminal transition or payment approval flip to render seal/footer
        var terminalFlip = isTerminal(newStatus) !== isTerminal(CURRENT_STATUS);
        var payFlip = (verified && CURRENT_PAY!=='paid' && CURRENT_PAY!=='approved' && CURRENT_PAY!=='verified');
        if(terminalFlip || payFlip){
          window.location.reload();
          return;
        }
        CURRENT_STATUS = newStatus;
        CURRENT_PAY = newPay;
      })
      .catch(function(){})
      .then(function(){
        inFlight = false;
        if(!isTerminal(CURRENT_STATUS)) setTimeout(poll, POLL_MS);
      });
  }

  if(!isTerminal(CURRENT_STATUS)) setTimeout(poll, POLL_MS);
})();
</script>
</body>
</html>
