<?php
/**
 * Shared helpers + shared CSS for order tracking pages.
 * Included by orders.php, petrol_orders.php, grocery_orders.php.
 */

if (!defined('UPLOAD_URL')) { define('UPLOAD_URL', 'uploads/'); }

/**
 * Status -> CSS class map used by both list + detail views.
 */
function og_status_map() {
    return [
        'pending'    => 'status-pending',
        'accepted'   => 'status-accepted',
        'picked_up'  => 'status-enroute',
        'in_transit' => 'status-enroute',
        'delivered'  => 'status-delivered',
        'completed'  => 'status-delivered',
        'cancelled'  => 'status-cancelled',
    ];
}

/**
 * Compute progress step (0..3) + status label from order row.
 * Handles the UPI "awaiting payment approval" gate.
 */
function og_progress_state(array $order) {
    $currentStatus = strtolower($order['status'] ?? 'pending');
    $payMethod     = strtolower($order['payment_method'] ?? 'cash');
    $payStatus     = strtolower($order['payment_status'] ?? 'pending');

    $map = og_status_map();
    $statusClass = $map[$currentStatus] ?? 'status-pending';
    $statusLabel = in_array($currentStatus, ['picked_up','in_transit'])
        ? 'On The Way'
        : ucfirst($currentStatus);

    $currentStep = 0;
    if ($currentStatus === 'accepted') $currentStep = 1;
    elseif (in_array($currentStatus, ['picked_up','in_transit'])) $currentStep = 2;
    elseif (in_array($currentStatus, ['delivered','completed'])) $currentStep = 3;

    // UPI gate: keep at "Ordered" until admin approves payment
    if ($payMethod === 'upi' && $payStatus !== 'paid'
        && !in_array($currentStatus, ['delivered','completed','cancelled'])) {
        $currentStep = 0;
        $statusClass = 'status-pending';
        $statusLabel = 'Awaiting Payment Approval';
    }

    return [
        'status'      => $currentStatus,
        'statusClass' => $statusClass,
        'statusLabel' => $statusLabel,
        'currentStep' => $currentStep,
        'fillPercent' => $currentStep === 3 ? 100 : ($currentStep / 3) * 100,
    ];
}

/**
 * OTP visibility rules — identical for petrol & grocery.
 */
function og_otp_state(array $order) {
    $currentStatus = strtolower($order['status'] ?? 'pending');
    $payMethod     = strtolower($order['payment_method'] ?? 'cash');
    $payStatus     = strtolower($order['payment_status'] ?? 'pending');
    $otp           = $order['otp'] ?? '';

    $show = false;
    $msg  = 'OTP will be generated after the order is accepted.';

    if (!in_array($currentStatus, ['delivered','completed'])) {
        if ($payMethod === 'cash') {
            $show = !empty($otp) && !in_array($currentStatus, ['delivered','completed','cancelled']);
        } elseif ($payMethod === 'upi') {
            if ($payStatus === 'paid' && !empty($otp)
                && !in_array($currentStatus, ['delivered','completed','cancelled'])) {
                $show = true;
            } else {
                $msg = 'OTP will appear after payment is confirmed by the admin.';
            }
        }
    }
    return ['show' => $show, 'otp' => $otp, 'msg' => $msg];
}

function og_rider_phone($conn, $order) {
    if (empty($order['rider_id'])) return '';
    $rid = (int)$order['rider_id'];
    $q = mysqli_query($conn, "SELECT phone FROM users WHERE id = $rid AND user_type = 'rider' LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        return mysqli_fetch_assoc($q)['phone'] ?? '';
    }
    return '';
}
?>
<style>
:root {
    --emerald: #083C33; --emerald-light: #0E5548; --emerald-dark: #04261F;
    --gold: #D4AF37; --gold-light: #E8C84A; --gold-dark: #B8962E;
    --gold-glow: rgba(212, 175, 55, 0.35); --gold-glow-soft: rgba(212, 175, 55, 0.12);
    --white: #FFFFFF; --gray-soft: #AEB8B2; --gray-muted: #6B7A73;
    --bg-glass: rgba(8, 60, 51, 0.65); --border-glass: rgba(212, 175, 55, 0.15);
    --shadow-glass: 0 30px 80px rgba(0, 0, 0, 0.5);
    --radius-card: 28px; --radius-btn: 16px;
    --font: 'Plus Jakarta Sans', 'Outfit', sans-serif;
    --status-delivered: #2ED573; --status-ongoing: #FFA502; --status-cancelled: #FF4757;
    --status-accepted: #4A9BF5;
}
* { margin:0; padding:0; box-sizing:border-box; }
html, body { height: 100%; overflow-x: hidden; overflow-y: auto; }
body {
    font-family: var(--font); background: var(--emerald); color: var(--white);
    display: flex; justify-content: center;
    padding: 20px 16px 100px; position: relative; min-height: 100vh;
}
.bg-layer {
    position: fixed; top:0; left:0; width:100%; height:100%; z-index:0;
    background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
}
.container { width:100%; max-width:500px; position: relative; z-index:2; }
.glass-card, .order-card {
    background: var(--bg-glass); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
    border: 1px solid var(--border-glass); border-radius: var(--radius-card);
    box-shadow: var(--shadow-glass), inset 0 1px 0 rgba(255,255,255,0.04);
    padding: 20px; margin-bottom: 16px; position: relative; overflow: hidden;
}
.glass-card::before, .order-card::before {
    content: ''; position: absolute; top: -40%; left: -40%; width: 180%; height: 180%;
    background: radial-gradient(circle at 30% 20%, rgba(212, 175, 55, 0.04) 0%, transparent 60%);
    pointer-events: none;
}
.glass-card > *, .order-card > * { position: relative; z-index: 1; }
.btn-primary {
    display: inline-block; padding: 10px 30px;
    background: linear-gradient(145deg, var(--gold), var(--gold-dark));
    color: var(--emerald-dark); border-radius: 30px; font-weight: 700;
    text-decoration: none; font-size: 14px; box-shadow: 0 8px 32px rgba(212, 175, 55, 0.25);
}
.status-badge { padding: 6px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
.status-pending   { background: rgba(255,165,2,0.15);  color: #FFA502; border: 1px solid rgba(255,165,2,0.2); }
.status-accepted  { background: rgba(74,155,245,0.15); color: #4A9BF5; border: 1px solid rgba(74,155,245,0.2); }
.status-enroute   { background: rgba(138,43,226,0.15); color: #8A2BE2; border: 1px solid rgba(138,43,226,0.2); }
.status-delivered { background: rgba(46,213,115,0.15); color: #2ED573; border: 1px solid rgba(46,213,115,0.2); }
.status-cancelled { background: rgba(255,71,87,0.15);  color: #FF4757; border: 1px solid rgba(255,71,87,0.2); }

.otp-card { background: rgba(212, 175, 55, 0.08); border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 16px; padding: 16px; text-align: center; margin: 16px 0; }
.otp-code { font-size: 34px; font-weight: 800; color: var(--gold); letter-spacing: 8px; margin-top: 4px; }

.btn-action { flex: 1; padding: 10px 0; border-radius: var(--radius-btn); background: rgba(255,255,255,0.04); border: 1px solid var(--border-glass); color: var(--white); font-size: 14px; font-weight: 600; text-align: center; text-decoration: none; font-family: var(--font); }
.btn-action i { margin-right: 6px; }
.btn-action:hover { background: rgba(212, 175, 55, 0.1); border-color: var(--gold); }
.btn-action.primary { background: rgba(212, 175, 55, 0.15); border-color: var(--gold); color: var(--gold); }

.bottom-nav {
    position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
    width: calc(100% - 32px); max-width: 500px;
    background: rgba(8, 60, 51, 0.85); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px);
    border-radius: 30px; border: 1px solid var(--border-glass);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    display: flex; justify-content: space-around; padding: 8px 0; z-index: 999;
}
.nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--gray-muted); font-size: 10px; font-weight: 500; transition: all 0.3s; padding: 4px 12px; border-radius: 20px; }
.nav-item i { font-size: 20px; margin-bottom: 2px; }
.nav-item.active { color: var(--gold); }
.nav-item:hover { color: var(--gold-light); }

.filter-tabs { display: flex; gap: 8px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 4px; }
.filter-btn { padding: 8px 18px; border-radius: 30px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: var(--gray-soft); font-size: 13px; font-weight: 500; white-space: nowrap; cursor: pointer; transition: all 0.3s; }
.filter-btn.active { background: rgba(212, 175, 55, 0.12); border-color: var(--gold); color: var(--gold); box-shadow: 0 0 20px var(--gold-glow-soft); }

#trackingMap { width:100%; height:280px; border-radius:18px; margin-top:16px; border:1px solid var(--border-glass); }
.price-row { display:flex; justify-content:space-between; font-size:14px; color:var(--gray-soft); padding:4px 0; }
.price-row.total { font-size:18px; font-weight:700; color:var(--gold); border-top:1px solid rgba(212,175,55,0.15); margin-top:6px; padding-top:10px; }

@media (max-width: 420px) {
    body { padding: 16px 12px 100px; }
    .order-card, .glass-card { padding: 16px; }
}
</style>
