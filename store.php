<?php
require_once "config.php";

if (!isLoggedIn()) { redirect('index.php'); }

$user = getUserData($_SESSION['user_id']);
$name = $user['full_name'] ?? 'User';

// Helper to check if store is open now
function isStoreOpenNow($openTimeStr) {
    if (empty($openTimeStr)) return false;
    $openTimeStr = str_replace(['–', '—', 'to'], '-', $openTimeStr);
    $parts = explode('-', $openTimeStr);
    if (count($parts) !== 2) return false;
    $start = strtotime(trim($parts[0]));
    $end   = strtotime(trim($parts[1]));
    if ($start === false || $end === false) return false;
    $now = time();
    $midnight = strtotime('today midnight');
    $nowSeconds = $now - $midnight;
    $startSeconds = $start - $midnight;
    $endSeconds = $end - $midnight;
    if ($end < $start) $endSeconds += 86400;
    return ($nowSeconds >= $startSeconds && $nowSeconds < $endSeconds);
}

$storesQuery = mysqli_query($conn, "
    SELECT s.*,
           u.full_name AS owner_name,
           (SELECT GROUP_CONCAT(sc.name SEPARATOR ', ')
            FROM store_categories sc
            WHERE sc.store_id = s.id
           ) AS categories_list,
           (SELECT AVG(rating) FROM store_reviews r WHERE r.store_id = s.id) AS avg_rating,
           (SELECT COUNT(*)   FROM store_reviews r WHERE r.store_id = s.id) AS review_count
    FROM stores s
    JOIN users u ON s.owner_id = u.id
    ORDER BY s.id DESC
");
$storesArr = [];
while ($row = mysqli_fetch_assoc($storesQuery)) { $storesArr[] = $row; }
$totalStores = count($storesArr);
$openCount = 0;
foreach ($storesArr as $s) { if (isStoreOpenNow($s['open_time'] ?? '')) $openCount++; }

function storeCategoryIcon($category) {
    $map = [
        'Restaurant'=>'fa-utensils','Thattukada'=>'fa-store-alt','Grocery'=>'fa-shopping-basket',
        'Bakery'=>'fa-bread-slice','Pharmacy'=>'fa-medkit','Fruits & Vegetables'=>'fa-apple-alt',
        'Meat & Fish'=>'fa-drumstick-bite','Stationery'=>'fa-pencil-alt','Electronics'=>'fa-plug',
        'Fashion'=>'fa-tshirt','Chicken Shop'=>'fa-drumstick-bite','Metal Shop'=>'fa-hard-hat',
        'Pet Shop'=>'fa-paw','Other'=>'fa-store'
    ];
    return $map[$category] ?? 'fa-store';
}

// Quick-filter categories (top of page pill row)
$quickCats = [
  ['label'=>'All','icon'=>'fa-layer-group'],
  ['label'=>'Food','icon'=>'fa-utensils'],
  ['label'=>'Grocery','icon'=>'fa-shopping-basket'],
  ['label'=>'Bakery','icon'=>'fa-bread-slice'],
  ['label'=>'Tea','icon'=>'fa-mug-hot'],
  ['label'=>'Meat','icon'=>'fa-drumstick-bite'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Local Stores – HelpGo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root{
    --bg:#03271e;
    --bg-2:#05362a;
    --surface:rgba(255,255,255,0.02);
    --surface-2:rgba(201,168,76,0.06);
    --line:rgba(201,168,76,0.28);
    --line-soft:rgba(201,168,76,0.15);
    --gold:#d4b45a;
    --gold-2:#e8c976;
    --gold-deep:#a8873a;
    --text:#f4ecd4;
    --muted:#9db3a8;
    --green:#22c55e;
    --red:#ef4444;
    --radius:22px;
  }
  *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{background:var(--bg);}
  body{
    font-family:'Manrope',sans-serif;color:var(--text);min-height:100vh;
    background:
      radial-gradient(1200px 500px at 50% -100px, #0a4a37 0%, transparent 60%),
      radial-gradient(600px 400px at 100% 100%, #0a3d2e 0%, transparent 70%),
      var(--bg);
    display:flex;justify-content:center;
  }
  a{text-decoration:none;color:inherit;}
  .app{width:100%;max-width:430px;padding:18px 16px 120px;position:relative;}

  /* ── Top bar ─────────────────────────────── */
  .topbar{display:flex;align-items:center;gap:12px;margin-bottom:18px;}
  .avatar{
    width:46px;height:46px;border-radius:50%;
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    display:flex;align-items:center;justify-content:center;
    font-family:'Sora',sans-serif;font-size:17px;font-weight:800;color:#03271e;flex:none;
  }
  .greet{flex:1;line-height:1.15;}
  .greet .hi{font-size:12px;color:var(--muted);}
  .greet .nm{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;}
  .icon-btn{
    width:44px;height:44px;border-radius:14px;
    background:var(--surface);border:1px solid var(--line);
    display:flex;align-items:center;justify-content:center;
    color:var(--gold);font-size:15px;position:relative;
  }
  .notif-dot{position:absolute;top:9px;right:10px;width:8px;height:8px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 3px var(--bg);}

  /* ── Hero banner ─────────────────────────── */
  .hero{
    position:relative;overflow:hidden;
    border:1px solid var(--line);border-radius:var(--radius);
    padding:22px 20px;margin-bottom:18px;
    background:
      radial-gradient(400px 200px at 100% 0%, rgba(212,180,90,0.22), transparent 60%),
      linear-gradient(135deg, #064e3b 0%, #05362a 60%, #03271e 100%);
  }
  .hero .eyebrow{
    display:inline-flex;align-items:center;gap:6px;
    font-size:11px;font-weight:700;letter-spacing:0.15em;
    color:var(--gold);text-transform:uppercase;margin-bottom:8px;
  }
  .hero h1{
    font-family:'Sora',sans-serif;font-weight:800;
    font-size:26px;line-height:1.1;margin-bottom:6px;letter-spacing:-0.5px;
  }
  .hero h1 span{color:var(--gold);}
  .hero p{color:var(--muted);font-size:13px;margin-bottom:14px;}
  .hero-stats{display:flex;gap:10px;}
  .stat{
    flex:1;background:rgba(0,0,0,0.25);border:1px solid var(--line-soft);
    border-radius:14px;padding:10px 12px;
  }
  .stat .n{font-family:'Sora',sans-serif;font-weight:800;font-size:18px;color:var(--gold);}
  .stat .l{font-size:11px;color:var(--muted);}

  /* ── Search ─────────────────────────────── */
  .search-row{display:flex;gap:10px;margin-bottom:16px;}
  .search{
    flex:1;display:flex;align-items:center;gap:10px;
    border:1.5px solid var(--line);border-radius:16px;
    padding:14px 16px;background:var(--surface);
  }
  .search i{color:var(--gold);}
  .search input{flex:1;background:transparent;border:none;outline:none;color:var(--text);font-family:inherit;font-size:14px;}
  .search input::placeholder{color:var(--muted);}
  .filter-btn{
    width:52px;height:52px;border-radius:16px;
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    color:#03271e;display:flex;align-items:center;justify-content:center;
    font-size:18px;border:none;
  }

  /* ── Quick category rail ─────────────────── */
  .rail{
    display:flex;gap:10px;overflow-x:auto;
    scrollbar-width:none;margin:0 -16px 20px;padding:2px 16px 6px;
  }
  .rail::-webkit-scrollbar{display:none;}
  .qchip{
    flex:none;display:inline-flex;align-items:center;gap:8px;
    padding:10px 16px;border-radius:999px;
    border:1px solid var(--line-soft);color:var(--text);
    font-size:13px;font-weight:600;background:var(--surface);
  }
  .qchip i{color:var(--gold);font-size:12px;}
  .qchip.active{
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    color:#03271e;border-color:transparent;
  }
  .qchip.active i{color:#03271e;}

  /* ── Section head ────────────────────────── */
  .sec-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px;}
  .sec-head h2{font-family:'Sora',sans-serif;font-size:19px;font-weight:800;}
  .sec-head a{font-size:12px;color:var(--gold);font-weight:700;}

  /* ── List Shop CTA (compact card) ────────── */
  .cta-card{
    display:flex;align-items:center;gap:12px;
    padding:14px 16px;margin-bottom:22px;
    border:1px solid var(--line);border-radius:18px;
    background:
      linear-gradient(135deg, rgba(212,180,90,0.10), rgba(212,180,90,0.02));
  }
  .cta-icon{
    width:44px;height:44px;border-radius:12px;flex:none;
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    color:#03271e;display:flex;align-items:center;justify-content:center;font-size:18px;
  }
  .cta-text{flex:1;line-height:1.2;}
  .cta-text b{font-family:'Sora',sans-serif;font-size:14px;display:block;}
  .cta-text small{color:var(--muted);font-size:11.5px;}
  .cta-btn{
    padding:9px 14px;border-radius:999px;
    border:1px solid var(--gold);color:var(--gold);
    font-size:12px;font-weight:700;
  }

  /* ── Store cards ─────────────────────────── */
  .store-grid{display:flex;flex-direction:column;gap:18px;}
  .store-card{
    border:1.5px solid var(--line);border-radius:var(--radius);
    overflow:hidden;background:var(--surface);
  }
  .store-banner-wrap{position:relative;}
  .store-banner{width:100%;aspect-ratio:16/9;object-fit:cover;display:block;background:linear-gradient(135deg,#0a4a37,#03271e);}
  .banner-scrim{position:absolute;inset:0;background:linear-gradient(180deg, transparent 40%, rgba(3,39,30,0.85) 100%);}
  .banner-top{position:absolute;top:12px;left:12px;right:12px;display:flex;justify-content:space-between;align-items:center;}
  .status-pill{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 12px;border-radius:999px;font-size:11.5px;font-weight:700;
    background:rgba(3,39,30,0.75);backdrop-filter:blur(6px);
  }
  .status-pill.open{color:var(--green);border:1px solid rgba(34,197,94,0.4);}
  .status-pill.open .dot{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 0 3px rgba(34,197,94,0.15);}
  .status-pill.closed{color:var(--red);border:1px solid rgba(239,68,68,0.4);}
  .status-pill.closed .dot{width:7px;height:7px;border-radius:50%;background:var(--red);box-shadow:0 0 0 3px rgba(239,68,68,0.15);}
  .rating-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 11px;border-radius:999px;
    background:rgba(3,39,30,0.75);backdrop-filter:blur(6px);
    border:1px solid var(--line-soft);
    font-size:12px;font-weight:700;color:var(--text);
  }
  .rating-badge i{color:var(--gold);}
  .banner-name{
    position:absolute;left:14px;right:14px;bottom:12px;
    display:flex;align-items:center;gap:8px;
  }
  .banner-name .nm{
    font-family:'Sora',sans-serif;font-weight:800;font-size:19px;color:#fff;
    text-shadow:0 2px 8px rgba(0,0,0,0.5);
  }
  .banner-name i{color:var(--gold);font-size:15px;}

  .store-body{padding:14px 16px 16px;}
  .chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;}
  .chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 11px;border-radius:999px;
    border:1px solid var(--line-soft);color:var(--gold);
    font-size:11.5px;font-weight:600;background:transparent;
  }
  .chip i{font-size:10px;}
  .store-bottom{display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .store-owner{font-size:12px;color:var(--muted);display:inline-flex;align-items:center;gap:6px;}
  .visit-btn{
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    color:#03271e;font-weight:700;font-size:13px;
    padding:10px 18px;border-radius:999px;
    display:inline-flex;align-items:center;gap:8px;white-space:nowrap;
  }

  /* ── Why card ────────────────────────────── */
  .why-card{
    margin-top:22px;padding:20px 14px;
    border:1.5px solid var(--line);border-radius:var(--radius);
    background:var(--surface);
    display:grid;grid-template-columns:repeat(4,1fr);gap:8px;
  }
  .why-item{display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;}
  .why-icon{
    width:46px;height:46px;border-radius:14px;
    background:rgba(212,180,90,0.1);border:1px solid var(--line-soft);
    display:flex;align-items:center;justify-content:center;
    color:var(--gold);font-size:16px;
  }
  .why-title{font-family:'Sora',sans-serif;font-size:12px;font-weight:700;}
  .why-sub{font-size:10px;color:var(--muted);line-height:1.3;}

  .empty-state{
    padding:34px;text-align:center;color:var(--muted);
    border:1.5px dashed var(--line-soft);border-radius:var(--radius);
  }
  .empty-state i{font-size:36px;margin-bottom:10px;color:var(--gold);opacity:0.7;}

  /* ── Bottom nav ──────────────────────────── */
  .bottom-nav{
    position:fixed;bottom:14px;left:50%;transform:translateX(-50%);
    width:calc(100% - 24px);max-width:406px;
    background:rgba(5,54,42,0.85);backdrop-filter:blur(20px);
    border:1px solid var(--line-soft);
    display:flex;justify-content:space-around;align-items:center;
    padding:10px 6px;z-index:99;border-radius:24px;
    box-shadow:0 12px 30px rgba(0,0,0,0.4);
  }
  .nav-item{flex:1;display:flex;flex-direction:column;align-items:center;color:var(--muted);font-size:11px;font-weight:600;gap:3px;padding:6px 0;}
  .nav-item i{font-size:19px;}
  .nav-item.active{color:var(--gold);}
  .nav-book{
    width:56px;height:56px;border-radius:50%;
    background:linear-gradient(135deg,var(--gold-2),var(--gold-deep));
    color:#03271e;display:flex;align-items:center;justify-content:center;
    font-size:24px;margin-top:-32px;flex:none;
    box-shadow:0 10px 24px rgba(212,180,90,0.45);
    border:4px solid var(--bg);
  }
</style>
</head>
<body>
<div class="app">

  <!-- Top bar: avatar + greeting + notif -->
  <div class="topbar">
    <a href="profile.php" class="avatar"><?= strtoupper(substr($name,0,1)) ?></a>
    <div class="greet">
      <div class="hi">Welcome back</div>
      <div class="nm"><?= htmlspecialchars($name) ?> 👋</div>
    </div>
    <a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i><span class="notif-dot"></span></a>
  </div>

  <!-- Hero card -->
  <section class="hero">
    <div class="eyebrow"><i class="fas fa-store"></i> Local Stores</div>
    <h1>Shop from your <span>neighbourhood</span></h1>
    <p>Fresh food, groceries & essentials, delivered fast.</p>
    <div class="hero-stats">
      <div class="stat"><div class="n"><?= $totalStores ?></div><div class="l">Stores listed</div></div>
      <div class="stat"><div class="n"><?= $openCount ?></div><div class="l">Open now</div></div>
      <div class="stat"><div class="n">24/7</div><div class="l">Support</div></div>
    </div>
  </section>

  <!-- Search -->
  <div class="search-row">
    <div class="search">
      <i class="fas fa-magnifying-glass"></i>
      <input type="text" placeholder="Search stores, food, items...">
    </div>
    <button class="filter-btn" aria-label="Filter"><i class="fas fa-sliders"></i></button>
  </div>

  <!-- Category rail -->
  <div class="rail">
    <?php foreach ($quickCats as $i => $q): ?>
      <span class="qchip <?= $i===0?'active':'' ?>"><i class="fas <?= $q['icon'] ?>"></i><?= $q['label'] ?></span>
    <?php endforeach; ?>
  </div>

  <!-- List Shop CTA -->
  <a href="/list-shop.php" class="cta-card">
    <div class="cta-icon"><i class="fas fa-store-alt"></i></div>
    <div class="cta-text">
      <b>Own a shop?</b>
      <small>List it free and start selling today</small>
    </div>
    <span class="cta-btn">List <i class="fas fa-arrow-right"></i></span>
  </a>

  <div class="sec-head">
    <h2>Nearby Stores</h2>
    <a href="#">See all</a>
  </div>

  <div class="store-grid">
    <?php if ($totalStores > 0): ?>
      <?php foreach ($storesArr as $store):
        $bannerFile = $store['banner'] ?? '';
        if (!empty($bannerFile)) {
          $bannerSrc = filter_var($bannerFile, FILTER_VALIDATE_URL) ? $bannerFile : 'assets/storebanner/' . $bannerFile;
        } else {
          $bannerSrc = 'https://placehold.co/600x340/03271e/d4b45a?text=' . urlencode($store['name']);
        }
        $isOpen = isStoreOpenNow($store['open_time'] ?? '');
        $avgRating = $store['avg_rating'] ? round((float)$store['avg_rating'], 1) : 0;
        $reviewCount = $store['review_count'] ? (int)$store['review_count'] : 0;
        $categoriesStr = $store['categories_list'] ?? '';
        $cats = !empty($categoriesStr) ? array_map('trim', explode(',', $categoriesStr)) : [];
        if (empty($cats)) $cats = ['Meals','Snacks'];

        $iconMap = [
          'meals'=>'fa-utensils','snacks'=>'fa-cookie-bite','biryani'=>'fa-drumstick-bite',
          'bbq'=>'fa-fire','combo'=>'fa-layer-group','tea'=>'fa-mug-hot',
          'coffee'=>'fa-mug-saucer','tea & coffee'=>'fa-mug-hot',
          'breakfast'=>'fa-egg','bun'=>'fa-bread-slice','drinks'=>'fa-glass-water',
          'dessert'=>'fa-ice-cream','bakery'=>'fa-cake-candles'
        ];
      ?>
        <div class="store-card">
          <div class="store-banner-wrap">
            <img src="<?= htmlspecialchars($bannerSrc) ?>"
                 alt="<?= htmlspecialchars($store['name']) ?>"
                 class="store-banner"
                 onerror="this.src='https://placehold.co/600x340/03271e/d4b45a?text=Store'">
            <div class="banner-scrim"></div>
            <div class="banner-top">
              <span class="status-pill <?= $isOpen ? 'open' : 'closed' ?>">
                <span class="dot"></span> <?= $isOpen ? 'Open Now' : 'Closed' ?>
              </span>
              <span class="rating-badge">
                <i class="fas fa-star"></i> <?= $avgRating > 0 ? $avgRating : '–' ?>
                <?php if ($reviewCount > 0): ?><small>(<?= $reviewCount ?>)</small><?php endif; ?>
              </span>
            </div>
            <div class="banner-name">
              <i class="fas <?= storeCategoryIcon($store['category'] ?? 'Other') ?>"></i>
              <span class="nm"><?= htmlspecialchars($store['name']) ?></span>
            </div>
          </div>
          <div class="store-body">
            <div class="chips">
              <?php foreach ($cats as $c):
                $key = strtolower($c);
                $ic  = $iconMap[$key] ?? 'fa-bowl-food';
              ?>
                <span class="chip"><i class="fas <?= $ic ?>"></i><?= htmlspecialchars($c) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="store-bottom">
              <span class="store-owner"><i class="fas fa-user"></i><?= htmlspecialchars($store['owner_name'] ?? 'Owner') ?></span>
              <a href="store/<?= urlencode($store['slug'] ?? $store['id']) ?>" class="visit-btn">
                Visit <i class="fas fa-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-store-slash"></i>
        <p>No stores yet. Be the first to list your shop!</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="why-card">
    <div class="why-item"><div class="why-icon"><i class="fas fa-location-dot"></i></div><div class="why-title">Local</div><div class="why-sub">Verified stores</div></div>
    <div class="why-item"><div class="why-icon"><i class="fas fa-shield-halved"></i></div><div class="why-title">Secure</div><div class="why-sub">Safe orders</div></div>
    <div class="why-item"><div class="why-icon"><i class="fas fa-bolt"></i></div><div class="why-title">Fast</div><div class="why-sub">Quick delivery</div></div>
    <div class="why-item"><div class="why-icon"><i class="fas fa-headset"></i></div><div class="why-title">Support</div><div class="why-sub">Here 24/7</div></div>
  </div>
</div>

<nav class="bottom-nav">
  <a href="home.php" class="nav-item"><i class="fas fa-house"></i><span>Home</span></a>
  <a href="orders.php" class="nav-item"><i class="fas fa-clipboard-list"></i><span>Orders</span></a>
  <a href="book_service.php" class="nav-book"><i class="fas fa-plus"></i></a>
  <a href="store.php" class="nav-item active"><i class="fas fa-store"></i><span>Store</span></a>
  <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
</nav>

</body>
</html>
