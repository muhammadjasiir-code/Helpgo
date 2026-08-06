<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$user = getUserData($_SESSION['user_id']);
$name = $user['full_name'] ?? 'User';
$uid  = (int)$_SESSION['user_id'];

// --- Store‑specific cart (optional) ---
$storeSlug = isset($_GET['store']) ? trim($_GET['store']) : '';
$specificStoreId = null;
$specificStore = null;

if (!empty($storeSlug)) {
    $slugSafe = mysqli_real_escape_string($conn, $storeSlug);
    $res = mysqli_query($conn, "SELECT id, name, logo, slug FROM stores WHERE slug = '$slugSafe' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $specificStore = mysqli_fetch_assoc($res);
        $specificStoreId = $specificStore['id'];
    } else {
        header("Location: /cart"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Cart – HelpGo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-0:#0B1F17; --bg-1:#0F2A1F; --gold:#E8C36A; --gold-deep:#B8892B; --text:#F4EEDC; --muted:#9BB0A4; --border:rgba(212,175,55,0.22); --border-soft:rgba(255,255,255,0.08); --radius:22px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Manrope',sans-serif; background:var(--bg-0); color:var(--text); display:flex; justify-content:center; min-height:100vh; }
        .app { width:100%; max-width:430px; padding:22px 16px 140px; }
        .topbar { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
        .icon-btn { width:44px; height:44px; border-radius:14px; background:linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--gold); text-decoration:none; }
        .cart-title { font-family:'Poppins',sans-serif; font-size:22px; font-weight:800; flex:1; }
        .store-group { background:linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02)); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:24px; overflow:hidden; }
        .store-header { display:flex; align-items:center; gap:12px; padding:14px 16px; background:rgba(0,0,0,0.25); border-bottom:1px solid var(--border); }
        .store-header img { width:44px; height:44px; border-radius:50%; }
        .cart-item { display:flex; align-items:center; gap:14px; padding:14px 16px; border-bottom:1px solid var(--border-soft); }
        .cart-item:last-child { border-bottom:none; }
        .item-img { width:56px; height:56px; border-radius:14px; background:radial-gradient(circle at 30% 30%, rgba(232,195,106,0.20), rgba(232,195,106,0.03)); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--gold); overflow:hidden; }
        .item-img img { width:100%; height:100%; object-fit:cover; }
        .item-details { flex:1; }
        .item-name { font-weight:700; }
        .item-price { color:var(--gold); font-size:13px; }
        .qty-controls { display:inline-flex; align-items:center; gap:2px; margin-top:10px; background:rgba(0,0,0,0.35); border:1px solid var(--border-soft); border-radius:999px; padding:3px; }
        .qty-btn { width:26px; height:26px; border-radius:50%; background:linear-gradient(180deg, var(--gold) 0%, var(--gold-deep) 100%); border:none; color:#1A1206; font-weight:800; cursor:pointer; }
        .qty-value { font-weight:800; min-width:26px; text-align:center; }
        .remove-btn { background:rgba(255,107,107,0.10); border:1px solid rgba(255,107,107,0.25); color:#FF6B6B; width:30px; height:30px; border-radius:10px; cursor:pointer; }
        .checkout-btn { display:inline-block; background:var(--gold); color:#000; padding:12px 24px; border-radius:30px; font-weight:800; text-decoration:none; }
        .empty-cart { text-align:center; padding:60px 20px; }
        .browse-btn { background:var(--gold); color:#000; padding:12px 24px; border-radius:30px; font-weight:700; text-decoration:none; display:inline-block; margin-top:20px; }
    </style>
</head>
<body>
<div class="app" id="cartApp">
    <div class="topbar">
        <?php if ($specificStoreId): ?>
            <a href="/store/<?= urlencode($storeSlug) ?>" class="icon-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="cart-title"><?= htmlspecialchars($specificStore['name']) ?> Cart</div>
        <?php else: ?>
            <a href="javascript:history.back()" class="icon-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="cart-title">Your Cart</div>
        <?php endif; ?>
    </div>
    <div id="cartContent"></div>
</div>

<script>
const SPECIFIC_STORE_ID = <?= $specificStoreId ? $specificStoreId : 'null' ?>;
const SPECIFIC_STORE_SLUG = <?= json_encode($storeSlug) ?>;

// ----- Load all store carts -----
function loadAllCarts() {
    const carts = {};
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('helpgo_cart_')) {
            const storeId = key.replace('helpgo_cart_', '');
            try {
                const items = JSON.parse(localStorage.getItem(key)) || [];
                if (items.length > 0) carts[storeId] = items;
            } catch(e) {}
        }
    }
    return carts;
}

// ----- Fetch store info (cached) -----
async function fetchStoreInfo(storeId) {
    const cacheKey = `store_info_${storeId}`;
    let cached = localStorage.getItem(cacheKey);
    if (cached) { try { return JSON.parse(cached); } catch(e) {} }
    try {
        const res = await fetch(`/ajax_store_info.php?id=${storeId}`);
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();
        const store = { name: data.name || `Store #${storeId}`, slug: data.slug || storeId, logo: data.logo_url || '' };
        localStorage.setItem(cacheKey, JSON.stringify(store));
        return store;
    } catch(e) {
        return { name: `Store #${storeId}`, slug: storeId, logo: '' };
    }
}

// ----- Make sure image path is absolute -----
function fixImageUrl(url) {
    if (!url) return '';
    if (url.startsWith('http') || url.startsWith('//')) return url;
    if (url.startsWith('/')) return url;
    return '/' + url;
}

// ----- Render the cart -----
async function renderCart() {
    const container = document.getElementById('cartContent');
    const allCarts = loadAllCarts();
    let storeIds = Object.keys(allCarts);

    if (SPECIFIC_STORE_ID) {
        if (!allCarts[SPECIFIC_STORE_ID]) {
            container.innerHTML = `<div class="empty-cart">
                <i class="fas fa-shopping-bag" style="font-size:48px; color:var(--gold); opacity:0.5;"></i>
                <h3 style="color:var(--text); margin-top:10px;">No items for this store</h3>
                <a href="/store/${SPECIFIC_STORE_SLUG}" class="browse-btn">Browse Items</a>
            </div>`;
            return;
        }
        storeIds = [SPECIFIC_STORE_ID];
    }

    if (storeIds.length === 0) {
        container.innerHTML = `<div class="empty-cart">
            <i class="fas fa-shopping-bag" style="font-size:48px; color:var(--gold); opacity:0.5;"></i>
            <h3 style="color:var(--text); margin-top:10px;">Your cart is empty</h3>
            <a href="/store" class="browse-btn">Browse Stores</a>
        </div>`;
        return;
    }

    let html = '';
    for (const storeId of storeIds) {
        const store = await fetchStoreInfo(storeId);
        const items = allCarts[storeId];

        html += `<div class="store-group">
            <div class="store-header">
                <img src="${store.logo || 'https://placehold.co/44x44/123528/E8C36A?text=S'}" onerror="this.src='https://placehold.co/44x44/123528/E8C36A?text=S'">
                <div>
                    <h2 style="font-family:'Poppins',sans-serif; font-size:15px; font-weight:700;">${store.name}</h2>
                    <div style="font-size:11px; color:var(--muted);"><span style="color:#4ADE80;">●</span> Open Now</div>
                </div>
                <a href="/store/${store.slug}" style="margin-left:auto; color:var(--gold); font-size:12px; text-decoration:none;">View</a>
            </div>`;

        items.forEach((item, idx) => {
            const itemTotal = item.price * item.qty;
            const imgSrc = fixImageUrl(item.image);

            html += `
            <div class="cart-item">
                <div class="item-img">
                    ${imgSrc ? `<img src="${imgSrc}" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\\'fas fa-utensils\\'></i>';">` : `<i class="fas fa-utensils"></i>`}
                </div>
                <div class="item-details">
                    <div class="item-name">${item.name}</div>
                    <div class="item-price">₹${item.price.toFixed(2)}<small> / each</small></div>
                    <div class="qty-controls">
                        <button class="qty-btn" onclick="updateQty('${storeId}', ${idx}, -1)">−</button>
                        <span class="qty-value">${item.qty}</span>
                        <button class="qty-btn" onclick="updateQty('${storeId}', ${idx}, 1)">+</button>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:700;">₹${itemTotal.toFixed(2)}</div>
                    <button class="remove-btn" onclick="removeItem('${storeId}', ${idx})"><i class="fas fa-trash-can"></i></button>
                </div>
            </div>`;
        });

        html += `<div style="padding:12px 16px; border-top:1px solid var(--border); text-align:right;">
            <a href="/checkout?store_id=${storeId}" class="checkout-btn">
                <i class="fas fa-shopping-bag"></i> Proceed to Checkout
            </a>
        </div></div>`; // close store-group
    }

    container.innerHTML = html;
}

// ----- Quantity update -----
function updateQty(storeId, index, change) {
    const key = `helpgo_cart_${storeId}`;
    let cart = JSON.parse(localStorage.getItem(key)) || [];
    if (cart[index]) {
        cart[index].qty += change;
        if (cart[index].qty <= 0) cart.splice(index, 1);
        if (cart.length === 0) localStorage.removeItem(key);
        else localStorage.setItem(key, JSON.stringify(cart));
        renderCart();
    }
}

// ----- Remove item -----
function removeItem(storeId, index) {
    const key = `helpgo_cart_${storeId}`;
    let cart = JSON.parse(localStorage.getItem(key)) || [];
    cart.splice(index, 1);
    if (cart.length === 0) localStorage.removeItem(key);
    else localStorage.setItem(key, JSON.stringify(cart));
    renderCart();
}

// Initial render
window.addEventListener('load', renderCart);
</script>
</body>
</html>