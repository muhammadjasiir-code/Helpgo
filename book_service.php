<?php
// book_service.php – HelpGo Service Hub (Emerald Prestige theme)
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) { redirect(SITE_URL . 'login.php'); }

$services = [
    [
        'title' => 'Petrol Delivery',
        'desc'  => 'Fuel delivered to your location in minutes.',
        'badge' => 'Fast &amp; Reliable',
        'icon'  => 'fa-bolt',
        'img'   => 'assets/petrol.png',
        'link'  => 'petrol.php',
    ],
    [
        'title' => 'Grocery Delivery',
        'desc'  => 'Fresh groceries picked up &amp; delivered fast.',
        'badge' => 'Fresh &amp; Fast',
        'icon'  => 'fa-leaf',
        'img'   => 'assets/grocery.png',
        'link'  => 'grocery.php',
    ],
    [
        'title' => 'Parcel Delivery',
        'desc'  => 'Send packages anywhere across the city.',
        'badge' => 'Safe &amp; Secure',
        'icon'  => 'fa-shield-halved',
        'img'   => 'assets/parcel.png',
        'link'  => 'parcel.php',
    ],
    [
        'title' => 'Passenger Rides',
        'desc'  => 'Comfortable &amp; safe rides anytime, anywhere.',
        'badge' => 'Comfortable',
        'icon'  => 'fa-user',
        'img'   => 'assets/ride.png',
        'link'  => 'passenger.php',
    ],
];

$trust = [
    ['fa-shield-halved', 'Trusted Service', '100% Safe &amp; Reliable'],
    ['fa-bolt',          'Fast Delivery',   'On-Time, Every Time'],
    ['fa-location-dot',  'Live Tracking',   'Track in Real-Time'],
    ['fa-headset',       '24/7 Support',    "We're Always Here"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Book a Service – HelpGo</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
:root{
  --emerald:#0A2A20; --emerald-light:#12463A; --emerald-dark:#04140F;
  --card:#0E2C22; --card-2:#0B241C;
  --gold:#E5B23C; --gold-light:#F5D77A; --gold-dark:#B8862A;
  --white:#FFFFFF; --gray-soft:#C3CEC7; --gray-muted:#8FA096;
  --glass-border:rgba(229,178,60,.22);
  --shadow:0 22px 50px rgba(0,0,0,.45);
  --radius:26px; --font:'Poppins',sans-serif;
  --t:.35s cubic-bezier(.4,0,.2,1);
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:var(--font); color:var(--white); min-height:100vh;
  background:radial-gradient(ellipse at 15% -10%, #165044 0%, var(--emerald) 45%, var(--emerald-dark) 100%);
  display:flex; justify-content:center; padding:0 14px 40px; overflow-x:hidden; position:relative;
}
/* ambient orbs */
.bg-orb{position:fixed;border-radius:50%;filter:blur(140px);opacity:.14;pointer-events:none;z-index:0;animation:orbFloat 22s infinite alternate ease-in-out}
.bg-orb:nth-child(1){width:520px;height:520px;background:var(--gold);top:-220px;right:-170px}
.bg-orb:nth-child(2){width:380px;height:380px;background:var(--gold);bottom:-120px;left:-140px;animation-delay:-11s}
@keyframes orbFloat{0%{transform:translate(0,0) scale(1)}100%{transform:translate(45px,-35px) scale(1.12)}}

.container{width:100%;max-width:520px;position:relative;z-index:2}

/* gold swoosh top */
.swoosh{position:absolute;top:0;left:-14px;right:-14px;height:70px;z-index:3;pointer-events:none}
.swoosh svg{width:100%;height:100%}

/* ---------- HEADER ---------- */
.header{display:flex;align-items:flex-start;gap:14px;padding:44px 4px 26px;animation:fadeDown .7s ease both}
.back-btn{
  width:52px;height:52px;flex-shrink:0;border-radius:50%;
  background:linear-gradient(145deg,rgba(229,178,60,.10),rgba(0,0,0,.25));
  border:1px solid var(--glass-border);backdrop-filter:blur(18px);
  display:flex;align-items:center;justify-content:center;
  color:var(--gold);font-size:20px;text-decoration:none;
  box-shadow:0 8px 22px rgba(0,0,0,.35);transition:var(--t)
}
.back-btn:hover{background:rgba(229,178,60,.18);border-color:var(--gold);transform:translateX(-3px)}
.head-text{flex:1}
.head-text h1{font-size:32px;font-weight:800;letter-spacing:-.5px;line-height:1.1}
.head-text h1 span{color:var(--gold)}
.head-text p{font-size:14px;color:var(--gray-soft);margin-top:6px;font-weight:300}
.head-rule{width:56px;height:4px;border-radius:4px;background:var(--gold);margin-top:14px}
.brand{text-align:right;flex-shrink:0}
.brand .logo{font-size:26px;font-weight:900;font-style:italic;letter-spacing:-1px;line-height:1}
.brand .logo b{color:var(--gold)}
.brand small{display:block;font-size:6.5px;letter-spacing:1.4px;font-weight:600;color:var(--gray-soft);margin-top:5px;text-transform:uppercase}

/* ---------- SERVICE CARDS ---------- */
.service-grid{display:flex;flex-direction:column;gap:18px}
.service-card{
  position:relative;display:flex;align-items:center;gap:16px;
  background:linear-gradient(135deg,var(--card) 0%,var(--card-2) 100%);
  border:1px solid rgba(229,178,60,.12);
  border-radius:var(--radius);padding:14px;
  box-shadow:var(--shadow);text-decoration:none;color:var(--white);
  overflow:hidden;transition:var(--t);
  opacity:0;transform:translateY(26px);animation:fadeInUp .7s ease forwards
}
.service-card:nth-child(1){animation-delay:.10s}
.service-card:nth-child(2){animation-delay:.22s}
.service-card:nth-child(3){animation-delay:.34s}
.service-card:nth-child(4){animation-delay:.46s}
.service-card::after{
  content:'';position:absolute;top:-40%;right:-15%;width:180px;height:180px;
  background:radial-gradient(circle,rgba(229,178,60,.14) 0%,transparent 70%);
  border-radius:50%;pointer-events:none
}
.service-card:hover{transform:translateY(-6px);border-color:var(--gold);
  box-shadow:0 30px 60px rgba(0,0,0,.55),0 0 26px rgba(229,178,60,.22)}

.thumb{
  position:relative;width:120px;height:120px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  border-radius:24px;
  background:radial-gradient(circle at 50% 45%,rgba(229,178,60,.16) 0%,rgba(229,178,60,.04) 45%,transparent 72%);
}
.thumb::before{
  content:'';position:absolute;bottom:10px;left:18%;width:64%;height:12px;
  background:radial-gradient(ellipse,rgba(0,0,0,.55) 0%,transparent 70%);
  filter:blur(4px);animation:shadowPulse 4s ease-in-out infinite
}
.thumb img{
  position:relative;width:100%;height:100%;object-fit:contain;display:block;
  filter:drop-shadow(0 14px 22px rgba(0,0,0,.55)) drop-shadow(0 0 18px rgba(229,178,60,.22));
  animation:float3d 4s ease-in-out infinite;
  transition:transform .5s cubic-bezier(.4,0,.2,1)
}
.service-card:hover .thumb img{transform:scale(1.12) rotate(-3deg)}
@keyframes float3d{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
@keyframes shadowPulse{0%,100%{opacity:.75;transform:scaleX(1)}50%{opacity:.45;transform:scaleX(.82)}}

.service-info{flex:1;min-width:0}
.service-info h3{font-size:19px;font-weight:700;letter-spacing:-.3px}
.title-rule{width:34px;height:3px;border-radius:3px;background:var(--gold);margin:8px 0 10px}
.service-info p{font-size:13.5px;line-height:1.45;color:var(--gray-soft);font-weight:300}
.badge{
  display:inline-flex;align-items:center;gap:7px;margin-top:11px;
  padding:7px 14px;border-radius:50px;font-size:12.5px;font-weight:600;color:var(--gold);
  background:rgba(229,178,60,.07);border:1px solid rgba(229,178,60,.35)
}
.badge i{font-size:12px}

.go{display:flex;flex-direction:column;align-items:center;gap:9px;flex-shrink:0;padding-right:4px}
.go-circle{
  width:52px;height:52px;border-radius:50%;
  border:1.5px solid var(--gold);color:var(--gold);font-size:18px;
  display:flex;align-items:center;justify-content:center;
  background:radial-gradient(circle,rgba(229,178,60,.16),transparent 70%);
  box-shadow:0 0 18px rgba(229,178,60,.25);transition:var(--t);
  animation:pulseGold 2.6s infinite
}
.service-card:hover .go-circle{background:var(--gold);color:var(--emerald-dark);transform:translateX(4px);box-shadow:0 0 28px rgba(229,178,60,.6)}
.go span{font-size:13px;font-weight:700;color:var(--gold)}
@keyframes pulseGold{0%,100%{box-shadow:0 0 16px rgba(229,178,60,.20)}50%{box-shadow:0 0 26px rgba(229,178,60,.45)}}

/* ---------- TRUST BAR ---------- */
.trust{
  margin-top:22px;padding:22px 10px;border-radius:var(--radius);
  background:linear-gradient(135deg,var(--card) 0%,var(--card-2) 100%);
  border:1px solid rgba(229,178,60,.14);box-shadow:var(--shadow);
  display:grid;grid-template-columns:repeat(4,1fr);
  opacity:0;transform:translateY(26px);animation:fadeInUp .7s ease .6s forwards;
  position:relative
}
.trust::before{content:'';position:absolute;top:-1px;left:12%;right:12%;height:1px;
  background:linear-gradient(90deg,transparent,var(--gold),transparent)}
.trust-item{text-align:center;padding:0 6px;position:relative}
.trust-item + .trust-item::before{content:'';position:absolute;left:0;top:8%;height:84%;width:1px;background:rgba(229,178,60,.14)}
.trust-item i{font-size:24px;color:var(--gold);margin-bottom:10px;display:block;transition:var(--t)}
.trust-item:hover i{transform:translateY(-4px) scale(1.12)}
.trust-item h4{font-size:12.5px;font-weight:700}
.trust-item p{font-size:10.5px;color:var(--gray-muted);margin-top:3px;font-weight:300}

@keyframes fadeInUp{to{opacity:1;transform:translateY(0)}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:none}}

@media (max-width:420px){
  .head-text h1{font-size:26px}
  .brand .logo{font-size:21px}
  .thumb{width:96px;height:96px}
  .service-info h3{font-size:16.5px}
  .service-info p{font-size:12.5px}
  .go-circle{width:44px;height:44px;font-size:16px}
  .trust-item i{font-size:20px}
}
@media (prefers-reduced-motion:reduce){
  *{animation:none !important;transition:none !important}
  .service-card,.trust{opacity:1;transform:none}
}
</style>
</head>
<body>
<div class="bg-orb"></div>
<div class="bg-orb"></div>

<div class="container">
  <div class="swoosh">
    <svg viewBox="0 0 520 70" preserveAspectRatio="none" fill="none">
      <path d="M0 6 C120 6 150 2 210 2 C300 2 340 34 420 40 C470 44 500 46 520 46"
            stroke="url(#g)" stroke-width="3" stroke-linecap="round"/>
      <defs><linearGradient id="g" x1="0" y1="0" x2="520" y2="0">
        <stop stop-color="#B8862A" stop-opacity="0"/><stop offset=".45" stop-color="#F5D77A"/>
        <stop offset="1" stop-color="#B8862A" stop-opacity=".2"/>
      </linearGradient></defs>
    </svg>
  </div>

  <div class="header">
    <a href="home.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="head-text">
      <h1>Book a <span>Service</span></h1>
      <p>Thrikkaripur</p>
      <div class="head-rule"></div>
    </div>
    <div class="brand">
      <div class="logo">Help<b>Go</b></div>
      <small>Thrikkaripur</small>
    </div>
  </div>

  <div class="service-grid">
    <?php foreach ($services as $s): ?>
    <a href="<?= htmlspecialchars($s['link']) ?>" class="service-card">
      <div class="thumb">
        <img src="<?= htmlspecialchars($s['img']) ?>" alt="<?= htmlspecialchars($s['title']) ?>" loading="lazy" width="120" height="120">
      </div>
      <div class="service-info">
        <h3><?= $s['title'] ?></h3>
        <div class="title-rule"></div>
        <p><?= $s['desc'] ?></p>
        <span class="badge"><i class="fa-solid <?= $s['icon'] ?>"></i><?= $s['badge'] ?></span>
      </div>
      <div class="go">
        <span class="go-circle"><i class="fa-solid fa-arrow-right"></i></span>
        <span>Book Now</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="trust">
    <?php foreach ($trust as $t): ?>
    <div class="trust-item">
      <i class="fa-solid <?= $t[0] ?>"></i>
      <h4><?= $t[1] ?></h4>
      <p><?= $t[2] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
