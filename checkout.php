<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$user = getUserData($_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];

$storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if ($storeId <= 0) { header("Location: /cart"); exit; }

$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, logo, payment_methods, upi_id, slug FROM stores WHERE id = $storeId"));
if (!$store) die("Store not found.");

$deliveryFee = 25;
$feeRes = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'delivery_fee' LIMIT 1");
if ($feeRes && ($row = mysqli_fetch_assoc($feeRes))) $deliveryFee = (float)$row['setting_value'];

$logoFile = $store['logo'] ?? '';
$logoUrl = (!empty($logoFile))
    ? (filter_var($logoFile, FILTER_VALIDATE_URL) ? $logoFile : 'assets/storebanner/' . $logoFile)
    : 'https://placehold.co/56x56/0B3B2E/D4AF37?text=S';

$paymentMethods = array_map('trim', explode(',', $store['payment_methods'] ?? 'upi,cod'));
$upiId = $store['upi_id'] ?? '';
$storeSlug = $store['slug'] ?? '';

$mapAddr = isset($_GET['address']) ? $_GET['address'] : '';
$defaultAddr = $mapAddr ?: ($user['address'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout – <?= htmlspecialchars($store['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --emerald-dark: #04261F; --emerald: #083C33; --emerald-light: #0E5548;
            --gold: #D4AF37; --gold-light: #E8C84A; --gold-dark: #B8962E;
            --text: #F4EEDC; --text-muted: #9DB3A8;
            --glass-bg: rgba(8, 60, 51, 0.6); --glass-border: rgba(212, 175, 55, 0.2);
            --radius: 22px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',sans-serif; background: radial-gradient(ellipse at 20% 0%, var(--emerald-light), var(--emerald-dark) 70%, var(--emerald)); color: var(--text); display:flex; justify-content:center; min-height:100vh; padding-bottom:140px; }
        .app { width:100%; max-width:430px; padding:20px 16px; }
        .topbar { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
        .back-btn { width:44px; height:44px; border-radius:14px; background:var(--glass-bg); backdrop-filter:blur(16px); border:1px solid var(--glass-border); display:flex; align-items:center; justify-content:center; color:var(--gold); font-size:18px; text-decoration:none; }
        .card { background:var(--glass-bg); backdrop-filter:blur(20px); border:1px solid var(--glass-border); border-radius:var(--radius); padding:18px; margin-bottom:18px; }
        .rest-header { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .rest-logo { width:56px; height:56px; border-radius:50%; border:2px solid var(--gold); object-fit:cover; }
        .item-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
        .qty-controls { display:flex; align-items:center; gap:12px; background:rgba(0,0,0,0.35); border:1px solid var(--glass-border); border-radius:30px; padding:6px 10px; }
        .qty-btn { width:28px; height:28px; border-radius:50%; background:var(--gold); color:#04261F; border:none; font-weight:800; font-size:16px; cursor:pointer; }
        .payment-card { flex:1 1 45%; background:rgba(0,0,0,0.3); border:2px solid var(--glass-border); border-radius:18px; padding:16px; cursor:pointer; text-align:center; }
        .payment-card.selected { background:rgba(212,175,55,0.1); border-color:var(--gold); }
        .address-field, .input-field {
            width:100%; padding:14px 16px; background:rgba(0,0,0,0.3);
            border:1px solid var(--glass-border); border-radius:16px;
            color:var(--text); font-family:inherit; font-size:15px; margin-bottom:10px;
            resize:vertical; min-height:90px;
        }
        .input-field { min-height: auto; resize:none; }
        .bottom-bar { position:fixed; bottom:0; left:50%; transform:translateX(-50%); width:100%; max-width:430px; background:rgba(8,60,51,0.9); backdrop-filter:blur(30px); border-top:1px solid var(--glass-border); padding:16px; display:flex; align-items:center; justify-content:space-between; border-radius:24px 24px 0 0; z-index:100; }
        .checkout-btn { background:linear-gradient(135deg,var(--gold),var(--gold-dark)); color:#04261F; border:none; padding:16px 28px; border-radius:30px; font-weight:800; font-size:16px; cursor:pointer; }
        .error-msg { background:rgba(255,71,87,0.1); color:#FF6B6B; padding:14px; border-radius:14px; margin-top:14px; display:none; }
    </style>
</head>
<body>
<div class="app">
    <div class="topbar">
        <a href="/store/<?= urlencode($storeSlug) ?>/cart" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div><h2 style="color:var(--gold);">Checkout</h2><p style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($store['name']) ?></p></div>
    </div>
    <div id="checkoutContent" style="text-align:center; padding:40px; color:var(--text-muted);">Loading…</div>
</div>

<div class="bottom-bar" id="bottomBar" style="display:none;">
    <div><span style="font-size:12px; color:var(--text-muted);">Grand Total</span><br><span id="bottomTotal" style="font-size:22px; font-weight:800; color:var(--gold);">₹0</span></div>
    <button class="checkout-btn" id="placeOrderBtn" onclick="placeOrder()">Place Order <i class="fas fa-arrow-right"></i></button>
</div>

<script>
const STORE_ID = <?= $storeId ?>;
const DELIVERY_FEE = <?= $deliveryFee ?>;
const STORE_LOGO = "<?= htmlspecialchars($logoUrl) ?>";
const STORE_NAME = "<?= htmlspecialchars($store['name']) ?>";
const PAYMENT_METHODS = <?= json_encode($paymentMethods) ?>;
const UPI_ID = "<?= htmlspecialchars($upiId) ?>";
const STORE_SLUG = "<?= urlencode($storeSlug) ?>";
const DEFAULT_ADDR = <?= json_encode($defaultAddr) ?>;

let selectedPayment = PAYMENT_METHODS.includes('upi') ? 'upi' : 'cod';
let currentAddress = DEFAULT_ADDR;

function loadCart() {
    try { return JSON.parse(localStorage.getItem(`helpgo_cart_${STORE_ID}`)) || []; } catch(e) { return []; }
}

function renderCheckout() {
    const container = document.getElementById('checkoutContent');
    const items = loadCart();
    if (!items.length) {
        container.innerHTML = `<div class="card" style="text-align:center;">Cart empty. <a href="/store/${STORE_SLUG}/cart" style="color:var(--gold);">Back</a></div>`;
        return;
    }
    let subtotal = 0, html = `<div class="card"><div class="rest-header"><img src="${STORE_LOGO}" class="rest-logo" onerror="this.src='https://placehold.co/56x56/0B3B2E/D4AF37?text=S'"><div><b>${STORE_NAME}</b><br><span style="color:#4ADE80;">⏳ ~20 min</span></div></div>`;
    items.forEach((item, idx) => {
        const line = item.price * item.qty; subtotal += line;
        html += `<div class="item-row"><span>${item.name}</span><div class="qty-controls"><button class="qty-btn" onclick="changeQty(${idx},-1)">−</button><span>${item.qty}</span><button class="qty-btn" onclick="changeQty(${idx},1)">+</button></div><span style="color:var(--gold);">₹${line.toFixed(2)}</span></div>`;
    });
    html += `</div>`;

    html += `<div class="card"><h3 style="margin-bottom:10px;">Payment</h3><div style="display:flex; gap:10px;">`;
    if (PAYMENT_METHODS.includes('upi')) html += `<div class="payment-card ${selectedPayment==='upi'?'selected':''}" onclick="selectPayment('upi')"><i class="fas fa-mobile-alt" style="font-size:24px; color:var(--gold);"></i><br>UPI</div>`;
    if (PAYMENT_METHODS.includes('cod')) html += `<div class="payment-card" style="opacity:0.5;" onclick="alert('Cash on Delivery not available.')"><i class="fas fa-money-bill-wave" style="font-size:24px; color:var(--gold);"></i><br>COD (off)</div>`;
    html += `</div></div>`;

    const finalTotal = subtotal + DELIVERY_FEE;
    html += `<div class="card"><h3 style="margin-bottom:10px;">Summary</h3><div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>₹${subtotal.toFixed(2)}</span></div><div style="display:flex;justify-content:space-between;"><span>Delivery</span><span>₹${DELIVERY_FEE.toFixed(2)}</span></div><div style="display:flex;justify-content:space-between; margin-top:12px; border-top:1px solid var(--gold); padding-top:12px;"><b>Total</b><b style="color:var(--gold);">₹${finalTotal.toFixed(2)}</b></div></div>`;

    // ★ Address + Pincode fields
    html += `<div class="card"><h3 style="margin-bottom:10px;">Delivery Address</h3>
        <textarea class="address-field" id="address" placeholder="Full address…" oninput="currentAddress=this.value">${currentAddress}</textarea>
        <input type="text" class="input-field" id="pincode" placeholder="Pincode (6 digits)" maxlength="6" pattern="\d{6}" inputmode="numeric">
        <div style="display:flex; gap:10px; margin-top:10px;">
            <button class="qty-btn" style="width:auto; padding:10px 20px;" onclick="getCurrentLocation()"><i class="fas fa-map-marker-alt"></i> GPS</button>
            <a class="qty-btn" style="width:auto; padding:10px 20px; text-decoration:none; background:var(--gold); color:#04261F;" href="/map_picker.php?return=checkout&store_id=<?= $storeId ?>">🗺️ Map</a>
        </div>
        <span id="locStatus" style="font-size:12px;"></span>
        <input type="text" class="input-field" id="phone" placeholder="Phone number" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" style="margin-top:10px;">
        <input type="hidden" id="lat"><input type="hidden" id="lng">
    </div>
    <div id="orderError" class="error-msg"></div>`;

    container.innerHTML = html;
    document.getElementById('bottomTotal').textContent = '₹' + finalTotal.toFixed(2);
    document.getElementById('bottomBar').style.display = 'flex';
}

function changeQty(idx, delta) {
    let cart = loadCart();
    if (cart[idx]) {
        cart[idx].qty += delta;
        if (cart[idx].qty <= 0) cart.splice(idx,1);
        localStorage.setItem(`helpgo_cart_${STORE_ID}`, JSON.stringify(cart));
        renderCheckout();
    }
}

function selectPayment(method) {
    selectedPayment = method;
    document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.payment-card:not([style*="opacity"])`).classList.add('selected');
}

function getCurrentLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('lat').value = pos.coords.latitude;
        document.getElementById('lng').value = pos.coords.longitude;
        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${pos.coords.latitude}&lon=${pos.coords.longitude}`)
            .then(r => r.json())
            .then(d => {
                if (d.display_name) {
                    document.getElementById('address').value = d.display_name;
                    currentAddress = d.display_name;
                    // Try to extract pincode from address
                    const pc = d.address?.postcode || '';
                    if (pc) document.getElementById('pincode').value = pc;
                }
            });
    });
}

async function placeOrder() {
    const address = currentAddress || document.getElementById('address')?.value.trim() || '';
    const pincode = document.getElementById('pincode')?.value.trim() || '';
    const phone = document.getElementById('phone')?.value.trim() || '';

    console.log('Address:', address);
    console.log('Pincode:', pincode);

    if (!address) { alert('Enter address.'); return; }
    if (!pincode || !/^\d{6}$/.test(pincode)) { alert('Enter a valid 6-digit pincode.'); return; }
    if (!phone) { alert('Enter phone.'); return; }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true; btn.innerHTML = 'Placing…';

    const fd = new FormData();
    fd.append('store_id', STORE_ID);
    fd.append('address', address);
    fd.append('delivery_address', address);   // legacy
    fd.append('pincode', pincode);
    fd.append('phone', phone);
    fd.append('payment_method', selectedPayment);
    fd.append('cart_items', JSON.stringify(loadCart()));
    fd.append('lat', document.getElementById('lat').value);
    fd.append('lng', document.getElementById('lng').value);

    try {
        const res = await fetch('/place_order.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            localStorage.removeItem(`helpgo_cart_${STORE_ID}`);
            window.location.href = data.redirect || '/orders';
        } else {
            const errDiv = document.getElementById('orderError');
            errDiv.textContent = data.message || 'Order failed.';
            errDiv.style.display = 'block';
        }
    } catch(e) {
        alert('Network error.');
    } finally {
        btn.disabled = false; btn.innerHTML = 'Place Order <i class="fas fa-arrow-right"></i>';
    }
}

renderCheckout();
</script>
</body>
</html>