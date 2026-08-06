<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$uid = (int)$_SESSION['user_id'];
$user = getUserData($uid);

$totalOrders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE user_id = $uid"))['cnt'] ?? 0;

$riderApp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM riders WHERE user_id = $uid"));
$isRiderApproved = ($riderApp && $riderApp['verification_status'] == 'approved');
$riderStatus = $riderApp ? $riderApp['verification_status'] : 'none';

$riderStats = null;
if ($isRiderApproved) {
    $riderStats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as deliveries, COALESCE(SUM(delivery_charge*0.85),0) as earnings FROM orders WHERE rider_id=(SELECT id FROM riders WHERE user_id=$uid) AND status='delivered'"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>My Profile - HelpGo</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html{background:#0d1a12;}
body{
    font-family:'Plus Jakarta Sans',sans-serif;
    background:#0d1a12;
    min-height:100vh;
    color:#fff;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
    padding-bottom:90px;
}
::-webkit-scrollbar{display:none;}

/* HEADER */
.header{
    display:flex;align-items:center;justify-content:space-between;
    padding:52px 18px 16px;
}
.hdr-title{font-size:22px;font-weight:800;color:#fff;}
.hdr-title span{color:#D4A020;}
.hdr-icons{display:flex;gap:10px;}
.hbtn{
    position:relative;width:42px;height:42px;border-radius:50%;
    background:#1c2e20;border:1px solid rgba(255,255,255,0.07);
    display:flex;align-items:center;justify-content:center;
    color:rgba(255,255,255,0.75);text-decoration:none;
}
.hbtn i{font-size:16px;}
.hdot{
    position:absolute;top:9px;right:9px;width:8px;height:8px;
    border-radius:50%;background:#D4A020;border:1.5px solid #0d1a12;
}
.wrap{padding:0 14px;}

/* PROFILE CARD */
.pcard{
    background:#162b1e;border-radius:18px;
    padding:18px 16px 0;margin-bottom:14px;
}
.ptop{
    display:flex;align-items:flex-start;gap:14px;
    padding-bottom:18px;
    border-bottom:1px solid rgba(255,255,255,0.055);
    margin-bottom:18px;
}
.av-wrap{position:relative;flex-shrink:0;}
.av-ring{
    width:84px;height:84px;border-radius:50%;
    border:2px dashed #D4A020;
    display:flex;align-items:center;justify-content:center;
    padding:5px;
}
.av-inner{
    width:100%;height:100%;border-radius:50%;
    background:#1e3d28;
    display:flex;align-items:center;justify-content:center;
    overflow:hidden;
}
.av-inner svg{width:54px;height:54px;}
.av-cam{
    position:absolute;bottom:1px;right:1px;
    width:24px;height:24px;border-radius:50%;
    background:#1c2e20;border:2px solid #0d1a12;
    display:flex;align-items:center;justify-content:center;
}
.av-cam i{font-size:9px;color:#D4A020;}
.pinfo{flex:1;min-width:0;padding-top:3px;}
.pname{font-size:20px;font-weight:800;color:#fff;margin-bottom:7px;line-height:1.2;}
.rat-badge{
    display:inline-flex;align-items:center;gap:5px;
    background:#1e3d28;border-radius:50px;
    padding:4px 10px;font-size:12px;font-weight:700;
    color:#fff;margin-bottom:8px;
}
.rat-badge i{color:#D4A020;font-size:10px;}
.pphone{font-size:13px;font-weight:600;color:rgba(255,255,255,0.88);margin-bottom:3px;}
.pemail{font-size:12px;color:rgba(255,255,255,0.42);}
.parrow{
    flex-shrink:0;width:30px;height:30px;border-radius:50%;
    border:1px solid rgba(255,255,255,0.09);
    background:rgba(255,255,255,0.04);
    display:flex;align-items:center;justify-content:center;
    align-self:center;text-decoration:none;
}
.parrow i{font-size:11px;color:rgba(255,255,255,0.40);}

/* Stats */
.stats-row{
    display:flex;align-items:center;
    padding-bottom:18px;
}
.stat-item{
    display:flex;align-items:center;gap:10px;flex:1;
}
.stat-item:not(:last-child){
    padding-right:10px;margin-right:10px;
    border-right:1px solid rgba(255,255,255,0.07);
}
.stat-icon{
    width:38px;height:38px;flex-shrink:0;border-radius:10px;
    background:#1e3d28;border:1px solid rgba(255,255,255,0.05);
    display:flex;align-items:center;justify-content:center;
}
.stat-icon i{font-size:16px;color:rgba(255,255,255,0.55);}
.snum{font-size:20px;font-weight:900;color:#fff;line-height:1;}
.slbl{font-size:10px;color:rgba(255,255,255,0.35);margin-top:2px;white-space:nowrap;}

/* RIDER BANNER */
.r-banner{
    background:linear-gradient(130deg,#c08a08,#D4A020 55%,#e0b830);
    border-radius:18px;overflow:hidden;
    display:flex;align-items:stretch;
    min-height:148px;margin-bottom:14px;
}
.rbl{
    width:55%;padding:20px 0 20px 18px;
    display:flex;flex-direction:column;justify-content:center;
}
.r-title{font-size:20px;font-weight:900;color:#1a1000;margin-bottom:5px;line-height:1.2;}
.r-sub{font-size:12px;color:rgba(26,16,0,0.62);line-height:1.5;margin-bottom:14px;}
.r-btn{
    display:inline-flex;align-items:center;gap:6px;
    background:#1a1000;color:#fff;
    padding:10px 18px;border-radius:50px;
    font-size:13px;font-weight:700;text-decoration:none;
    width:fit-content;
}
.r-btn i{font-size:10px;}
.rbr{
    width:45%;position:relative;
    display:flex;align-items:flex-end;
    overflow:hidden;
}
.rbr svg{width:100%;height:auto;}

/* Status cards */
.s-card{
    background:#162b1e;border-radius:18px;
    border:1px solid rgba(255,255,255,0.07);
    padding:16px;margin-bottom:14px;
}
.s-card h4{font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px;margin-bottom:5px;}
.s-card p{font-size:12px;color:rgba(255,255,255,0.50);line-height:1.5;}
.s-card .r-btn{margin-top:12px;background:#0d1a12;}
.a-card{
    background:#162b1e;border-radius:18px;
    border:1px solid rgba(212,160,32,0.22);
    padding:16px;margin-bottom:14px;
}
.a-card h4{font-size:14px;font-weight:700;color:#D4A020;display:flex;align-items:center;gap:7px;margin-bottom:5px;}
.a-card p{font-size:12px;color:rgba(255,255,255,0.50);line-height:1.5;margin-bottom:10px;}
.a-mini-row{display:flex;gap:9px;margin-bottom:12px;}
.a-mini{
    background:rgba(212,160,32,0.08);
    border:1px solid rgba(212,160,32,0.22);
    border-radius:12px;padding:8px 14px;text-align:center;
}

/* MENU */
.menu-card{
    background:#162b1e;border-radius:18px;
    overflow:hidden;margin-bottom:14px;
}
.mi{
    display:flex;align-items:center;
    padding:14px 16px;text-decoration:none;color:#fff;
}
.mi:active{background:rgba(255,255,255,0.04);}
.mi+.mi{border-top:1px solid rgba(255,255,255,0.05);}
.mic{
    width:38px;height:38px;border-radius:50%;
    background:#1e3d28;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    margin-right:13px;
}
.mic i{font-size:15px;color:rgba(255,255,255,0.72);}
.mic.red{background:rgba(220,50,50,0.16);}
.mic.red i{color:#e05555;}
.mt{font-size:14px;font-weight:700;color:#fff;}
.ms{font-size:11px;color:rgba(255,255,255,0.36);margin-top:1px;}
.mc{font-size:12px;color:rgba(255,255,255,0.22);margin-left:auto;}

/* BOTTOM NAV */
.bnav{
    position:fixed;bottom:0;left:0;width:100%;height:70px;
    background:#162b1e;
    border-top:1px solid rgba(255,255,255,0.06);
    display:flex;align-items:center;justify-content:space-around;
    padding:0 8px;z-index:999;
}
.ni{
    display:flex;flex-direction:column;align-items:center;gap:3px;
    color:rgba(255,255,255,0.35);text-decoration:none;
    font-size:10px;font-weight:600;flex:1;
}
.ni i{font-size:20px;}
.ni.active{color:#D4A020;}
.fab-slot{flex:1;display:flex;justify-content:center;margin-top:-24px;}
.fab{
    width:54px;height:54px;border-radius:14px;
    background:#D4A020;
    display:flex;align-items:center;justify-content:center;
    text-decoration:none;
    box-shadow:0 4px 18px rgba(212,160,32,0.40);
}
.fab i{font-size:24px;color:#1a1000;}
</style>
</head>
<body>

<div class="header">
    <div class="hdr-title">My <span>Profile</span></div>
    <div class="hdr-icons">
        <a href="notifications.php" class="hbtn">
            <i class="fas fa-bell"></i>
            <span class="hdot"></span>
        </a>
        <a href="settings.php" class="hbtn">
            <i class="fas fa-gear"></i>
        </a>
    </div>
</div>

<div class="wrap">

    <div class="pcard">
        <div class="ptop">
            <div class="av-wrap">
                <div class="av-ring">
                    <div class="av-inner">
                        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="33" r="19" fill="#D4A020"/>
                            <ellipse cx="50" cy="80" rx="30" ry="22" fill="#D4A020"/>
                        </svg>
                    </div>
                </div>
                <div class="av-cam"><i class="fas fa-camera"></i></div>
            </div>
            <div class="pinfo">
                <div class="pname"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="rat-badge"><i class="fas fa-star"></i> 4.8 Rating</div>
                <div class="pphone">+91 <?= htmlspecialchars($user['phone']) ?></div>
                <?php if (!empty($user['email'])): ?>
                <div class="pemail"><?= htmlspecialchars($user['email']) ?></div>
                <?php endif; ?>
            </div>
            <a href="settings.php" class="parrow">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-bag-shopping"></i></div>
                <div>
                    <div class="snum"><?= $totalOrders ?></div>
                    <div class="slbl">Total Orders</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="far fa-star"></i></div>
                <div>
                    <div class="snum">4.8</div>
                    <div class="slbl">Rating</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-motorcycle"></i></div>
                <div>
                    <div class="snum"><?= $isRiderApproved ? ($riderStats['deliveries'] ?? 0) : 0 ?></div>
                    <div class="slbl">Deliveries</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isRiderApproved): ?>
    <div class="a-card">
        <h4><i class="fas fa-motorcycle"></i> Rider Dashboard</h4>
        <p>You are a verified rider. Accept deliveries and earn.</p>
        <div class="a-mini-row">
            <div class="a-mini">
                <div class="snum"><?= $riderStats['deliveries'] ?? 0 ?></div>
                <div class="slbl">Deliveries</div>
            </div>
            <div class="a-mini">
                <div class="snum">&#8377;<?= number_format($riderStats['earnings'] ?? 0, 0) ?></div>
                <div class="slbl">Earnings</div>
            </div>
        </div>
        <a href="rider/home.php" class="r-btn">Rider Panel <i class="fas fa-chevron-right"></i></a>
    </div>

    <?php elseif ($riderStatus == 'pending'): ?>
    <div class="s-card">
        <h4><i class="fas fa-clock" style="color:#FFA502;"></i> Application Pending</h4>
        <p>Your documents are under review. We'll notify you once approved.</p>
    </div>

    <?php elseif ($riderStatus == 'rejected'): ?>
    <div class="s-card" style="border-color:rgba(220,50,50,0.20);">
        <h4><i class="fas fa-circle-xmark" style="color:#e05555;"></i> Application Rejected</h4>
        <p>Your application was rejected. You may re-apply below.</p>
        <a href="become_rider.php" class="r-btn">Re-apply <i class="fas fa-chevron-right"></i></a>
    </div>

    <?php else: ?>
    <div class="r-banner">
        <div class="rbl">
            <div class="r-title">Become a Rider</div>
            <div class="r-sub">Earn money by delivering orders on your bicycle or scooter.</div>
            <a href="become_rider.php" class="r-btn">Start Application <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="rbr">
            <svg viewBox="0 0 190 155" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="105" width="190" height="50" fill="#b07808" fill-opacity="0.22"/>
                <rect x="8"  y="75"  width="22" height="30" fill="#b07808" fill-opacity="0.18" rx="2"/>
                <rect x="12" y="65"  width="14" height="12" fill="#b07808" fill-opacity="0.18" rx="1"/>
                <rect x="38" y="88"  width="20" height="17" fill="#b07808" fill-opacity="0.15" rx="2"/>
                <rect x="148" y="68" width="24" height="37" fill="#b07808" fill-opacity="0.16" rx="2"/>
                <rect x="155" y="58" width="14" height="12" fill="#b07808" fill-opacity="0.15" rx="1"/>
                <line x1="0" y1="127" x2="190" y2="127" stroke="#b07808" stroke-opacity="0.28" stroke-width="2" stroke-dasharray="12 8"/>
                <path d="M152 18 C152 7 140 7 140 18 C140 29 146 36 146 36 C146 36 152 29 152 18 Z" fill="#1c3020"/>
                <circle cx="146" cy="17" r="4" fill="#b07808" fill-opacity="0.55"/>
                <ellipse cx="96" cy="126" rx="30" ry="6" fill="#1a1000" fill-opacity="0.18"/>
                <circle cx="72"  cy="118" r="14" fill="#1a3020" stroke="#0d1a12" stroke-width="2.5"/>
                <circle cx="72"  cy="118" r="6"  fill="#D4A020"/>
                <circle cx="72"  cy="118" r="2"  fill="#1a3020"/>
                <circle cx="124" cy="118" r="14" fill="#1a3020" stroke="#0d1a12" stroke-width="2.5"/>
                <circle cx="124" cy="118" r="6"  fill="#D4A020"/>
                <circle cx="124" cy="118" r="2"  fill="#1a3020"/>
                <path d="M72 106 L82 85 L104 80 L124 86 L128 106 L124 109 L82 109 Z" fill="#1a3020"/>
                <path d="M82 85 L87 78 L104 76 L104 80 Z" fill="#1c4030"/>
                <path d="M72 106 L82 109 L82 118 L72 118 Z" fill="#163525"/>
                <path d="M124 106 L124 118 L110 118 L110 109 Z" fill="#163525"/>
                <rect x="76" y="66" width="28" height="22" rx="3" fill="#1a3020" stroke="#D4A020" stroke-width="1.5"/>
                <line x1="90" y1="66" x2="90" y2="88" stroke="#D4A020" stroke-width="1" stroke-opacity="0.55"/>
                <line x1="76" y1="77" x2="104" y2="77" stroke="#D4A020" stroke-width="1" stroke-opacity="0.55"/>
                <path d="M118 86 L130 79 L135 79" stroke="#0d1a12" stroke-width="3.5" stroke-linecap="round"/>
                <circle cx="135" cy="79" r="3.5" fill="#D4A020"/>
                <ellipse cx="106" cy="74" rx="11" ry="15" fill="#1a1000"/>
                <circle cx="106" cy="56" r="12" fill="#1a1000"/>
                <path d="M96 53 C96 43 116 43 116 53" fill="#1c3828"/>
                <path d="M97 56 C97 53 115 53 115 56 L114 59 C110 62 102 63 98 60 Z" fill="#D4A020" fill-opacity="0.55"/>
                <path d="M116 70 L130 77" stroke="#1a1000" stroke-width="5" stroke-linecap="round"/>
            </svg>
        </div>
    </div>
    <?php endif; ?>

    <div class="menu-card">
        <a href="orders.php" class="mi">
            <div class="mic"><i class="fas fa-box"></i></div>
            <div style="flex:1;"><div class="mt">My Orders</div><div class="ms">View your order history</div></div>
            <i class="fas fa-chevron-right mc"></i>
        </a>
        <a href="contact.php" class="mi">
            <div class="mic"><i class="fas fa-headset"></i></div>
            <div style="flex:1;"><div class="mt">Help &amp; Support</div><div class="ms">Get help and contact us</div></div>
            <i class="fas fa-chevron-right mc"></i>
        </a>
        <a href="coupons.php" class="mi">
            <div class="mic"><i class="fas fa-tag"></i></div>
            <div style="flex:1;"><div class="mt">Coupons &amp; Offers</div><div class="ms">View available discounts</div></div>
            <i class="fas fa-chevron-right mc"></i>
        </a>
        <a href="settings.php" class="mi">
            <div class="mic"><i class="fas fa-gear"></i></div>
            <div style="flex:1;"><div class="mt">Settings</div><div class="ms">Manage your preferences</div></div>
            <i class="fas fa-chevron-right mc"></i>
        </a>
        <a href="logout.php" class="mi">
            <div class="mic red"><i class="fas fa-right-from-bracket"></i></div>
            <div style="flex:1;"><div class="mt" style="color:#e05555;">Logout</div><div class="ms">Sign out of your account</div></div>
            <i class="fas fa-chevron-right mc"></i>
        </a>
    </div>

</div>

<nav class="bnav">
    <a href="home.php" class="ni"><i class="fas fa-house"></i><span>Home</span></a>
    <a href="orders.php" class="ni"><i class="fas fa-clipboard-list"></i><span>Orders</span></a>
    <div class="fab-slot">
        <a href="new_order.php" class="fab"><i class="fas fa-plus"></i></a>
    </div>
    <a href="store.php" class="ni"><i class="fas fa-store"></i><span>Store</span></a>
    <a href="profile.php" class="ni active"><i class="fas fa-user"></i><span>Profile</span></a>
</nav>

</body>
</html>