<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$user   = getUserData($_SESSION['user_id']);
$name   = $user['full_name'] ?? 'User';
$uid    = (int)$_SESSION['user_id'];

/* ---------- get store by slug ---------- */
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { header("Location: /store"); exit; }
$slug_safe = mysqli_real_escape_string($conn, $slug);

$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM stores LIKE 'slug'");
if (mysqli_num_rows($colCheck) == 0) {
    die("Error: 'slug' column missing.");
}

$sql = "SELECT s.*, u.full_name AS owner_name
        FROM stores s
        JOIN users u ON s.owner_id = u.id
        WHERE s.slug = '$slug_safe'";
$res = mysqli_query($conn, $sql);
if (!$res) die("DB error: " . mysqli_error($conn));
$store = mysqli_fetch_assoc($res);
if (!$store) { header("Location: /store"); exit; }

/* ---------- Robust image resolver ---------- */
function resolveStoreImage($file, $fallback = '') {
    if (empty($file)) return $fallback;
    if (filter_var($file, FILTER_VALIDATE_URL)) return $file;
    $file = ltrim($file, '/');
    $candidates = [];
    if (strpos($file, '/') !== false) $candidates[] = $file;
    $bases = ['assets/storebanner/', 'assets/stores/', 'uploads/stores/', 'uploads/', 'assets/'];
    foreach ($bases as $b) $candidates[] = $b . basename($file);
    foreach ($candidates as $c) {
        if (file_exists(__DIR__ . '/' . $c)) return '/' . $c;
    }
    return '/' . $candidates[0];
}

$logoRaw  = $store['logo']  ?? $store['store_logo']  ?? $store['image'] ?? '';
$coverRaw = $store['cover_banner'] ?? $store['banner'] ?? $store['cover'] ?? $store['offer_banner'] ?? '';
$logoUrl  = resolveStoreImage($logoRaw,  'https://placehold.co/400x400/1F1F1F/F4B400?text=Logo');
$coverUrl = resolveStoreImage($coverRaw, '');

$location = $store['location'] ?? '';
$openTime = $store['open_time'] ?? '';
$category = $store['category'] ?? 'Other';

/* ---------- Category icon helper ---------- */
function storeCategoryIcon($category) {
    $map = [
        'Restaurant'          => 'fa-utensils',
        'Thattukada'          => 'fa-store-alt',
        'Grocery'             => 'fa-shopping-basket',
        'Bakery'              => 'fa-bread-slice',
        'Pharmacy'            => 'fa-medkit',
        'Fruits & Vegetables' => 'fa-apple-alt',
        'Meat & Fish'         => 'fa-drumstick-bite',
        'Stationery'          => 'fa-pencil-alt',
        'Electronics'         => 'fa-plug',
        'Fashion'             => 'fa-tshirt',
        'Chicken Shop'        => 'fa-drumstick-bite',
        'Metal Shop'          => 'fa-hard-hat',
        'Pet Shop'            => 'fa-paw',
        'Other'               => 'fa-store'
    ];
    return $map[$category] ?? 'fa-store';
}

/* ---------- Categories ---------- */
$categories = [];
$catRes = mysqli_query($conn, "SELECT * FROM store_categories WHERE store_id = " . (int)$store['id'] . " ORDER BY id");
while ($row = mysqli_fetch_assoc($catRes)) $categories[] = $row;

/* ---------- Products ---------- */
$products = [];
$prodRes = mysqli_query($conn, "SELECT * FROM store_products WHERE store_id = " . (int)$store['id'] . " ORDER BY id DESC LIMIT 20");
while ($row = mysqli_fetch_assoc($prodRes)) $products[] = $row;

/* ---------- Review permission ---------- */
$hasPurchased = false;
$purchaseCheck = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE user_id = $uid AND store_id = " . (int)$store['id'] . " AND status = 'delivered'");
if ($purchaseCheck) {
    $row = mysqli_fetch_assoc($purchaseCheck);
    $hasPurchased = ($row['cnt'] > 0);
}

/* ---------- Review submit ---------- */
$reviewMsg = '';
if (isset($_POST['submit_review']) && $uid && $hasPurchased) {
    $rating  = (int)$_POST['rating'];
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    $sqlRev  = "INSERT INTO store_reviews (store_id, user_id, rating, comment)
                VALUES (" . (int)$store['id'] . ", $uid, $rating, '$comment')";
    $reviewMsg = mysqli_query($conn, $sqlRev)
        ? '<div class="alert success">✅ Thank you for your review!</div>'
        : '<div class="alert error">❌ Could not save review.</div>';
}

$reviewsRes = mysqli_query($conn, "
    SELECT r.*, u.full_name AS user_name
    FROM store_reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.store_id = " . (int)$store['id'] . "
    ORDER BY r.created_at DESC
");

$ratingAvg = 0; $ratingCount = 0;
$aggRes = mysqli_query($conn, "SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM store_reviews WHERE store_id = " . (int)$store['id']);
if ($aggRes && ($agg = mysqli_fetch_assoc($aggRes))) {
    $ratingAvg   = round((float)$agg['avg_r'], 1);
    $ratingCount = (int)$agg['cnt'];
}
if ($ratingCount === 0) {
    $ratingAvg   = 0;
    $ratingCount = 0;
}

$isOwner = ($uid == $store['owner_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($store['name']) ?> – HelpGo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #FFC107; --gold-deep: #F4B400; --ink: #1A1A1A; --muted: #7A7A7A;
            --bg: #F6F5F1; --card: #ffffff; --shadow: 0 10px 30px rgba(0,0,0,.08); --radius: 22px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { background:var(--bg); }
        body { font-family:'Manrope', sans-serif; color:var(--ink); display:flex; justify-content:center; min-height:100vh; }
        a { text-decoration:none; color:inherit; }
        button { font-family:inherit; }

        #loading-screen { position:fixed; inset:0; background:#fff; display:flex; align-items:center; justify-content:center; z-index:9999; transition:opacity .5s; }
        #loading-screen.hidden { opacity:0; pointer-events:none; }
        .loader { width:40px; height:40px; border:4px solid #eee; border-top-color:var(--gold); border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg)} }

        .app { width:100%; max-width:430px; background:var(--bg); position:relative; padding-bottom:40px; overflow:hidden; }

        .hero {
            position: relative; height: 340px;
            background: #111 center/cover no-repeat;
            border-radius: 0 0 28px 28px; overflow: hidden; margin-top: 0;
        }
        .hero::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,.25) 0%, rgba(0,0,0,.55) 100%);
            z-index: 1;
        }
        .hero-header {
            position: absolute; top: 0; left: 0; right: 0; z-index: 5;
            display: flex; align-items: center; gap: 10px; padding: 16px;
        }
        .back-btn {
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.25); backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px; transition: 0.2s; flex-shrink: 0; text-decoration: none;
        }
        .back-btn:hover { background: rgba(255,255,255,0.4); }
        .hero-search { flex: 1; position: relative; }
        .hero-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.7); font-size: 16px; }
        .hero-search input {
            width: 100%; padding: 12px 16px 12px 42px;
            background: rgba(255,255,255,0.2); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.3); border-radius: 30px;
            color: #fff; font-size: 14px; font-family: inherit; outline: none; transition: 0.2s;
        }
        .hero-search input::placeholder { color: rgba(255,255,255,0.7); }
        .hero-search input:focus { background: rgba(255,255,255,0.3); border-color: var(--gold); }
        .hero-cart {
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.25); backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px; position: relative; cursor: pointer;
            transition: 0.2s; flex-shrink: 0; text-decoration: none;
        }
        .hero-cart:hover { background: rgba(255,255,255,0.4); }
        .cart-badge {
            position: absolute; top: -6px; right: -6px;
            background: var(--gold); color: #000; min-width: 20px; height: 20px;
            border-radius: 50%; font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; padding: 0 5px;
        }

        .info-card {
            position: relative; margin: -70px 14px 18px;
            background: var(--card); border-radius: 26px;
            padding: 18px 18px 18px 108px; min-height: 120px;
            box-shadow: var(--shadow); z-index: 5;
        }
        .info-logo {
            position: absolute; left: 16px; top: 16px;
            width: 82px; height: 82px; border-radius: 50%;
            object-fit: cover; background: #111;
            border: 3px solid #fff; box-shadow: 0 6px 16px rgba(0,0,0,.15);
        }
        .info-card h2 { font-family: 'Poppins',sans-serif; font-size: 22px; font-weight: 700; line-height: 1.2; display: flex; align-items: center; gap: 8px; }
        .info-card h2 i { color: var(--gold); font-size: 20px; }
        .info-loc { color: var(--muted); font-size: 13px; margin-top: 4px; }
        .info-loc i { color: var(--gold); }
        .info-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 8px; font-size: 13px; }
        .open-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block; margin-right: 5px; }
        .open { color: #16a34a; font-weight: 600; }
        .sep { color: #ccc; }
        .rating { display: flex; align-items: center; gap: 4px; font-weight: 700; }
        .rating i { color: var(--gold); font-size: 13px; }
        .rating small { font-weight: 500; color: var(--muted); margin-left: 3px; }
        .info-btn {
            margin-top: 12px; background: var(--gold); color: #000; border: none;
            padding: 10px 18px; border-radius: 30px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
            box-shadow: 0 6px 14px rgba(244,180,0,.35);
        }
        .info-btn i { background: #000; color: var(--gold); width: 18px; height: 18px; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; }

        .owner-link {
            display: inline-flex; align-items: center; gap: 6px;
            margin: 0 16px 12px; background: rgba(244,180,0,.14);
            padding: 8px 14px; border-radius: 20px; font-size: 12px; color: #8a6a00; font-weight: 600;
        }

        .chips { display: flex; gap: 10px; overflow-x: auto; padding: 6px 14px 8px; -webkit-overflow-scrolling: touch; }
        .chips::-webkit-scrollbar { display: none; }
        .chip {
            flex: 0 0 auto; background: #fff; border: 1px solid rgba(0,0,0,.05);
            padding: 12px 18px; border-radius: 30px; font-size: 14px; font-weight: 600; color: #333;
            display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,.04);
            cursor: pointer; white-space: nowrap;
        }
        .chip i { color: var(--gold-deep); }
        .chip.active { background: var(--gold); color: #000; border-color: transparent; }
        .chip.active i { color: #000; }

        .section { padding: 18px 16px 0; }
        .section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .section-title { font-family: 'Poppins',sans-serif; font-size: 20px; font-weight: 700; }
        .view-all { color: var(--muted); font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }

        .products-scroll { display: flex; gap: 14px; overflow-x: auto; padding: 2px 2px 14px; -webkit-overflow-scrolling: touch; }
        .products-scroll::-webkit-scrollbar { display: none; }
        .food-card {
            flex: 0 0 170px; background: #fff; border-radius: 22px; overflow: hidden;
            box-shadow: 0 6px 18px rgba(0,0,0,.06); position: relative;
        }
        .food-img { height: 150px; background: #f2efe8; position: relative; overflow: hidden; }
        .food-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .food-img .ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 44px; }
        .food-body { padding: 12px 12px 14px; }
        .food-name { font-weight: 700; font-size: 15px; }
        .food-sub { font-size: 12px; color: var(--muted); margin: 2px 0 10px; min-height: 16px; }
        .food-row { display: flex; justify-content: space-between; align-items: center; }
        .food-price { font-weight: 800; font-size: 16px; }
        .add-pill {
            background: var(--gold); color: #000; border: none; padding: 6px 12px; border-radius: 20px;
            font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
        }

        .why { margin: 22px 14px 0; background: #FFF4D6; border-radius: 24px; padding: 18px 16px; }
        .why h3 { font-family: 'Poppins',sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 14px; }
        .why-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .why-card { background: #fff; border-radius: 18px; padding: 14px 8px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,.03); }
        .why-card i { color: var(--gold-deep); font-size: 22px; margin-bottom: 6px; display: block; }
        .why-card span { font-size: 11px; font-weight: 700; color: var(--ink); line-height: 1.2; display: block; }

        .cta { margin: 18px 14px 0; background: #1A1A1A; border-radius: 24px; padding: 18px 18px; color: #fff; display: flex; align-items: center; gap: 12px; position: relative; overflow: hidden; }
        .cta-text { flex: 1; }
        .cta-tag { color: var(--gold); font-weight: 800; font-size: 11px; letter-spacing: 1px; }
        .cta h3 { font-family: 'Poppins',sans-serif; font-size: 17px; font-weight: 700; margin-top: 4px; }
        .cta p { font-size: 12px; color: #c9c9c9; margin-top: 2px; }
        .cta-btn { background: var(--gold); color: #000; border: none; padding: 12px 20px; border-radius: 24px; font-weight: 800; font-size: 13px; cursor: pointer; white-space: nowrap; }
        .cta-scoot { font-size: 34px; color: var(--gold); opacity: .9; margin: 0 4px; }

        .reviews { padding: 22px 16px 0; }
        .review-form,.review-card,.review-empty { background: #fff; border-radius: 20px; padding: 16px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.04); }
        .review-form label { font-size: 13px; font-weight: 600; display: block; margin-top: 6px; }
        .review-form select,.review-form textarea { width: 100%; padding: 10px 12px; border: 1px solid #eee; border-radius: 12px; margin-top: 6px; font-family: inherit; font-size: 14px; }
        .submit-review { margin-top: 12px; background: var(--gold); color: #000; border: none; padding: 12px; border-radius: 22px; font-weight: 700; width: 100%; cursor: pointer; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .review-user { font-weight: 700; }
        .review-stars { color: var(--gold); }
        .review-empty { text-align: center; color: var(--muted); font-size: 13px; }
        .alert { padding: 10px 12px; border-radius: 12px; margin-top: 10px; font-size: 13px; }
        .alert.success { background: #e6f7e6; color: #2e7d32; }
        .alert.error { background: #fdecea; color: #c62828; }
    </style>
</head>
<body>

<div id="loading-screen"><div class="loader"></div></div>

<div class="app">

    <div class="hero" id="hero">
        <div class="hero-header">
            <a href="/store" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="hero-search">
                <i class="fas fa-search"></i>
                <input type="text" id="liveSearch" placeholder="Search items..." autocomplete="off">
            </div>
            <div class="hero-cart" id="cartIcon" title="View Cart">
                <i class="fas fa-shopping-bag"></i>
                <span class="cart-badge" id="cartCount">0</span>
            </div>
        </div>
    </div>

    <div class="info-card">
        <img src="<?= htmlspecialchars($logoUrl) ?>"
             class="info-logo"
             alt="<?= htmlspecialchars($store['name']) ?>"
             onerror="this.onerror=null;this.src='https://placehold.co/200x200/1F1F1F/F4B400?text=Logo';">
        <h2>
            <i class="fas <?= storeCategoryIcon($category) ?>"></i>
            <?= htmlspecialchars($store['name']) ?>
        </h2>
        <?php if (!empty($location)): ?>
            <div class="info-loc"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($location) ?></div>
        <?php endif; ?>
        <div class="info-meta">
            <span class="open"><span class="open-dot"></span>Open Now</span>
            <?php if (!empty($openTime)): ?>
                <span class="sep">✕</span>
                <span style="color:#333;font-weight:500"><?= htmlspecialchars($openTime) ?></span>
            <?php endif; ?>
        </div>
        <div class="info-meta" style="margin-top:6px">
            <div class="rating">
                <i class="fas fa-star"></i>
                <?php if ($ratingCount > 0): ?>
                    <?= $ratingAvg ?> <small>(<?= $ratingCount ?> reviews)</small>
                <?php else: ?>
                    <small>No reviews yet</small>
                <?php endif; ?>
            </div>
        </div>
        <button class="info-btn"><i class="fas fa-info"></i> Store Info</button>
    </div>

    <?php if ($isOwner): ?>
        <a href="/owner/dashboard.php" class="owner-link"><i class="fas fa-cogs"></i> Manage Store</a>
    <?php endif; ?>

    <?php if (!empty($categories)): ?>
        <div class="chips">
            <div class="chip active" data-filter="all"><i class="fas fa-mug-hot"></i> All Items</div>
            <?php foreach($categories as $cat): ?>
                <div class="chip" data-filter="<?= htmlspecialchars($cat['name']) ?>"><i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-utensils') ?>"></i> <?= htmlspecialchars($cat['name']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-head">
            <div class="section-title">Popular Items</div>
            <a href="#" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="products-scroll" id="productsContainer">
            <?php if (!empty($products)): foreach($products as $prod):
                $prodImgRaw = $prod['image'] ?? '';
                $prodImg = !empty($prodImgRaw) ? resolveStoreImage($prodImgRaw, 'https://placehold.co/200x200/F2EFE8/999?text=📦') : '';
                $prodName = htmlspecialchars($prod['name']);
                $prodCategory = htmlspecialchars($prod['cat_name'] ?? '');
            ?>
            <div class="food-card" data-name="<?= strtolower($prodName) ?>" data-category="<?= strtolower($prodCategory) ?>">
                <div class="food-img">
                    <?php if ($prodImg): ?>
                        <img src="<?= htmlspecialchars($prodImg) ?>" alt="<?= $prodName ?>"
                             onerror="this.parentNode.innerHTML='<div class=&quot;ph&quot;>🍽️</div>'">
                    <?php else: ?>
                        <div class="ph">🍽️</div>
                    <?php endif; ?>
                </div>
                <div class="food-body">
                    <div class="food-name"><?= $prodName ?></div>
                    <div class="food-sub"><?= htmlspecialchars(mb_strimwidth($prod['description'] ?? '', 0, 40, '…')) ?></div>
                    <div class="food-row">
                        <span class="food-price">₹<?= number_format($prod['price'],2) ?></span>
                        <button class="add-pill" 
                            data-name="<?= $prodName ?>" 
                            data-price="<?= $prod['price'] ?>"
                            data-image="<?= $prodImg ? htmlspecialchars($prodImg) : '' ?>">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div style="padding:24px;color:#888">No products yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="why">
        <h3>Why Choose Us?</h3>
        <div class="why-grid">
            <div class="why-card"><i class="fas fa-leaf"></i><span>Fresh &amp;<br>Hygienic</span></div>
            <div class="why-card"><i class="fas fa-mug-hot"></i><span>Best<br>Quality</span></div>
            <div class="why-card"><i class="fas fa-heart"></i><span>Made with<br>Love</span></div>
            <div class="why-card"><i class="fas fa-motorcycle"></i><span>Quick<br>Service</span></div>
        </div>
    </div>

    <div class="cta">
        <div class="cta-text">
            <div class="cta-tag">ORDER NOW</div>
            <h3>Fast &amp; Fresh to Your Doorstep!</h3>
            <p>From our Thattukada to your home.</p>
        </div>
        <i class="fas fa-motorcycle cta-scoot"></i>
        <button class="cta-btn">Order Now</button>
    </div>

    <div class="reviews">
        <div class="section-head" style="padding:0;margin-bottom:12px">
            <div class="section-title">Customer Reviews</div>
        </div>

        <?php if ($hasPurchased): ?>
            <div class="review-form">
                <form method="POST">
                    <label>Your Rating</label>
                    <select name="rating" required>
                        <option value="5">★★★★★ (5)</option>
                        <option value="4">★★★★☆ (4)</option>
                        <option value="3">★★★☆☆ (3)</option>
                        <option value="2">★★☆☆☆ (2)</option>
                        <option value="1">★☆☆☆☆ (1)</option>
                    </select>
                    <label>Your Review</label>
                    <textarea name="comment" rows="3" placeholder="Share your experience..."></textarea>
                    <button type="submit" name="submit_review" class="submit-review">Submit Review</button>
                </form>
                <?= $reviewMsg ?>
            </div>
        <?php else: ?>
            <div class="review-empty"><i class="fas fa-lock"></i> Only customers who purchased from this store can leave a review.</div>
        <?php endif; ?>

        <?php if ($reviewsRes && mysqli_num_rows($reviewsRes) > 0): ?>
            <?php while($rev = mysqli_fetch_assoc($reviewsRes)): ?>
                <div class="review-card">
                    <div class="review-header">
                        <span class="review-user"><?= htmlspecialchars($rev['user_name']) ?></span>
                        <span class="review-stars"><?php for($i=1;$i<=5;$i++) echo $i <= $rev['rating'] ? '★' : '☆'; ?></span>
                    </div>
                    <div style="font-size:13px;color:#555"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="review-empty">No reviews yet. Be the first!</div>
        <?php endif; ?>
    </div>

</div>

<script>
    (function(){
        const hero = document.getElementById('hero');
        const cover = <?= json_encode($coverUrl) ?>;
        const logo  = <?= json_encode($logoUrl) ?>;
        const url = (cover && cover !== '/') ? cover : logo;
        if (url) hero.style.backgroundImage = `url('${url}')`;
    })();

    window.addEventListener('load', () => {
        const l = document.getElementById('loading-screen');
        l.classList.add('hidden');
        setTimeout(() => l.style.display = 'none', 500);
    });

    let cart = JSON.parse(localStorage.getItem('helpgo_cart_<?= $store['id'] ?>')) || [];
    const cartCountEl = document.getElementById('cartCount');
    function updateCartBadge() {
        const total = cart.reduce((sum, item) => sum + item.qty, 0);
        cartCountEl.textContent = total;
    }
    updateCartBadge();

    document.querySelectorAll('.add-pill').forEach(btn => {
        btn.addEventListener('click', function(e){
            if (this.closest('form')) return;
            e.preventDefault();
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            const image = this.dataset.image || '';
            const existing = cart.find(item => item.name === name);
            if (existing) existing.qty += 1;
            else cart.push({ name, price, qty: 1, image: image });
            localStorage.setItem('helpgo_cart_<?= $store['id'] ?>', JSON.stringify(cart));
            updateCartBadge();
            this.innerHTML = '✓';
            this.style.background = '#22c55e';
            this.style.color = '#fff';
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-plus"></i> Add';
                this.style.background = '';
                this.style.color = '';
            }, 800);
        });
    });

    document.getElementById('cartIcon').addEventListener('click', () => {
        window.location.href = '/store/<?= urlencode($slug) ?>/cart';
    });

    const searchInput = document.getElementById('liveSearch');
    const productCards = document.querySelectorAll('#productsContainer .food-card');
    function filterProducts() {
        const query = searchInput.value.toLowerCase().trim();
        const activeChip = document.querySelector('.chip.active');
        const filter = activeChip ? activeChip.dataset.filter : 'all';
        productCards.forEach(card => {
            const name = card.dataset.name || '';
            const category = card.dataset.category || '';
            let show = true;
            if (filter !== 'all') show = category === filter.toLowerCase();
            if (query && !name.includes(query) && !category.includes(query)) show = false;
            card.style.display = show ? '' : 'none';
        });
    }
    searchInput.addEventListener('input', filterProducts);
    document.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            filterProducts();
        });
    });
</script>
</body>
</html>