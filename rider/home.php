<?php
require_once '../config.php';
if (!isRider()) { redirect('../index.php'); }

$riderId = (int)$_SESSION['user_id'];

// Toggle online/offline
if (isset($_POST['toggle_online'])) {
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM riders WHERE user_id = $riderId"))['status'];
    $new = ($current == 'online') ? 'offline' : 'online';
    mysqli_query($conn, "UPDATE riders SET status = '$new' WHERE user_id = $riderId");
    header("Location: home.php"); exit;
}

// Accept a ready shop order directly from home
if (isset($_GET['accept']) && isset($_GET['id'])) {
    $orderId = sanitize($_GET['id']);
    $check = mysqli_query($conn, "SELECT id FROM orders WHERE order_id = '$orderId' AND status = 'ready' AND rider_id IS NULL AND service_type = 'store_delivery'");
    if (mysqli_num_rows($check) > 0) {
        $riderInternalId = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM riders WHERE user_id = $riderId"))['id'];
        mysqli_query($conn, "UPDATE orders SET rider_id = $riderInternalId, status = 'accepted', store_order_status = 'accepted_by_rider' WHERE order_id = '$orderId'");
        header("Location: order.php?id=$orderId");
        exit;
    }
}

// Reject order (petrol/grocery)
if (isset($_POST['reject_order']) && isset($_POST['order_id'])) {
    $oid = sanitize($_POST['order_id']);
    $exists = mysqli_query($conn, "SELECT id FROM order_rejections WHERE rider_id = $riderId AND order_id = '$oid'");
    if (mysqli_num_rows($exists) == 0) {
        mysqli_query($conn, "INSERT INTO order_rejections (rider_id, order_id) VALUES ($riderId, '$oid')");
    }
    header("Location: home.php"); exit;
}

// Update location
if (isset($_POST['update_location']) && isset($_POST['lat'])) {
    $lat = sanitize($_POST['lat']);
    $lng = sanitize($_POST['lng']);
    mysqli_query($conn, "UPDATE riders SET current_latitude = '$lat', current_longitude = '$lng' WHERE user_id = $riderId");
}

$riderData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM riders WHERE user_id = $riderId"));
$wallet = getWalletBalance($riderId);

function renderActiveOrders($conn, $riderId) {
    $q = mysqli_query($conn, "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
        FROM orders o JOIN users u ON o.user_id = u.id
        WHERE o.rider_id = (SELECT id FROM riders WHERE user_id = $riderId)
        AND o.status NOT IN ('delivered', 'completed', 'cancelled')
        ORDER BY o.id DESC");
    if (!$q || mysqli_num_rows($q) == 0) {
        echo '<div class="empty-state">No active orders.</div>';
        return;
    }
    while ($active = mysqli_fetch_assoc($q)) {
        $pay = isset($active['payment_status']) ? $active['payment_status'] : '';
        $method = isset($active['payment_method']) ? $active['payment_method'] : '';
        $payBadge = '';
        if ($method == 'upi') {
            if ($pay == 'paid') $payBadge = '<span class="pay-badge pay-paid"><i class="fas fa-check-circle"></i> Paid</span>';
            else if ($pay == 'pending') $payBadge = '<span class="pay-badge pay-pending"><i class="fas fa-hourglass-half"></i> Awaiting Payment</span>';
            else $payBadge = '<span class="pay-badge pay-pending"><i class="fas fa-clock"></i> UPI</span>';
        } else if ($method == 'cash') {
            $payBadge = '<span class="pay-badge pay-cash"><i class="fas fa-money-bill"></i> Cash</span>';
        }
        ?>
        <div class="order-card">
            <div class="order-info">
                <h4><?= ucfirst($active['service_type']) ?>
                    <span class="status-badge status-<?= $active['status'] ?>"><?= ucfirst(str_replace('_',' ',$active['status'])) ?></span>
                </h4>
                <p><?= htmlspecialchars($active['customer_name']) ?> · <?= htmlspecialchars($active['customer_phone']) ?></p>
                <p style="color:var(--primary); font-weight:500;">₹<?= number_format($active['total_amount'], 2) ?> <?= $payBadge ?></p>
            </div>
            <div class="order-actions">
                <a href="order.php?id=<?= $active['order_id'] ?>" class="btn btn-track">Track / Update</a>
            </div>
        </div>
        <?php
    }
}

// AJAX endpoint for active orders refresh
if (isset($_GET['ajax']) && $_GET['ajax'] == 'active') {
    renderActiveOrders($conn, $riderId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Rider Dashboard – HelpGo</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
:root { --bg:#0f1117; --surface:#1a1d27; --primary:#FF6B35; --green:#2ED573; --danger:#FF4757; --text:#fff; --text-secondary:#9aa0b0; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:20px 16px 100px; }
.container { max-width:500px; margin:0 auto; }

.back-button {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--surface); border: 1px solid rgba(255,255,255,0.1);
    color: var(--text-secondary); font-size: 18px;
    cursor: pointer; transition: 0.2s; margin-right: 12px;
}
.back-button:hover { background: #252835; color: #fff; }

.header { display:flex; align-items:center; margin-bottom:30px; }
.header-left { display:flex; align-items:center; gap:12px; flex:1; }
.greeting { display:flex; flex-direction:column; }
.greeting h2 { font-size:22px; font-weight:700; }
.greeting span { font-size:13px; color:var(--text-secondary); display:block; }
.avatar-status { position:relative; margin-left: auto; }
.avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#ff8c5a); display:flex; align-items:center; justify-content:center; font-size:24px; }
.online-indicator { position:absolute; bottom:0; right:0; width:16px; height:16px; border-radius:50%; border:3px solid var(--bg); background:var(--green); }
.offline-indicator { background:#555; }
.toggle-card { background:var(--surface); border-radius:20px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; cursor:pointer; }
.toggle-label { font-weight:600; font-size:16px; display:flex; align-items:center; gap:8px; }
.toggle-switch { width:52px; height:28px; border-radius:30px; background:#555; position:relative; transition:0.3s; }
.toggle-switch::after { content:''; position:absolute; top:4px; left:4px; width:20px; height:20px; border-radius:50%; background:#fff; transition:0.3s; }
.toggle-on .toggle-switch { background:var(--green); }
.toggle-on .toggle-switch::after { left:28px; }
.wallet-card { background:var(--surface); border-radius:20px; padding:18px 20px; display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.wallet-card h3 { font-size:24px; font-weight:700; color:var(--primary); }
.wallet-card small { color:var(--text-secondary); }
.wallet-btn { background:var(--primary); color:#fff; padding:10px 20px; border-radius:12px; text-decoration:none; font-weight:600; }
.chip-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; }
.chip { background:var(--surface); border-radius:50px; padding:8px 16px; font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none; color:var(--text); }
.chip i { color:var(--primary); }
.section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.section-header h3 { font-size:18px; font-weight:700; }
.live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--green); margin-right:6px; animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
.order-card { background:var(--surface); border-radius:18px; padding:16px; margin-bottom:14px; }
.order-info h4 { font-size:16px; font-weight:600; text-transform:capitalize; }
.order-info p { font-size:12px; color:var(--text-secondary); margin-top:4px; }
.order-actions { display:flex; gap:10px; margin-top:12px; }
.btn { flex:1; padding:10px; border-radius:12px; font-weight:600; font-size:14px; border:none; cursor:pointer; text-align:center; text-decoration:none; }
.btn-accept { background:var(--green); color:#000; }
.btn-reject { background:transparent; border:1px solid var(--danger); color:var(--danger); }
.btn-track { background:var(--primary); color:#fff; }
.status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; text-transform:capitalize; margin-left:6px; }
.status-accepted { background:rgba(0,119,182,0.2); color:#0077b6; }
.status-picked_up, .status-in_transit { background:rgba(187,134,252,0.2); color:#bb86fc; }
.status-pending { background:rgba(255,193,7,0.2); color:#ffc107; }
.pay-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; margin-left:6px; }
.pay-paid { background:rgba(46,213,115,0.15); color:#2ED573; }
.pay-pending { background:rgba(255,193,7,0.15); color:#ffc107; }
.pay-cash { background:rgba(154,160,176,0.15); color:#9aa0b0; }
.empty-state { text-align:center; padding:20px; color:var(--text-secondary); }
.bottom-nav { position:fixed; bottom:0; left:0; width:100%; background:var(--surface); border-top:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-around; padding:12px 0; z-index:999; }
.nav-item { display:flex; flex-direction:column; align-items:center; color:var(--text-secondary); text-decoration:none; font-size:11px; }
.nav-item i { font-size:20px; margin-bottom:2px; }
.nav-item.active { color:var(--primary); }

.popup-overlay { position:fixed; bottom:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:1001; display:flex; align-items:flex-end; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.3s; }
.popup-overlay.active { opacity:1; pointer-events:all; }
.popup-card { background:linear-gradient(145deg,#1a1d27 0%,#14161f 100%); border-radius:28px 28px 0 0; width:100%; max-width:500px; padding:24px 24px 32px; transform:translateY(100%); transition:transform 0.5s cubic-bezier(0.22,0.61,0.36,1); border:1px solid rgba(255,255,255,0.1); position:relative; overflow:hidden; }
.popup-overlay.active .popup-card { transform:translateY(0); }
.popup-handle { width:40px; height:5px; background:rgba(255,255,255,0.15); border-radius:3px; margin:0 auto 24px; }
.popup-icon { width:64px; height:64px; border-radius:50%; background:rgba(255,107,53,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:28px; color:var(--primary); }
.popup-details { text-align:center; margin-bottom:24px; }
.popup-details .service { font-size:22px; font-weight:700; text-transform:capitalize; margin-bottom:8px; }
.popup-details .address { font-size:14px; color:var(--text-secondary); margin-bottom:12px; display:flex; align-items:center; justify-content:center; gap:6px; }
.popup-details .fare { font-size:28px; font-weight:800; color:var(--primary); background:rgba(255,107,53,0.1); display:inline-block; padding:6px 20px; border-radius:30px; margin-top:8px; }
.popup-slider { position:relative; width:100%; height:64px; background:rgba(255,255,255,0.05); border-radius:40px; border:1px solid rgba(255,255,255,0.1); overflow:hidden; user-select:none; touch-action:pan-y; }
.popup-slider .slide-text { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); font-weight:600; font-size:16px; color:var(--text-secondary); pointer-events:none; white-space:nowrap; }
.popup-slider .slide-thumb { position:absolute; left:6px; top:50%; width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#ff8c5a); display:flex; align-items:center; justify-content:center; cursor:grab; z-index:2; box-shadow:0 8px 25px rgba(255,107,53,0.5); transform:translateY(-50%); }
.popup-slider .slide-thumb i { color:#fff; font-size:22px; }
.popup-slider .slide-progress { position:absolute; top:0; left:0; height:100%; background:linear-gradient(90deg,transparent,rgba(255,107,53,0.2)); width:0%; }
.popup-dismiss { position:absolute; top:14px; right:18px; background:transparent; border:none; color:var(--text-secondary); font-size:22px; cursor:pointer; z-index:3; }
.soft-toast { position:fixed; left:50%; bottom:92px; transform:translate(-50%,18px); background:rgba(26,29,39,0.96); color:#fff; border:1px solid rgba(255,255,255,0.12); box-shadow:0 18px 45px rgba(0,0,0,0.35); border-radius:16px; padding:12px 16px; font-size:13px; font-weight:600; z-index:2000; opacity:0; pointer-events:none; transition:opacity .25s ease, transform .25s ease; max-width:calc(100% - 32px); text-align:center; }
.soft-toast.show { opacity:1; transform:translate(-50%,0); }

/* Additional styles for shop orders */
.store-name { font-weight:700; font-size:16px; }
.order-id { color:var(--text-secondary); font-size:12px; }
.customer-info, .address { font-size:13px; color:var(--text-secondary); margin-bottom:6px; display:flex; align-items:center; gap:6px; }
.items { font-size:13px; color:var(--text); margin-bottom:8px; }
.amount { font-weight:700; color:var(--primary); font-size:18px; margin-bottom:12px; }
.top-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-left">
            <button class="back-button" onclick="history.back()" aria-label="Go back"><i class="fas fa-arrow-left"></i></button>
            <div class="greeting">
                <span>Good day,</span>
                <h2><?= htmlspecialchars($_SESSION['user_name']) ?></h2>
            </div>
        </div>
        <div class="avatar-status">
            <div class="avatar"><i class="fas fa-user"></i></div>
            <div class="online-indicator <?= $riderData['status']=='offline'?'offline-indicator':'' ?>"></div>
        </div>
    </div>

    <form method="POST" id="toggleForm">
        <div class="toggle-card <?= $riderData['status']=='online'?'toggle-on':'' ?>" onclick="document.getElementById('toggleForm').submit();">
            <span class="toggle-label">
                <i class="fas <?= $riderData['status']=='online'?'fa-check-circle':'fa-power-off' ?>" style="color:<?= $riderData['status']=='online'?'var(--green)':'#555' ?>"></i>
                <?= $riderData['status']=='online'?'You are online':'You are offline' ?>
            </span>
            <div class="toggle-switch"></div>
        </div>
        <input type="hidden" name="toggle_online" value="1">
    </form>

    <div class="wallet-card">
        <div>
            <small>Wallet Balance</small>
            <h3>₹<?= number_format($wallet, 2) ?></h3>
        </div>
        <a href="wallet.php" class="wallet-btn">Withdraw</a>
    </div>

    <div class="chip-row">
        <?php if ($riderData['status']=='online'): ?>
        <div class="chip" onclick="getLocation()"><i class="fas fa-location-crosshairs"></i> Update Location</div>
        <?php endif; ?>
        <a href="shop-order.php" class="chip"><i class="fas fa-store"></i> Shop Orders</a>
        <a href="petrol_orders.php" class="chip"><i class="fas fa-gas-pump"></i> Petrol</a>
        <a href="grocery_orders.php" class="chip"><i class="fas fa-shopping-basket"></i> Grocery</a>
        <a href="wallet.php" class="chip"><i class="fas fa-wallet"></i> Wallet</a>
    </div>

    <!-- ===== Ready Shop Orders Section (appears only when online) ===== -->
    <?php if ($riderData['status'] == 'online'): 
        $readyStoreOrders = mysqli_query($conn, "
            SELECT o.*, s.name AS store_name, s.location AS store_location,
                   u.full_name AS customer_name, u.phone AS customer_phone
            FROM orders o
            JOIN stores s ON o.store_id = s.id
            JOIN users u ON o.user_id = u.id
            WHERE o.service_type = 'store_delivery'
              AND o.status = 'ready'
              AND o.rider_id IS NULL
            ORDER BY o.id ASC
        ");
        if (mysqli_num_rows($readyStoreOrders) > 0):
    ?>
        <div class="section-header" style="margin-top:24px;">
            <h3>🏪 Ready Shop Orders</h3>
        </div>
        <?php while ($ord = mysqli_fetch_assoc($readyStoreOrders)): ?>
        <div class="order-card">
            <div class="top-row">
                <span class="store-name"><?= htmlspecialchars($ord['store_name']) ?></span>
                <span class="order-id">#<?= htmlspecialchars($ord['order_id']) ?></span>
            </div>
            <div class="customer-info">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($ord['customer_name']) ?> · <?= htmlspecialchars($ord['customer_phone']) ?>
            </div>
            <div class="address">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($ord['drop_address']) ?>
            </div>
            <div class="items">
                <i class="fas fa-shopping-basket"></i>
                <?= htmlspecialchars($ord['product_details'] ?? 'Items not listed') ?>
            </div>
            <div class="amount">₹<?= number_format($ord['total_amount'], 2) ?></div>
            <a href="home.php?accept=1&id=<?= urlencode($ord['order_id']) ?>" class="btn btn-accept" style="display:inline-block; padding:10px 20px; border-radius:12px; background:var(--green); color:#000; font-weight:600; text-decoration:none;">
                <i class="fas fa-check"></i> Accept Order
            </a>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
    <?php endif; ?>

    <div class="section-header">
        <h3><span class="live-dot"></span>My Active Orders</h3>
        <span style="font-size:12px; color:var(--text-secondary);" id="lastUpdate">Live</span>
    </div>
    <div id="activeOrdersList">
        <?php renderActiveOrders($conn, $riderId); ?>
    </div>

    <div class="section-header" style="margin-top:24px;">
        <h3>Nearby Orders</h3>
        <span style="font-size:13px; color:var(--text-secondary);"><?= $riderData['status']=='online'?'Accept or reject':'Go online to view' ?></span>
    </div>
    <div id="pendingOrdersList">
        <?php if ($riderData['status']=='online'):
            $ordersQuery = mysqli_query($conn, "SELECT o.* FROM orders o
                WHERE o.status='pending' AND o.rider_id IS NULL
                AND NOT EXISTS (SELECT 1 FROM order_rejections r WHERE r.order_id=o.order_id AND r.rider_id=$riderId)
                ORDER BY o.id DESC LIMIT 20");
            if (mysqli_num_rows($ordersQuery)>0):
                while ($ord = mysqli_fetch_assoc($ordersQuery)): ?>
                <div class="order-card">
                    <div class="order-info">
                        <h4><?= ucfirst($ord['service_type']) ?></h4>
                        <p><?= $ord['drop_address'] ? substr($ord['drop_address'],0,60).'...' : 'N/A' ?></p>
                    </div>
                    <div class="order-actions">
                        <a href="order_detail.php?id=<?= $ord['order_id'] ?>" class="btn btn-accept">Accept</a>
                        <form method="POST" style="flex:1;" onsubmit="return confirm('Reject this order?')">
                            <input type="hidden" name="order_id" value="<?= $ord['order_id'] ?>">
                            <button type="submit" name="reject_order" class="btn btn-reject" style="width:100%;">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="empty-state">No pending orders right now.</div>
            <?php endif;
        else: ?>
            <div class="empty-state">Go online to see orders.</div>
        <?php endif; ?>
    </div>
</div>

<div class="popup-overlay" id="popupOverlay">
    <div class="popup-card">
        <button class="popup-dismiss" onclick="dismissPopup()" aria-label="Close"><i class="fas fa-times"></i></button>
        <div class="popup-handle"></div>
        <div class="popup-icon"><i class="fas fa-motorcycle"></i></div>
        <div class="popup-details" id="popupDetails"></div>
        <div class="popup-slider" id="popupSlider">
            <div class="slide-progress" id="popupProgress"></div>
            <span class="slide-text" id="popupSlideText">Slide to Accept</span>
            <div class="slide-thumb" id="popupThumb"><i class="fas fa-chevron-right"></i></div>
        </div>
    </div>
</div>

<div id="softToast" class="soft-toast"></div>

<nav class="bottom-nav">
    <a href="home.php" class="nav-item active"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="shop-order.php" class="nav-item"><i class="fas fa-store"></i><span>Shop Orders</span></a>
    <a href="petrol_orders.php" class="nav-item"><i class="fas fa-gas-pump"></i><span>Petrol</span></a>
    <a href="grocery_orders.php" class="nav-item"><i class="fas fa-shopping-basket"></i><span>Grocery</span></a>
    <a href="wallet.php" class="nav-item"><i class="fas fa-wallet"></i><span>Wallet</span></a>
</nav>

<form method="POST" id="locForm" style="display:none;">
    <input type="hidden" name="update_location" value="1">
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">
</form>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('lat').value = pos.coords.latitude;
            document.getElementById('lng').value = pos.coords.longitude;
            document.getElementById('locForm').submit();
        });
    }
}

function playChime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        [523.25, 659.25].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine'; osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.15, now + i*0.15);
            gain.gain.exponentialRampToValueAtTime(0.001, now + i*0.15 + 0.3);
            osc.connect(gain); gain.connect(ctx.destination);
            osc.start(now + i*0.15); osc.stop(now + i*0.15 + 0.3);
        });
    } catch(e) {}
}

/* ===== AUTO REFRESH ACTIVE ORDERS EVERY 3s ===== */
let lastActiveHTML = document.getElementById('activeOrdersList').innerHTML;
async function refreshActive() {
    try {
        const res = await fetch('home.php?ajax=active&_=' + Date.now());
        const html = await res.text();
        if (html && html !== lastActiveHTML) {
            const prevHadPaidBadge = /pay-paid/.test(lastActiveHTML);
            const nowHasPaidBadge = /pay-paid/.test(html);
            document.getElementById('activeOrdersList').innerHTML = html;
            lastActiveHTML = html;
            if (!prevHadPaidBadge && nowHasPaidBadge) playChime();
        }
        document.getElementById('lastUpdate').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch(e) {}
}
setInterval(refreshActive, 3000);

/* ===== POPUP + SLIDER (unchanged) ===== */
let knownOrderIds = new Set();
document.querySelectorAll('#pendingOrdersList .order-card a[href*="order_detail.php"]').forEach(link => {
    const url = new URL(link.href, window.location.origin);
    knownOrderIds.add(String(url.searchParams.get('id')));
});

const popupOverlay = document.getElementById('popupOverlay');
const popupDetails = document.getElementById('popupDetails');
let soundInterval = null, popupActive = false, currentPopupOrder = null;
const riderStatus = "<?= $riderData['status'] ?>";

function stopSound(){ if(soundInterval){clearInterval(soundInterval); soundInterval=null;} }
function startAlertSound(){ if(riderStatus!=='online')return; stopSound(); playChime(); soundInterval=setInterval(playChime,2000); }
function dismissPopup(){ popupOverlay.classList.remove('active'); popupActive=false; currentPopupOrder=null; stopSound(); resetPopupSlider(); }

const popupSliderEl = document.getElementById('popupSlider');
const popupThumb = document.getElementById('popupThumb');
const popupProgress = document.getElementById('popupProgress');
const popupSlideText = document.getElementById('popupSlideText');
let sliderDragging=false, sliderStartX=0, currentTranslate=0;
const getMaxTranslate = () => popupSliderEl.offsetWidth - popupThumb.offsetWidth - 12;

function animateSlider(t){
    t = Math.min(getMaxTranslate(), Math.max(0,t)); currentTranslate=t;
    popupThumb.style.transform = `translate(${t}px, -50%)`;
    const pct = (t/getMaxTranslate())*100;
    popupProgress.style.width = pct+'%';
    if (pct>=90){ popupSlideText.textContent='Release to Accept'; popupThumb.style.background='linear-gradient(135deg,#2ED573,#1a8f42)'; }
    else { popupSlideText.textContent='Slide to Accept'; popupThumb.style.background='linear-gradient(135deg,#FF6B35,#E55A2B)'; }
}
function resetPopupSlider(){ animateSlider(0); }
function snapBack(){ popupThumb.style.transition='transform 0.4s cubic-bezier(0.25,0.46,0.45,0.94)'; animateSlider(0); setTimeout(()=>{popupThumb.style.transition='transform 0.1s linear';},400); }
popupThumb.addEventListener('pointerdown', e=>{ e.preventDefault(); sliderDragging=true; sliderStartX=e.clientX-currentTranslate; popupThumb.setPointerCapture(e.pointerId); popupThumb.style.transition='none'; });
window.addEventListener('pointermove', e=>{ if(!sliderDragging)return; e.preventDefault(); requestAnimationFrame(()=>animateSlider(e.clientX-sliderStartX)); });
window.addEventListener('pointerup', ()=>{ if(!sliderDragging)return; sliderDragging=false; if(currentTranslate>=getMaxTranslate()*0.9) acceptOrder(currentPopupOrder); else snapBack(); });
popupSliderEl.addEventListener('touchstart', e=>e.preventDefault());

let toastTimer = null;
function showSoftToast(message){
    const toast = document.getElementById('softToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
}

async function acceptOrder(order){
    if(!order)return;
    popupThumb.style.pointerEvents='none';
    try {
        const res = await fetch('ajax_accept.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'order_id=' + encodeURIComponent(order.order_id)
        });
        const result = await res.json().catch(() => ({ success:false, message:'' }));
        if (result.success) {
            window.location.href = 'order.php?id=' + encodeURIComponent(result.order_id || order.order_id);
            return;
        }
        dismissPopup();
        if (typeof refreshActive === 'function') refreshActive();
        showSoftToast('This order is no longer available.');
    } catch(err){
        dismissPopup();
        showSoftToast('Network issue. Please try again.');
    }
    finally { popupThumb.style.pointerEvents='auto'; }
}

function showPopup(order){
    if (riderStatus!=='online') return;
    if (popupActive) dismissPopup();
    currentPopupOrder = order;
    popupDetails.innerHTML = `<div class="service">${order.service_type}</div><div class="address"><i class="fas fa-map-marker-alt"></i> ${order.drop_address?order.drop_address.substring(0,80):'Address not available'}</div><div class="fare">₹${order.delivery_fare}</div>`;
    popupOverlay.classList.add('active'); popupActive=true;
    resetPopupSlider(); startAlertSound();
}
popupOverlay.addEventListener('click', e=>{ if(e.target===popupOverlay) dismissPopup(); });
window.addEventListener('load', ()=>dismissPopup());

async function pollNewOrders(){
    if (riderStatus!=='online'){ if(popupActive) dismissPopup(); return; }
    try {
        const res = await fetch('ajax_pending_orders.php?_=' + Date.now());
        const data = await res.json();
        const orders = Array.isArray(data.orders) ? data.orders : [];
        const currentIds = new Set(orders.map(o => String(o.order_id)));
        if (popupActive && currentPopupOrder && !currentIds.has(String(currentPopupOrder.order_id))) {
            dismissPopup();
        }
        orders.forEach(order=>{
            const orderId = String(order.order_id);
            if (!knownOrderIds.has(orderId)){
                knownOrderIds.add(orderId);
                if (!popupActive) showPopup(order);
            }
        });
    } catch(err){}
}
setInterval(pollNewOrders, 1000);
</script>
</body>
</html>