<?php
// payment_confirm.php — Waiting for admin to confirm UPI payment (Emerald Prestige)
// Auto checks every 2 seconds; when payment_status = 'paid', redirects to the correct tracking page.

ob_start();
@ini_set('display_errors', '0');
error_reporting(E_ALL);
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// ---------------------------------------------------------------
// 1) Load config / DB connection tolerantly
// ---------------------------------------------------------------
$__loaded_config = null;
$__try = [
    __DIR__ . '/config.php',
    __DIR__ . '/db.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../db.php',
    __DIR__ . '/includes/config.php',
    __DIR__ . '/inc/config.php',
];
foreach ($__try as $__f) {
    if (is_file($__f)) { require $__f; $__loaded_config = $__f; break; }
}

$__db_var = null;
$__db_kind = null;
foreach (['conn','db','mysqli','link','connection','database','DB','pdo','PDO'] as $__v) {
    if (isset($$__v) && is_object($$__v)) {
        if ($$__v instanceof mysqli) { $conn = $$__v; $__db_kind = 'mysqli'; break; }
        if ($$__v instanceof PDO)    { $conn = $$__v; $__db_kind = 'pdo';    break; }
    }
}
if (!isset($conn) && isset($GLOBALS['conn']) && is_object($GLOBALS['conn'])) {
    $conn = $GLOBALS['conn'];
    if ($conn instanceof mysqli) $__db_kind = 'mysqli';
    elseif ($conn instanceof PDO) $__db_kind = 'pdo';
}

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (!isset($conn) || !is_object($conn)) {
    echo "Database connection not available.";
    exit;
}

// ---------------------------------------------------------------
// 2) Helpers
// ---------------------------------------------------------------
function db_fetch_one($conn, $sql, $params = []) {
    global $__db_kind;
    if ($__db_kind === 'mysqli') {
        if ($params) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) return null;
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_assoc() : null;
        }
        $res = $conn->query($sql);
        return $res ? $res->fetch_assoc() : null;
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
function first_key($row, $keys) {
    foreach ($keys as $k) if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
    return null;
}

if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');
if (!defined('SITE_NAME'))       define('SITE_NAME', 'HelpGo');

// ---------------------------------------------------------------
// 3) Load order
// ---------------------------------------------------------------
$order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
if ($order_id === '') { echo "Missing order id."; exit; }

$order = db_fetch_one($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1", [$order_id]);
if (!$order) {
    $order = db_fetch_one($conn, "SELECT * FROM orders WHERE id = ? LIMIT 1", [$order_id]);
}
if (!$order) { echo "Order not found."; exit; }

$service    = strtolower((string)first_key($order, ['service_type','service','type','category','order_type']));
$pay_status = strtolower((string)first_key($order, ['payment_status','paymentStatus','pay_status']));
$amount     = (float)first_key($order, ['total_amount','totalAmount','amount','grand_total','product_amount']);
$utr        = (string)first_key($order, ['utr','transaction_id','txn_id','reference']);

// Robust service detection: petrol only if explicitly petrol/fuel or fuel_type column present
$isPetrol = false;
if (strpos($service, 'petrol') !== false || strpos($service, 'fuel') !== false || strpos($service, 'diesel') !== false) {
    $isPetrol = true;
} elseif (strpos($service, 'grocery') !== false || strpos($service, 'shop') !== false || strpos($service, 'store') !== false) {
    $isPetrol = false;
} else {
    // Fallback: sniff columns
    if (!empty($order['fuel_type']) || !empty($order['liters']) || !empty($order['litres']) || !empty($order['quantity_liters'])) {
        $isPetrol = true;
    } elseif (!empty($order['items']) || !empty($order['grocery_list']) || !empty($order['shop_name']) || !empty($order['bill_image']) || !empty($order['bill_amount'])) {
        $isPetrol = false;
    }
}
$trackPage = $isPetrol ? 'petrol_orders.php' : 'grocery_orders.php';

// If already paid, redirect immediately
if ($pay_status === 'paid') {
    header("Location: " . $trackPage . "?order_id=" . urlencode($order_id));
    exit;
}
// If rejected, back to pay.php to retry
if ($pay_status === 'rejected' || $pay_status === 'failed') {
    header("Location: pay.php?order_id=" . urlencode($order_id) . "&err=rejected");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="2">
<title>Waiting for Payment Confirmation · <?php echo htmlspecialchars(SITE_NAME); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#04150f; --bg2:#082419; --emerald:#0f5132; --emerald-2:#14805a;
    --gold:#d4b062; --gold-2:#f0d488; --text:#eaf6ef; --muted:#9dbfae;
    --card:rgba(255,255,255,.045); --border:rgba(212,176,98,.22);
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    font-family:'Manrope',system-ui,-apple-system,sans-serif;
    color:var(--text);
    background:
      radial-gradient(1200px 600px at 20% -10%,rgba(20,128,90,.35),transparent 60%),
      radial-gradient(900px 500px at 100% 10%,rgba(212,176,98,.12),transparent 60%),
      linear-gradient(180deg,#04150f 0%,#082419 100%);
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    padding:24px 16px;
  }
  .wrap{width:100%;max-width:440px}
  h1{
    font-family:'Sora',sans-serif;font-weight:700;
    font-size:22px;margin:0 0 4px;text-align:center;letter-spacing:.2px;
  }
  .sub{color:var(--muted);font-size:13px;text-align:center;margin-bottom:22px}
  .card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:28px 22px;
    backdrop-filter:blur(14px);
    box-shadow:0 20px 60px rgba(0,0,0,.35);
    text-align:center;
  }
  .hourglass{
    width:96px;height:96px;margin:6px auto 14px;
    border-radius:50%;
    background:radial-gradient(circle at 50% 50%,rgba(212,176,98,.28),rgba(212,176,98,0) 70%);
    display:flex;align-items:center;justify-content:center;
    animation:pulse 2s ease-in-out infinite;
    position:relative;
  }
  .hourglass::before{
    content:"";position:absolute;inset:0;border-radius:50%;
    border:2px solid rgba(212,176,98,.35);
    animation:ring 2s ease-out infinite;
  }
  .hourglass svg{width:44px;height:44px}
  @keyframes pulse{
    0%,100%{transform:scale(1)}
    50%{transform:scale(1.06)}
  }
  @keyframes ring{
    0%{transform:scale(.9);opacity:.9}
    100%{transform:scale(1.35);opacity:0}
  }
  .status{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 14px;border-radius:999px;
    background:rgba(212,176,98,.12);
    border:1px solid rgba(212,176,98,.35);
    color:var(--gold-2);
    font-size:12px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;
    margin-bottom:14px;
  }
  .dot{width:8px;height:8px;border-radius:50%;background:var(--gold);animation:blink 1.2s infinite}
  @keyframes blink{50%{opacity:.35}}
  h2{font-family:'Sora',sans-serif;margin:6px 0 8px;font-size:18px}
  p.msg{color:var(--muted);font-size:14px;line-height:1.55;margin:0 0 18px}
  .meta{
    display:flex;flex-direction:column;gap:8px;
    background:rgba(0,0,0,.22);
    border:1px solid rgba(255,255,255,.06);
    border-radius:14px;padding:14px 16px;
    margin-top:8px;text-align:left;
  }
  .row{display:flex;justify-content:space-between;align-items:center;font-size:13px}
  .row .k{color:var(--muted)}
  .row .v{color:#fff;font-weight:600;font-family:'Sora',sans-serif}
  .amt{color:var(--gold-2)}
  .btns{display:flex;gap:10px;margin-top:18px}
  .btn{
    flex:1;text-align:center;padding:12px 14px;border-radius:12px;
    text-decoration:none;font-weight:600;font-size:14px;
    border:1px solid rgba(255,255,255,.14);color:var(--text);
    background:rgba(255,255,255,.04);
  }
  .btn.primary{
    background:linear-gradient(135deg,var(--emerald-2),var(--emerald));
    border-color:transparent;color:#fff;
  }
  .foot{color:var(--muted);font-size:11px;text-align:center;margin-top:16px}
</style>
</head>
<body>
  <div class="wrap">
    <h1>Payment Submitted</h1>
    <div class="sub">Please wait while admin verifies your payment</div>

    <div class="card">
      <div class="hourglass" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="#f0d488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 2h12M6 22h12M6 2v4a6 6 0 0 0 12 0V2M6 22v-4a6 6 0 0 1 12 0v4"/>
        </svg>
      </div>

      <div class="status"><span class="dot"></span> Waiting for Confirmation</div>
      <h2>Verifying your payment…</h2>
      <p class="msg">We've received your payment proof. Our team is reviewing it now. This page will automatically continue once your payment is confirmed.</p>

      <div class="meta">
        <div class="row"><span class="k">Order ID</span><span class="v">#<?php echo htmlspecialchars($order_id); ?></span></div>
        <div class="row"><span class="k">Amount</span><span class="v amt"><?php echo CURRENCY_SYMBOL . number_format($amount, 2); ?></span></div>
        <?php if ($utr !== ''): ?>
        <div class="row"><span class="k">UTR / Ref</span><span class="v"><?php echo htmlspecialchars($utr); ?></span></div>
        <?php endif; ?>
        <div class="row"><span class="k">Status</span><span class="v amt" id="statusText">Pending Review</span></div>
      </div>

      <div class="btns">
        <a class="btn" href="orders.php">My Orders</a>
        <a class="btn primary" href="<?php echo htmlspecialchars($trackPage) . '?order_id=' . urlencode($order_id); ?>">Track Order</a>
      </div>
    </div>

    <div class="foot">This usually takes 1–3 minutes. Do not close this page.</div>
  </div>

<script>
  var ORDER_ID = <?php echo json_encode($order_id); ?>;
  var TRACK    = <?php echo json_encode($trackPage); ?>;

  function checkStatus(){
    fetch('get_order_status.php?order_id=' + encodeURIComponent(ORDER_ID) + '&_=' + Date.now(), {cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d) return;
        var ps = (d.paymentStatus || d.payment_status || '').toString().toLowerCase();
        if(ps === 'paid' || ps === 'approved' || ps === 'confirmed'){
          document.getElementById('statusText').textContent = 'Confirmed';
          setTimeout(function(){
            window.location.href = TRACK + '?order_id=' + encodeURIComponent(ORDER_ID);
          }, 700);
        } else if(ps === 'rejected' || ps === 'failed'){
          window.location.href = 'pay.php?order_id=' + encodeURIComponent(ORDER_ID) + '&err=rejected';
        }
      })
      .catch(function(){});
  }
  setInterval(checkStatus, 2000);
  checkStatus();
</script>
</body>
</html>
