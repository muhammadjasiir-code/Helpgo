<?php
/**
 * Shared JS for both tracking pages.
 * Expects $order in scope; $serviceKind = 'petrol' | 'grocery'.
 * Grocery page appends its own bill/pay-now handling section (block passed via $extraJs).
 */
$dropLat = $order['drop_latitude'] ?? null;
$dropLng = $order['drop_longitude'] ?? null;
$currentStatus = strtolower($order['status'] ?? 'pending');
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    // Cancel
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#cancelOrderBtn');
        if (!btn) return;
        e.preventDefault();
        if (confirm('Cancel this order?')) {
            fetch('cancel_order.php?order_id=<?= $order['order_id'] ?>').then(() => location.reload());
        }
    });

    // Copy OTP
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#copyOtpBtn')) return;
        const oc = document.getElementById('otpCode');
        const otp = oc ? oc.textContent.trim() : '';
        if (otp) navigator.clipboard?.writeText(otp).then(() => alert('OTP copied!'));
    });

    function updateStepCircles(currentStep) {
        document.querySelectorAll('.track-step').forEach(step => {
            const i = parseInt(step.dataset.step);
            const circle = step.querySelector('.step-circle');
            if (!circle) return;
            let state = '';
            if (currentStep === 3 || i < currentStep) state = 'completed';
            else if (i === currentStep) state = 'active';
            let bg, icon;
            if (state === 'completed') { bg='var(--status-delivered)'; icon='<i class="fas fa-check"></i>'; circle.style.color='#fff'; }
            else if (state === 'active') { bg='var(--gold)'; icon='<i class="fas fa-spinner"></i>'; circle.style.color='var(--emerald-dark)'; }
            else { bg='rgba(255,255,255,0.1)'; icon='<i class="fas fa-circle"></i>'; circle.style.color='#fff'; }
            circle.style.background = bg;
            circle.innerHTML = icon;
        });
    }

    function refreshOrderStatus() {
        fetch('get_order_status.php?order_id=<?= $order['order_id'] ?>')
            .then(r => r.json())
            .then(d => {
                if (d.error) return;
                const badge = document.getElementById('statusBadge');
                if (badge) { badge.className = 'status-badge ' + d.statusClass; badge.textContent = d.statusText; }
                const fill = document.getElementById('progressFill');
                if (fill) fill.style.width = (d.currentStep/3)*100 + '%';
                updateStepCircles(d.currentStep);

                const isTerminal = d.isTerminal === true
                    || ['delivered','completed','cancelled'].includes((d.statusText || '').toLowerCase());

                if (isTerminal) {
                    const container = document.getElementById('bottomActions');
                    if (container) {
                        container.innerHTML = '<a href="<?= $serviceKind === 'petrol' ? 'petrol.php' : 'grocery.php' ?>" class="btn-action primary">Repeat Order</a>';
                    }
                    ['mapCard','etaBox','etaBanner'].forEach(id => {
                        const el = document.getElementById(id); if (el) el.style.display = 'none';
                    });
                }

                // OTP update
                const otpCard = document.getElementById('otpCard');
                const od = document.getElementById('otpDisplayCard');
                const op = document.getElementById('otpPendingMsg');
                const oc = document.getElementById('otpCode');
                const ot = document.getElementById('otpPendingText');
                if (otpCard && od && op && oc) {
                    if (isTerminal) { otpCard.style.display = 'none'; }
                    else {
                        otpCard.style.display = '';
                        if (d.showOtp && d.otp) {
                            oc.textContent = d.otp;
                            od.style.display = 'block'; op.style.display = 'none';
                        } else {
                            od.style.display = 'none'; op.style.display = 'block';
                            if (ot) ot.textContent = (String(d.paymentMethod).toLowerCase() === 'upi')
                                ? 'OTP will appear after payment is confirmed by the admin.'
                                : 'OTP will appear after the order is accepted.';
                        }
                    }
                }

                // Hook for service-specific extras (bill / pay now)
                if (typeof window.__onStatusUpdate === 'function') window.__onStatusUpdate(d);
            });
    }
    refreshOrderStatus();
    setInterval(refreshOrderStatus, 3000);

    <?php if (!in_array($currentStatus, ['delivered','completed','cancelled']) && $dropLat && $dropLng): ?>
    var map = L.map('trackingMap', { zoomControl: false }).setView([<?= $dropLat ?>, <?= $dropLng ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    L.marker([<?= $dropLat ?>, <?= $dropLng ?>]).addTo(map).bindPopup('Your Location');
    var riderMarker = null;
    function updateRider() {
        fetch('get_rider_location.php?order_id=<?= $order['order_id'] ?>')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.lat) {
                    var pos = [parseFloat(d.lat), parseFloat(d.lng)];
                    if (!riderMarker) riderMarker = L.marker(pos).addTo(map).bindPopup('Rider');
                    else riderMarker.setLatLng(pos);
                    var dist = map.distance([<?= $dropLat ?>, <?= $dropLng ?>], pos) / 1000;
                    document.getElementById('etaText').innerText = Math.round((dist / 20) * 60) + ' min';
                    document.getElementById('etaBox').style.display = 'block';
                }
            });
    }
    updateRider();
    setInterval(updateRider, 5000);
    <?php endif; ?>
})();
</script>
