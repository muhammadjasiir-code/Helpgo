<?php
// orders.php — Clean list + dispatcher (Emerald Prestige theme)
require_once __DIR__ . '/config.php';

if (!isLoggedIn()) { redirect('index.php'); }
$uid = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- Dispatcher: forward legacy ?order_id= links ---------- */
if (isset($_GET['order_id']) && $_GET['order_id'] !== '') {
    $raw = trim($_GET['order_id']);
    $safe = mysqli_real_escape_string($conn, $raw);
    $q = mysqli_query($conn, "SELECT id, order_id, service_type FROM orders
        WHERE user_id = $uid AND (id = " . (int)$raw . " OR order_id = '$safe') LIMIT 1");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        $svc = strtolower(trim((string)$row['service_type']));
        $oid = (int)$row['id'];
        if ($svc === 'petrol')          { redirect('petrol_orders.php?order_id=' . $oid); }
        if ($svc === 'grocery')         { redirect('grocery_orders.php?order_id=' . $oid); }
        if ($svc === 'store_delivery')  { redirect('store_order.php?id=' . $oid); }
    }
}

/* ---------- Load orders ---------- */
$orders = array();
$res = mysqli_query($conn, "SELECT * FROM orders WHERE user_id = $uid ORDER BY id DESC LIMIT 30");
if ($res) { while ($r = mysqli_fetch_assoc($res)) $orders[] = $r; }

function service_meta($svc) {
    $svc = strtolower(trim((string)$svc));
    switch ($svc) {
        case 'petrol':          return array('label'=>'Petrol',  'icon'=>'fa-gas-pump', 'page'=>'petrol_orders.php');
        case 'grocery':         return array('label'=>'Grocery', 'icon'=>'fa-shopping-basket', 'page'=>'grocery_orders.php');
        case 'parcel':          return array('label'=>'Parcel',  'icon'=>'fa-box', 'page'=>'orders.php');
        case 'medicine':        return array('label'=>'Medicine','icon'=>'fa-medkit', 'page'=>'orders.php');
        case 'store_delivery':
        case 'food':
        case 'restaurant':      return array('label'=>'Store Order', 'icon'=>'fa-store', 'page'=>'store_order.php');
        default:                return array('label'=>ucfirst($svc ?: 'Order'), 'icon'=>'fa-file-alt', 'page'=>'orders.php');
    }
}
function status_pill($s) {
    $s = strtolower(str_replace(array(' ', '-'), '_', trim((string)$s)));
    $map = array(
        'pending'=>'pending','placed'=>'pending','new'=>'pending',
        'accepted'=>'accepted','confirmed'=>'accepted','assigned'=>'accepted',
        'picked_up'=>'enroute','picked'=>'enroute','collected'=>'enroute',
        'in_transit'=>'enroute','on_the_way'=>'enroute','out_for_delivery'=>'enroute','arrived'=>'enroute','reached'=>'enroute',
        'delivered'=>'delivered','completed'=>'delivered','success'=>'delivered',
        'cancelled'=>'cancelled','canceled'=>'cancelled','failed'=>'cancelled',
    );
    $k = isset($map[$s]) ? $map[$s] : 'pending';
    $labels = array('pending'=>'Pending','accepted'=>'Accepted','enroute'=>'On The Way','delivered'=>'Delivered','cancelled'=>'Cancelled');
    return array($k, $labels[$k]);
}

// Calculate statistics
$totalOrders = count($orders);
$totalSpent = 0;
foreach ($orders as $o) {
    $totalSpent += floatval($o['total_amount'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Orders – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33;
            --emerald-light: #0E5548;
            --emerald-dark: #04261F;
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8962E;
            --white: #FFFFFF;
            --gray-soft: #AEB8B2;
            --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.6);
            --glass-border: rgba(212, 175, 55, 0.2);
            --shadow-glass: 0 20px 50px rgba(0, 0, 0, 0.4);
            --radius-card: 28px;
            --radius-btn: 20px;
            --font: 'Poppins', sans-serif;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --status-pending: #FFA502;
            --status-accepted: #4A9BF5;
            --status-enroute: #FF7F50;
            --status-delivered: #2ED573;
            --status-cancelled: #FF4757;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
            color: var(--white);
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding: 20px 16px 60px;
            overflow-x: hidden;
            position: relative;
        }

        /* Floating background elements */
        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(130px);
            opacity: 0.12;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width: 500px; height: 500px; background: var(--gold); top: -200px; right: -150px; }
        .bg-orb:nth-child(2) { width: 350px; height: 350px; background: var(--gold); bottom: -100px; left: -120px; animation-delay: -10s; }
        @keyframes orbFloat { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(40px, -30px) scale(1.1); } }

        .gold-dust {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(circle, rgba(212,175,55,0.08) 1px, transparent 1px);
            background-size: 25px 25px;
            pointer-events: none; z-index: 0;
        }

        .container { width: 100%; max-width: 500px; position: relative; z-index: 2; }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .back-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .back-btn:hover {
            background: rgba(212,175,55,0.15);
            border-color: var(--gold);
        }
        .header-title {
            flex: 1;
            text-align: center;
        }
        .header-title h1 { font-size: 28px; font-weight: 800; color: var(--white); }
        .header-title span { color: var(--gold); }
        .header-title p { font-size: 12px; color: var(--gray-soft); font-weight: 400; margin-top: -2px; }
        .help-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            cursor: pointer;
        }
        .help-btn:hover {
            background: rgba(212,175,55,0.15);
            border-color: var(--gold);
        }

        /* Statistics Card */
        .stats-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-glass);
            position: relative;
            overflow: hidden;
        }
        .stats-card::after {
            content: '';
            position: absolute;
            right: -20px; top: -20px;
            width: 120px; height: 120px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M70 30 L80 50 L60 70 L40 60 L20 80' fill='none' stroke='%23D4AF37' stroke-width='4' opacity='0.15' /%3E%3C/svg%3E") no-repeat;
            pointer-events: none;
        }
        .stats-left {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stats-left i {
            font-size: 40px;
            color: var(--gold);
            filter: drop-shadow(0 0 10px rgba(212,175,55,0.4));
        }
        .stats-left h3 { font-size: 22px; font-weight: 700; color: var(--gold); }
        .stats-left span { font-size: 12px; color: var(--gray-muted); }
        .stats-divider {
            width: 2px; height: 50px;
            background: linear-gradient(to bottom, transparent, rgba(212,175,55,0.3), transparent);
            margin: 0 20px;
        }
        .stats-right {
            text-align: center;
        }
        .stats-right h3 { font-size: 22px; font-weight: 700; color: var(--gold); }
        .stats-right span { font-size: 12px; color: var(--gray-muted); }

        /* Filter Chips */
        .filter-bar {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            scrollbar-width: none;
        }
        .filter-bar::-webkit-scrollbar { display: none; }
        .filter-chip {
            padding: 10px 22px;
            border-radius: 50px;
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            color: var(--gray-soft);
            font-weight: 500;
            font-size: 14px;
            white-space: nowrap;
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .filter-chip i { margin-right: 6px; }
        .filter-chip.active {
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            border-color: var(--gold);
            font-weight: 700;
            box-shadow: 0 0 25px rgba(212,175,55,0.5);
        }

        /* Order Card */
        .order-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-glass);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        .order-card.visible { opacity: 1; transform: translateY(0); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
        .order-card:hover { transform: translateY(-4px); box-shadow: 0 30px 60px rgba(0,0,0,0.5); }
        .order-card::before {
            content: '';
            position: absolute;
            top: -30%; left: -30%;
            width: 160%; height: 160%;
            background: radial-gradient(circle at 30% 20%, rgba(212,175,55,0.04) 0%, transparent 60%);
            pointer-events: none;
        }
        .order-card > * { position: relative; z-index: 1; }

        .order-top {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }
        .order-icon {
            width: 56px; height: 56px;
            border-radius: 20px;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--gold);
            box-shadow: 0 0 20px rgba(212,175,55,0.15);
        }
        .order-mid { flex: 1; }
        .order-service { font-size: 18px; font-weight: 700; text-transform: capitalize; }
        .order-id { font-size: 13px; color: var(--gray-muted); display: flex; align-items: center; gap: 6px; }
        .order-id .copy-btn { color: var(--gray-muted); cursor: pointer; background: none; border: none; font-size: 14px; }
        .order-date { font-size: 11px; color: var(--gray-muted); }
        .status-pill {
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
            margin-left: auto;
        }
        .status-pending { background: rgba(255,165,2,0.2); color: var(--status-pending); border: 1px solid rgba(255,165,2,0.3); }
        .status-accepted { background: rgba(74,155,245,0.2); color: var(--status-accepted); border: 1px solid rgba(74,155,245,0.3); }
        .status-enroute { background: rgba(255,127,80,0.2); color: var(--status-enroute); border: 1px solid rgba(255,127,80,0.3); }
        .status-delivered { background: rgba(46,213,115,0.2); color: var(--status-delivered); border: 1px solid rgba(46,213,115,0.3); }
        .status-cancelled { background: rgba(255,71,87,0.2); color: var(--status-cancelled); border: 1px solid rgba(255,71,87,0.3); }

        .order-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
        }
        .order-amount {
            font-size: 24px;
            font-weight: 800;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .order-amount span { font-size: 14px; color: var(--gray-muted); font-weight: 400; margin-right: 2px; }
        .track-btn {
            padding: 12px 28px;
            border-radius: 50px;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            box-shadow: 0 0 25px rgba(212,175,55,0.4);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .track-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(212,175,55,0.7);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-card);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glass);
            margin-top: 20px;
            display: none;
            animation: fadeInUp 0.6s ease;
        }
        .empty-state.visible { display: block; }
        .empty-state i {
            font-size: 70px;
            color: var(--gold);
            opacity: 0.5;
            margin-bottom: 20px;
        }
        .empty-state h2 { font-size: 24px; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .empty-state p { color: var(--gray-soft); margin-bottom: 24px; font-size: 15px; }
        .book-btn {
            padding: 14px 40px;
            border-radius: 50px;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            box-shadow: 0 0 30px rgba(212,175,55,0.4);
        }

        /* Safety Banner */
        .safety-banner {
            margin-top: 32px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-glass);
        }
        .safety-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .safety-left i {
            font-size: 36px;
            color: var(--gold);
            filter: drop-shadow(0 0 8px rgba(212,175,55,0.4));
        }
        .safety-left h4 { font-size: 16px; font-weight: 700; color: var(--white); }
        .safety-left p { font-size: 12px; color: var(--gray-soft); }
        .scooter-illustration {
            font-size: 60px;
            color: var(--gold);
            opacity: 0.7;
            animation: floatScooter 3s infinite ease-in-out;
        }
        @keyframes floatScooter {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(-3deg); }
        }

        @media (max-width: 400px) {
            .order-service { font-size: 16px; }
            .order-amount { font-size: 20px; }
            .track-btn { padding: 10px 20px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>
    <div class="gold-dust"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="header-title">
                <h1>My <span>Orders</span></h1>
                <p>Track and manage your deliveries</p>
            </div>
            <a href="contact.php" class="help-btn"><i class="fas fa-question"></i></a>
        </div>

        <!-- Statistics Card -->
        <div class="stats-card">
            <div class="stats-left">
                <i class="fas fa-clipboard-list"></i>
                <div>
                    <h3><?= $totalOrders ?></h3>
                    <span>Total Orders</span>
                </div>
            </div>
            <div class="stats-divider"></div>
            <div class="stats-right">
                <h3>₹<?= number_format($totalSpent, 0) ?></h3>
                <span>Total Spent</span>
            </div>
        </div>

        <!-- Filter Chips -->
        <div class="filter-bar" id="filterBar">
            <div class="filter-chip active" data-filter="all"><i class="fas fa-list-ul"></i> All Orders</div>
            <div class="filter-chip" data-filter="pending"><i class="fas fa-clock"></i> Pending</div>
            <div class="filter-chip" data-filter="accepted"><i class="fas fa-check-circle"></i> Accepted</div>
            <div class="filter-chip" data-filter="enroute"><i class="fas fa-truck"></i> On The Way</div>
            <div class="filter-chip" data-filter="delivered"><i class="fas fa-box"></i> Delivered</div>
            <div class="filter-chip" data-filter="cancelled"><i class="fas fa-ban"></i> Cancelled</div>
        </div>

        <!-- Order Cards Container -->
        <div id="ordersContainer">
            <?php if (empty($orders)): ?>
                <div class="empty-state visible" id="emptyState">
                    <i class="fas fa-box-open"></i>
                    <h2>No Orders Yet</h2>
                    <p>Book your first service and track it here.</p>
                    <a href="home.php" class="book-btn">Book Service</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $o):
                    $svc = $o['service_type'];
                    $meta = service_meta($svc);
                    list($statusKey, $statusLabel) = status_pill($o['status']);
                    $trackPage = $meta['page'];
                    $orderIdDb = (int)$o['id'];
                    $orderIdDisplay = h($o['order_id']);
                    $amount = floatval($o['total_amount'] ?? 0);
                    $date = date('d M, h:i A', strtotime($o['order_date'] ?? ''));

                    // Build track URL correctly for each service type
                    $trackUrl = ($trackPage === 'store_order.php')
                        ? $trackPage . '?id=' . $orderIdDb
                        : $trackPage . '?order_id=' . $orderIdDb;
                ?>
                <div class="order-card visible" data-status="<?= $statusKey ?>">
                    <div class="order-top">
                        <div class="order-icon"><i class="fas <?= $meta['icon'] ?>"></i></div>
                        <div class="order-mid">
                            <div class="order-service"><?= h($meta['label']) ?> Delivery</div>
                            <div class="order-id">
                                #<?= $orderIdDisplay ?>
                                <button class="copy-btn" data-orderid="<?= $orderIdDisplay ?>"><i class="fas fa-copy"></i></button>
                            </div>
                            <div class="order-date"><?= $date ?></div>
                        </div>
                        <div class="status-pill status-<?= $statusKey ?>"><?= $statusLabel ?></div>
                    </div>
                    <div class="order-bottom">
                        <div class="order-amount"><span>₹</span><?= number_format($amount, 2) ?></div>
                        <a href="<?= $trackUrl ?>" class="track-btn">
                            <i class="fas fa-map-marker-alt"></i> Track Order
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <!-- Empty state hidden for now, shown by JS when filter reveals no results -->
                <div class="empty-state" id="emptyState" style="display:none;">
                    <i class="fas fa-box-open"></i>
                    <h2>No matching orders</h2>
                    <p>Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Safety Banner -->
        <div class="safety-banner">
            <div class="safety-left">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <h4>100% Safe & Secure</h4>
                    <p>We ensure safe, reliable and fast delivery every time.</p>
                </div>
            </div>
            <div class="scooter-illustration">
                <i class="fas fa-motorcycle"></i>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
        const filterChips = document.querySelectorAll('.filter-chip');
        const orderCards = document.querySelectorAll('.order-card');
        const emptyState = document.getElementById('emptyState');

        filterChips.forEach(chip => {
            chip.addEventListener('click', function() {
                filterChips.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                let visibleCount = 0;
                orderCards.forEach(card => {
                    if (filter === 'all' || card.dataset.status === filter) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                if (visibleCount === 0) {
                    emptyState.style.display = 'block';
                } else {
                    emptyState.style.display = 'none';
                }
            });
        });

        // Copy order ID
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const orderId = this.dataset.orderid;
                if (orderId && navigator.clipboard) {
                    navigator.clipboard.writeText(orderId);
                    this.innerHTML = '<i class="fas fa-check" style="color:var(--gold)"></i>';
                    setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
                }
            });
        });

        // Animate order cards on load
        const cards = document.querySelectorAll('.order-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('visible');
            }, 100 * index);
        });
    </script>
</body>
</html>