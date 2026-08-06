<?php
// coupons.php – Premium Coupons & Offers (Emerald Prestige theme)
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) { redirect(SITE_URL . 'login.php'); }

$uid = (int)$_SESSION['user_id'];

// Helper
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Fetch active coupons (gracefully handle missing table)
$coupons = [];
$featuredCoupon = null;
$tableExists = mysqli_query($conn, "SHOW TABLES LIKE 'coupons'");
if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    $now = date('Y-m-d H:i:s');
    $res = mysqli_query($conn, "
        SELECT id, code, discount_type, discount_value, min_order_amount, max_discount,
               description, valid_from, valid_to, is_featured
        FROM coupons
        WHERE is_active = 1
          AND (valid_from IS NULL OR valid_from <= '$now')
          AND (valid_to   IS NULL OR valid_to   >= '$now')
        ORDER BY is_featured DESC, id DESC
    ");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $coupons[] = $row;
            if ($row['is_featured'] && !$featuredCoupon) {
                $featuredCoupon = $row;
            }
        }
    }
}

// Apply coupon logic (simulated)
$applyMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $code = sanitize($_POST['coupon_code'] ?? '');
    $found = false;
    foreach ($coupons as $c) {
        if (strcasecmp($c['code'], $code) === 0) {
            $found = true;
            $disc = ($c['discount_type'] == 'percentage') ? $c['discount_value'].'%' : '₹'.$c['discount_value'];
            $applyMsg = "Coupon applied! $disc off on your next order.";
            break;
        }
    }
    if (!$found) {
        $applyMsg = "Invalid or expired coupon code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Coupons – HelpGo</title>
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

        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(130px);
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width: 500px; height: 500px; background: var(--gold); top: -200px; right: -150px; }
        .bg-orb:nth-child(2) { width: 350px; height: 350px; background: var(--gold); bottom: -100px; left: -120px; animation-delay: -10s; }
        @keyframes orbFloat { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(40px, -30px) scale(1.1); } }

        .container { width: 100%; max-width: 500px; position: relative; z-index: 2; }

        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .back-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex; align-items: center; justify-content: center;
            color: var(--white); font-size: 20px; text-decoration: none;
            transition: var(--transition); box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .back-btn:hover { background: rgba(212,175,55,0.15); border-color: var(--gold); }
        .header h1 { font-size: 28px; font-weight: 800; color: var(--white); }
        .header h1 span { color: var(--gold); }

        /* Apply bar */
        .apply-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-btn);
            padding: 6px 6px 6px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-glass);
        }
        .apply-bar input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--white);
            font-family: var(--font);
            font-size: 15px;
            outline: none;
        }
        .apply-bar input::placeholder { color: var(--gray-muted); }
        .apply-bar button {
            padding: 12px 24px;
            border-radius: 50px;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .apply-bar button:hover { box-shadow: 0 8px 25px rgba(212,175,55,0.4); }

        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .message.success { background: rgba(46,213,115,0.15); color: #2ED573; }
        .message.error { background: rgba(255,71,87,0.15); color: #FF4757; }

        /* Featured Banner */
        .featured-banner {
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            color: var(--emerald-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 20px 40px rgba(212,175,55,0.3);
            position: relative;
            overflow: hidden;
        }
        .featured-banner::after {
            content: '';
            position: absolute;
            right: -30px; top: -30px;
            width: 140px; height: 140px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
        }
        .featured-banner .badge {
            background: var(--emerald);
            color: var(--gold);
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
        }
        .featured-banner .discount {
            font-size: 36px;
            font-weight: 800;
        }
        .featured-banner .code {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .featured-banner .copy-btn {
            background: rgba(255,255,255,0.3);
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--emerald-dark);
        }
        .featured-banner .copy-btn:hover { background: rgba(255,255,255,0.5); }

        /* Coupon Cards */
        .coupon-list { display: flex; flex-direction: column; gap: 20px; }

        .coupon-ticket {
            position: relative;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-glass);
            display: flex;
            align-items: center;
            transition: var(--transition);
            border: 1px solid var(--glass-border);
        }
        .coupon-ticket:hover { transform: translateY(-4px); box-shadow: 0 30px 60px rgba(0,0,0,0.5); }
        .coupon-ticket::before,
        .coupon-ticket::after {
            content: '';
            position: absolute;
            width: 24px; height: 24px;
            background: radial-gradient(circle at 0 0, transparent 12px, #083C33 12px);
            z-index: 2;
        }
        .coupon-ticket::before {
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
        }
        .coupon-ticket::after {
            right: -12px;
            top: 50%;
            transform: translateY(-50%);
        }
        .coupon-left {
            flex: 1;
            padding: 20px;
            position: relative;
            z-index: 3;
            border-right: 2px dashed rgba(212,175,55,0.3);
        }
        .coupon-left .discount-badge {
            display: inline-block;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .coupon-left h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 6px;
        }
        .coupon-left p {
            font-size: 13px;
            color: var(--gray-soft);
            margin-bottom: 4px;
        }
        .coupon-left .expiry {
            font-size: 11px;
            color: var(--gray-muted);
            margin-top: 8px;
        }
        .coupon-right {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            min-width: 90px;
            position: relative;
            z-index: 3;
        }
        .coupon-right .copy-code {
            background: rgba(212,175,55,0.15);
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
        }
        .coupon-right .copy-code:hover { background: var(--gold); color: var(--emerald-dark); }
        .coupon-right .copy-code.copied { background: #2ED573; border-color: #2ED573; color: #fff; }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-card);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glass);
        }
        .empty-state i { font-size: 60px; color: var(--gold); opacity: 0.5; margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .empty-state p { color: var(--gray-soft); }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <div class="header">
            <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1><span>Coupons</span> & Offers</h1>
        </div>

        <!-- Apply Bar -->
        <form method="POST" class="apply-bar">
            <input type="text" name="coupon_code" placeholder="Enter coupon code" required>
            <button type="submit" name="apply_coupon">Apply</button>
        </form>

        <?php if ($applyMsg): ?>
            <div class="message <?= (strpos($applyMsg, 'Invalid') !== false || strpos($applyMsg, 'expired') !== false) ? 'error' : 'success' ?>">
                <?= h($applyMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Featured Coupon -->
        <?php if ($featuredCoupon): ?>
            <div class="featured-banner">
                <div>
                    <span class="badge">🔥 Hot Offer</span>
                    <div class="discount" style="margin-top:12px;">
                        <?= ($featuredCoupon['discount_type'] == 'percentage') ? $featuredCoupon['discount_value'].'%' : '₹'.$featuredCoupon['discount_value'] ?> OFF
                    </div>
                    <div class="code">
                        <?= h($featuredCoupon['code']) ?>
                        <button class="copy-btn" data-code="<?= h($featuredCoupon['code']) ?>">Copy</button>
                    </div>
                </div>
                <i class="fas fa-gift" style="font-size: 50px; opacity: 0.6;"></i>
            </div>
        <?php endif; ?>

        <!-- Coupon List -->
        <div class="coupon-list">
            <?php if (empty($coupons)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>No Coupons Available</h3>
                    <p>Check back later for exciting offers!</p>
                </div>
            <?php else: ?>
                <?php foreach ($coupons as $c): ?>
                    <?php if ($c['is_featured']) continue; // skip if already shown as featured ?>
                    <?php
                        $discText = ($c['discount_type'] == 'percentage') ? $c['discount_value'].'% Off' : '₹'.$c['discount_value'].' Off';
                        $expiry = $c['valid_to'] ? 'Expires: '.date('d M Y', strtotime($c['valid_to'])) : 'No expiry';
                        $minOrder = $c['min_order_amount'] > 0 ? 'Min. order: ₹'.$c['min_order_amount'] : '';
                    ?>
                    <div class="coupon-ticket">
                        <div class="coupon-left">
                            <span class="discount-badge"><?= $discText ?></span>
                            <h3><?= h($c['code']) ?></h3>
                            <p><?= h($c['description'] ?? '') ?></p>
                            <div class="expiry"><?= $expiry ?> <?= $minOrder ? ' | '.$minOrder : '' ?></div>
                        </div>
                        <div class="coupon-right">
                            <button class="copy-code" data-code="<?= h($c['code']) ?>">Copy</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Copy coupon code (both featured and ticket copy)
        document.querySelectorAll('.copy-btn, .copy-code').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const code = this.dataset.code;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(code).then(() => {
                        this.textContent = 'Copied!';
                        this.classList.add('copied');
                        setTimeout(() => {
                            this.textContent = 'Copy';
                            this.classList.remove('copied');
                        }, 2000);
                    });
                }
            });
        });
    </script>
</body>
</html>