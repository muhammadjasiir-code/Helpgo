<?php
/**
 * Shared tracking UI partial — used by petrol_orders.php and grocery_orders.php.
 *
 * Expects in scope:
 *   $order         (assoc array from `orders` table)
 *   $conn          (mysqli connection)
 *   $serviceKind   'petrol' | 'grocery'
 *   $priceRowsHtml (string of <div class="price-row"> rows specific to service)
 *   $extraSections (string of HTML injected before the price summary, optional)
 *
 * NOTE: Bill / Pay Now UI is grocery-only and rendered by grocery_orders.php.
 */

$prog = og_progress_state($order);
$otpState = og_otp_state($order);
$currentStatus = $prog['status'];
$statusClass   = $prog['statusClass'];
$statusLabel   = $prog['statusLabel'];
$currentStep   = $prog['currentStep'];
$fillPercent   = $prog['fillPercent'];

$dropAddress = $order['drop_address'] ?? 'N/A';
$dropLat     = $order['drop_latitude'] ?? null;
$dropLng     = $order['drop_longitude'] ?? null;
$riderPhone  = og_rider_phone($conn, $order);

$steps = ['Ordered', 'Accepted', 'On The Way', 'Delivered'];
?>
<div class="glass-card">
    <?php if (!empty($_GET['booked']) || (isset($_GET['payment']) && $_GET['payment'] === 'submitted')): ?>
        <div style="background:rgba(46,213,115,0.12); border-radius:12px; padding:12px; margin-bottom:16px; color:#2ED573;">
            <i class="fas fa-check-circle"></i>
            <?= isset($_GET['payment']) ? 'Payment proof uploaded. Waiting for admin approval.' : 'Order placed successfully!' ?>
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
        <div>
            <div style="font-size:20px; font-weight:700; text-transform:capitalize;"><?= htmlspecialchars($order['service_type']) ?></div>
            <div style="font-size:13px; color:var(--gray-muted);">#<?= htmlspecialchars($order['order_id']) ?></div>
        </div>
        <span class="status-badge <?= $statusClass ?>" id="statusBadge"><?= $statusLabel ?></span>
    </div>

    <div style="display:flex; justify-content:space-between; margin:20px 0; position:relative;" id="progressContainer">
        <div style="position:absolute; top:20px; left:20px; right:20px; height:3px; background:rgba(255,255,255,0.1);"></div>
        <div id="progressFill" style="position:absolute; top:20px; left:20px; height:3px; background:linear-gradient(90deg, var(--gold), var(--gold-light)); border-radius:3px; width:<?= $fillPercent ?>%; z-index:1;"></div>
        <?php foreach ($steps as $i => $step):
            $state = ($currentStep == 3 || $i < $currentStep) ? 'completed' : ($i == $currentStep ? 'active' : '');
        ?>
            <div class="track-step" data-step="<?= $i ?>" style="display:flex; flex-direction:column; align-items:center; z-index:2;">
                <div class="step-circle" style="width:44px; height:44px; border-radius:50%; background:<?= $state=='completed'?'var(--status-delivered)':($state=='active'?'var(--gold)':'rgba(255,255,255,0.1)') ?>; display:flex; align-items:center; justify-content:center; color:<?= $state=='active'?'var(--emerald-dark)':'#fff' ?>;">
                    <?= $state=='completed'?'<i class="fas fa-check"></i>':($state=='active'?'<i class="fas fa-spinner"></i>':'<i class="fas fa-circle"></i>') ?>
                </div>
                <span style="font-size:11px; color:var(--gray-muted); margin-top:6px;"><?= $step ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!in_array($currentStatus, ['delivered','completed','cancelled'])): ?>
        <div id="etaBanner" style="background:rgba(46,213,115,0.08); border-radius:12px; padding:10px; margin:10px 0;">
            <i class="fas fa-clock" style="color:var(--status-delivered);"></i> Estimated delivery:
            <strong><?= $serviceKind === 'petrol' ? '10–15 min' : '18–20 min' ?></strong>
        </div>
    <?php endif; ?>

    <?= $extraSections ?? '' ?>

    <?php if (!in_array($currentStatus, ['delivered','completed'])): ?>
        <div class="otp-card" id="otpCard">
            <div style="margin-bottom:8px; font-weight:600;"><i class="fas fa-shield-alt" style="color:var(--gold); margin-right:6px;"></i> Verification OTP</div>
            <div id="otpDisplayCard" style="display:<?= $otpState['show'] ? 'block' : 'none' ?>;">
                <div style="font-size:13px; color:var(--gray-soft);">Share this OTP only after receiving your order</div>
                <div class="otp-code" id="otpCode"><?= $otpState['show'] ? htmlspecialchars($otpState['otp']) : '' ?></div>
                <button id="copyOtpBtn" style="background:none; border:none; color:var(--gold); cursor:pointer;"><i class="fas fa-copy"></i> Copy OTP</button>
            </div>
            <div id="otpPendingMsg" style="display:<?= $otpState['show'] ? 'none' : 'block' ?>; padding:12px 0; color:var(--gray-muted);">
                <i class="fas fa-clock" style="color:var(--gold); margin-right:8px;"></i>
                <span id="otpPendingText"><?= htmlspecialchars($otpState['msg']) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-top:16px;">
        <h4 style="margin-bottom:8px;"><i class="fas fa-tag" style="color:var(--gold);"></i> Price Summary</h4>
        <?= $priceRowsHtml ?>
        <div class="price-row total"><span>Grand Total</span><span id="totalAmount">₹<?= number_format(floatval($order['total_amount'] ?? 0), 2) ?></span></div>
        <?= $priceExtraHtml ?? '' ?>
    </div>

    <div style="margin-top:16px;">
        <h4 style="margin-bottom:8px;"><i class="fas fa-location-dot" style="color:var(--gold);"></i> Delivery Address</h4>
        <p id="deliveryAddress"><?= htmlspecialchars($dropAddress) ?></p>
    </div>

    <?php if (!in_array($currentStatus, ['delivered','completed','cancelled']) && $dropLat && $dropLng): ?>
        <div style="margin-top:16px;" id="mapCard">
            <h4><i class="fas fa-map-location-dot" style="color:var(--gold);"></i> Live Tracking <span style="color:#2ED573; font-size:10px;">● LIVE</span></h4>
            <div id="trackingMap"></div>
            <div id="etaBox" style="display:none; margin-top:8px; background:rgba(212,175,55,0.08); padding:8px; border-radius:12px;">
                <i class="fas fa-motorcycle" style="color:var(--gold);"></i> ETA: <strong id="etaText">--</strong>
            </div>
        </div>
    <?php endif; ?>

    <div style="display:flex; gap:10px; margin-top:16px;" id="bottomActions">
        <?php if (!in_array($currentStatus, ['delivered','completed','cancelled'])): ?>
            <?php if (!empty($riderPhone)): ?>
                <a href="tel:<?= htmlspecialchars($riderPhone) ?>" class="btn-action"><i class="fas fa-phone"></i> Call Rider</a>
            <?php endif; ?>
            <a href="#" class="btn-action" id="cancelOrderBtn" style="color:var(--status-cancelled);">Cancel Order</a>
        <?php else: ?>
            <a href="<?= $serviceKind === 'petrol' ? 'petrol.php' : 'grocery.php' ?>" class="btn-action primary">Repeat Order</a>
        <?php endif; ?>
    </div>
</div>
