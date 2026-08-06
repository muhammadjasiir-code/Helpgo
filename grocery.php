<?php

ob_start();

require_once "config.php";

if (!isLoggedIn()) { redirect('index.php'); }

$user   = getUserData($_SESSION['user_id']);
$wallet = getWalletBalance($_SESSION['user_id']);
$message = "";

$grocery_delivery_charge = defined('GROCERY_CHARGE') ? GROCERY_CHARGE : 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_grocery'])) {
    $delivery_address = sanitize($_POST['delivery_address'] ?? '');
    $phone            = sanitize($_POST['phone'] ?? '');
    $grocery_list     = sanitize($_POST['grocery_list'] ?? '');
    $shop_name        = sanitize($_POST['shop_name'] ?? '');
    $payment_method   = sanitize($_POST['payment_method'] ?? 'upi');
    $lat_raw          = $_POST['lat'] ?? '';
    $lng_raw          = $_POST['lng'] ?? '';

    if (empty($phone)) $phone = $user['phone'] ?? '';

    if (empty($delivery_address)) {
        $message = "Please confirm your delivery address.";
    } elseif (empty($grocery_list)) {
        $message = "Please enter your grocery list.";
    } elseif ($payment_method === 'cash') {
        $message = "Cash on Delivery is temporarily unavailable due to high cancellations. Please choose UPI.";
    } elseif ($payment_method === 'wallet' && $wallet < $grocery_delivery_charge) {
        $message = "Insufficient wallet balance. Please choose UPI.";
    } else {
        $total_amount   = $grocery_delivery_charge;
        $product_amount = 0;

        $lat_sql = (!empty($lat_raw) && is_numeric($lat_raw)) ? "'" . sanitize($lat_raw) . "'" : "NULL";
        $lng_sql = (!empty($lng_raw) && is_numeric($lng_raw)) ? "'" . sanitize($lng_raw) . "'" : "NULL";

        $order_id = generateOrderId('grocery');
        $uid = (int)$_SESSION['user_id'];

        $insert = mysqli_query($conn,
            "INSERT INTO orders
            (order_id, user_id, service_type, status,
             drop_address, drop_latitude, drop_longitude,
             grocery_list, shop_name, product_amount, delivery_fare, total_amount,
             payment_method, payment_status)
            VALUES
            ('$order_id', $uid, 'grocery', 'pending',
             '$delivery_address', $lat_sql, $lng_sql,
             '$grocery_list', '$shop_name', $product_amount, $grocery_delivery_charge, $total_amount,
             '$payment_method', 'pending')");

        if ($insert) {
            redirect('orders.php?order_id=' . $order_id . '&booked=1');
        } else {
            $message = "Booking failed: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#04120C">
<title>Grocery Delivery – HelpGo</title>
<link rel="icon" href="assets/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
/* ===== HELPGO — DEEP FOREST / GOLD NEON SYSTEM ===== */
:root{
  --bg:#04120C; --bg-2:#07301E;
  --panel:#0A1F16; --panel-2:#0C2A1B;
  --line:rgba(232,184,74,.28);
  --line-soft:rgba(255,255,255,.07);
  --gold:#F5C038; --gold-2:#FFD873; --gold-dark:#C9922A;
  --green:#2ED573;
  --white:#FFFFFF; --gray:#C6D2CB; --muted:#7E8F85;
  --danger:#FF4757;
  --r-xl:22px; --r-lg:16px; --r-md:13px;
  --font:'Plus Jakarta Sans','Outfit',sans-serif;
  --ease:cubic-bezier(.22,.9,.28,1); --t:all .28s var(--ease);
  --glow:0 0 0 1px rgba(232,184,74,.22), 0 18px 46px rgba(0,0,0,.55);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  font-family:var(--font);color:var(--white);letter-spacing:-.01em;
  background:
    radial-gradient(720px 420px at 88% -6%, rgba(245,192,56,.18), transparent 62%),
    radial-gradient(620px 460px at -10% 34%, rgba(46,213,115,.10), transparent 60%),
    linear-gradient(178deg,#061A11 0%, var(--bg) 42%, #030D08 100%);
  background-attachment:fixed;min-height:100vh;
  display:flex;justify-content:center;padding:0 0 150px;
}
.wrap{width:100%;max-width:480px;padding:0 14px;position:relative;z-index:2}
.reveal{opacity:0;transform:translateY(14px);animation:up .65s var(--ease) forwards}
@keyframes up{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.1s}.d3{animation-delay:.16s}
.d4{animation-delay:.22s}.d5{animation-delay:.28s}.d6{animation-delay:.34s}.d7{animation-delay:.4s}

/* ---------- HERO ---------- */
.hero{position:relative;padding:calc(14px + env(safe-area-inset-top)) 0 20px;overflow:hidden}
.hero-img{position:absolute;right:-26px;top:14px;width:230px;max-width:56%;
  filter:drop-shadow(0 22px 38px rgba(0,0,0,.6));pointer-events:none;user-select:none;z-index:1}
.hero-glow{position:absolute;right:-40px;top:-60px;width:320px;height:320px;border-radius:50%;
  background:radial-gradient(circle,rgba(245,192,56,.22),transparent 66%);pointer-events:none}
.hero-row{display:flex;align-items:center;gap:12px;position:relative;z-index:3}
.circle-btn{width:46px;height:46px;border-radius:50%;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;color:var(--gold);font-size:16px;text-decoration:none;
  background:rgba(255,255,255,.04);border:1.5px solid var(--line);transition:var(--t)}
.circle-btn:active{transform:scale(.93)}
.circle-btn:hover{background:var(--gold);color:#062015;border-color:var(--gold)}
.logo{height:44px;width:auto;object-fit:contain}
.hero-copy{position:relative;z-index:3;margin-top:14px;max-width:64%}
h1{font-family:'Outfit',var(--font);font-size:34px;line-height:1.02;font-weight:800;letter-spacing:-.035em}
h1 em{font-style:normal;color:var(--gold)}
.sub{margin-top:9px;font-size:13.5px;line-height:1.55;color:var(--gray);font-weight:500}
.hero-rule{margin-top:18px;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent);
  box-shadow:0 0 14px rgba(245,192,56,.5)}

/* ---------- CARD ---------- */
.card{background:linear-gradient(180deg,var(--panel-2),var(--panel));
  border:1.5px solid var(--line);border-radius:var(--r-xl);
  box-shadow:var(--glow);padding:15px;margin-bottom:13px}
.card.plain{border-color:var(--line-soft)}
.chead{display:flex;align-items:center;gap:10px;margin-bottom:13px}
.step{width:28px;height:28px;border-radius:9px;flex-shrink:0;font-size:12px;font-weight:800;
  color:var(--gold);background:rgba(245,192,56,.10);border:1.5px solid var(--line);
  display:flex;align-items:center;justify-content:center}
.chead h4{font-size:15px;font-weight:700;letter-spacing:-.02em}
.chead .opt{font-size:11.5px;color:var(--muted);font-weight:600}
.chead .pill{margin-left:auto;display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:100px;
  font-size:11.5px;font-weight:700;color:var(--gold);background:rgba(245,192,56,.07);
  border:1.5px solid var(--line);cursor:pointer;transition:var(--t);white-space:nowrap;font-family:var(--font)}
.chead .pill:hover{background:var(--gold);color:#062015}
.chead .pill:active{transform:scale(.96)}

/* ---------- FIELDS ---------- */
.control{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.03);
  border:1.5px solid var(--line);border-radius:100px;padding:0 8px 0 8px;transition:var(--t)}
.control .ic{width:32px;height:32px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;
  justify-content:center;background:rgba(245,192,56,.10);color:var(--gold);font-size:13px}
.control input,.control textarea{flex:1;min-width:0;background:transparent;border:none;outline:none;
  color:var(--white);font-family:var(--font);font-size:14px;font-weight:500;padding:14px 12px 14px 0;resize:none}
.control input::placeholder,.control textarea::placeholder{color:var(--muted);font-weight:400}
.control:focus-within{border-color:var(--gold);box-shadow:0 0 0 4px rgba(245,192,56,.13)}
.control.block{border-radius:var(--r-lg);align-items:flex-start;padding:12px 8px 10px 8px}
.control.block .ic{margin-top:2px}
.control.block textarea{min-height:92px;line-height:1.6;padding:6px 10px 0 0}
.list-hint{padding:0 14px 10px 51px;font-size:12px;color:var(--muted);line-height:1.5}
.counter{padding:0 16px 10px;text-align:right;font-size:11.5px;color:var(--muted);font-weight:600}
.tipbar{display:flex;align-items:center;gap:9px;margin-top:11px;padding:12px 14px;border-radius:var(--r-lg);
  background:rgba(46,213,115,.06);border:1px solid rgba(46,213,115,.18);
  font-size:12.5px;font-weight:600;color:var(--green)}

/* ---------- MAP ---------- */
.map-shell{position:relative;margin-top:12px;border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--line-soft)}
#map{width:100%;height:210px;background:#12302c;touch-action:none}
.leaflet-container{font-family:var(--font)}
.locate-chip{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:1000;
  background:rgba(4,18,12,.8);backdrop-filter:blur(10px);color:var(--gold);border:1px solid var(--line);
  border-radius:100px;padding:9px 18px;font-family:var(--font);font-weight:700;font-size:12px;cursor:pointer;
  display:flex;align-items:center;gap:8px;box-shadow:0 10px 26px rgba(0,0,0,.5)}
.locate-chip:active{transform:translateX(-50%) scale(.96)}
.addr-strip{display:flex;align-items:center;gap:11px;margin-top:10px;padding:12px 14px;border-radius:var(--r-lg);
  background:rgba(46,213,115,.06);border:1px solid rgba(46,213,115,.16)}
.addr-strip .dot{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;
  justify-content:center;background:rgba(46,213,115,.16);color:var(--green);font-size:14px}
.addr-strip .tx{min-width:0;flex:1}
.addr-strip .tx b{display:block;font-size:13.5px;font-weight:700;color:var(--gold);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.addr-strip .tx small{font-size:11.5px;color:var(--gray);font-weight:500}
.addr-strip > i{color:var(--muted);font-size:13px}

/* ---------- STORE ---------- */
.store-row{display:flex;align-items:center;gap:13px;padding:11px 14px 11px 11px;border-radius:var(--r-lg);
  background:rgba(255,255,255,.03);border:1.5px solid var(--line-soft);transition:var(--t)}
.store-row:focus-within{border-color:var(--gold)}
.store-row img{width:56px;height:56px;border-radius:13px;object-fit:contain;flex-shrink:0;
  background:rgba(255,255,255,.05);padding:4px}
.store-row .tx{flex:1;min-width:0}
.store-row input{width:100%;background:transparent;border:none;outline:none;color:var(--white);
  font-family:var(--font);font-size:15px;font-weight:700;padding:0 0 3px}
.store-row input::placeholder{color:var(--gray);font-weight:700}
.store-row small{font-size:11.5px;color:var(--muted);font-weight:500}
.store-row > i{color:var(--muted);font-size:13px}

/* ---------- PAYMENT ---------- */
.pay-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}
.pay{position:relative;display:flex;flex-direction:column;gap:8px;padding:12px 11px;cursor:pointer;
  background:rgba(255,255,255,.03);border:1.5px solid var(--line-soft);border-radius:var(--r-md);transition:var(--t)}
.pay input{display:none}
.pay img{height:26px;width:auto;max-width:46px;object-fit:contain}
.pay b{font-size:13.5px;font-weight:700;color:var(--white);line-height:1.1}
.pay small{font-size:10.5px;color:var(--muted);font-weight:500;line-height:1.2}
.pay .check{position:absolute;right:9px;bottom:9px;width:19px;height:19px;border-radius:50%;
  background:var(--gold);color:#062015;font-size:10px;display:none;align-items:center;justify-content:center}
.pay.selected{border-color:var(--gold);background:rgba(245,192,56,.08);box-shadow:0 0 0 3px rgba(245,192,56,.12)}
.pay.selected .check{display:flex}
.pay.selected b{color:var(--gold)}
.pay.unavailable{opacity:.4;cursor:not-allowed}
.pay .tag{position:absolute;top:7px;right:7px;background:rgba(255,71,87,.2);color:var(--danger);
  border:1px solid rgba(255,71,87,.35);font-size:7.5px;font-weight:800;padding:2px 6px;border-radius:100px;
  letter-spacing:.06em;text-transform:uppercase;display:none}
.pay.unavailable .tag{display:block}
.secure{display:flex;align-items:center;gap:10px;margin-top:11px;padding:12px 14px;border-radius:var(--r-lg);
  background:rgba(46,213,115,.06);border:1px solid rgba(46,213,115,.16);font-size:12.5px;font-weight:600;color:var(--gray)}
.secure i.shield{color:var(--green);font-size:14px}
.secure i.arrow{margin-left:auto;color:var(--muted);font-size:12px}
.alert{display:none;margin-top:11px;padding:11px 13px;border-radius:12px;gap:9px;
  background:rgba(255,71,87,.12);border:1px solid rgba(255,71,87,.25);
  color:#FF7A85;font-size:12px;font-weight:600;line-height:1.45}
.alert.show{display:flex}
.alert i{margin-top:2px}

/* ---------- SUMMARY ---------- */
.row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px;
  color:var(--gray);font-weight:500}
.row span:last-child{color:var(--white);font-weight:600;font-variant-numeric:tabular-nums}
.row.total{margin-top:9px;padding-top:13px;border-top:1px dashed rgba(245,192,56,.28)}
.row.total span:first-child{font-size:15px;font-weight:800;color:var(--white)}
.row.total span:last-child{font-family:'Outfit',var(--font);font-size:25px;font-weight:800;color:var(--gold);letter-spacing:-.03em}

/* ---------- CTA ---------- */
.cta-bar{position:fixed;left:0;right:0;bottom:0;z-index:900;display:flex;justify-content:center;
  padding:14px 18px calc(12px + env(safe-area-inset-bottom));
  background:linear-gradient(to top,rgba(3,12,8,.97) 58%,rgba(3,12,8,0));backdrop-filter:blur(12px)}
.cta-inner{width:100%;max-width:452px}
.btn{position:relative;width:100%;height:62px;border:none;border-radius:100px;cursor:pointer;
  font-family:'Outfit',var(--font);font-size:19px;font-weight:700;letter-spacing:-.02em;color:#0A1F16;
  background:linear-gradient(180deg,var(--gold-2),var(--gold) 52%,var(--gold-dark));
  box-shadow:0 14px 36px rgba(245,192,56,.32),inset 0 1px 0 rgba(255,255,255,.55);
  display:flex;align-items:center;justify-content:center;gap:12px;transition:var(--t)}
.btn:active{transform:scale(.976)}
.btn .go{position:absolute;right:9px;width:44px;height:44px;border-radius:50%;background:#0A1F16;color:var(--gold);
  display:flex;align-items:center;justify-content:center;font-size:15px}
.btn:disabled{background:rgba(255,255,255,.07);color:var(--muted);box-shadow:none;cursor:not-allowed}
.btn:disabled .go{background:rgba(255,255,255,.06);color:var(--muted)}
.cta-note{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:9px;
  font-size:11.5px;color:var(--muted);font-weight:500;text-align:center}
.cta-note i{color:var(--green)}
.err-top{display:flex;gap:9px;align-items:flex-start;padding:12px 15px;margin-bottom:13px;border-radius:14px;
  background:rgba(255,71,87,.12);border:1px solid rgba(255,71,87,.25);color:#FF7A85;font-size:12.5px;
  font-weight:600;line-height:1.5}

@media (max-width:400px){
  h1{font-size:29px}
  .hero-img{width:190px}
  .pay-grid{grid-template-columns:1fr 1fr}
  .btn{height:58px;font-size:17.5px}
}
::-webkit-scrollbar{width:3px}
::-webkit-scrollbar-thumb{background:var(--gold);border-radius:10px}
</style>
</head>
<body>

<div class="wrap">

  <!-- HERO -->
  <header class="hero reveal d1">
    <div class="hero-glow"></div>
    <img src="assets/hero-basket.png" alt="Fresh grocery crate" class="hero-img">
    <div class="hero-row">
      <a href="home.php" class="circle-btn" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
      <img src="assets/logo-helpgo.png" alt="HelpGo — We care. We deliver." class="logo">
      <a href="tel:<?= htmlspecialchars($user['phone'] ?? '') ?>" class="circle-btn" style="margin-left:auto" aria-label="Call support"><i class="fas fa-phone"></i></a>
    </div>
    <div class="hero-copy">
      <h1>Grocery <em>Delivery</em></h1>
      <p class="sub">Fresh groceries, on time.<br>You relax, we deliver.</p>
    </div>
    <div class="hero-rule"></div>
  </header>

  <?php if (!empty($message)): ?>
    <div class="err-top reveal d1">
      <i class="fas fa-circle-exclamation" style="margin-top:2px"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" id="groceryForm">
    <input type="hidden" name="lat" id="latInput">
    <input type="hidden" name="lng" id="lngInput">
    <input type="hidden" name="book_grocery" value="1">

    <!-- 1. LOCATION -->
    <section class="card reveal d2">
      <div class="chead">
        <span class="step">1</span>
        <h4>Delivery Location</h4>
        <button type="button" class="pill" id="locatePill"><i class="fas fa-location-crosshairs"></i> Use Current Location</button>
      </div>
      <div class="control">
        <span class="ic"><i class="fas fa-map-pin"></i></span>
        <input type="text" name="delivery_address" id="deliveryAddress" placeholder="Enter address or pick on the map" required>
      </div>
      <div class="map-shell">
        <div id="map"></div>
        <button type="button" class="locate-chip" id="locateBtn"><i class="fas fa-location-crosshairs"></i> Use Current Location</button>
      </div>
      <div class="addr-strip">
        <span class="dot"><i class="fas fa-location-dot"></i></span>
        <span class="tx">
          <b id="addrTitle">Pin your doorstep</b>
          <small id="addrSub">Drag the pin or tap the map to fine-tune</small>
        </span>
        <i class="fas fa-chevron-right"></i>
      </div>
    </section>

    <!-- 2. STORE -->
    <section class="card reveal d3">
      <div class="chead">
        <span class="step">2</span>
        <h4>Preferred Store</h4>
        <span class="opt">(Optional)</span>
      </div>
      <div class="store-row">
        <img src="assets/icon-store.png" alt="Store">
        <span class="tx">
          <input type="text" name="shop_name" id="shopName" placeholder="Fresh Mart">
          <small>Fresh &amp; quality assured</small>
        </span>
        <i class="fas fa-chevron-right"></i>
      </div>
    </section>

    <!-- 3. LIST -->
    <section class="card reveal d4">
      <div class="chead">
        <span class="step"><i class="fas fa-cart-shopping" style="font-size:11px"></i></span>
        <h4>Shopping List</h4>
        <button type="button" class="pill" id="addFromList"><i class="fas fa-plus"></i> Add from list</button>
      </div>
      <div class="control block">
        <span class="ic"><i class="fas fa-list-ul"></i></span>
        <div style="flex:1;min-width:0">
          <textarea name="grocery_list" id="groceryList" maxlength="250" rows="4" placeholder="Type your items here...&#10;e.g. Milk 2 pack, Bread 1 loaf, Eggs 12 pcs, Tomato 1 kg" required></textarea>
          <div class="counter"><span id="charCount">0</span> / 250</div>
        </div>
      </div>
      <div class="tipbar"><i class="fas fa-lightbulb"></i> Tip: Be specific for better shopping</div>
    </section>

    <!-- 4. PAYMENT -->
    <section class="card reveal d5">
      <div class="chead">
        <span class="step">4</span>
        <h4>Payment Method</h4>
      </div>
      <div class="pay-grid" id="paymentOptions">
        <label class="pay selected" data-method="upi" id="upiCard">
          <input type="radio" name="payment_method" value="upi" checked>
          <img src="assets/icon-upi.png" alt="UPI">
          <span><b>UPI</b><br><small>Instant &amp; Secure</small></span>
          <span class="check"><i class="fas fa-check"></i></span>
        </label>
        <label class="pay unavailable" data-method="cash" id="cashCard">
          <input type="radio" name="payment_method" value="cash">
          <img src="assets/icon-cash.png" alt="Cash">
          <span><b>Cash</b><br><small>Pay on delivery</small></span>
          <span class="tag">N/A</span>
          <span class="check"><i class="fas fa-check"></i></span>
        </label>
        <label class="pay" data-method="wallet" id="walletCard">
          <input type="radio" name="payment_method" value="wallet">
          <img src="assets/icon-wallet.png" alt="Wallet">
          <span><b>Wallet</b><br><small>₹<?= number_format((float)$wallet, 0) ?> available</small></span>
          <span class="check"><i class="fas fa-check"></i></span>
        </label>
      </div>
      <div class="secure">
        <i class="fas fa-shield-halved shield"></i> Your payment is 100% secure with us.
        <i class="fas fa-chevron-right arrow"></i>
      </div>
      <div class="alert" id="cashWarning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Cash on Delivery is currently unavailable due to high cancellations. Please continue with UPI.</span>
      </div>
    </section>

    <!-- SUMMARY -->
    <section class="card reveal d6">
      <div class="chead">
        <span class="step"><i class="fas fa-receipt" style="font-size:11px"></i></span>
        <h4>Price Summary</h4>
      </div>
      <div class="row"><span>Service Charge</span><span>₹<?= $grocery_delivery_charge ?></span></div>
      <div class="row"><span>Estimated Product Cost</span><span>₹0.00</span></div>
      <div class="row"><span>Delivery Charge</span><span>₹0.00</span></div>
      <div class="row total"><span>Grand Total</span><span>₹<?= $grocery_delivery_charge ?></span></div>
    </section>

    <div class="cta-bar">
      <div class="cta-inner">
        <button type="submit" name="book_grocery" value="1" id="placeOrderBtn" class="btn" disabled>
          <i class="fas fa-basket-shopping"></i> Place Order
          <span class="go"><i class="fas fa-arrow-right"></i></span>
        </button>
        <div class="cta-note"><i class="fas fa-lock"></i> Our rider purchases your items and delivers to your doorstep.</div>
      </div>
    </div>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  const deliveryInput = document.getElementById('deliveryAddress');
  const groceryList   = document.getElementById('groceryList');
  const placeOrderBtn = document.getElementById('placeOrderBtn');
  const paymentCards  = document.querySelectorAll('.pay');
  const cashCard      = document.getElementById('cashCard');
  const upiCard       = document.getElementById('upiCard');
  const cashWarning   = document.getElementById('cashWarning');
  const charCount     = document.getElementById('charCount');
  const addrTitle     = document.getElementById('addrTitle');
  const addrSub       = document.getElementById('addrSub');

  /* Cash disabled by policy */
  upiCard.classList.add('selected');
  upiCard.querySelector('input').checked = true;
  cashCard.classList.add('unavailable');
  cashCard.querySelector('input').checked = false;

  function validateForm(){
    const address = deliveryInput.value.trim();
    const list    = groceryList.value.trim();
    const checked = document.querySelector('input[name="payment_method"]:checked');
    const isCash  = checked && checked.value === 'cash';
    if (isCash){
      cashWarning.classList.add('show');
      placeOrderBtn.disabled = true;
    } else {
      cashWarning.classList.remove('show');
      placeOrderBtn.disabled = !(address.length > 0 && list.length > 0);
    }
    return !placeOrderBtn.disabled;
  }

  paymentCards.forEach(card => {
    card.addEventListener('click', function(e){
      if (this.dataset.method === 'cash'){
        e.preventDefault();
        cashWarning.classList.add('show');
        paymentCards.forEach(c => c.classList.remove('selected'));
        upiCard.classList.add('selected');
        upiCard.querySelector('input').checked = true;
      } else {
        paymentCards.forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input').checked = true;
        cashWarning.classList.remove('show');
      }
      validateForm();
    });
  });

  groceryList.addEventListener('input', function(){
    charCount.textContent = this.value.length;
    validateForm();
  });
  deliveryInput.addEventListener('input', function(){
    if (this.value.trim()){ addrTitle.textContent = this.value.trim().split(',').slice(0,2).join(','); }
    validateForm();
  });

  document.getElementById('addFromList').addEventListener('click', function(){
    const preset = "Milk 2 pack\nBread 1 loaf\nEggs 12 pcs\nTomato 1 kg";
    groceryList.value = groceryList.value.trim() ? groceryList.value.trim() + "\n" + preset : preset;
    groceryList.value = groceryList.value.slice(0,250);
    charCount.textContent = groceryList.value.length;
    groceryList.focus(); validateForm();
  });

  /* ---- Map ---- */
  const defaultLat = 10.8505, defaultLng = 76.2711;
  const latInput = document.getElementById('latInput');
  const lngInput = document.getElementById('lngInput');
  let marker = null;

  const map = L.map('map',{zoomControl:true}).setView([defaultLat,defaultLng],16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);

  function setMarker(lat,lng){
    if (marker){ marker.setLatLng([lat,lng]); }
    else {
      marker = L.marker([lat,lng],{draggable:true}).addTo(map);
      marker.on('dragend', e => { const ll = e.target.getLatLng(); setLocation(ll.lat, ll.lng); });
    }
  }
  function fetchAddress(lat,lng){
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
      .then(r=>r.json())
      .then(d=>{
        const name = d && d.display_name ? d.display_name : `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
        deliveryInput.value = name;
        const parts = name.split(',');
        addrTitle.textContent = parts.slice(0,2).join(',').trim();
        addrSub.textContent   = parts.slice(2,5).join(',').trim() || 'Tap to adjust';
        validateForm();
      })
      .catch(()=>{ deliveryInput.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`; validateForm(); });
  }
  function setLocation(lat,lng){
    latInput.value = lat; lngInput.value = lng;
    setMarker(lat,lng); fetchAddress(lat,lng);
  }
  map.on('click', e => { setLocation(e.latlng.lat, e.latlng.lng); });

  function locate(btn){
    if (!navigator.geolocation){ alert('Geolocation not supported. Please type your address manually.'); return; }
    const html = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating…';
    navigator.geolocation.getCurrentPosition(
      pos => { const {latitude:lat, longitude:lng} = pos.coords; map.setView([lat,lng],18); setLocation(lat,lng); btn.innerHTML = html; },
      () => { alert('Unable to retrieve your location. Please allow location access or type your address manually.'); btn.innerHTML = html; },
      {enableHighAccuracy:true}
    );
  }
  document.getElementById('locateBtn').addEventListener('click', function(){ locate(this); });
  document.getElementById('locatePill').addEventListener('click', function(){ locate(this); });

  if (navigator.geolocation){
    navigator.geolocation.getCurrentPosition(
      pos => { const {latitude:lat, longitude:lng} = pos.coords; map.setView([lat,lng],17); setLocation(lat,lng); },
      () => setLocation(defaultLat, defaultLng)
    );
  } else { setLocation(defaultLat, defaultLng); }

  validateForm();
})();
</script>
</body>
</html>
