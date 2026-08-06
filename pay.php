<?php
// pay.php – UPI payment proof upload (redirects to payment_confirm.php)
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');   // remove after debugging

// ---------- Load config ----------
$__loaded_config = null;
foreach ([__DIR__.'/config.php', __DIR__.'/db.php', __DIR__.'/../config.php', __DIR__.'/../db.php',
          __DIR__.'/includes/config.php', __DIR__.'/inc/config.php'] as $__f) {
    if (is_file($__f)) { require $__f; $__loaded_config = $__f; break; }
}

// ---------- Normalize DB handle ----------
$__db_kind = null;
foreach (['conn','db','mysqli','link','connection','database','DB','pdo','PDO'] as $__v) {
    if (isset($$__v) && is_object($$__v)) {
        if ($$__v instanceof mysqli) { $conn = $$__v; $__db_kind='mysqli'; break; }
        if ($$__v instanceof PDO)    { $conn = $$__v; $__db_kind='pdo';    break; }
    }
}
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (!isset($conn) || !is_object($conn)) {
    die("Database connection not available.");
}

// ---------- Helpers ----------
function db_fetch_one($conn, $sql, $params = array()) {
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
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}
function db_exec_safe($conn, $sql, $params = array()) {
    global $__db_kind;
    try {
        if ($__db_kind === 'mysqli') {
            if ($params) {
                $stmt = $conn->prepare($sql);
                if (!$stmt) return false;
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                return $stmt->execute();
            }
            return (bool)$conn->query($sql);
        }
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) { return false; }
}

// ---------- Constants ----------
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');
if (!defined('SITE_NAME'))       define('SITE_NAME', 'HelpGo');
$UPI_ID   = defined('UPI_ID')   ? UPI_ID   : 'muhammadjaleelpv1@okhdfcbank';
$UPI_NAME = defined('UPI_NAME') ? UPI_NAME : SITE_NAME;

// ---------- Load order ----------
$order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
if ($order_id === '' && isset($_POST['order_id'])) $order_id = trim($_POST['order_id']);
if ($order_id === '') die("Missing order id.");

$order = db_fetch_one($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1", array($order_id));
if (!$order) $order = db_fetch_one($conn, "SELECT * FROM orders WHERE id = ? LIMIT 1", array($order_id));
if (!$order) die("Order not found.");

$service    = strtolower((string)($order['service_type'] ?? $order['service'] ?? $order['type'] ?? 'petrol'));
$amount     = (float)($order['total_amount'] ?? $order['totalAmount'] ?? $order['amount'] ?? $order['grand_total'] ?? 0);
$pay_status = strtolower((string)($order['payment_status'] ?? $order['paymentStatus'] ?? $order['pay_status'] ?? ''));

// Use internal ID for reliable tracking
$internalId = $order['id'];

// If already paid, redirect directly to payment_confirm (which will then redirect to tracking)
if ($pay_status === 'paid') {
    header("Location: payment_confirm.php?order_id=" . $internalId);
    exit;
}

// ---------- POST handler ----------
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $utr = trim($_POST['utr'] ?? '');
    if ($utr === '') $err = 'Enter UTR / Reference number.';

    $proof_path = null;
    if (!$err) {
        if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
            $err = 'Attach payment screenshot (error code: ' . ($_FILES['screenshot']['error'] ?? '') . ')';
        } else {
            $f = $_FILES['screenshot'];
            if ($f['size'] > 5*1024*1024) { $err = 'Screenshot too large (max 5 MB).'; }
            else {
                $ext = 'jpg';
                if (function_exists('mime_content_type')) {
                    $mime = @mime_content_type($f['tmp_name']);
                    $map = array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');
                    if (isset($map[$mime])) $ext = $map[$mime];
                } else {
                    $orig = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    if (in_array($orig, array('jpg','jpeg','png','webp'))) $ext = ($orig==='jpeg'?'jpg':$orig);
                }
                $dir = __DIR__ . '/uploads/payments';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $fname = 'pay_' . preg_replace('/[^A-Za-z0-9_-]/','', $order_id) . '_' . time() . '.' . $ext;
                $dst = $dir . '/' . $fname;
                if (@move_uploaded_file($f['tmp_name'], $dst)) {
                    $proof_path = 'uploads/payments/' . $fname;
                } else { $err = 'Failed to save screenshot. Check folder permissions.'; }
            }
        }
    }

    if (!$err) {
        // ★ Use exact columns your admin panel reads
        $sql = "UPDATE orders
                SET payment_screenshot = ?,
                    payment_utr = ?,
                    payment_status = 'pending'
                WHERE id = ?";
        db_exec_safe($conn, $sql, array($proof_path, $utr, $internalId));

        // ★ Redirect to the waiting room (payment_confirm.php)
        header("Location: payment_confirm.php?order_id=" . $internalId);
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pay • <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
        body{margin:0;font-family:'Manrope',system-ui,sans-serif;background:radial-gradient(1200px 600px at -10% -10%,#0f3d2e 0%,transparent 60%),radial-gradient(900px 500px at 110% 0%,#0a5c3c 0%,transparent 55%),#04120c;color:#e8f3ec;min-height:100vh}
        .wrap{max-width:520px;margin:0 auto;padding:18px 16px 120px}
        .top{display:flex;align-items:center;gap:10px;margin-bottom:14px}
        .back{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);display:grid;place-items:center;color:#e8f3ec;text-decoration:none;font-size:18px}
        .title{font-family:'Sora';font-weight:700;font-size:18px;letter-spacing:.2px}
        .card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:18px;backdrop-filter:blur(10px);margin-bottom:14px}
        .amt{font-family:'Sora';font-weight:800;font-size:34px;color:#f5d67a;text-align:center;margin:6px 0 2px}
        .oid{text-align:center;font-size:12px;color:#a9c3b3;letter-spacing:.5px}
        .upi{display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(0,0,0,.25);border:1px dashed rgba(245,214,122,.4);border-radius:14px;padding:12px 14px;margin-top:14px}
        .upi b{font-family:'Sora';color:#f5d67a}
        .copy{background:#f5d67a;color:#0b2a1e;border:0;padding:8px 12px;border-radius:10px;font-weight:700;cursor:pointer}
        .qrbox{display:grid;place-items:center;margin:14px 0 4px}
        .qrbox img{width:200px;height:200px;border-radius:14px;background:#fff;padding:10px}
        label{display:block;font-size:13px;color:#bcd3c5;margin:14px 0 6px;font-weight:600}
        input[type=text],input[type=file]{width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.25);color:#e8f3ec;font:inherit}
        input[type=file]{padding:10px}
        .btn{width:100%;margin-top:16px;padding:14px;border-radius:14px;border:0;background:linear-gradient(135deg,#f5d67a,#c9a24a);color:#0b2a1e;font-weight:800;font-family:'Sora';font-size:16px;cursor:pointer;box-shadow:0 10px 24px rgba(245,214,122,.2)}
        .btn[disabled]{opacity:.7;cursor:wait}
        .err{background:rgba(255,80,80,.12);border:1px solid rgba(255,120,120,.3);color:#ffb4b4;padding:10px 12px;border-radius:12px;margin-bottom:12px;font-size:13px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <a class="back" href="orders.php">←</a>
        <div class="title">UPI Payment</div>
    </div>

    <div class="card">
        <div class="oid">ORDER <?php echo htmlspecialchars($order_id); ?></div>
        <div class="amt"><?php echo CURRENCY_SYMBOL . number_format($amount, 2); ?></div>
    </div>

    <?php if ($err): ?><div class="err"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card">
        <div style="font-family:Sora;font-weight:700;font-size:15px;margin-bottom:6px">Pay via UPI</div>
        <div class="upi">
            <div>UPI ID: <b id="upiId"><?php echo htmlspecialchars($UPI_ID); ?></b></div>
            <button class="copy" type="button" onclick="navigator.clipboard.writeText(document.getElementById('upiId').innerText);this.innerText='Copied'">Copy</button>
        </div>
        <div class="qrbox">
            <a href="<?php echo 'upi://pay?pa='.urlencode($UPI_ID).'&pn='.urlencode($UPI_NAME).'&am='.urlencode($amount).'&cu=INR&tn=Order'.urlencode($order_id); ?>">
                <img alt="UPI QR" src="assets/helpgo_qr.png" onerror="this.onerror=null;this.src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode('upi://pay?pa='.$UPI_ID.'&pn='.$UPI_NAME.'&am='.$amount.'&cu=INR&tn=Order'.$order_id); ?>';">
            </a>
        </div>
        <div style="text-align:center;margin-top:8px">
            <a class="btn" style="display:inline-block;width:auto;padding:10px 18px;text-decoration:none;margin:0" href="<?php echo 'upi://pay?pa='.urlencode($UPI_ID).'&pn='.urlencode($UPI_NAME).'&am='.urlencode($amount).'&cu=INR&tn=Order'.urlencode($order_id); ?>">Pay with UPI App</a>
        </div>
        <div style="text-align:center;color:#bcd3c5;font-size:12px">Scan with any UPI app • GPay / PhonePe / Paytm</div>
    </div>

    <form id="payForm" class="card" method="post" enctype="multipart/form-data" action="">
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
        <div style="font-family:Sora;font-weight:700;font-size:15px">Submit Payment Proof</div>
        <label>UTR / Reference Number</label>
        <input type="text" name="utr" placeholder="12-digit UTR" required>
        <label>Payment Screenshot (JPG/PNG, max 5MB)</label>
        <input type="file" name="screenshot" accept="image/*" required>
        <button id="submitBtn" class="btn" type="submit">Submit for Confirmation</button>
    </form>
    <script>
        document.getElementById('payForm').addEventListener('submit', function(){
            var b = document.getElementById('submitBtn');
            b.disabled = true; b.innerText = 'Submitting…';
        });
    </script>
</div>
</body>
</html>