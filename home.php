<?php
/**
 * HelpGo — Home
 * Requires: config.php (provides $conn, isLoggedIn(), redirect(), getUserData())
 */
declare(strict_types=1);
require_once "config.php";

if (!isLoggedIn()) { redirect('index.php'); }

$uid  = (int)($_SESSION['user_id'] ?? 0);
$user = getUserData($uid);
$name = trim((string)($user['full_name'] ?? '')) ?: 'User';
$initial = strtoupper(mb_substr($name, 0, 1, 'UTF-8'));

/* ---------- Recent orders (prepared statement) ---------- */
$orders = [];
if ($stmt = mysqli_prepare($conn, "SELECT order_id, service_type, status, order_date
                                   FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5")) {
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $orders[] = $row; }
    mysqli_stmt_close($stmt);
}

/* ---------- Unread notifications (safe fallback) ---------- */
$notifCount = 0;
if ($stmt = @mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")) {
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    if (@mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $notifCount);
        mysqli_stmt_fetch($stmt);
        $notifCount = (int)$notifCount;
    }
    mysqli_stmt_close($stmt);
}

/* ---------- Helpers ---------- */
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$hour      = (int)date('H');
$partOfDay = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$SERVICES = [
    ['slug' => 'petrol',    'href' => 'petrol.php',    'icon' => 'fa-gas-pump',        'ghost' => 'fa-gas-pump',        'title' => 'Petrol Rescue',       'sub' => 'Emergency fuel, delivered'],
    ['slug' => 'grocery',   'href' => 'grocery.php',   'icon' => 'fa-basket-shopping', 'ghost' => 'fa-basket-shopping', 'title' => 'Grocery Delivery',    'sub' => 'Daily essentials'],
    ['slug' => 'parcel',    'href' => 'parcel.php',    'icon' => 'fa-box-open',        'ghost' => 'fa-boxes-packing',   'title' => 'Parcel Delivery',     'sub' => 'Fast &amp; insured'],
    ['slug' => 'passenger', 'href' => 'passenger.php', 'icon' => 'fa-user-group',      'ghost' => 'fa-car-side',        'title' => 'Passenger Rides',     'sub' => 'Safe rides, anytime'],
];

$SERVICE_MAP = [
    'petrol'    => ['fa-gas-pump',        'Petrol Rescue'],
    'grocery'   => ['fa-basket-shopping', 'Grocery Delivery'],
    'parcel'    => ['fa-box-open',        'Parcel Delivery'],
    'passenger' => ['fa-user-group',      'Passenger Rides'],
];

function statusChip(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['delivered','completed'], true))                            return 'c-done';
    if ($s === 'cancelled' || $s === 'canceled' || $s === 'failed')                return 'c-cancel';
    if (in_array($s, ['accepted','confirmed','picked_up','in_transit'], true))     return 'c-active';
    return 'c-wait';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>HelpGo — Fast Service. Every Time.</title>
<meta name="description" content="HelpGo delivers petrol rescue, groceries, parcels and passenger rides in minutes. Real people, 24/7 support.">
<meta name="robots" content="noindex,nofollow">
<link rel="canonical" href="/home.php">

<!-- Social -->
<meta property="og:type" content="website">
<meta property="og:title" content="HelpGo — Fast Service. Every Time.">
<meta property="og:description" content="Petrol rescue, groceries, parcels and rides — delivered in minutes.">
<meta property="og:image" content="/assets/hero.jpg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="/assets/hero.jpg">

<!-- PWA -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#03150f">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="HelpGo">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon.png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="image" href="/assets/hero.jpg" fetchpriority="high">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ══ HelpGo design tokens ══════════════════ */
:root{
  --bg:#03150f; --bg-soft:#052e24;
  --surface:rgba(6,78,59,.42); --surface-hi:rgba(13,122,95,.30);
  --primary:#c9a84c; --primary-lt:#f0d78c; --primary-soft:rgba(201,168,76,.14);
  --line-gold:rgba(201,168,76,.28);
  --fg:#f5f0e0; --fg-mute:#a9a894;
  --border:rgba(245,240,224,.08);
  --ok:#3ddc97;
  --r-lg:24px; --r-md:18px; --r-sm:14px;
  --ease:cubic-bezier(.22,1,.36,1); --t:.28s var(--ease);
  --shadow:0 12px 34px rgba(0,0,0,.45);
  --safe-b:env(safe-area-inset-bottom,0px);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth}
body{
  font-family:'Manrope',system-ui,sans-serif;background:var(--bg);color:var(--fg);
  min-height:100svh;overflow-x:hidden;display:flex;justify-content:center;align-items:flex-start;
  -webkit-font-smoothing:antialiased;
}
body::before{
  content:"";position:fixed;inset:-20%;z-index:-2;pointer-events:none;
  background:
    radial-gradient(520px 420px at 20% 8%,  rgba(13,122,95,.38), transparent 62%),
    radial-gradient(460px 380px at 88% 26%, rgba(201,168,76,.12), transparent 62%),
    radial-gradient(560px 460px at 50% 96%, rgba(13,122,95,.22), transparent 65%);
  animation:drift 22s ease-in-out infinite alternate;
}
@keyframes drift{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(-2%,2%,0) scale(1.06)}100%{transform:translate3d(2%,-2%,0) scale(1.02)}}
a{text-decoration:none;color:inherit}
:focus-visible{outline:2px solid var(--primary);outline-offset:3px;border-radius:12px}
.app{width:100%;max-width:440px;padding:16px 16px calc(132px + var(--safe-b));position:relative}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

.reveal{opacity:0;transform:translateY(18px);transition:opacity .6s var(--ease),transform .6s var(--ease)}
.reveal.in{opacity:1;transform:none}

.sheen{position:relative;overflow:hidden}
.sheen::after{
  content:"";position:absolute;top:0;left:-140%;width:60%;height:100%;
  background:linear-gradient(100deg,transparent,rgba(255,255,255,.10),transparent);
  transform:skewX(-18deg);animation:sheen 5.5s var(--ease) infinite;pointer-events:none;
}
@keyframes sheen{0%,72%{left:-140%}100%{left:150%}}

/* ══ Header ══ */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}
.brand{display:flex;align-items:center;gap:11px}
.brand-mark{width:42px;height:42px;border-radius:13px;flex-shrink:0;object-fit:cover;
  border:1px solid var(--line-gold);box-shadow:0 6px 16px rgba(0,0,0,.4)}
.logo{font-family:'Sora',sans-serif;font-size:23px;font-weight:800;letter-spacing:-.7px;line-height:1}
.logo span{color:var(--primary)}
.tagline{font-size:10.5px;color:var(--fg-mute);margin-top:4px;letter-spacing:.3px}
.head-actions{display:flex;gap:10px;align-items:center}
.ibtn{width:44px;height:44px;border-radius:50%;position:relative;background:var(--surface);
  border:1px solid var(--border);display:flex;align-items:center;justify-content:center;
  color:var(--fg);font-size:16px;transition:transform var(--t),background var(--t),border-color var(--t)}
.ibtn:hover{background:var(--surface-hi);border-color:var(--line-gold)}
.ibtn:active{transform:scale(.93)}
.ibtn .fa-bell{transform-origin:50% 12%}
.ibtn:hover .fa-bell{animation:ring .7s var(--ease)}
@keyframes ring{0%,100%{transform:rotate(0)}20%{transform:rotate(14deg)}40%{transform:rotate(-11deg)}60%{transform:rotate(7deg)}80%{transform:rotate(-4deg)}}
.badge{position:absolute;top:-3px;right:-3px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;
  background:var(--primary);color:#052e24;font-size:10.5px;font-weight:800;display:flex;align-items:center;
  justify-content:center;border:2px solid var(--bg);animation:pop .5s var(--ease) .5s both}
@keyframes pop{from{transform:scale(0)}60%{transform:scale(1.18)}to{transform:scale(1)}}
.avatar{width:44px;height:44px;border-radius:50%;
  background:linear-gradient(140deg,rgba(13,122,95,.6),rgba(3,21,15,.9));
  border:1px solid var(--line-gold);display:flex;align-items:center;justify-content:center;
  font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:var(--primary);transition:transform var(--t)}
.avatar:active{transform:scale(.93)}

/* ══ Greeting ══ */
.greet{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:20px}
.greet .kicker{font-size:12.5px;color:var(--fg-mute);letter-spacing:.3px}
.greet h1{font-family:'Sora',sans-serif;font-size:24px;font-weight:700;line-height:1.25;margin-top:4px}
.greet h1 b{color:var(--primary);font-weight:800}
.wave{display:inline-block;transform-origin:70% 80%;animation:wave 2.6s var(--ease) infinite}
@keyframes wave{0%,60%,100%{transform:rotate(0)}10%{transform:rotate(14deg)}20%{transform:rotate(-8deg)}30%{transform:rotate(14deg)}40%{transform:rotate(-4deg)}50%{transform:rotate(10deg)}}
.online{display:inline-flex;align-items:center;gap:8px;flex-shrink:0;padding:9px 14px;border-radius:30px;
  background:var(--surface);border:1px solid var(--border);font-size:12px;font-weight:600;white-space:nowrap}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--ok);animation:ripple 2s infinite}
@keyframes ripple{0%{box-shadow:0 0 0 0 rgba(61,220,151,.5)}70%{box-shadow:0 0 0 8px rgba(61,220,151,0)}100%{box-shadow:0 0 0 0 rgba(61,220,151,0)}}

/* ══ Hero (real photography) ══ */
.hero{position:relative;overflow:hidden;border-radius:var(--r-lg);border:1px solid var(--line-gold);
  box-shadow:var(--shadow);padding:26px 20px;margin-bottom:28px;isolation:isolate}
.hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}
.hero::after{content:"";position:absolute;inset:0;z-index:-1;
  background:linear-gradient(100deg,rgba(3,21,15,.96) 18%,rgba(5,46,36,.72) 55%,rgba(3,21,15,.35) 100%)}
.hero::before{content:"";position:absolute;left:0;top:14%;height:72%;width:3px;border-radius:3px;
  background:linear-gradient(180deg,transparent,var(--primary),transparent);animation:barGlow 3.2s ease-in-out infinite}
@keyframes barGlow{0%,100%{opacity:.45;box-shadow:0 0 8px var(--primary)}50%{opacity:1;box-shadow:0 0 20px var(--primary)}}
.hero-in{position:relative;z-index:2;max-width:74%}
.hero h2{font-family:'Sora',sans-serif;font-size:28px;font-weight:800;line-height:1.12;letter-spacing:-.6px;text-shadow:0 2px 18px rgba(0,0,0,.6)}
.hero h2 b{display:block;color:var(--primary);font-weight:800}
.hero p{font-size:12.5px;color:#d8d3c1;margin-top:11px;line-height:1.65}
.cta{display:inline-flex;align-items:center;gap:11px;margin-top:18px;padding:11px 11px 11px 20px;border-radius:40px;
  background:linear-gradient(135deg,var(--primary-lt),var(--primary));color:#052e24;font-weight:800;font-size:14.5px;
  box-shadow:0 10px 24px rgba(201,168,76,.28);transition:transform var(--t),box-shadow var(--t)}
.cta:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(201,168,76,.4)}
.cta:active{transform:translateY(1px) scale(.98)}
.cta i{width:27px;height:27px;border-radius:50%;background:#052e24;color:var(--primary);
  display:flex;align-items:center;justify-content:center;font-size:11px;transition:transform var(--t)}
.cta:hover i{transform:translateX(3px)}
.trust{display:flex;gap:14px;flex-wrap:wrap;margin-top:16px;position:relative;z-index:2}
.trust span{font-size:10.5px;color:#cfcab5;display:inline-flex;align-items:center;gap:6px}
.trust i{color:var(--primary)}

/* ══ Section titles ══ */
.sec{display:flex;justify-content:space-between;align-items:center;margin:0 0 14px}
.sec h3{font-family:'Sora',sans-serif;font-size:17px;font-weight:700;position:relative;padding-left:12px}
.sec h3::before{content:"";position:absolute;left:0;top:22%;height:56%;width:3px;border-radius:3px;background:var(--primary)}
.link{font-size:12.5px;color:var(--primary);font-weight:700;display:inline-flex;align-items:center;gap:6px;transition:gap var(--t)}
.link:hover{gap:10px}

/* ══ Services ══ */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px}
.svc{position:relative;overflow:hidden;min-height:142px;border-radius:var(--r-md);padding:14px;
  border:1px solid var(--border);background:linear-gradient(150deg,var(--surface-hi),rgba(4,26,20,.85));
  display:flex;flex-direction:column;transition:transform var(--t),border-color var(--t),box-shadow var(--t)}
.svc:hover{transform:translateY(-4px);border-color:var(--line-gold);box-shadow:0 14px 28px rgba(0,0,0,.4)}
.svc:active{transform:translateY(-1px) scale(.99)}
.svc::after{content:"";position:absolute;left:14%;right:14%;bottom:0;height:2px;
  background:linear-gradient(90deg,transparent,var(--primary),transparent);opacity:0;transition:opacity var(--t)}
.svc:hover::after{opacity:.85}
.svc-ic{width:48px;height:48px;border-radius:15px;background:var(--primary-soft);border:1px solid var(--line-gold);
  display:flex;align-items:center;justify-content:center;font-size:21px;color:var(--primary);
  transition:transform var(--t),background var(--t)}
.svc:hover .svc-ic{transform:translateY(-3px) rotate(-6deg);background:rgba(201,168,76,.22)}
.svc h4{font-family:'Sora',sans-serif;font-size:15px;font-weight:600;margin-top:auto}
.svc p{font-size:11.5px;color:var(--fg-mute);margin-top:3px}
.svc-go{position:absolute;right:12px;bottom:12px;width:30px;height:30px;border-radius:10px;background:var(--primary);
  color:#052e24;display:flex;align-items:center;justify-content:center;font-size:12px;transition:transform var(--t)}
.svc:hover .svc-go{transform:translateX(3px)}
.svc-ghost{position:absolute;right:-8px;top:12px;font-size:48px;color:rgba(201,168,76,.10);transition:transform var(--t)}
.svc:hover .svc-ghost{transform:scale(1.12) rotate(6deg)}

/* ══ Overview ══ */
.card{border-radius:var(--r-lg);border:1px solid var(--border);
  background:linear-gradient(160deg,var(--surface),rgba(3,21,15,.82));box-shadow:var(--shadow);padding:16px 14px;margin-bottom:28px}
.card-head{display:flex;align-items:center;gap:9px;margin-bottom:16px}
.card-head i{color:var(--ok);font-size:14px;animation:beat 1.8s ease-in-out infinite}
@keyframes beat{0%,100%{transform:scale(1);opacity:.85}50%{transform:scale(1.22);opacity:1}}
.card-head h3{font-family:'Sora',sans-serif;font-size:16px;font-weight:700}
.stats{display:flex;justify-content:space-between;text-align:center}
.stat{flex:1;position:relative}
.stat + .stat::before{content:"";position:absolute;left:0;top:12%;height:76%;width:1px;background:var(--border)}
.stat .ring{width:40px;height:40px;margin:0 auto 8px;border-radius:50%;background:var(--primary-soft);
  border:1px solid var(--line-gold);display:flex;align-items:center;justify-content:center;color:var(--primary);
  font-size:15px;transition:transform var(--t)}
.stat:hover .ring{transform:translateY(-3px) scale(1.06)}
.stat h4{font-family:'Sora',sans-serif;font-size:17px;font-weight:800}
.stat p{font-size:9px;color:var(--fg-mute);text-transform:uppercase;letter-spacing:.8px;margin-top:2px}
.install{margin-top:16px;padding-top:14px;border-top:1px solid var(--border);text-align:center}
.btn-gold{display:inline-flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:13px 18px;border:none;
  cursor:pointer;border-radius:30px;background:linear-gradient(135deg,var(--primary-lt),var(--primary));color:#052e24;
  font-weight:800;font-size:14.5px;font-family:'Manrope',sans-serif;box-shadow:0 8px 20px rgba(201,168,76,.26);
  transition:transform var(--t),box-shadow var(--t)}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(201,168,76,.38)}
.btn-gold:active{transform:translateY(1px) scale(.99)}
.btn-gold i{animation:bob 2.2s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(3px)}}
.install small{display:block;margin-top:10px;font-size:11.5px;color:var(--fg-mute)}
.hint{display:none;margin-top:12px;padding:12px 14px;background:rgba(6,78,59,.55);border:1px solid var(--border);
  border-radius:12px;font-size:12px;line-height:1.55;text-align:left}

/* ══ Location ══ */
.loc{border-radius:var(--r-lg);border:1px solid var(--border);
  background:linear-gradient(160deg,var(--surface),rgba(3,21,15,.82));padding:14px 16px;margin-bottom:28px;
  display:flex;align-items:center;gap:12px}
.loc-ic{width:44px;height:44px;border-radius:14px;background:var(--primary-soft);border:1px solid var(--line-gold);
  display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--primary);animation:ripGold 2.6s infinite}
@keyframes ripGold{0%{box-shadow:0 0 0 0 rgba(201,168,76,.3)}70%{box-shadow:0 0 0 12px rgba(201,168,76,0)}100%{box-shadow:0 0 0 0 rgba(201,168,76,0)}}
.loc-tx{flex:1}
.loc-tx h4{font-family:'Sora',sans-serif;font-size:14px;font-weight:600}
.loc-tx p{font-size:11px;color:var(--fg-mute);margin-top:2px}
.pill{background:linear-gradient(135deg,var(--primary-lt),var(--primary));color:#052e24;border:none;padding:10px 17px;
  border-radius:30px;font-weight:800;font-size:12.5px;font-family:'Manrope',sans-serif;cursor:pointer;white-space:nowrap;
  transition:transform var(--t),filter var(--t)}
.pill:hover{transform:translateY(-2px)}
.pill:disabled{filter:saturate(.5);cursor:default;transform:none}

/* ══ Orders ══ */
.orders{display:flex;flex-direction:column;gap:9px}
.order{position:relative;overflow:hidden;background:linear-gradient(120deg,var(--surface),rgba(3,21,15,.85));
  border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px 12px 17px;display:flex;align-items:center;
  gap:12px;transition:transform var(--t),background var(--t),border-color var(--t)}
.order::before{content:"";position:absolute;left:0;top:16%;height:68%;width:3px;border-radius:3px;
  background:linear-gradient(180deg,var(--primary),rgba(201,168,76,0))}
.order:hover{transform:translateX(4px);background:var(--surface-hi);border-color:var(--line-gold)}
.order-ic{width:38px;height:38px;border-radius:12px;background:var(--primary-soft);border:1px solid var(--line-gold);
  display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--primary);transition:transform var(--t)}
.order:hover .order-ic{transform:rotate(-8deg)}
.order-tx{flex:1;min-width:0}
.order-tx h4{font-size:13.5px;font-weight:700}
.order-tx p{font-size:10.5px;color:var(--fg-mute);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chip{padding:5px 11px;border-radius:20px;font-size:10.5px;font-weight:700;border:1px solid transparent;white-space:nowrap;text-transform:capitalize}
.c-done{background:rgba(61,220,151,.12);color:var(--ok);border-color:rgba(61,220,151,.3)}
.c-active{background:rgba(127,227,187,.10);color:#7fe3bb;border-color:rgba(127,227,187,.26)}
.c-wait{background:rgba(201,168,76,.12);color:var(--primary-lt);border-color:var(--line-gold)}
.c-cancel{background:rgba(255,107,107,.12);color:#ff8b8b;border-color:rgba(255,107,107,.28)}
.chev{color:var(--fg-mute);font-size:13px;transition:transform var(--t)}
.order:hover .chev{transform:translateX(3px);color:var(--primary)}
.empty{padding:28px;text-align:center;color:var(--fg-mute);background:var(--surface);border-radius:var(--r-lg);border:1px dashed var(--border)}
.empty img{width:78px;height:78px;border-radius:20px;opacity:.75;margin-bottom:12px}

/* ══ Bottom nav ══ */
.nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:440px;z-index:99;
  background:rgba(4,26,20,.9);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-top:1px solid var(--border);border-radius:24px 24px 0 0;box-shadow:0 -8px 26px rgba(0,0,0,.5);
  display:flex;justify-content:space-around;align-items:flex-end;padding:11px 8px calc(18px + var(--safe-b));
  animation:navUp .6s var(--ease) .2s both}
@keyframes navUp{from{transform:translate(-50%,100%)}to{transform:translate(-50%,0)}}
.nav a{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;color:var(--fg-mute);font-size:10.5px;
  font-weight:600;transition:color var(--t),transform var(--t)}
.nav a i{font-size:19px;transition:transform var(--t)}
.nav a:active i{transform:scale(.86)}
.nav a.on{color:var(--primary)}
.nav a.on i{transform:translateY(-2px)}
.nav .fab{width:60px;height:60px;border-radius:50%;flex:0 0 60px;margin-top:-34px;
  background:linear-gradient(135deg,var(--primary-lt),var(--primary));color:#052e24;display:flex;align-items:center;
  justify-content:center;font-size:23px;border:4px solid var(--bg);
  box-shadow:0 0 24px rgba(201,168,76,.4),0 10px 20px rgba(0,0,0,.45);animation:fabPulse 3s ease-in-out infinite}
@keyframes fabPulse{0%,100%{box-shadow:0 0 18px rgba(201,168,76,.32),0 10px 20px rgba(0,0,0,.45)}50%{box-shadow:0 0 34px rgba(201,168,76,.6),0 10px 20px rgba(0,0,0,.45)}}
.nav .fab:active{transform:scale(.92) rotate(90deg);transition:transform .25s var(--ease)}

/* ══ PWA popup ══ */
#pwaPop{position:fixed;bottom:calc(104px + var(--safe-b));left:50%;transform:translate(-50%,20px);width:92%;max-width:392px;
  display:none;align-items:center;gap:12px;z-index:9999;background:linear-gradient(135deg,#064e3b,#0b6b52);
  border:1px solid var(--line-gold);border-radius:var(--r-md);padding:14px 16px;box-shadow:0 16px 36px rgba(0,0,0,.55);
  opacity:0;transition:opacity .4s var(--ease),transform .4s var(--ease)}
#pwaPop.show{display:flex;opacity:1;transform:translate(-50%,0)}
.pop-ic{width:42px;height:42px;border-radius:12px;flex-shrink:0;object-fit:cover}
.pop-tx{flex:1;min-width:0}
.pop-tx h4{font-family:'Sora',sans-serif;font-size:13.5px;font-weight:700}
.pop-tx p{font-size:11px;color:#cfcab5;margin-top:2px}
.pop-yes{background:var(--primary);color:#052e24;border:none;padding:9px 14px;border-radius:20px;font-weight:800;font-size:12px;cursor:pointer;font-family:'Manrope',sans-serif}
.pop-no{background:transparent;color:#cfcab5;border:none;font-size:19px;cursor:pointer;padding:4px 6px;line-height:1}

/* ══ Toast ══ */
#toast{position:fixed;left:50%;bottom:calc(120px + var(--safe-b));transform:translate(-50%,16px);
  background:rgba(6,78,59,.96);border:1px solid var(--line-gold);color:var(--fg);padding:11px 18px;border-radius:30px;
  font-size:13px;font-weight:600;box-shadow:var(--shadow);opacity:0;pointer-events:none;z-index:10000;
  transition:opacity .3s var(--ease),transform .3s var(--ease)}
#toast.show{opacity:1;transform:translate(-50%,0)}

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important}
  .reveal{opacity:1;transform:none}
}
@media (max-width:360px){.hero h2{font-size:25px}.svc{min-height:134px}}
</style>
</head>
<body>

<div class="app">

  <!-- Header -->
  <header class="header reveal">
    <div class="brand">
      <img class="brand-mark" src="/assets/icon-192.png" width="42" height="42" alt="HelpGo logo">
      <div>
        <div class="logo">Help<span>Go</span></div>
        <div class="tagline">We care. We deliver.</div>
      </div>
    </div>
    <div class="head-actions">
      <a href="notifications.php" class="ibtn" aria-label="Notifications<?= $notifCount > 0 ? ', ' . $notifCount . ' unread' : '' ?>">
        <i class="fas fa-bell" aria-hidden="true"></i>
        <?php if ($notifCount > 0): ?><span class="badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span><?php endif; ?>
      </a>
      <a href="profile.php" class="avatar" aria-label="Your profile"><?= e($initial) ?></a>
    </div>
  </header>

  <!-- Greeting -->
  <section class="greet reveal" aria-label="Greeting">
    <div>
      <div class="kicker"><?= e($partOfDay) ?>,</div>
      <h1><b><?= e($name) ?></b> <span class="wave" aria-hidden="true">👋</span></h1>
    </div>
    <span class="online"><span class="live-dot" aria-hidden="true"></span> Online</span>
  </section>

  <!-- Hero -->
  <section class="hero reveal sheen">
    <img class="hero-img" src="/assets/hero.jpg" width="1280" height="720" alt="" aria-hidden="true" fetchpriority="high">
    <div class="hero-in">
      <h2>Fast Service.<b>Every Time.</b></h2>
      <p>Quick support. Real people.<br>We're here for you 24/7.</p>
      <a href="book_service.php" class="cta">Book Now <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="trust">
      <span><i class="fas fa-shield-halved" aria-hidden="true"></i> Verified riders</span>
      <span><i class="fas fa-bolt" aria-hidden="true"></i> ~12 min arrival</span>
      <span><i class="fas fa-lock" aria-hidden="true"></i> Secure payments</span>
    </div>
  </section>

  <!-- Services -->
  <div class="sec reveal">
    <h3>Our Services</h3>
    <a href="book_service.php" class="link">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
  </div>
  <section class="grid" aria-label="Services">
    <?php foreach ($SERVICES as $s): ?>
      <a href="<?= e($s['href']) ?>" class="svc reveal">
        <div class="svc-ic"><i class="fas <?= e($s['icon']) ?>" aria-hidden="true"></i></div>
        <i class="fas <?= e($s['ghost']) ?> svc-ghost" aria-hidden="true"></i>
        <h4><?= $s['title'] ?></h4>
        <p><?= $s['sub'] ?></p>
        <span class="svc-go"><i class="fas fa-arrow-right" aria-hidden="true"></i></span>
      </a>
    <?php endforeach; ?>
  </section>

  <!-- Live Overview -->
  <section class="card reveal">
    <div class="card-head"><i class="fas fa-circle" aria-hidden="true"></i><h3>Live Overview</h3></div>
    <div class="stats">
      <div class="stat"><div class="ring"><i class="fas fa-headset" aria-hidden="true"></i></div><h4 data-count="24" data-suffix="/7">24/7</h4><p>Support</p></div>
      <div class="stat"><div class="ring"><i class="fas fa-clock" aria-hidden="true"></i></div><h4 data-count="12" data-suffix="m">12m</h4><p>Avg. Arrival</p></div>
      <div class="stat"><div class="ring"><i class="fas fa-shield-halved" aria-hidden="true"></i></div><h4 data-count="98" data-suffix="%">98%</h4><p>Success</p></div>
      <div class="stat"><div class="ring"><i class="fas fa-star" aria-hidden="true"></i></div><h4 data-count="4.9">4.9</h4><p>Rating</p></div>
    </div>
    <div class="install">
      <button type="button" id="installBtn" class="btn-gold"><i class="fas fa-download" aria-hidden="true"></i> Install App</button>
      <small>Get the best experience</small>
      <div id="iosHint" class="hint"><b>Install on iPhone:</b> tap the <i class="fas fa-share" aria-hidden="true"></i> Share icon in Safari, then <b>"Add to Home Screen"</b>.</div>
    </div>
  </section>

  <!-- Live location -->
  <section class="loc reveal">
    <div class="loc-ic"><i class="fas fa-map-pin" aria-hidden="true"></i></div>
    <div class="loc-tx">
      <h4>Live Location</h4>
      <p>Enable GPS for accurate tracking</p>
    </div>
    <button class="pill" id="locBtn" type="button">Enable</button>
  </section>

  <!-- Recent orders -->
  <div class="sec reveal">
    <h3>Recent Orders</h3>
    <a href="orders.php" class="link">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
  </div>
  <section class="orders" aria-label="Recent orders">
    <?php if ($orders): ?>
      <?php foreach ($orders as $ord):
        $svc = strtolower((string)$ord['service_type']);
        [$icon, $label] = $SERVICE_MAP[$svc] ?? ['fa-box-open', ucfirst($svc)];
        $chip = statusChip((string)$ord['status']);
        $when = $ord['order_date'] ? date('d M · H:i', strtotime((string)$ord['order_date'])) : '';
      ?>
        <a href="orders.php?order_id=<?= urlencode((string)$ord['order_id']) ?>" class="order reveal">
          <div class="order-ic"><i class="fas <?= e($icon) ?>" aria-hidden="true"></i></div>
          <div class="order-tx">
            <h4><?= e($label) ?></h4>
            <p>#<?= e((string)$ord['order_id']) ?> &nbsp;•&nbsp; <?= e($when) ?></p>
          </div>
          <span class="chip <?= $chip ?>"><?= e(ucfirst(str_replace('_', ' ', (string)$ord['status']))) ?></span>
          <i class="fas fa-chevron-right chev" aria-hidden="true"></i>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty reveal">
        <img src="/assets/icon-192.png" width="78" height="78" alt="" loading="lazy">
        <p style="font-size:13px">No orders yet — book your first service</p>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- PWA popup -->
<div id="pwaPop" role="dialog" aria-label="Install HelpGo">
  <img class="pop-ic" src="/assets/icon-192.png" width="42" height="42" alt="">
  <div class="pop-tx">
    <h4>Install HelpGo</h4>
    <p>Faster access, works offline</p>
  </div>
  <button id="popYes" class="pop-yes" type="button">Install</button>
  <button id="popNo" class="pop-no" type="button" aria-label="Dismiss">&times;</button>
</div>

<div id="toast" role="status" aria-live="polite"></div>

<!-- Bottom nav -->
<nav class="nav" aria-label="Primary">
  <a href="home.php" class="on" aria-current="page"><i class="fas fa-house" aria-hidden="true"></i><span>Home</span></a>
  <a href="orders.php"><i class="fas fa-clipboard-list" aria-hidden="true"></i><span>Orders</span></a>
  <a href="book_service.php" class="fab" aria-label="Book service"><i class="fas fa-plus" aria-hidden="true"></i></a>
  <a href="store.php"><i class="fas fa-store" aria-hidden="true"></i><span>Store</span></a>
  <a href="profile.php"><i class="fas fa-user" aria-hidden="true"></i><span>Profile</span></a>
</nav>

<script>
/* ── Toast ── */
const toastEl = document.getElementById('toast');
let toastTimer;
function toast(msg){
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2600);
}

/* ── Scroll reveal ── */
(function(){
  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach((e, i) => {
      if (!e.isIntersecting) return;
      setTimeout(() => e.target.classList.add('in'), i * 70);
      obs.unobserve(e.target);
    });
  }, { threshold:.12, rootMargin:'0px 0px -40px 0px' });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));
})();

/* ── Stat counters (values render server-side, JS only animates) ── */
(function(){
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const el = e.target, target = parseFloat(el.dataset.count), suffix = el.dataset.suffix || '';
      const dec = target % 1 !== 0 ? 1 : 0, dur = 1100, t0 = performance.now();
      (function tick(now){
        const p = Math.min((now - t0) / dur, 1);
        el.textContent = (target * (1 - Math.pow(1 - p, 3))).toFixed(dec) + suffix;
        if (p < 1) requestAnimationFrame(tick);
      })(t0);
      obs.unobserve(el);
    });
  }, { threshold:.5 });
  document.querySelectorAll('[data-count]').forEach(n => io.observe(n));
})();

/* ── Ripple ── */
document.querySelectorAll('.svc, .order, .cta, .btn-gold, .pill, .nav a').forEach(el => {
  el.addEventListener('pointerdown', ev => {
    const r = el.getBoundingClientRect(), s = Math.max(r.width, r.height) * 1.4;
    const d = document.createElement('span');
    d.style.cssText =
      `position:absolute;left:${ev.clientX - r.left - s/2}px;top:${ev.clientY - r.top - s/2}px;` +
      `width:${s}px;height:${s}px;border-radius:50%;background:rgba(201,168,76,.20);pointer-events:none;` +
      `transform:scale(0);opacity:1;transition:transform .55s cubic-bezier(.22,1,.36,1),opacity .6s ease;`;
    if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
    el.style.overflow = 'hidden';
    el.appendChild(d);
    requestAnimationFrame(() => { d.style.transform = 'scale(1)'; d.style.opacity = '0'; });
    setTimeout(() => d.remove(), 650);
  });
});

/* ── Tilt (pointer devices only) ── */
if (window.matchMedia('(hover: hover)').matches) {
  document.querySelectorAll('.svc').forEach(card => {
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
      card.style.transform = `translateY(-4px) rotateX(${-py*6}deg) rotateY(${px*6}deg)`;
    });
    card.addEventListener('pointerleave', () => { card.style.transform = ''; });
  });
}

/* ── PWA install ── */
let deferredPrompt = null;
const pop = document.getElementById('pwaPop');
const installBtn = document.getElementById('installBtn');
const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

if (standalone) { pop.style.display = 'none'; installBtn.style.display = 'none'; }

window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  deferredPrompt = e;
  if (standalone) return;
  const dismissed = parseInt(localStorage.getItem('pwaDismissedAt') || '0', 10);
  if (!dismissed || Date.now() - dismissed > 3*24*60*60*1000) setTimeout(() => pop.classList.add('show'), 1800);
});

async function promptInstall(){
  if (!deferredPrompt) return false;
  deferredPrompt.prompt();
  const choice = await deferredPrompt.userChoice;
  deferredPrompt = null;
  if (choice.outcome === 'accepted') installBtn.style.display = 'none';
  return true;
}

document.getElementById('popYes').addEventListener('click', () => { pop.classList.remove('show'); promptInstall(); });
document.getElementById('popNo').addEventListener('click', () => {
  pop.classList.remove('show');
  localStorage.setItem('pwaDismissedAt', Date.now().toString());
});

window.addEventListener('appinstalled', () => {
  pop.classList.remove('show');
  installBtn.style.display = 'none';
  toast('HelpGo installed');
});

installBtn.addEventListener('click', async () => {
  if (await promptInstall()) return;
  if (/iPad|iPhone|iPod/.test(navigator.userAgent || '') && !window.MSStream) {
    const hint = document.getElementById('iosHint');
    hint.style.display = 'block';
    hint.scrollIntoView({ behavior:'smooth', block:'center' });
    return;
  }
  toast("Open in Chrome → menu (⋮) → 'Install app'");
});

/* ── Service worker + update prompt ── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').then(reg => {
      reg.addEventListener('updatefound', () => {
        const sw = reg.installing;
        if (!sw) return;
        sw.addEventListener('statechange', () => {
          if (sw.state === 'installed' && navigator.serviceWorker.controller) toast('New version ready — reopen the app');
        });
      });
    }).catch(() => {});
  });
}

/* ── Enable location ── */
document.getElementById('locBtn').addEventListener('click', function () {
  const btn = this;
  if (!navigator.geolocation) { toast('Geolocation not supported'); return; }
  btn.textContent = 'Locating…';
  btn.disabled = true;
  navigator.geolocation.getCurrentPosition(
    () => {
      btn.textContent = '✓ Enabled';
      btn.style.background = 'rgba(61,220,151,.18)';
      btn.style.color = '#3ddc97';
      toast('Live location enabled');
    },
    () => { btn.textContent = 'Enable'; btn.disabled = false; toast('Allow location access in browser settings'); },
    { enableHighAccuracy:true, timeout:10000 }
  );
});
</script>
</body>
</html>
