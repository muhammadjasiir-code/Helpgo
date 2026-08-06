<?php
// =============================================================================
// petrol_orders.php  —  Petrol delivery tracking (Reference-mockup theme)
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
        'accepted'=>2,'confirmed'=>2,'assigned'=>2,
        'picked_up'=>3,'pickedup'=>3,'picked'=>3,'ready'=>3,
        'in_transit'=>4,'transit'=>4,'on_the_way'=>4,'ontheway'=>4,'enroute'=>4,'out_for_delivery'=>4,
        'delivered'=>5,'completed'=>5,'done'=>5,
        'cancelled'=>0,'canceled'=>0,'rejected'=>0,
    );
    return isset($map[$s]) ? $map[$s] : 1;
}
function status_headline($step){
    $h = array(
        1 => array('Order Placed',    'We received your order'),
        2 => array('Order Accepted',  'A rider is being assigned'),
        3 => array('Picked Up',       'Your rider has the fuel'),
        4 => array('On the Way',      'Your fuel is coming to you'),
        5 => array('Delivered',       'Your fuel has been delivered successfully'),
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

if ($debug) {
    echo '<pre style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;font:12px/1.5 monospace;">';
    echo "raw order id: " . htmlspecialchars($rawOrder) . "\n";
    echo "session uid: " . htmlspecialchars($sessionUserId) . "\n";
    echo "SESSION keys: " . htmlspecialchars(implode(', ', array_keys($_SESSION))) . "\n";
    echo "</pre>";
}
if ($rawOrder === '') {
    die('<div style="padding:24px;font-family:sans-serif;">Missing order id. <a href="' . SITE_URL . 'orders.php">Back to orders</a></div>');
}

// ---------- lookup (try string order_id column first, then numeric id) ----------
$safeRaw = mysqli_real_escape_string($conn, $rawOrder);
$sql = "SELECT * FROM orders WHERE order_id = '" . $safeRaw . "'";
if ($orderIdNum > 0) { $sql .= " OR id = " . $orderIdNum; }
$sql .= " LIMIT 1";
$res = mysqli_query($conn, $sql);

if ($debug) {
    echo '<pre style="background:#111;color:#0ff;padding:12px;white-space:pre-wrap;font:12px/1.5 monospace;">';
    echo "SQL: " . htmlspecialchars($sql) . "\n";
    echo "err: " . htmlspecialchars(mysqli_error($conn)) . "\n";
    echo "rows: " . ($res ? mysqli_num_rows($res) : 'query failed') . "\n";
    echo "</pre>";
}
if (!$res || mysqli_num_rows($res) === 0) {
    die('<div style="padding:24px;font-family:sans-serif;">Order #' . htmlspecialchars($rawOrder) . ' not found. <a href="' . SITE_URL . 'orders.php">Back</a></div>');
}
$orderId = $rawOrder;

$order = mysqli_fetch_assoc($res);

$rowUserId = 0;
foreach (array('user_id','customer_id','uid') as $k) {
    if (isset($order[$k])) { $rowUserId = (int)$order[$k]; break; }
}
if ($rowUserId > 0 && $sessionUserId > 0 && $rowUserId !== $sessionUserId) {
    die('<div style="padding:24px;font-family:sans-serif;">This order does not belong to your account. <a href="' . SITE_URL . 'orders.php">Back</a></div>');
}
$svc = '';
foreach (array('service_type','service','type','category') as $k) {
    if (!empty($order[$k])) { $svc = strtolower(trim($order[$k])); break; }
}
if ($svc !== '' && strpos($svc,'petrol')===false && strpos($svc,'fuel')===false) {
    header('Location: ' . SITE_URL . 'grocery_orders.php?order_id=' . $orderId); exit;
}

// ---------- fields ----------
$status    = strtolower(first_value($order, array('status','order_status'), 'pending'));
$step      = status_step($status);
$hl        = status_headline($step);
$quantity  = first_value($order, array('petrol_quantity','quantity','liters','litres','qty','fuel_qty'), '1');
$fuelType  = first_value($order, array('fuel_type','fuel','product'), 'Petrol');
$address   = first_value($order, array('delivery_address','address','user_address','drop_address','location'), '');
$amount    = first_value($order, array('total_amount','amount','total','grand_total','price'), 0);
$delivery  = first_value($order, array('delivery_fare','delivery_fee','delivery_charge','shipping'), 0);
$product   = first_value($order, array('product_amount','subtotal','item_amount'), '');
$startOtp  = first_value($order, array('start_otp','pickup_otp','otp_start'), '');
$deliverOtp= first_value($order, array('delivery_otp','end_otp','otp_delivery','otp'), '');
$riderName = first_value($order, array('rider_name','delivery_boy_name','courier_name'), '');
$riderPhone= first_value($order, array('rider_phone','delivery_boy_phone','courier_phone','rider_mobile'), '');
$payMethod = strtolower(first_value($order, array('payment_method','pay_method','payment_mode'), 'cash'));
$payStatus = strtolower(first_value($order, array('payment_status','pay_status'), 'pending'));
$orderCode = first_value($order, array('order_id','order_code','order_number','order_ref'), 'HLP' . str_pad($orderId, 10, '0', STR_PAD_LEFT));

$currency  = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
$siteName  = defined('SITE_NAME') ? SITE_NAME : 'HelpGo';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (!in_array($status, array('delivered','completed','done','cancelled','canceled','rejected'))): ?>
<meta http-equiv="refresh" content="2">
<?php endif; ?>
<title>Order Tracking #<?php echo $orderId; ?> — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
  :root{
    --bg:#081810; --bg2:#0a1f14;
    --card:#0f2d1e; --card2:#0d2618;
    --line:rgba(240,200,96,.18);
    --line-2:rgba(240,200,96,.10);
    --gold:#f0c860; --gold-2:#f5b840; --gold-soft:#e6c068;
    --ink:#0a1f14;
    --text:#f0ede4; --muted:#8ea89b;
    --green:#1a5c3e; --green-2:#0f3d2a;
    --live:#39d16a;
  }
  html,body{background:var(--bg);color:var(--text);font-family:'Manrope',system-ui,sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased}
  .wrap{max-width:480px;margin:0 auto;padding:14px 14px 40px;position:relative}

  /* --- Top bar --- */
  .topbar{display:flex;align-items:center;gap:12px;padding:10px 2px 18px}
  .chip{width:40px;height:40px;border-radius:50%;background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--gold);border:1px solid var(--line);text-decoration:none;font-size:16px;flex:0 0 auto}
  .chip:active{transform:scale(.94)}
  .topbar .title{flex:1;font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:#fff;letter-spacing:.2px}
  .top-actions{display:flex;gap:10px}

  /* --- Card --- */
  .card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:18px;margin-bottom:14px;position:relative;overflow:hidden}
  .card-lbl{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700;text-transform:uppercase;margin-bottom:14px}
  .card-lbl svg{width:14px;height:14px}

  /* --- Hero --- */
  .hero{position:relative;padding-bottom:20px}
  .hero-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px}
  .hero .oid-lbl{font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700}
  .hero .oid{font-family:'Sora',sans-serif;font-size:15px;color:#fff;font-weight:700;margin-top:2px;letter-spacing:.5px}
  .live-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(57,209,106,.12);border:1px solid rgba(57,209,106,.35);color:var(--live);padding:5px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:1.5px}
  .live-pill .dot{width:7px;height:7px;border-radius:50%;background:var(--live);box-shadow:0 0 8px var(--live);animation:pulse 1.4s infinite}
  @keyframes pulse{0%,100%{opacity:.5}50%{opacity:1}}

  .hero-headline{font-family:'Sora',sans-serif;font-size:38px;font-weight:800;color:#fff;line-height:1.05;margin-top:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .seal{display:none;width:26px;height:26px}
  .seal.on{display:inline-block}
  .hero-sub{color:var(--muted);font-size:13.5px;margin-top:8px;max-width:65%;line-height:1.4}

  .nozzle{position:absolute;top:8px;right:-14px;width:160px;height:180px;pointer-events:none;opacity:.95}
  .nozzle::before{content:"";position:absolute;top:30px;right:20px;width:130px;height:130px;border-radius:50%;background:radial-gradient(circle,rgba(240,200,96,.10),transparent 70%)}

  /* Progress */
  .progress{position:relative;margin-top:22px;display:flex;justify-content:space-between;align-items:flex-start;padding:0 4px}
  .progress::before{content:"";position:absolute;top:16px;left:20px;right:20px;height:2px;background:rgba(240,200,96,.18);z-index:0}
  .progress .fill{position:absolute;top:16px;left:20px;height:2px;background:var(--gold);z-index:1;transition:width .5s ease;width:0}
  .p-step{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:10px;flex:1;min-width:0}
  .p-dot{width:32px;height:32px;border-radius:50%;background:var(--green-2);border:2px solid rgba(240,200,96,.25);display:flex;align-items:center;justify-content:center;color:transparent;font-size:14px;font-weight:800;transition:.3s}
  .p-step.done .p-dot{background:var(--gold);border-color:var(--gold);color:var(--ink)}
  .p-step.active .p-dot{background:transparent;border-color:var(--gold);box-shadow:0 0 0 5px rgba(240,200,96,.18), 0 0 16px rgba(240,200,96,.55);position:relative}
  .p-step.active .p-dot::after{content:"";width:10px;height:10px;border-radius:50%;background:#fff;position:absolute}
  .p-step small{font-size:9.5px;color:var(--muted);letter-spacing:1.2px;font-weight:700;text-transform:uppercase;text-align:center;line-height:1.1;max-width:68px}
  .p-step.done small{color:#d8cfb8}
  .p-step.active small{color:var(--gold)}

  /* --- Tiles --- */
  .tiles{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .tile{background:var(--card2);border:1px solid var(--line-2);border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:12px}
  .tile-ic{width:38px;height:38px;border-radius:50%;background:var(--green-2);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
  .tile-ic svg{width:20px;height:20px}
  .tile .lbl{font-size:11px;color:var(--muted)}
  .tile .val{font-family:'Sora',sans-serif;font-size:15px;color:#fff;font-weight:700;margin-top:1px}
  .tile .val.gold{color:var(--gold)}

  .addr{display:flex;gap:12px;align-items:flex-start;margin-top:14px;padding-top:16px;border-top:1px dashed var(--line-2)}
  .addr-ic{width:38px;height:38px;border-radius:50%;background:var(--green-2);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
  .addr-ic svg{width:18px;height:18px}
  .addr .k{font-size:12px;color:var(--muted);margin-bottom:2px}
  .addr .v{font-size:14px;color:#fff;line-height:1.4}

  /* --- Payment --- */
  .prow{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px dashed var(--line-2);font-size:14px}
  .prow:last-of-type{border-bottom:none}
  .prow .k{color:#c9d6cf}
  .prow .v{font-family:'Sora',sans-serif;color:#fff;font-weight:700}
  .method-pill{border:1px solid var(--gold);color:var(--gold);padding:5px 14px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:1.5px;font-family:'Manrope',sans-serif}
  .total-row{display:flex;justify-content:space-between;align-items:center;padding-top:14px;margin-top:6px;border-top:1px solid var(--line-2)}
  .total-row .k{font-size:15px;color:#f0ede4;font-weight:600}
  .total-row .v{font-family:'Sora',sans-serif;font-size:22px;color:var(--gold);font-weight:800}

  /* --- Rider --- */
  .rider{display:flex;align-items:center;gap:14px}
  .avatar{width:52px;height:52px;border-radius:50%;background:var(--gold);color:var(--ink);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;font-family:'Sora',sans-serif;flex:0 0 auto}
  .rider-info{flex:1;min-width:0}
  .rider-info .n{font-family:'Sora',sans-serif;font-weight:700;font-size:16px;color:#fff}
  .rider-info .p{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-top:2px}
  .rider-info .p svg{width:13px;height:13px;color:var(--gold)}
  .r-actions{display:flex;gap:10px}
  .r-btn{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid var(--line);flex:0 0 auto}
  .r-btn.call{background:var(--green);color:#fff}
  .r-btn.chat{background:var(--card2);color:#fff}
  .r-btn svg{width:20px;height:20px}

  /* --- OTP --- */
  .otp{background:linear-gradient(135deg,#1a5c46,#0f3d2e);border:1px solid var(--gold);border-radius:16px;padding:16px;text-align:center;margin-bottom:14px}
  .otp .lbl{font-size:11px;color:var(--gold-soft);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;font-weight:700}
  .otp .code{font-family:'Sora',sans-serif;font-size:32px;font-weight:800;color:var(--gold);letter-spacing:10px}

  /* --- Thank-you footer --- */
  .thanks{display:none;background:var(--card);border:1px solid var(--line);border-radius:22px;padding:16px 18px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden}
  .thanks.on{display:flex}
  .thanks .shield{width:36px;height:36px;flex:0 0 auto;color:var(--gold)}
  .thanks .t-txt{flex:1}
  .thanks .t-txt h4{font-family:'Sora',sans-serif;font-size:14px;color:#fff;font-weight:700;margin-bottom:2px}
  .thanks .t-txt p{font-size:12.5px;color:var(--muted)}
  .thanks .scooter{position:absolute;right:-10px;bottom:-10px;width:110px;height:80px;opacity:.10;pointer-events:none;color:var(--gold)}

  @media (max-width:340px){
    .hero-headline{font-size:32px}
    .p-step small{font-size:9px}
  }
</style>
</head>
<body>
<div class="wrap">

  <!-- Top bar -->
  <div class="topbar">
    <a href="<?php echo SITE_URL; ?>orders.php" class="chip" aria-label="Back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div class="title">Order Tracking</div>
    <div class="top-actions">
      <span class="chip" aria-label="Support">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3v4zM3 19a2 2 0 0 0 2 2h1v-6H3v4z"/></svg>
      </span>
      <span class="chip" aria-label="Safety">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
      </span>
    </div>
  </div>

  <!-- Hero card -->
  <div class="card hero">
    <div class="hero-top">
      <div>
        <div class="oid-lbl">ORDER ID</div>
        <div class="oid"><?php echo htmlspecialchars($orderCode); ?></div>
      </div>
      <div class="live-pill"><span class="dot"></span> LIVE</div>
    </div>

    <!-- Fuel nozzle illustration -->
    <svg class="nozzle" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="nz" x1="0" x2="1" y1="0" y2="1">
          <stop offset="0" stop-color="#2a7a52"/>
          <stop offset="1" stop-color="#0f3d2a"/>
        </linearGradient>
      </defs>
      <!-- pump body -->
      <path d="M60 80 L60 155 Q60 168 73 168 L110 168 Q123 168 123 155 L123 90 Q123 78 135 78 L155 78 L165 88 L165 118 Q165 128 155 128 L150 128" stroke="url(#nz)" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      <!-- nozzle head highlight -->
      <path d="M150 70 L175 70 L182 78 L182 90" stroke="#3b8a63" stroke-width="6" stroke-linecap="round" fill="none"/>
      <!-- trigger -->
      <path d="M75 100 Q90 92 100 100" stroke="#0a1f14" stroke-width="4" stroke-linecap="round" fill="none"/>
      <!-- drop -->
      <path d="M92 178 Q86 186 92 194 Q98 186 92 178 Z" fill="#f5b840"/>
    </svg>

    <h1 class="hero-headline" id="hlText">
      <span id="hlLabel"><?php echo htmlspecialchars($hl[0]); ?></span>
      <svg class="seal <?php echo $step===5?'on':''; ?>" id="seal" viewBox="0 0 24 24" fill="#f0c860" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 1l2.5 2.5L18 3l1 3.5L22 8l-1.5 3.5L22 15l-3 1.5L18 20l-3.5-.5L12 22l-2.5-2.5L6 20l-1-3.5L2 15l1.5-3.5L2 8l3-1.5L6 3l3.5.5L12 1z"/>
        <path d="M8.5 12l2.5 2.5 4.5-4.5" stroke="#0a1f14" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    </h1>
    <div class="hero-sub" id="hlSub"><?php echo htmlspecialchars($hl[1]); ?></div>

    <!-- Progress -->
    <div class="progress" id="progress">
      <div class="fill" id="progFill"></div>
      <div class="p-step" data-s="1"><div class="p-dot">✓</div><small>Placed</small></div>
      <div class="p-step" data-s="2"><div class="p-dot">✓</div><small>Accepted</small></div>
      <div class="p-step" data-s="3"><div class="p-dot">✓</div><small>Picked Up</small></div>
      <div class="p-step" data-s="4"><div class="p-dot">✓</div><small>On The Way</small></div>
      <div class="p-step" data-s="5"><div class="p-dot">✓</div><small>Delivered</small></div>
    </div>
  </div>

  <!-- Start OTP -->
  <?php if ($startOtp && $step < 3): ?>
  <div class="otp" id="startOtpCard">
    <div class="lbl">Share with rider — Pickup OTP</div>
    <div class="code" id="startOtp"><?php echo htmlspecialchars($startOtp); ?></div>
  </div>
  <?php endif; ?>

  <!-- Delivery OTP -->
  <?php if ($deliverOtp && $step >= 3 && $step < 5): ?>
  <div class="otp" id="deliverOtpCard">
    <div class="lbl">Delivery OTP</div>
    <div class="code" id="deliverOtp"><?php echo htmlspecialchars($deliverOtp); ?></div>
  </div>
  <?php endif; ?>

  <!-- Delivery Details -->
  <div class="card">
    <div class="card-lbl">
      <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
      DELIVERY DETAILS
    </div>

    <div class="tiles">
      <div class="tile">
        <div class="tile-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="#f0c860" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M3 22h12"/><path d="M6 10h6"/><path d="M15 8h2a2 2 0 0 1 2 2v6a2 2 0 0 0 2 2v0a2 2 0 0 0 2-2V9l-3-3"/></svg>
        </div>
        <div>
          <div class="lbl">Fuel</div>
          <div class="val"><?php echo htmlspecialchars(ucfirst($fuelType)); ?></div>
        </div>
      </div>
      <div class="tile">
        <div class="tile-ic" style="background:rgba(240,200,96,.12)">
          <svg viewBox="0 0 24 24" fill="#f5b840" xmlns="http://www.w3.org/2000/svg"><path d="M12 2s-6 8-6 13a6 6 0 0 0 12 0c0-5-6-13-6-13z"/></svg>
        </div>
        <div>
          <div class="lbl">Quantity</div>
          <div class="val gold" id="qty"><?php echo htmlspecialchars($quantity); ?> Liter</div>
        </div>
      </div>
    </div>

    <?php if ($address): ?>
    <div class="addr">
      <div class="addr-ic">
        <svg viewBox="0 0 24 24" fill="#f0c860" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
      </div>
      <div>
        <div class="k">Deliver To</div>
        <div class="v"><?php echo nl2br(htmlspecialchars($address)); ?></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Payment Details -->
  <div class="card">
    <div class="card-lbl">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f0c860" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      PAYMENT DETAILS
    </div>

    <?php if ($product !== '' && (float)$product > 0): ?>
    <div class="prow"><span class="k">Product Amount</span><span class="v"><?php echo $currency . number_format((float)$product, 2); ?></span></div>
    <?php endif; ?>
    <?php if ($delivery !== '' && (float)$delivery > 0): ?>
    <div class="prow"><span class="k">Delivery Fare</span><span class="v"><?php echo $currency . number_format((float)$delivery, 2); ?></span></div>
    <?php endif; ?>
    <div class="prow"><span class="k">Method</span><span class="method-pill"><?php echo strtoupper(htmlspecialchars($payMethod)); ?></span></div>

    <div class="total-row">
      <div class="k">Total Amount</div>
      <div class="v" id="totalAmount"><?php echo $currency . number_format((float)$amount, 2); ?></div>
    </div>
  </div>

  <!-- Rider -->
  <?php if ($riderName): ?>
  <div class="card" id="riderCard">
    <div class="card-lbl">
      <svg viewBox="0 0 24 24" fill="#f0c860" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5 0-9 2.5-9 6v2h18v-2c0-3.5-4-6-9-6z"/></svg>
      YOUR RIDER
    </div>
    <div class="rider">
      <div class="avatar" id="riderAvatar"><?php echo htmlspecialchars(strtoupper(substr($riderName,0,1))); ?></div>
      <div class="rider-info">
        <div class="n" id="riderName"><?php echo htmlspecialchars($riderName); ?></div>
        <div class="p">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 15.5c-1.2 0-2.4-.2-3.6-.6a1 1 0 0 0-1 .2l-2.2 2.2a15 15 0 0 1-6.6-6.6l2.2-2.2a1 1 0 0 0 .2-1c-.4-1.2-.6-2.4-.6-3.6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3.4a1 1 0 0 0-1-1z"/></svg>
          <span id="riderPhone"><?php echo htmlspecialchars($riderPhone); ?></span>
        </div>
      </div>
      <?php if ($riderPhone): ?>
      <div class="r-actions" id="rActions">
        <a href="tel:<?php echo htmlspecialchars($riderPhone); ?>" class="r-btn call" id="callBtn" aria-label="Call">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 15.5c-1.2 0-2.4-.2-3.6-.6a1 1 0 0 0-1 .2l-2.2 2.2a15 15 0 0 1-6.6-6.6l2.2-2.2a1 1 0 0 0 .2-1c-.4-1.2-.6-2.4-.6-3.6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3.4a1 1 0 0 0-1-1z"/></svg>
        </a>
        <a href="sms:<?php echo htmlspecialchars($riderPhone); ?>" class="r-btn chat" aria-label="Message">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Thank you -->
  <div class="thanks <?php echo $step===5?'on':''; ?>" id="thanks">
    <svg class="shield" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 4 5v6c0 5 4 9 8 11 4-2 8-6 8-11V5l-8-3z"/><path d="M9 12l2 2 4-4" stroke="#0a1f14" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
    <div class="t-txt">
      <h4>Thank you for choosing <?php echo htmlspecialchars($siteName); ?>.</h4>
      <p>We hope to serve you again!</p>
    </div>
    <svg class="scooter" viewBox="0 0 120 90" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="30" cy="65" r="14"/><circle cx="95" cy="65" r="14"/>
      <path d="M30 65 L55 65 L70 40 L90 40 L95 65"/><path d="M55 65 L70 30 L82 30"/>
    </svg>
  </div>

</div>

<script>
(function(){
  var currentStatus = <?php echo json_encode($status); ?>;
  var orderId = <?php echo json_encode((string)$orderCode); ?>;
  var currency = <?php echo json_encode($currency); ?>;

  var HEAD = {
    1:['Order Placed','We received your order'],
    2:['Order Accepted','A rider is being assigned'],
    3:['Picked Up','Your rider has the fuel'],
    4:['On the Way','Your fuel is coming to you'],
    5:['Delivered','Your fuel has been delivered successfully']
  };

  function stepFrom(s){
    s = String(s||'').toLowerCase().trim();
    var m = {'pending':1,'placed':1,'new':1,'accepted':2,'confirmed':2,'assigned':2,'picked_up':3,'pickedup':3,'picked':3,'ready':3,'in_transit':4,'transit':4,'on_the_way':4,'ontheway':4,'enroute':4,'out_for_delivery':4,'delivered':5,'completed':5,'done':5,'cancelled':0,'canceled':0,'rejected':0};
    return m.hasOwnProperty(s) ? m[s] : 1;
  }

  function apply(status){
    var step = stepFrom(status);
    var h = HEAD[step] || HEAD[1];
    var hl = document.getElementById('hlLabel'); if (hl) hl.textContent = h[0];
    var hs = document.getElementById('hlSub'); if (hs) hs.textContent = h[1];
    var seal = document.getElementById('seal'); if (seal) seal.classList.toggle('on', step===5);
    var thanks = document.getElementById('thanks'); if (thanks) thanks.classList.toggle('on', step===5);

    document.querySelectorAll('.p-step').forEach(function(el){
      var s = parseInt(el.getAttribute('data-s'),10);
      el.classList.remove('done','active');
      if (s < step || step === 5) el.classList.add('done');
      else if (s === step) el.classList.add('active');
    });
    var pct = step===5 ? 100 : Math.max(0, (step-1)/4*100);
    var f = document.getElementById('progFill'); if (f) f.style.width = pct + '%';

    // OTP visibility
    var so = document.getElementById('startOtpCard'); if (so) so.style.display = (step < 3) ? '' : 'none';
    var doc = document.getElementById('deliverOtpCard'); if (doc) doc.style.display = (step >=3 && step < 5) ? '' : 'none';

    // Hide rider actions after delivered
    var ra = document.getElementById('rActions'); if (ra) ra.style.display = (step===5) ? 'none' : '';
  }

  function fmt(n){
    n = Number(n)||0;
    return currency + n.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  var pollTimer = null;
  var inFlight = false;

  function isTerminal(s){ s=String(s||'').toLowerCase(); return s==='delivered'||s==='completed'||s==='done'||s==='cancelled'||s==='canceled'||s==='rejected'; }

  function update(data){
    if (!data) return;
    var newStatus = data.status ? String(data.status).toLowerCase() : currentStatus;
    if (newStatus && newStatus !== currentStatus){
      window.location.reload();
      return;
    }

    if (data.riderName){
      var rc = document.getElementById('riderCard'); if (rc) rc.style.display='';
      var rn = document.getElementById('riderName'); if (rn) rn.textContent = data.riderName;
      var av = document.getElementById('riderAvatar'); if (av) av.textContent = (data.riderName[0]||'?').toUpperCase();
    }
    if (data.riderPhone){
      var rp = document.getElementById('riderPhone'); if (rp) rp.textContent = data.riderPhone;
      var cb = document.getElementById('callBtn'); if (cb) cb.href='tel:'+data.riderPhone;
      var cb2 = document.getElementById('callBtnSticky'); if (cb2) cb2.href='tel:'+data.riderPhone;
    }
    if (data.productAmount !== undefined){
      var pa = document.getElementById('productAmount'); if (pa) pa.textContent = fmt(data.productAmount);
    }
    if (data.deliveryFare !== undefined){
      var df = document.getElementById('deliveryFare'); if (df) df.textContent = fmt(data.deliveryFare);
    }
    if (data.totalAmount !== undefined){
      var ta = document.getElementById('totalAmount'); if (ta) ta.textContent = fmt(data.totalAmount);
    }
    if (data.paymentStatus){
      var ps = document.getElementById('paymentStatus');
      if (ps){
        var v = String(data.paymentStatus).toLowerCase();
        ps.textContent = v.charAt(0).toUpperCase()+v.slice(1);
        ps.className = 'pay-pill ' + (v==='paid'?'paid':(v==='pending'||v==='awaiting'?'pending':''));
      }
    }
    // Endpoint returns a single `otp` field; route it by current step
    var otpVal = data.startOtp || data.deliveryOtp || data.otp;
    if (otpVal){
      var st = stepFrom(currentStatus);
      if (st < 3){ var so=document.getElementById('startOtp'); if (so) so.textContent = otpVal; }
      else if (st < 5){ var doel=document.getElementById('deliverOtp'); if (doel) doel.textContent = otpVal; }
    }

    if (isTerminal(currentStatus) && pollTimer){ clearInterval(pollTimer); pollTimer = null; }
  }

  function poll(){
    if (inFlight) return;
    inFlight = true;
    fetch('get_order_status.php?order_id=' + encodeURIComponent(orderId) + '&_=' + Date.now(), {cache:'no-store', credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        inFlight=false;
        if (d && d.error){ if (!window.__pollWarned){ console.warn('get_order_status.php error:', d.error); window.__pollWarned=true; } return; }
        update(d);
      })
      .catch(function(e){ inFlight=false; });
  }

  apply(currentStatus);
  if (!isTerminal(currentStatus)){
    pollTimer = setInterval(poll, 2000);
    poll();
    // Hard fallback: even if get_order_status.php fails, reload the page every 2 seconds.
    setInterval(function(){ if (!isTerminal(currentStatus)) window.location.reload(); }, 2000);
  }
})();
</script>
</body>
</html>
