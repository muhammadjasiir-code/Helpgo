<?php
// =============================================================================
// grocery_orders.php — Grocery delivery tracking (Emerald Prestige theme)
// Auto refreshes every 2 seconds until the order is finished.
// =============================================================================

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

require_once __DIR__ . '/config.php';

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    if (function_exists('redirect')) { redirect(SITE_URL . 'login.php'); }
    header('Location: login.php'); exit;
}

// ---------- helpers ----------
function first_value($row, $keys, $default = '') {
    if (!is_array($row)) return $default;
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return $default;
}
function status_step($status) {
    $s = strtolower(trim((string)$status));
    $map = array(
        'pending'=>1,'placed'=>1,'new'=>1,
        'accepted'=>2,'confirmed'=>2,'assigned'=>2,'shopping'=>2,
        'picked_up'=>3,'pickedup'=>3,'picked'=>3,'ready'=>3,'bill_uploaded'=>3,
        'in_transit'=>4,'transit'=>4,'on_the_way'=>4,'ontheway'=>4,'enroute'=>4,'out_for_delivery'=>4,
        'delivered'=>5,'completed'=>5,'done'=>5,
        'cancelled'=>0,'canceled'=>0,'rejected'=>0,
    );
    return isset($map[$s]) ? $map[$s] : 1;
}
function status_headline($step){
    $h = array(
        1 => array('Order Placed',   'We received your grocery order'),
        2 => array('Rider Shopping', 'A rider is shopping your items'),
        3 => array('Items Packed',   'Your groceries are packed'),
        4 => array('On the Way',     'Your groceries are coming to you'),
        5 => array('Delivered',      'Your groceries have been delivered'),
    );
    return isset($h[$step]) ? $h[$step] : array('Order Placed','We received your order');
}

// ---------- inputs ----------
$debug     = isset($_GET['debug']);
$rawOrder  = isset($_GET['order_id']) ? trim($_GET['order_id']) : (isset($_GET['id']) ? trim($_GET['id']) : '');
$orderIdNum = ctype_digit($rawOrder) ? (int)$rawOrder : 0;

$sessionUserId = 0;
foreach (array('user_id','id','uid','userId') as $k) {
    if (!empty($_SESSION[$k])) { $sessionUserId = (int)$_SESSION[$k]; break; }
}

if ($rawOrder === '') {
    die('<div style="padding:24px;font-family:sans-serif;">Missing order id. <a href="' . SITE_URL . 'orders.php">Back to orders</a></div>');
}

// ---------- lookup ----------
$safeRaw = mysqli_real_escape_string($conn, $rawOrder);
$sql = "SELECT * FROM orders WHERE order_id = '" . $safeRaw . "'";
if ($orderIdNum > 0) { $sql .= " OR id = " . $orderIdNum; }
$sql .= " LIMIT 1";
$res = mysqli_query($conn, $sql);

if ($debug) {
    echo '<pre style="background:#111;color:#0ff;padding:12px;">SQL: '.htmlspecialchars($sql).' err='.htmlspecialchars(mysqli_error($conn)).' rows='.($res?mysqli_num_rows($res):'X').'</pre>';
}
if (!$res || mysqli_num_rows($res) === 0) {
    die('<div style="padding:24px;font-family:sans-serif;">Order #' . htmlspecialchars($rawOrder) . ' not found. <a href="' . SITE_URL . 'orders.php">Back</a></div>');
}
$order = mysqli_fetch_assoc($res);

$rowUserId = 0;
foreach (array('user_id','customer_id','uid') as $k) {
    if (isset($order[$k])) { $rowUserId = (int)$order[$k]; break; }
}
if ($rowUserId > 0 && $sessionUserId > 0 && $rowUserId !== $sessionUserId) {
    die('<div style="padding:24px;font-family:sans-serif;">This order does not belong to your account. <a href="' . SITE_URL . 'orders.php">Back</a></div>');
}

// Route wrong service to petrol page
$svc = '';
foreach (array('service_type','service','type','category') as $k) {
    if (!empty($order[$k])) { $svc = strtolower(trim($order[$k])); break; }
}
if ($svc !== '' && strpos($svc,'grocery')===false && strpos($svc,'shop')===false) {
    header('Location: ' . SITE_URL . 'petrol_orders.php?order_id=' . urlencode($rawOrder)); exit;
}

// ---------- fields ----------
$status    = strtolower(first_value($order, array('status','order_status'), 'pending'));
$step      = status_step($status);
$hl        = status_headline($step);
$address   = first_value($order, array('delivery_address','address','user_address','drop_address','location'), '');
$shopName  = first_value($order, array('shop_name','store_name','shop'), '');
$groceryList = first_value($order, array('grocery_list','items','products','list','item_list'), '');
$billImage = first_value($order, array('bill_image','bill','bill_photo','invoice_image'), '');
$billUploadedAt = first_value($order, array('bill_uploaded_at','bill_time','bill_at'), '');
$product   = first_value($order, array('product_amount','subtotal','item_amount'), 0);
$delivery  = first_value($order, array('delivery_fare','delivery_fee','delivery_charge','shipping'), 0);
$amount    = first_value($order, array('total_amount','amount','total','grand_total','price'), 0);
$deliverOtp= first_value($order, array('delivery_otp','end_otp','otp_delivery','otp'), '');
$riderName = first_value($order, array('rider_name','delivery_boy_name','courier_name'), '');
$riderPhone= first_value($order, array('rider_phone','delivery_boy_phone','courier_phone','rider_mobile'), '');
$payMethod = strtolower(first_value($order, array('payment_method','pay_method','payment_mode'), 'cash'));
$payStatus = strtolower(first_value($order, array('payment_status','pay_status'), 'pending'));
$orderCode = first_value($order, array('order_id','order_code','order_number','order_ref'), 'HLP' . str_pad(isset($order['id'])?$order['id']:0, 10, '0', STR_PAD_LEFT));

$currency  = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
$siteName  = defined('SITE_NAME') ? SITE_NAME : 'HelpGo';
$uploadUrl = defined('UPLOAD_URL') ? UPLOAD_URL : 'uploads/';

$isTerminal = in_array($status, array('delivered','completed','done','cancelled','canceled','rejected'));
$showPay = ($payMethod === 'upi' && $payStatus === 'pending' && !empty($billImage));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (!$isTerminal): ?>
<meta http-equiv="refresh" content="2">
<?php endif; ?>
<title>Grocery Order #<?php echo htmlspecialchars($orderCode); ?> — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
  :root{
    --bg:#081810; --card:#0f2d1e; --card2:#0d2618;
    --line:rgba(240,200,96,.18); --line-2:rgba(240,200,96,.10);
    --gold:#f0c860; --gold-2:#f5b840; --ink:#0a1f14;
    --text:#f0ede4; --muted:#8ea89b;
    --green:#1a5c3e; --green-2:#0f3d2a; --live:#39d16a;
  }
  html,body{background:var(--bg);color:var(--text);font-family:'Manrope',system-ui,sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased}
  .wrap{max-width:480px;margin:0 auto;padding:14px 14px 40px;position:relative}
  .topbar{display:flex;align-items:center;gap:12px;padding:10px 2px 18px}
  .chip{width:40px;height:40px;border-radius:50%;background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--gold);border:1px solid var(--line);text-decoration:none;font-size:18px;font-weight:700}
  .chip:active{transform:scale(.94)}
  .topbar .title{flex:1;font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:#fff}

  .card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:18px;margin-bottom:14px;position:relative;overflow:hidden}
  .card-lbl{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700;text-transform:uppercase;margin-bottom:14px}

  .hero-top{display:flex;justify-content:space-between;align-items:flex-start}
  .oid-lbl{font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700}
  .oid{font-family:'Sora',sans-serif;font-size:15px;color:#fff;font-weight:700;margin-top:2px;letter-spacing:.5px}
  .live-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(57,209,106,.12);border:1px solid rgba(57,209,106,.35);color:var(--live);padding:5px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:1.5px}
  .live-pill .dot{width:7px;height:7px;border-radius:50%;background:var(--live);box-shadow:0 0 8px var(--live);animation:pulse 1.4s infinite}
  @keyframes pulse{0%,100%{opacity:.5}50%{opacity:1}}
  .hero-headline{font-family:'Sora',sans-serif;font-size:32px;font-weight:800;color:#fff;line-height:1.05;margin-top:16px}
  .hero-sub{color:var(--muted);font-size:13.5px;margin-top:8px;line-height:1.4}

  .progress{position:relative;margin-top:22px;display:flex;justify-content:space-between;padding:0 4px}
  .progress::before{content:"";position:absolute;top:16px;left:20px;right:20px;height:2px;background:rgba(240,200,96,.18);z-index:0}
  .progress .fill{position:absolute;top:16px;left:20px;height:2px;background:var(--gold);z-index:1;transition:width .5s ease;width:0}
  .p-step{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:10px;flex:1;min-width:0}
  .p-dot{width:32px;height:32px;border-radius:50%;background:var(--green-2);border:2px solid rgba(240,200,96,.25);display:flex;align-items:center;justify-content:center;color:transparent;font-size:14px;font-weight:800}
  .p-step.done .p-dot{background:var(--gold);border-color:var(--gold);color:var(--ink)}
  .p-step.done .p-dot::before{content:"✓"}
  .p-step.active .p-dot{background:transparent;border-color:var(--gold);box-shadow:0 0 0 5px rgba(240,200,96,.18),0 0 16px rgba(240,200,96,.55);position:relative}
  .p-step.active .p-dot::after{content:"";width:10px;height:10px;border-radius:50%;background:#fff;position:absolute}
  .p-step small{font-size:9.5px;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;text-align:center;line-height:1.1;max-width:70px}
  .p-step.active small{color:var(--gold)}
  .p-step.done small{color:#d8cfb8}

  .prow{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px dashed var(--line-2);font-size:14px}
  .prow:last-of-type{border-bottom:none}
  .prow .k{color:#c9d6cf}
  .prow .v{font-family:'Sora',sans-serif;color:#fff;font-weight:700}
  .total-row{display:flex;justify-content:space-between;padding-top:14px;margin-top:6px;border-top:1px solid var(--line-2)}
  .total-row .k{font-size:15px;color:#f0ede4;font-weight:600}
  .total-row .v{font-family:'Sora',sans-serif;font-size:22px;color:var(--gold);font-weight:800}
  .method-pill{border:1px solid var(--gold);color:var(--gold);padding:5px 14px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:1.5px}
  .pay-pill{padding:5px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:1px;border:1px solid var(--line)}
  .pay-pill.paid{background:rgba(57,209,106,.12);color:var(--live);border-color:rgba(57,209,106,.35)}
  .pay-pill.pending{background:rgba(240,200,96,.10);color:var(--gold);border-color:rgba(240,200,96,.35)}

  .list-box{background:var(--card2);border:1px solid var(--line-2);border-radius:14px;padding:14px;white-space:pre-wrap;font-size:14px;color:#e5e0d0;line-height:1.55;max-height:260px;overflow:auto}
  .shop-badge{display:inline-flex;align-items:center;gap:8px;background:var(--green-2);border:1px solid var(--line);color:#fff;padding:8px 14px;border-radius:999px;font-weight:700;margin-bottom:12px;font-size:13px}

  .bill-btn,.pay-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:800;font-family:'Sora',sans-serif;letter-spacing:.5px}
  .bill-btn{background:var(--card2);color:var(--gold);border:1px solid var(--gold)}
  .pay-btn{background:linear-gradient(135deg,var(--gold),var(--gold-2));color:var(--ink);width:100%;margin-top:8px;font-size:15px;box-shadow:0 8px 24px rgba(240,200,96,.25)}
  .pay-note{text-align:center;margin-top:10px;color:var(--muted);font-size:12px}

  .placeholder{text-align:center;color:var(--muted);font-size:13px;padding:22px;border:1px dashed var(--line-2);border-radius:14px}

  .addr{display:flex;gap:12px;align-items:flex-start;margin-top:14px;padding-top:16px;border-top:1px dashed var(--line-2)}
  .addr-ic{width:38px;height:38px;border-radius:50%;background:var(--green-2);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:16px;flex:0 0 auto}
  .addr .k{font-size:12px;color:var(--muted);margin-bottom:2px}
  .addr .v{font-size:14px;color:#fff;line-height:1.4}

  .rider{display:flex;align-items:center;gap:14px}
  .avatar{width:52px;height:52px;border-radius:50%;background:var(--gold);color:var(--ink);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;font-family:'Sora',sans-serif}
  .rider-info{flex:1;min-width:0}
  .rider-info .n{font-family:'Sora',sans-serif;font-weight:700;font-size:16px;color:#fff}
  .rider-info .p{font-size:13px;color:var(--muted);margin-top:2px}
  .r-btn{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;background:var(--green);color:#fff;font-size:18px}

  .otp{background:linear-gradient(135deg,#1a5c46,#0f3d2e);border:1px solid var(--gold);border-radius:16px;padding:16px;text-align:center;margin-bottom:14px}
  .otp .lbl{font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700}
  .otp .code{font-family:'Sora',sans-serif;font-size:34px;color:#fff;letter-spacing:10px;font-weight:800;margin-top:6px}

  .lightbox{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
  .lightbox.on{display:flex}
  .lightbox img{max-width:100%;max-height:90vh;border-radius:12px;border:1px solid var(--gold)}
  .lightbox .close{position:absolute;top:20px;right:20px;color:#fff;font-size:26px;background:rgba(0,0,0,.6);width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:1px solid var(--gold)}

  .thanks{display:none;text-align:center;padding:22px;background:linear-gradient(135deg,#1a5c46,#0f3d2e);border:1px solid var(--gold);border-radius:18px}
  .thanks.on{display:block}
  .thanks h4{font-family:'Sora',sans-serif;color:#fff;font-size:18px;margin-bottom:6px}
  .thanks p{color:#d8cfb8;font-size:13px}
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <a href="orders.php" class="chip">‹</a>
    <div class="title">Grocery Tracking</div>
    <a href="home.php" class="chip">⌂</a>
  </div>

  <!-- HERO -->
  <div class="card">
    <div class="hero-top">
      <div>
        <div class="oid-lbl">ORDER ID</div>
        <div class="oid">#<?php echo htmlspecialchars($orderCode); ?></div>
      </div>
      <div class="live-pill"><span class="dot"></span> LIVE</div>
    </div>
    <div class="hero-headline" id="hlLabel"><?php echo htmlspecialchars($hl[0]); ?></div>
    <div class="hero-sub" id="hlSub"><?php echo htmlspecialchars($hl[1]); ?></div>

    <div class="progress">
      <div class="fill" id="progFill" style="width: <?php echo $step===5?100:max(0,($step-1)/4*100); ?>%"></div>
      <?php
      $labels = array(1=>'Placed',2=>'Shopping',3=>'Packed',4=>'On Way',5=>'Delivered');
      foreach ($labels as $i=>$lbl):
        $cls = ($i < $step || $step===5) ? 'done' : (($i===$step) ? 'active' : '');
      ?>
        <div class="p-step <?php echo $cls; ?>" data-s="<?php echo $i; ?>">
          <div class="p-dot"></div><small><?php echo $lbl; ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Delivery OTP (Cash, or UPI paid) -->
  <?php if ($deliverOtp && $step>=3 && $step<5 && ($payMethod==='cash' || $payStatus==='paid')): ?>
  <div class="otp" id="otpCard">
    <div class="lbl">DELIVERY OTP</div>
    <div class="code" id="deliverOtp"><?php echo htmlspecialchars($deliverOtp); ?></div>
    <div style="color:#d8cfb8;font-size:12px;margin-top:6px">Share with rider on delivery</div>
  </div>
  <?php endif; ?>

  <!-- SHOP + LIST -->
  <div class="card">
    <div class="card-lbl">🛒 Your Grocery List</div>
    <?php if ($shopName): ?>
      <div class="shop-badge" id="shopBadge">🏪 <span id="shopName"><?php echo htmlspecialchars($shopName); ?></span></div>
    <?php else: ?>
      <div class="shop-badge" id="shopBadge" style="display:none">🏪 <span id="shopName"></span></div>
    <?php endif; ?>
    <div class="list-box" id="groceryList"><?php echo htmlspecialchars($groceryList ?: 'No list provided'); ?></div>
  </div>

  <!-- BILL + PRICING -->
  <div class="card" id="billCard">
    <div class="card-lbl">🧾 Bill & Payment</div>
    <div id="billBody">
      <?php if (!empty($billImage)): ?>
        <div style="text-align:center;margin-bottom:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <a href="javascript:void(0)" onclick="openBill()" class="bill-btn">👁 View Bill</a>
          <a href="<?php echo htmlspecialchars($uploadUrl.'bills/'.$billImage); ?>" download="bill-<?php echo htmlspecialchars($orderCode); ?><?php echo htmlspecialchars(strrchr($billImage,'.') ?: '.jpg'); ?>" class="bill-btn" id="billDownloadBtn">⬇ Download Bill</a>
        </div>
        <div class="prow"><span class="k">Product Amount</span><span class="v" id="productAmount"><?php echo $currency.number_format((float)$product,2); ?></span></div>
        <div class="prow"><span class="k">Delivery Fare</span><span class="v" id="deliveryFare"><?php echo $currency.number_format((float)$delivery,2); ?></span></div>
        <div class="prow"><span class="k">Payment Method</span><span class="method-pill"><?php echo strtoupper($payMethod); ?></span></div>
        <div class="prow"><span class="k">Payment Status</span>
          <span class="pay-pill <?php echo $payStatus==='paid'?'paid':'pending'; ?>" id="paymentStatus"><?php echo ucfirst($payStatus); ?></span>
        </div>
        <div class="total-row"><span class="k">Total</span><span class="v" id="totalAmount"><?php echo $currency.number_format((float)$amount,2); ?></span></div>
        <div id="payArea">
        <?php if ($showPay): ?>
          <a href="pay.php?order_id=<?php echo urlencode($orderCode); ?>" class="pay-btn">💳 Pay <?php echo $currency.number_format((float)$amount,2); ?> Now</a>
        <?php elseif ($payMethod==='upi' && $payStatus==='paid'): ?>
          <div class="pay-note" style="color:var(--live);font-weight:700;margin-top:14px">✓ Payment Confirmed</div>
        <?php elseif ($payMethod==='cash'): ?>
          <div class="pay-note" style="margin-top:14px">💵 Pay <?php echo $currency.number_format((float)$amount,2); ?> in cash on delivery</div>
        <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="placeholder" id="billPlaceholder">
          🧾 Waiting for rider to shop items and upload bill…
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ADDRESS -->
  <div class="card">
    <div class="card-lbl">📍 Deliver To</div>
    <div class="addr">
      <div class="addr-ic">📍</div>
      <div>
        <div class="k">Address</div>
        <div class="v"><?php echo htmlspecialchars($address ?: 'N/A'); ?></div>
      </div>
    </div>
  </div>

  <!-- RIDER -->
  <?php if ($riderName): ?>
  <div class="card" id="riderCard">
    <div class="card-lbl">👤 Your Rider</div>
    <div class="rider">
      <div class="avatar" id="riderAvatar"><?php echo htmlspecialchars(strtoupper(substr($riderName,0,1))); ?></div>
      <div class="rider-info">
        <div class="n" id="riderName"><?php echo htmlspecialchars($riderName); ?></div>
        <div class="p" id="riderPhone"><?php echo htmlspecialchars($riderPhone); ?></div>
      </div>
      <?php if ($riderPhone && $step<5): ?>
        <a href="tel:<?php echo htmlspecialchars($riderPhone); ?>" class="r-btn" id="callBtn">📞</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- THANKS -->
  <div class="thanks <?php echo $step===5?'on':''; ?>" id="thanks">
    <h4>Thank you for choosing <?php echo htmlspecialchars($siteName); ?>!</h4>
    <p>We hope to serve you again soon.</p>
  </div>

</div>

<!-- Bill lightbox -->
<div class="lightbox" id="billLightbox">
  <div class="close" onclick="closeBill()">✕</div>
  <img id="billImg" src="" alt="Bill">
  <a id="billDownloadLink" href="#" download class="bill-btn" style="position:absolute;bottom:24px;left:50%;transform:translateX(-50%)">⬇ Download Bill</a>
</div>

<script>
(function(){
  var currentStatus = <?php echo json_encode($status); ?>;
  var orderId = <?php echo json_encode((string)$orderCode); ?>;
  var currency = <?php echo json_encode($currency); ?>;
  var uploadUrl = <?php echo json_encode($uploadUrl); ?>;
  var currentBill = <?php echo json_encode($billImage); ?>;
  var currentPayStatus = <?php echo json_encode($payStatus); ?>;
  var payMethod = <?php echo json_encode($payMethod); ?>;

  window.openBill = function(){
    if (!currentBill) return;
    var url = uploadUrl + 'bills/' + currentBill;
    document.getElementById('billImg').src = url;
    var dl = document.getElementById('billDownloadLink');
    if (dl){ dl.href = url; dl.setAttribute('download','bill-'+orderId); }
    document.getElementById('billLightbox').classList.add('on');
  };
  window.closeBill = function(){ document.getElementById('billLightbox').classList.remove('on'); };

  function fmt(n){ n=Number(n)||0; return currency + n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }

  function isTerminal(s){ s=String(s||'').toLowerCase(); return s==='delivered'||s==='completed'||s==='done'||s==='cancelled'||s==='canceled'||s==='rejected'; }

  var inFlight = false;
  function poll(){
    if (inFlight) return; inFlight = true;
    fetch('get_order_status.php?order_id=' + encodeURIComponent(orderId) + '&_=' + Date.now(), {cache:'no-store', credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        inFlight = false; if (!d || d.error) return;

        // Status change -> reload for full re-render
        if (d.status && String(d.status).toLowerCase() !== currentStatus){
          window.location.reload(); return;
        }

        if (d.shopName){
          var sb=document.getElementById('shopBadge'); if(sb) sb.style.display='';
          var sn=document.getElementById('shopName'); if(sn) sn.textContent=d.shopName;
        }
        if (d.groceryList){ var gl=document.getElementById('groceryList'); if(gl) gl.textContent=d.groceryList; }

        // Bill just uploaded -> reload to render pricing rows
        if (d.billImage && !currentBill){ window.location.reload(); return; }

        if (d.productAmount!==undefined){ var pa=document.getElementById('productAmount'); if(pa) pa.textContent=fmt(d.productAmount); }
        if (d.deliveryFare!==undefined){ var df=document.getElementById('deliveryFare'); if(df) df.textContent=fmt(d.deliveryFare); }
        if (d.totalAmount!==undefined){ var ta=document.getElementById('totalAmount'); if(ta) ta.textContent=fmt(d.totalAmount); }

        if (d.paymentStatus){
          var v = String(d.paymentStatus).toLowerCase();
          var ps = document.getElementById('paymentStatus');
          if (ps){ ps.textContent = v.charAt(0).toUpperCase()+v.slice(1); ps.className='pay-pill '+(v==='paid'?'paid':'pending'); }
          // Payment newly confirmed -> reload so OTP appears
          if (v==='paid' && currentPayStatus!=='paid'){ window.location.reload(); return; }
        }

        if (d.riderName){
          var rc=document.getElementById('riderCard');
          if (rc){
            var rn=document.getElementById('riderName'); if(rn) rn.textContent=d.riderName;
            var av=document.getElementById('riderAvatar'); if(av) av.textContent=(d.riderName[0]||'?').toUpperCase();
          }
        }
        if (d.riderPhone){
          var rp=document.getElementById('riderPhone'); if(rp) rp.textContent=d.riderPhone;
          var cb=document.getElementById('callBtn'); if(cb) cb.href='tel:'+d.riderPhone;
        }
      })
      .catch(function(){ inFlight=false; });
  }

  if (!isTerminal(currentStatus)){
    poll();
    setInterval(poll, 2000);
  }
})();
</script>
</body>
</html>
