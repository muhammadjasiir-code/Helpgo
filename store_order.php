<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$user = getUserData($_SESSION['user_id']);
$uid  = (int)$_SESSION['user_id'];

$orderIdInput = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['order_id']) ? $_GET['order_id'] : '');
if (empty($orderIdInput)) { die("Order ID missing."); }
$orderId_safe = mysqli_real_escape_string($conn, $orderIdInput);

// Fetch order with rider, store, and customer coordinates
$order = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT o.*, 
           s.name AS store_name,
           s.latitude AS store_lat, s.longitude AS store_lng,
           r.full_name AS rider_name,
           rd.current_latitude AS rider_lat, rd.current_longitude AS rider_lng,
           o.drop_latitude AS customer_lat, o.drop_longitude AS customer_lng
    FROM orders o
    LEFT JOIN stores s ON o.store_id = s.id
    LEFT JOIN riders rd ON o.rider_id = rd.id
    LEFT JOIN users r ON rd.user_id = r.id
    WHERE (o.id = '$orderId_safe' OR o.order_id = '$orderId_safe')
      AND o.user_id = $uid
    LIMIT 1
"));

if (!$order) { die("Order not found or access denied."); }

// Payment display
$paymentDisplay = ($order['payment_method'] == 'cod') ? 'Cash on Delivery' : 'Payment Pending';

// Build timeline (unchanged)
function buildTimeline($order) {
    $t = [];
    $t[] = ['label'=>'Order Placed','time'=>date('H:i', strtotime($order['order_date'])),'done'=>true];
    $t[] = ['label'=>'Shop Accepted','done'=>in_array($order['store_order_status'], ['accepted','ready_for_payment','payment_submitted','payment_verified']),'time'=>''];
    $t[] = ['label'=>'Preparing Order','done'=>in_array($order['status'], ['ready','accepted','picked_up','in_transit','delivered']),'time'=>''];
    $t[] = ['label'=>'Rider Picked Up','done'=>in_array($order['status'], ['picked_up','in_transit','delivered']),'time'=>''];
    $t[] = ['label'=>'On the Way','done'=>($order['status']=='in_transit'),'current'=>($order['status']=='in_transit'),'time'=>''];
    $t[] = ['label'=>'Delivered','done'=>($order['status']=='delivered'),'current'=>($order['status']=='delivered'),'time'=>''];
    return $t;
}
$timeline = buildTimeline($order);

$storeLat = $order['store_lat'] ?? 12.9716;
$storeLng = $order['store_lng'] ?? 77.5946;
$custLat  = $order['customer_lat'] ?: 12.9344;
$custLng  = $order['customer_lng'] ?: 77.6101;
$riderLat = $order['rider_lat'] ?? null;
$riderLng = $order['rider_lng'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Order Tracking #<?= htmlspecialchars($order['order_id']) ?> – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ★★★ Replace with your real Google Maps API key ★★★ -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAir-2N0RIqAYIeYY2osvzi_WzsnGvHleo&libraries=geometry"></script>
    <style>
        :root {
            --emerald: #0F9D58; --gold: #D4AF37; --white: #ffffff;
            --soft-grey: #F5F5F5; --text-dark: #1A1A1A; --text-muted: #777777;
            --card-shadow: 0 8px 24px rgba(0,0,0,0.06); --radius: 20px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Manrope', sans-serif;
            background: linear-gradient(180deg, #f9f9f9 0%, #fff 100%);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding-bottom: 30px;
        }
        .app { width: 100%; max-width: 430px; padding: 20px 16px; }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .back-btn { width: 38px; height: 38px; border-radius: 50%; background: var(--white); box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center; color: var(--text-dark); font-size: 18px; text-decoration: none; }
        .title-section { flex: 1; display: flex; flex-direction: column; }
        .order-id { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 18px; color: var(--text-dark); }
        .status-badge { display: inline-block; background: var(--emerald); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px; }
        .map-card { width: 100%; height: 320px; border-radius: var(--radius); overflow: hidden; margin-bottom: 20px; box-shadow: var(--card-shadow); }
        .map-container { width: 100%; height: 100%; }
        .arrival-card { background: linear-gradient(135deg, var(--emerald), #0b7a44); color: #fff; border-radius: var(--radius); padding: 20px; text-align: center; margin-bottom: 20px; }
        .arrival-card h2 { font-family: 'Poppins', sans-serif; font-size: 36px; font-weight: 800; }
        .arrival-card p { opacity: 0.9; margin-top: 4px; font-size: 14px; }
        .timeline-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--card-shadow); padding: 20px; margin-bottom: 20px; }
        .timeline { position: relative; padding-left: 36px; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 8px; bottom: 8px; width: 2px; background: #ddd; }
        .timeline-step { position: relative; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px; }
        .timeline-step:last-child { margin-bottom: 0; }
        .step-icon { position: absolute; left: -36px; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 2; background: var(--soft-grey); color: #aaa; border: 2px solid #ddd; }
        .timeline-step.completed .step-icon { background: var(--emerald); color: #fff; border-color: var(--emerald); }
        .timeline-step.current .step-icon { background: var(--gold); color: #fff; border-color: var(--gold); box-shadow: 0 0 0 6px rgba(212,175,55,0.15); animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(212,175,55,0.4); } 70% { box-shadow: 0 0 0 12px rgba(212,175,55,0); } 100% { box-shadow: 0 0 0 0 rgba(212,175,55,0); } }
        .step-content { flex: 1; }
        .step-label { font-weight: 700; font-size: 15px; color: var(--text-dark); }
        .step-time { font-size: 12px; color: var(--text-muted); }
        .summary-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--card-shadow); padding: 16px; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .summary-row.total { font-weight: 800; font-size: 18px; border-top: 1px solid #eee; padding-top: 12px; margin-top: 12px; color: var(--emerald); }
    </style>
</head>
<body>
<div class="app">
    <div class="header">
        <a href="/orders" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div class="title-section">
            <span class="order-id">Order #<?= htmlspecialchars($order['order_id']) ?></span>
            <span class="status-badge"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span>
        </div>
    </div>

    <!-- Live Arrival Card (shown when rider is active) -->
    <div class="arrival-card" id="arrivalCard" style="<?= (in_array($order['status'], ['accepted','picked_up','in_transit']) && !empty($order['rider_name'])) ? '' : 'display:none;' ?>">
        <h2 id="etaDisplay">-- min</h2>
        <p id="distDisplay">-- km away</p>
    </div>

    <!-- Google Map -->
    <div class="map-card">
        <div class="map-container" id="map"></div>
    </div>

    <!-- Timeline -->
    <div class="timeline-card">
        <h3 style="margin-bottom:16px; font-family:'Poppins',sans-serif;">Order Progress</h3>
        <div class="timeline" id="timelineContainer"></div>
    </div>

    <!-- Order Summary -->
    <div class="summary-card">
        <h3 style="font-family:'Poppins',sans-serif; margin-bottom:12px;">Order Summary</h3>
        <div class="summary-row"><span>Store</span><span><?= htmlspecialchars($order['store_name'] ?? 'N/A') ?></span></div>
        <div class="summary-row"><span>Items</span><span><?= htmlspecialchars($order['product_details'] ?? 'N/A') ?></span></div>
        <div class="summary-row"><span>Payment</span><span><?= $paymentDisplay ?></span></div>
        <div class="summary-row total"><span>Total</span><span>₹<?= number_format($order['total_amount'],2) ?></span></div>
    </div>
</div>

<script>
// ---------- Data from PHP ----------
const storeLat = <?= $storeLat ?>;
const storeLng = <?= $storeLng ?>;
const custLat  = <?= $custLat ?>;
const custLng  = <?= $custLng ?>;
let riderLat   = <?= $riderLat ? $riderLat : 'null' ?>;
let riderLng   = <?= $riderLng ? $riderLng : 'null' ?>;
const orderId  = "<?= $order['order_id'] ?>";
const timelineData = <?= json_encode($timeline) ?>;
let currentStatus = "<?= $order['status'] ?>";
let currentStoreStatus = "<?= $order['store_order_status'] ?>";

// ---------- Google Map objects ----------
let map, riderMarker, customerMarker, storeMarker;
let directionsService, directionsRenderer;
let lastRiderPos = null;
const ANIMATION_DURATION = 5000;  // 5 seconds smooth animation

// ---------- Initialize map ----------
function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: (storeLat + custLat) / 2, lng: (storeLng + custLng) / 2 },
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#0F9D58',
            strokeOpacity: 0.9,
            strokeWeight: 5,
        }
    });

    // ★ Store marker – custom image
    storeMarker = new google.maps.Marker({
        position: { lat: storeLat, lng: storeLng },
        map: map,
        icon: {
            url: 'assets/shop.png',           // ★ local image
            scaledSize: new google.maps.Size(40, 40),
            anchor: new google.maps.Point(20, 40),  // bottom-center
        },
        title: 'Store'
    });

    // ★ Customer marker – red circle (default)
    customerMarker = new google.maps.Marker({
        position: { lat: custLat, lng: custLng },
        map: map,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8,
            fillColor: '#e74c3c',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2,
        },
        title: 'You'
    });

    // ★ Rider marker – custom image (if coordinates exist)
    if (riderLat && riderLng) {
        lastRiderPos = new google.maps.LatLng(riderLat, riderLng);
        riderMarker = new google.maps.Marker({
            position: lastRiderPos,
            map: map,
            icon: {
                url: 'assets/rider.png',        // ★ local image
                scaledSize: new google.maps.Size(44, 44),
                anchor: new google.maps.Point(22, 44),
            },
            title: 'Rider'
        });
        updateRouteAndETA(lastRiderPos, new google.maps.LatLng(custLat, custLng));
    } else {
        updateRouteAndETA(new google.maps.LatLng(storeLat, storeLng),
                          new google.maps.LatLng(custLat, custLng));
    }
}

// ---------- Update route on the map and calculate ETA/distance ----------
function updateRouteAndETA(fromLatLng, toLatLng) {
    directionsService.route({
        origin: fromLatLng,
        destination: toLatLng,
        travelMode: google.maps.TravelMode.DRIVING,
    }, (response, status) => {
        if (status === 'OK') {
            directionsRenderer.setDirections(response);
            const route = response.routes[0].legs[0];
            const distanceKm = (route.distance.value / 1000).toFixed(1);
            const durationMin = Math.ceil(route.duration.value / 60);
            document.getElementById('etaDisplay').textContent = durationMin + ' min';
            document.getElementById('distDisplay').textContent = distanceKm + ' km away';
            document.getElementById('arrivalCard').style.display = 'block';
        } else {
            const dist = google.maps.geometry.spherical.computeDistanceBetween(fromLatLng, toLatLng) / 1000;
            const eta = Math.ceil(dist * 3.5);
            document.getElementById('etaDisplay').textContent = eta + ' min';
            document.getElementById('distDisplay').textContent = dist.toFixed(1) + ' km';
            document.getElementById('arrivalCard').style.display = 'block';
            new google.maps.Polyline({
                path: [fromLatLng, toLatLng],
                geodesic: true,
                strokeColor: '#0F9D58',
                strokeOpacity: 0.8,
                strokeWeight: 4,
                map: map
            });
        }
    });
}

// ---------- Smoothly move the rider marker ----------
function animateRiderMarker(newLatLng) {
    if (!riderMarker) {
        riderMarker = new google.maps.Marker({
            position: newLatLng,
            map: map,
            icon: {
                url: 'assets/rider.png',
                scaledSize: new google.maps.Size(44, 44),
                anchor: new google.maps.Point(22, 44),
            },
            title: 'Rider'
        });
        lastRiderPos = newLatLng;
        updateRouteAndETA(newLatLng, new google.maps.LatLng(custLat, custLng));
        return;
    }

    const startPos = riderMarker.getPosition();
    const startTime = performance.now();

    function step(now) {
        const elapsed = now - startTime;
        const fraction = Math.min(elapsed / ANIMATION_DURATION, 1);
        const lat = startPos.lat() + (newLatLng.lat() - startPos.lat()) * fraction;
        const lng = startPos.lng() + (newLatLng.lng() - startPos.lng()) * fraction;
        riderMarker.setPosition(new google.maps.LatLng(lat, lng));

        if (fraction < 1) {
            requestAnimationFrame(step);
        } else {
            lastRiderPos = newLatLng;
            updateRouteAndETA(newLatLng, new google.maps.LatLng(custLat, custLng));
        }
    }
    requestAnimationFrame(step);
}

// ---------- Poll server for live rider location ----------
async function fetchTrackingData() {
    try {
        const res = await fetch(`/ajax_order_tracking.php?order_id=${orderId}&_=${Date.now()}`);
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success) return;

        const rider = data.order;
        const newLat = parseFloat(rider.rider_lat);
        const newLng = parseFloat(rider.rider_lng);
        if (isNaN(newLat) || isNaN(newLng)) return;

        const newPos = new google.maps.LatLng(newLat, newLng);
        if (lastRiderPos && google.maps.geometry.spherical.computeDistanceBetween(lastRiderPos, newPos) < 1) {
            riderMarker.setPosition(newPos);
            lastRiderPos = newPos;
        } else {
            animateRiderMarker(newPos);
        }

        if (rider.status !== currentStatus || rider.store_order_status !== currentStoreStatus) {
            currentStatus = rider.status;
            currentStoreStatus = rider.store_order_status;
            fetch(`/ajax_order_tracking.php?order_id=${orderId}&full=1&_=${Date.now()}`)
                .then(r => r.json())
                .then(d => {
                    if (d.order) renderTimeline(buildTimeline(d.order));
                });
        }
    } catch (e) {}
}

// ---------- Timeline helpers (same logic as PHP, but in JS) ----------
function buildTimeline(order) {
    const t = [];
    t.push({label:'Order Placed', time: new Date(order.order_date).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}), done: true});
    const step2Done = ['accepted','ready_for_payment','payment_submitted','payment_verified'].includes(order.store_order_status) || ['ready','accepted','picked_up','in_transit','delivered'].includes(order.status);
    t.push({label:'Shop Accepted', done: step2Done, time: ''});
    const step3Done = ['ready_for_payment','payment_submitted','payment_verified'].includes(order.store_order_status) || ['ready','accepted','picked_up','in_transit','delivered'].includes(order.status);
    t.push({label:'Preparing Order', done: step3Done, time: ''});
    if (['picked_up','in_transit','delivered'].includes(order.status)) {
        t.push({label:'Rider Picked Up', done: true, time: ''});
    } else if (order.status === 'accepted') {
        t.push({label:'Rider Assigned', done: true, time: ''});
    } else {
        t.push({label:'Rider Picked Up', done: false, time: ''});
    }
    if (order.status === 'in_transit') {
        t.push({label:'On the Way', done: false, current: true, time: ''});
    } else if (order.status === 'delivered') {
        t.push({label:'On the Way', done: true, time: ''});
    } else {
        t.push({label:'On the Way', done: false, time: ''});
    }
    if (order.status === 'delivered') {
        t.push({label:'Delivered', done: true, current: true, time: new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})});
    } else {
        t.push({label:'Delivered', done: false, time: ''});
    }
    return t;
}

function renderTimeline(steps) {
    const container = document.getElementById('timelineContainer');
    let html = '';
    steps.forEach(s => {
        let cls = s.done ? 'completed' : (s.current ? 'current' : '');
        let icon = s.done ? 'fa-check' : (s.current ? 'fa-location-dot' : 'fa-clock');
        html += `<div class="timeline-step ${cls}"><div class="step-icon"><i class="fas ${icon}"></i></div><div class="step-content"><div class="step-label">${s.label}</div>${s.time ? `<div class="step-time">${s.time}</div>` : ''}</div></div>`;
    });
    container.innerHTML = html;
}

// ---------- Start everything ----------
window.addEventListener('load', () => {
    initMap();
    renderTimeline(timelineData);
    setInterval(fetchTrackingData, 5000);
    fetchTrackingData();
});
</script>
</body>
</html>