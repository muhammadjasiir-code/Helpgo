<?php

ob_start();

require_once "config.php";

if (!isLoggedIn()) { redirect('index.php'); }

$user   = getUserData($_SESSION['user_id']);
$wallet = getWalletBalance($_SESSION['user_id']);
$message = "";

$petrol_price_per_litre = floatval(getSetting('petrol_price_per_litre') ?? 114);
$delivery_fare = PETROL_CHARGE;

if (isset($_POST['book_petrol'])) {
    $delivery_address = sanitize($_POST['delivery_address'] ?? '');
    $phone            = sanitize($_POST['phone'] ?? '');
    $quantity         = floatval($_POST['quantity'] ?? 1);
    $payment_method   = sanitize($_POST['payment_method'] ?? 'upi');
    $lat_raw          = $_POST['lat'] ?? '';
    $lng_raw          = $_POST['lng'] ?? '';

    if (empty($phone)) $phone = $user['phone'] ?? '';

    if (empty($delivery_address)) {
        $message = "Please confirm your delivery address.";
    } elseif ($quantity < 1 || $quantity > 5) {
        $message = "Quantity must be between 1 and 5 litres.";
    } else {
        $product_amount = $quantity * $petrol_price_per_litre;
        $total_amount   = $product_amount + $delivery_fare;

        $lat_sql = (!empty($lat_raw) && is_numeric($lat_raw)) ? "'" . sanitize($lat_raw) . "'" : "NULL";
        $lng_sql = (!empty($lng_raw) && is_numeric($lng_raw)) ? "'" . sanitize($lng_raw) . "'" : "NULL";

        $order_id = generateOrderId();
        $uid = (int)$_SESSION['user_id'];

        $insert = mysqli_query($conn,
            "INSERT INTO orders 
            (order_id, user_id, service_type, status, 
             drop_address, drop_latitude, drop_longitude,
             petrol_quantity, delivery_fare, product_amount, total_amount,
             payment_method, payment_status)
            VALUES 
            ('$order_id', $uid, 'petrol', 'pending',
             '$delivery_address', $lat_sql, $lng_sql,
             $quantity, $delivery_fare, $product_amount, $total_amount,
             '$payment_method', 'pending')");

        if ($insert) {
            redirect('pay.php?order_id=' . $order_id);
        } else {
            $message = "Booking failed: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#02140d">
<title>Petrol Rescue — HelpGo</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,700;9..144,900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
:root{
  --bg:#02140d; --bg-2:#03231a;
  --ink:#f4ecd6; --ink-2:#c9d5cc; --muted:#7f9a8f;
  --emerald:#0d7a5f; --emerald-deep:#052e24;
  --gold:#e5c063; --gold-hi:#ffe9a8; --gold-lo:#a9863a;
  --gold-line:rgba(229,192,99,.30); --hair:rgba(255,255,255,.07); --hair-2:rgba(255,255,255,.04);
  --glass:rgba(10,52,40,.55); --success:#4de3a5;
  --radius:26px; --radius-sm:18px;
  --shadow:0 24px 60px -20px rgba(0,0,0,.65),0 6px 18px -8px rgba(0,0,0,.55);
  --shadow-gold:0 20px 44px -14px rgba(229,192,99,.45);
  --blur:saturate(160%) blur(18px);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{background:var(--bg);}
body{
  font-family:'Manrope',sans-serif;color:var(--ink);min-height:100vh;
  letter-spacing:-.01em;overflow-x:hidden;padding-bottom:120px;
  background:
    radial-gradient(1100px 620px at 88% -140px, rgba(229,192,99,.13), transparent 60%),
    radial-gradient(820px 540px at -12% 22%, rgba(13,122,95,.20), transparent 60%),
    radial-gradient(900px 680px at 50% 118%, rgba(10,90,70,.20), transparent 60%),
    linear-gradient(180deg,#021309 0%, #02140d 60%, #01100a 100%);
  display:flex;justify-content:center;align-items:flex-start;
}
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.45;mix-blend-mode:overlay;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 .86  0 0 0 0 .34  0 0 0 0 .05 0'/></filter><rect width='100%25' height='100%25' filter='url(%23n)'/></svg>");
}
body::after{
  content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(300px 300px at 12% 18%, rgba(77,227,165,.10), transparent 60%),
    radial-gradient(360px 360px at 92% 62%, rgba(229,192,99,.10), transparent 60%);
  filter:blur(30px);animation:auroraDrift 14s ease-in-out infinite alternate;
}
@keyframes auroraDrift{0%{transform:translate3d(0,0,0) scale(1);}100%{transform:translate3d(0,-18px,0) scale(1.06);}}
a{text-decoration:none;color:inherit;}
.app{width:100%;max-width:440px;padding:16px 16px 32px;position:relative;z-index:1;}

/* ── Entrance animation ─────────────────── */
.reveal{opacity:0;transform:translateY(18px);animation:reveal .7s cubic-bezier(.22,1,.36,1) forwards;}
@keyframes reveal{to{opacity:1;transform:translateY(0);}}
.d1{animation-delay:.05s}.d2{animation-delay:.13s}.d3{animation-delay:.21s}
.d4{animation-delay:.29s}.d5{animation-delay:.37s}.d6{animation-delay:.45s}
.d7{animation-delay:.53s}.d8{animation-delay:.61s}

/* ── Top bar ─────────────────────────────── */
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.top-l{display:flex;align-items:center;gap:12px;}
.back{
  width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(160deg,rgba(18,85,65,.9),rgba(6,36,28,.6));
  border:1px solid var(--hair);color:var(--ink);font-size:15px;
  backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 6px 16px rgba(0,0,0,.35);
  transition:transform .18s ease, border-color .18s ease;
}
.back:active{transform:scale(.92);border-color:var(--gold-line);}
.brand{display:flex;flex-direction:column;line-height:1;}
.brand-row{display:flex;align-items:center;gap:9px;}
.brand-logo{width:26px;height:26px;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.5));animation:logoFloat 4.5s ease-in-out infinite;}
@keyframes logoFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-3px);}}
.brand .name{
  font-family:'Fraunces',serif;font-weight:900;font-size:24px;letter-spacing:-.7px;
  background:linear-gradient(180deg,#fff,#d7cdae);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
.brand .sub{font-size:10.5px;letter-spacing:1.6px;color:var(--gold);font-weight:800;margin-top:5px;text-transform:uppercase;}
.trust{
  display:inline-flex;align-items:center;gap:6px;padding:9px 12px;border-radius:999px;
  font-size:10.5px;font-weight:800;color:var(--gold);letter-spacing:1px;text-transform:uppercase;
  background:linear-gradient(160deg,rgba(229,192,99,.14),rgba(229,192,99,.02));
  border:1px solid var(--gold-line);backdrop-filter:var(--blur);
}
.trust i{font-size:10px;animation:trustPulse 2.6s ease-in-out infinite;}
@keyframes trustPulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.6;transform:scale(1.15);}}

/* ── Hero ────────────────────────────────── */
.hero{
  position:relative;overflow:hidden;border-radius:var(--radius);
  padding:22px 20px 24px;margin-bottom:22px;
  background:
    radial-gradient(420px 220px at 90% 30%, rgba(229,192,99,.26), transparent 65%),
    radial-gradient(360px 220px at 10% 100%, rgba(77,227,165,.20), transparent 65%),
    linear-gradient(140deg,#0a4a38 0%,#0d5a44 55%,#052e24 100%);
  border:1px solid var(--gold-line);box-shadow:var(--shadow);
  min-height:196px;display:flex;align-items:center;gap:6px;
}
.hero::before{
  content:"";position:absolute;inset:0;pointer-events:none;
  background:
    linear-gradient(180deg,transparent 55%,rgba(0,0,0,.35) 100%),
    radial-gradient(600px 220px at 50% -40px, rgba(255,255,255,.07), transparent 70%);
}
.hero::after{
  content:"";position:absolute;top:-1px;left:12%;right:12%;height:1px;
  background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:.7;
}
.hero-badge{
  position:absolute;top:14px;right:14px;z-index:3;
  display:inline-flex;align-items:center;gap:6px;font-size:9.5px;font-weight:800;
  padding:7px 11px;border-radius:999px;color:#052e24;
  background:linear-gradient(180deg,#ffe9a8,#c9a84c);
  text-transform:uppercase;letter-spacing:1.2px;
  box-shadow:0 6px 16px rgba(229,192,99,.35);
}
.hero-badge i{animation:boltFlash 1.8s ease-in-out infinite;}
@keyframes boltFlash{0%,100%{opacity:1;}45%{opacity:.35;}}
.nozzle-wrap{position:relative;flex-shrink:0;width:150px;height:150px;display:flex;align-items:center;justify-content:center;z-index:2;margin-left:-14px;}
.nozzle-glow{
  position:absolute;inset:8px;
  background:radial-gradient(circle at 50% 50%, rgba(229,192,99,.40), transparent 66%);
  filter:blur(12px);animation:pulseGlow 3.2s ease-in-out infinite;
}
@keyframes pulseGlow{0%,100%{opacity:.55;transform:scale(1);}50%{opacity:1;transform:scale(1.14);}}
.nozzle-img{
  position:relative;z-index:2;width:150px;height:150px;object-fit:contain;
  filter:drop-shadow(0 14px 22px rgba(0,0,0,.55));
  animation:nozzleFloat 5s ease-in-out infinite;
}
@keyframes nozzleFloat{0%,100%{transform:translateY(0) rotate(-2deg);}50%{transform:translateY(-8px) rotate(1deg);}}
.drop{
  position:absolute;bottom:44px;left:14px;width:15px;height:20px;z-index:3;
  background:radial-gradient(circle at 35% 30%,#fff2b3,#c9a84c 70%);
  border-radius:50% 50% 50% 50%/60% 60% 40% 40%;
  box-shadow:0 0 22px rgba(229,192,99,.75);
  animation:drip 2.4s cubic-bezier(.5,0,.75,0) infinite;
}
@keyframes drip{0%{transform:translateY(0) scale(.7);opacity:0;}25%{opacity:1;transform:translateY(4px) scale(1);}80%{transform:translateY(34px) scale(.75);opacity:.15;}100%{opacity:0;transform:translateY(40px) scale(.5);}}
.hero-text{flex:1;position:relative;z-index:1;}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:800;letter-spacing:1.4px;
  color:var(--gold);text-transform:uppercase;margin-bottom:8px;
}
.hero-eyebrow::before{content:"";width:16px;height:1.5px;background:var(--gold);}
.hero-text h3{font-family:'Fraunces',serif;font-weight:500;font-size:27px;line-height:1.04;letter-spacing:-.9px;color:#fff;}
.hero-text h3 .gold{
  display:block;margin-top:2px;font-weight:900;font-style:italic;
  background:linear-gradient(110deg,#c9a84c 0%,#ffe9a8 45%,#c9a84c 90%);
  background-size:220% 100%;
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
  animation:goldSweep 5s linear infinite;
}
@keyframes goldSweep{0%{background-position:180% 0;}100%{background-position:-40% 0;}}
.price-chip{
  display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:9px 15px;
  background:rgba(0,0,0,.42);border:1px solid var(--gold-line);border-radius:999px;
  font-weight:800;color:#fff;font-size:13px;backdrop-filter:blur(6px);
}
.price-chip .dot{width:6px;height:6px;border-radius:50%;background:var(--gold);box-shadow:0 0 10px var(--gold);animation:blink 1.6s ease-in-out infinite;}
.price-note{color:#c8d8d0;font-size:11.5px;margin-top:7px;font-weight:500;}

/* ── Section title ───────────────────────── */
.sec-title{
  display:flex;align-items:center;gap:10px;margin:4px 4px 10px;
  font-size:11px;font-weight:800;color:var(--gold);letter-spacing:1.6px;text-transform:uppercase;
}
.sec-title::before{content:"";height:1px;width:16px;background:var(--gold-line);}
.sec-title::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--gold-line),transparent);}

/* ── Card ────────────────────────────────── */
.card{
  position:relative;background:var(--glass);border:1px solid var(--hair);
  border-radius:var(--radius);padding:18px;margin-bottom:16px;
  backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);
  box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.05);
}
.card::before{
  content:"";position:absolute;top:0;left:22px;right:22px;height:1px;
  background:linear-gradient(90deg,transparent,rgba(229,192,99,.35),transparent);
}

/* ── Info rows ───────────────────────────── */
.row{display:flex;align-items:center;gap:14px;padding:6px 0;}
.row + .row{border-top:1px solid var(--hair-2);padding-top:14px;margin-top:12px;}
.icn{
  width:46px;height:46px;border-radius:14px;flex-shrink:0;
  background:linear-gradient(160deg,rgba(229,192,99,.16),rgba(229,192,99,.03));
  border:1px solid var(--gold-line);color:var(--gold);font-size:16px;
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
  transition:transform .2s ease;
}
.row:focus-within .icn{transform:scale(1.06);}
.rbody{flex:1;min-width:0;}
.rlabel{color:var(--muted);font-size:10.5px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;
  display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.rval{color:var(--ink);font-size:15.5px;font-weight:700;word-break:break-word;}
.rval input{
  width:100%;background:transparent;border:none;outline:none;color:var(--ink);
  font:700 15.5px 'Manrope',sans-serif;padding:0;letter-spacing:-.01em;
}
.rval input::placeholder{color:rgba(244,236,214,.32);font-weight:500;}
.chk{color:var(--success);font-size:20px;filter:drop-shadow(0 0 8px rgba(77,227,165,.45));animation:popIn .5s cubic-bezier(.22,1.4,.36,1) both;}
@keyframes popIn{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.live{
  display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;
  font-size:9px;font-weight:900;letter-spacing:.8px;color:var(--success);
  background:rgba(77,227,165,.14);border:1px solid rgba(77,227,165,.32);
}
.live::before{content:"";width:5px;height:5px;border-radius:50%;background:var(--success);
  box-shadow:0 0 8px var(--success);animation:blink 1.4s ease-in-out infinite;}
@keyframes blink{50%{opacity:.35;}}

/* ── Map ─────────────────────────────────── */
.map-wrap{
  position:relative;border-radius:20px;overflow:hidden;margin-top:14px;
  border:1px solid var(--gold-line);
  box-shadow:0 12px 30px rgba(0,0,0,.4),inset 0 0 0 1px rgba(255,255,255,.03);
}
#map{width:100%;height:270px;touch-action:auto;filter:saturate(1.15) contrast(1.02);}
.map-wrap::after{
  content:"";position:absolute;inset:0;pointer-events:none;border-radius:inherit;
  box-shadow:inset 0 0 60px rgba(0,0,0,.55);
}
.locate{
  position:absolute;bottom:14px;left:50%;transform:translateX(-50%);z-index:600;
  display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border-radius:999px;
  background:linear-gradient(160deg,#0a3a2d,#031f18);color:var(--gold);
  border:1px solid var(--gold);font:800 12.5px 'Manrope',sans-serif;letter-spacing:.4px;
  box-shadow:0 12px 26px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.08);cursor:pointer;
  transition:transform .16s ease,box-shadow .16s ease;
}
.locate:active{transform:translateX(-50%) scale(.96);}
.locate::after{
  content:"";position:absolute;inset:-4px;border-radius:999px;border:1px solid var(--gold-line);
  animation:ripple 2.6s ease-out infinite;pointer-events:none;
}
@keyframes ripple{0%{opacity:.7;transform:scale(.96);}100%{opacity:0;transform:scale(1.12);}}
.map-hint{text-align:center;color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.4px;margin-top:10px;}

/* ── Grid ────────────────────────────────── */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
.tile{
  background:var(--glass);border:1px solid var(--hair);border-radius:var(--radius);
  padding:16px;backdrop-filter:var(--blur);
  box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.04);
}
.tile-h{display:flex;align-items:center;gap:8px;margin-bottom:14px;
  font-size:10.5px;font-weight:800;color:var(--ink);letter-spacing:1.2px;text-transform:uppercase;}
.tile-h i{color:var(--gold);font-size:12px;}
.tile-h img{width:15px;height:15px;object-fit:contain;}

/* Quantity segmented */
.qty{display:flex;align-items:stretch;gap:8px;
  background:rgba(3,23,17,.5);border:1px solid var(--hair);border-radius:16px;padding:6px;}
.qbtn{
  width:38px;flex-shrink:0;border-radius:11px;border:1px solid var(--gold-line);
  background:linear-gradient(160deg,rgba(229,192,99,.16),rgba(229,192,99,.02));
  color:var(--gold);font-size:19px;font-weight:800;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:transform .15s,background .15s;
}
.qbtn:active{transform:scale(.88);background:rgba(229,192,99,.24);}
.qbtn:disabled{opacity:.35;}
.qval{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--ink);}
.qval .n{font-family:'Sora',sans-serif;font-weight:800;font-size:21px;letter-spacing:-.5px;
  background:linear-gradient(180deg,#ffe9a8,#c9a84c);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.qval .n.bump{animation:bump .32s cubic-bezier(.22,1.6,.36,1);}
@keyframes bump{0%{transform:scale(1);}45%{transform:scale(1.22);}100%{transform:scale(1);}}
.qval .u{font-size:9.5px;font-weight:800;letter-spacing:1.2px;color:var(--muted);margin-top:-1px;}
.qrange{text-align:center;color:var(--muted);font-size:9.5px;font-weight:800;letter-spacing:1.2px;margin-top:9px;}

/* Payment tiles */
.pays{display:grid;grid-template-columns:1fr;gap:8px;}
.pay{
  position:relative;overflow:hidden;cursor:pointer;display:block;
  background:rgba(3,23,17,.55);border:1.5px solid var(--hair);
  border-radius:14px;padding:16px 6px 14px;text-align:center;transition:.22s;
}
.pay img{width:26px;height:26px;object-fit:contain;}
.pay span.t{display:block;margin-top:8px;font-weight:800;font-size:12px;color:var(--ink);letter-spacing:.3px;}
.pay .tick{
  position:absolute;top:8px;right:8px;width:18px;height:18px;border-radius:50%;
  background:var(--gold);color:#052e24;font-size:9px;display:flex;
  align-items:center;justify-content:center;font-weight:900;box-shadow:0 4px 8px rgba(0,0,0,.3);
  animation:popIn .4s cubic-bezier(.22,1.6,.36,1) both;
}
.pay.selected{
  border-color:var(--gold);
  background:linear-gradient(160deg,rgba(229,192,99,.14),rgba(229,192,99,.02));
  box-shadow:0 0 0 3px rgba(229,192,99,.10),0 10px 22px -10px rgba(229,192,99,.45);
}

/* Summary */
.summary{
  position:relative;overflow:hidden;
  background:linear-gradient(160deg,rgba(10,58,45,.9),rgba(3,23,17,.9));
  border:1px solid var(--gold-line);border-radius:var(--radius);
  padding:20px 20px 22px;margin-bottom:18px;
  backdrop-filter:var(--blur);box-shadow:var(--shadow),inset 0 1px 0 rgba(229,192,99,.18);
}
.summary::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--gold),transparent);}
.summary .ghost{
  position:absolute;right:-26px;bottom:-18px;width:150px;opacity:.10;pointer-events:none;
  filter:grayscale(.2);animation:ghostSway 8s ease-in-out infinite;
}
@keyframes ghostSway{0%,100%{transform:translateY(0) rotate(0);}50%{transform:translateY(-8px) rotate(-3deg);}}
.sum{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px;color:var(--ink-2);position:relative;z-index:1;}
.sum span:last-child{color:var(--ink);font-weight:700;font-family:'Sora',sans-serif;}
.sum-div{border-top:1px dashed var(--gold-line);margin:10px 0;}
.sum-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:2px;position:relative;z-index:1;}
.sum-total .l{font-family:'Sora',sans-serif;font-weight:800;font-size:13px;color:var(--ink);letter-spacing:1.2px;text-transform:uppercase;}
.sum-total .a{
  font-family:'Sora',sans-serif;font-weight:800;font-size:34px;letter-spacing:-1.4px;
  background:linear-gradient(180deg,#ffe9a8 0%,#c9a84c 100%);
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
.sum-total .a.bump{animation:bump .34s cubic-bezier(.22,1.6,.36,1);}

/* Book */
.book-wrap{width:100%;}
.book{opacity:1 !important;
  position:relative;overflow:hidden;width:100%;padding:20px;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:10px;
  background:linear-gradient(135deg,#ffe9a8 0%,#e5c063 45%,#a9863a 100%);
  color:#052e24;border-radius:999px;
  font-family:'Sora',sans-serif;font-weight:800;font-size:16.5px;letter-spacing:.6px;text-transform:uppercase;
  box-shadow:var(--shadow-gold),inset 0 1px 0 rgba(255,255,255,.55),inset 0 -2px 0 rgba(0,0,0,.12);
  transition:transform .15s;animation:breathe 4s ease-in-out infinite;
}
@keyframes breathe{0%,100%{box-shadow:var(--shadow-gold),inset 0 1px 0 rgba(255,255,255,.55);}50%{box-shadow:0 26px 54px -14px rgba(229,192,99,.65),inset 0 1px 0 rgba(255,255,255,.55);}}
.book::before{
  content:"";position:absolute;top:0;left:-100%;width:55%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);
  animation:shine 3.6s ease-in-out infinite;
}
@keyframes shine{0%{left:-100%;}55%,100%{left:120%;}}
.book:active{transform:translateY(1px) scale(.985);}
.book i{font-size:15px;}
.book.loading{pointer-events:none;opacity:.85;}

/* Trust footer */
.foot{
  display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:16px;padding:15px 6px;
  background:var(--glass);border:1px solid var(--hair);border-radius:var(--radius);
  backdrop-filter:var(--blur);
}
.foot div{display:flex;flex-direction:column;align-items:center;gap:5px;text-align:center;
  color:var(--ink);font-size:9.5px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;
  border-right:1px solid var(--hair-2);padding:0 4px;}
.foot div:last-child{border-right:none;}
.foot i{color:var(--gold);font-size:15px;}
.foot small{display:block;color:var(--muted);font-size:8px;font-weight:600;letter-spacing:.2px;text-transform:none;margin-top:1px;}

.alert{
  background:rgba(255,71,87,.12);border:1px solid rgba(255,71,87,.32);
  color:#ff9a9a;padding:12px 15px;border-radius:16px;margin-bottom:14px;
  font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;
  animation:shake .5s ease;
}
@keyframes shake{0%,100%{transform:translateX(0);}20%{transform:translateX(-6px);}40%{transform:translateX(6px);}60%{transform:translateX(-4px);}80%{transform:translateX(4px);}}

/* Toast */
.toast{
  position:fixed;left:50%;bottom:26px;transform:translate(-50%,26px);z-index:999;
  background:linear-gradient(160deg,#0a3a2d,#031f18);border:1px solid var(--gold-line);
  color:var(--ink);padding:12px 18px;border-radius:999px;font-size:12.5px;font-weight:800;
  box-shadow:var(--shadow);opacity:0;pointer-events:none;transition:.3s cubic-bezier(.22,1,.36,1);
}
.toast.show{opacity:1;transform:translate(-50%,0);}

@media (prefers-reduced-motion:reduce){*{animation:none !important;transition:none !important;}.reveal{opacity:1;transform:none;}}
</style>
</head>
<body>
<div class="app">

  <!-- Top -->
  <div class="top reveal d1">
    <div class="top-l">
      <a href="home.php" class="back"><i class="fas fa-arrow-left"></i></a>
      <div class="brand">
        <div class="brand-row">
          <img src="assets/logo-helpgo.png" alt="HelpGo" class="brand-logo">
          <span class="name">Petrol Rescue</span>
        </div>
        <span class="sub">Fuel · On Demand</span>
      </div>
    </div>
    <div class="trust"><i class="fas fa-shield-halved"></i> Trusted</div>
  </div>

  <!-- Hero -->
  <div class="hero reveal d2">
    <div class="hero-badge"><i class="fas fa-bolt"></i> Priority</div>
    <div class="nozzle-wrap">
      <div class="nozzle-glow"></div>
      <img src="assets/hero-nozzle.png" alt="Fuel nozzle" class="nozzle-img">
      <div class="drop"></div>
    </div>
    <div class="hero-text">
      <div class="hero-eyebrow">Emergency Fuel</div>
      <h3>Delivered<br><span class="gold">to your door</span></h3>
      <span class="price-chip"><span class="dot"></span>₹<?= $petrol_price_per_litre ?> / litre</span>
      <div class="price-note">+ ₹<?= $delivery_fare ?> delivery charge</div>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="POST" id="petrolForm">
    <input type="hidden" name="lat" id="latInput">
    <input type="hidden" name="lng" id="lngInput">

    <div class="sec-title reveal d3">Delivery Details</div>

    <div class="card reveal d3">
      <div class="row">
        <div class="icn"><i class="fas fa-phone"></i></div>
        <div class="rbody">
          <div class="rlabel">Phone Number</div>
          <div class="rval">
            <input type="tel" name="phone" id="phone" placeholder="Your phone number" value="<?= htmlspecialchars(isset($user['phone']) ? $user['phone'] : '') ?>" required>
          </div>
        </div>
        <div class="chk"><i class="fas fa-circle-check"></i></div>
      </div>

      <div class="row">
        <div class="icn"><i class="fas fa-location-dot"></i></div>
        <div class="rbody">
          <div class="rlabel">Delivery Address <span class="live" id="liveTag" style="display:none;">LIVE</span></div>
          <div class="rval">
            <input type="text" name="delivery_address" id="deliveryAddress" placeholder="Tap on the map to set your location" required>
          </div>
        </div>
      </div>

      <div class="map-wrap">
        <div id="map"></div>
        <button type="button" class="locate" id="locateBtn">
          <i class="fas fa-location-crosshairs"></i> Use Current Location
        </button>
      </div>
      <p class="map-hint">📍 Pinch to zoom · Drag to pan · Tap to drop a pin</p>
    </div>

    <div class="sec-title reveal d4">Order</div>

    <div class="grid-2 reveal d5">
      <div class="tile">
        <div class="tile-h"><img src="assets/icon-fuel.png" alt=""> Quantity</div>
        <div class="qty">
          <button type="button" class="qbtn" id="qtyMinus">–</button>
          <div class="qval">
            <span class="n" id="qtyBox"><span id="qtyDisplay">1</span> L</span>
            <span class="u">Litres</span>
          </div>
          <button type="button" class="qbtn" id="qtyPlus">+</button>
        </div>
        <div class="qrange">MIN 1L · MAX 5L</div>
        <input type="hidden" name="quantity" id="quantityInput" value="1">
      </div>

      <!-- Payment tile – UPI only -->
      <div class="tile">
        <div class="tile-h"><img src="assets/icon-wallet.png" alt=""> Payment</div>
        <div class="pays" id="paymentOptions">
          <label class="pay selected">
            <span class="tick"><i class="fas fa-check"></i></span>
            <input type="hidden" name="payment_method" value="upi">
            <img src="assets/icon-upi.svg" alt="UPI">
            <span class="t">Online (UPI)</span>
          </label>
        </div>
      </div>
    </div>

    <div class="summary reveal d6">
      <img src="assets/hero-nozzle.png" alt="" class="ghost">
      <div class="sum">
        <span>Petrol (<span id="qtyLabel">1</span> L × ₹<?= $petrol_price_per_litre ?>)</span>
        <span id="productAmount">₹<?= $petrol_price_per_litre ?></span>
      </div>
      <div class="sum">
        <span>Delivery Charge</span>
        <span>₹<?= $delivery_fare ?></span>
      </div>
      <div class="sum-div"></div>
      <div class="sum-total">
        <span class="l">Total</span>
        <span class="a" id="totalAmount">₹<?= $petrol_price_per_litre + $delivery_fare ?></span>
      </div>
    </div>

    <div class="reveal d7 book-wrap">
      <button type="submit" name="book_petrol" class="book" id="bookBtn"><i class="fas fa-bolt"></i> Book Now</button>
    </div>

    <div class="foot reveal d8">
      <div><i class="fas fa-shield-halved"></i> Secure<small>100% Safe Payments</small></div>
      <div><i class="fas fa-bolt"></i> Fast<small>15–30 Mins Delivery</small></div>
      <div><i class="fas fa-certificate"></i> Reliable<small>Verified Partners</small></div>
      <div><i class="fas fa-headset"></i> 24/7<small>We're Always Here</small></div>
    </div>
  </form>
</div>

<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var petrolPrice = <?= (int)$petrol_price_per_litre ?>;
var deliveryFare = <?= (int)$delivery_fare ?>;

var toastEl=document.getElementById('toast'),toastTimer=null;
function toast(msg){
  toastEl.textContent=msg;toastEl.classList.add('show');
  clearTimeout(toastTimer);toastTimer=setTimeout(function(){toastEl.classList.remove('show');},2400);
}
function bump(el){el.classList.remove('bump');void el.offsetWidth;el.classList.add('bump');}

var qtyMinus=document.getElementById('qtyMinus'),qtyPlus=document.getElementById('qtyPlus'),
    qtyDisplay=document.getElementById('qtyDisplay'),quantityInput=document.getElementById('quantityInput'),
    qtyLabel=document.getElementById('qtyLabel'),productAmount=document.getElementById('productAmount'),
    totalAmount=document.getElementById('totalAmount'),qtyBox=document.getElementById('qtyBox');

function updateTotal(animate){
  var q=parseInt(quantityInput.value),p=q*petrolPrice;
  productAmount.textContent='₹'+p; totalAmount.textContent='₹'+(p+deliveryFare);
  qtyDisplay.textContent=q; qtyLabel.textContent=q;
  qtyMinus.disabled=(q<=1); qtyPlus.disabled=(q>=5);
  if(animate){bump(qtyBox);bump(totalAmount);if(navigator.vibrate)navigator.vibrate(8);}
}
qtyMinus.addEventListener('click',function(){var q=parseInt(quantityInput.value);if(q>1){quantityInput.value=q-1;updateTotal(true);}});
qtyPlus.addEventListener('click',function(){var q=parseInt(quantityInput.value);if(q<5){quantityInput.value=q+1;updateTotal(true);}else{toast('Maximum 5 litres per order');}});
updateTotal(false);

var defaultLat=10.8505,defaultLng=76.2711;
var latInput=document.getElementById('latInput'),lngInput=document.getElementById('lngInput'),
    deliveryInput=document.getElementById('deliveryAddress'),liveTag=document.getElementById('liveTag'),
    locateBtn=document.getElementById('locateBtn');
var map=L.map('map',{scrollWheelZoom:true,dragging:true,touchZoom:true,doubleClickZoom:true,tap:true,zoomControl:false}).setView([defaultLat,defaultLng],15);
L.control.zoom({position:'topright'}).addTo(map);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);
var pinIcon=L.divIcon({className:'',
  html:'<div style="position:relative;animation:pinDrop .5s cubic-bezier(.22,1.4,.36,1);"><i class="fas fa-location-dot" style="font-size:48px;color:#e5c063;filter:drop-shadow(0 6px 10px rgba(0,0,0,.65));"></i><div style="position:absolute;top:11px;left:50%;transform:translateX(-50%);width:12px;height:12px;background:#052e24;border-radius:50%;border:2px solid #ffe9a8;"></div></div>',
  iconSize:[48,48],iconAnchor:[24,48]});
var styleTag=document.createElement('style');
styleTag.textContent='@keyframes pinDrop{0%{transform:translateY(-26px) scale(.7);opacity:0;}100%{transform:translateY(0) scale(1);opacity:1;}}';
document.head.appendChild(styleTag);
var marker=null;
function fetchAddress(lat,lng){
  liveTag.style.display='inline-flex';
  fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng)
    .then(function(r){return r.json();})
    .then(function(d){deliveryInput.value=(d&&d.display_name)?d.display_name:('Lat: '+lat.toFixed(6)+', Lng: '+lng.toFixed(6));})
    .catch(function(){deliveryInput.value='Lat: '+lat.toFixed(6)+', Lng: '+lng.toFixed(6);});
}
function placeMarker(lat,lng){
  latInput.value=lat;lngInput.value=lng;
  if(marker)map.removeLayer(marker);
  marker=L.marker([lat,lng],{icon:pinIcon,draggable:true}).addTo(map);
  marker.on('dragend',function(e){var p=e.target.getLatLng();latInput.value=p.lat;lngInput.value=p.lng;fetchAddress(p.lat,p.lng);});
  fetchAddress(lat,lng);
}
map.on('click',function(e){placeMarker(e.latlng.lat,e.latlng.lng);});
locateBtn.addEventListener('click',function(){
  if(!navigator.geolocation){toast('Geolocation not supported');return;}
  locateBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Locating...';locateBtn.disabled=true;
  navigator.geolocation.getCurrentPosition(function(pos){
    var lat=pos.coords.latitude,lng=pos.coords.longitude;map.setView([lat,lng],18);placeMarker(lat,lng);
    locateBtn.innerHTML='<i class="fas fa-location-crosshairs"></i> Use Current Location';locateBtn.disabled=false;
    toast('Location set');
  },function(){
    toast('Unable to get location — allow access');
    locateBtn.innerHTML='<i class="fas fa-location-crosshairs"></i> Use Current Location';locateBtn.disabled=false;
  },{enableHighAccuracy:true,timeout:10000});
});
if(navigator.geolocation){navigator.geolocation.getCurrentPosition(
  function(pos){var lat=pos.coords.latitude,lng=pos.coords.longitude;map.setView([lat,lng],17);placeMarker(lat,lng);},
  function(){placeMarker(defaultLat,defaultLng);});}
else{placeMarker(defaultLat,defaultLng);}

document.getElementById('petrolForm').addEventListener('submit',function(e){
  if(!deliveryInput.value.trim()){
    e.preventDefault();toast('Select your delivery location on the map');return;
  }
  var b=document.getElementById('bookBtn');
  b.classList.add('loading');
  b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
});
</script>
</body>
</html>
