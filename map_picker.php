<?php
$return = isset($_GET['return']) ? $_GET['return'] : 'checkout';
$storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Choose Delivery Location</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Manrope', -apple-system, sans-serif; height:100dvh; display:flex; flex-direction:column; }
        #map { flex:1; width:100%; }
        .search-box {
            position:fixed; top:16px; left:16px; right:16px; z-index:1000;
            display:flex; gap:8px; align-items:center;
        }
        .search-box input {
            flex:1; padding:14px 18px; border-radius:30px;
            border:2px solid #D4AF37; background:#fff;
            font-size:16px; font-family:inherit;
            box-shadow:0 4px 20px rgba(0,0,0,0.1);
            outline:none;
        }
        .back-btn {
            position:fixed; top:16px; left:16px; z-index:1001;
            width:42px; height:42px; border-radius:50%;
            background:rgba(0,0,0,0.5); color:#fff;
            display:flex; align-items:center; justify-content:center;
            text-decoration:none; font-size:18px;
        }
        .pin-hint {
            position:fixed; top:50%; left:50%;
            transform:translate(-50%, -100%);
            z-index:999; pointer-events:none;
            font-size:40px;
            filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            opacity: 0.7;
        }
        .confirm-btn {
            position:fixed; bottom:30px; left:50%;
            transform:translateX(-50%); z-index:1000;
            background:#D4AF37; color:#04140F;
            padding:16px 36px; border:none; border-radius:30px;
            font-weight:800; font-size:17px; cursor:pointer;
            box-shadow:0 8px 30px rgba(212,175,55,0.5);
            width:calc(100% - 32px); max-width:400px;
        }
        .addr-card {
            position:fixed; top:90px; left:16px; right:16px; z-index:1000;
            background:rgba(255,255,255,0.95); backdrop-filter:blur(10px);
            padding:14px 18px; border-radius:16px;
            font-size:14px; color:#1A1A1A;
            box-shadow:0 4px 16px rgba(0,0,0,0.08);
            display:flex; align-items:center; gap:8px;
        }
        .addr-card i { color:#D4AF37; font-size:18px; flex-shrink:0; }
        .addr-card span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gps-btn {
            position:fixed; bottom:100px; right:16px; z-index:1000;
            width:48px; height:48px; border-radius:50%;
            background:#fff; border:none;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            display:flex; align-items:center; justify-content:center;
            font-size:20px; cursor:pointer; color:#D4AF37;
        }
        .instruction {
            position:fixed; bottom:140px; left:50%; transform:translateX(-50%);
            background:rgba(0,0,0,0.7); color:#fff; padding:6px 16px;
            border-radius:20px; font-size:13px; z-index:1000;
            pointer-events:none;
        }
    </style>
</head>
<body>
    <!-- Back -->
    <a href="checkout.php?store_id=<?= $storeId ?>" class="back-btn"><i class="fas fa-arrow-left"></i></a>

    <!-- Search -->
    <div class="search-box">
        <input id="searchInput" type="text" placeholder="Search for a place…" autocomplete="off">
    </div>

    <!-- Map -->
    <div id="map"></div>

    <!-- Floating pin hint (disappears once marker is placed) -->
    <div class="pin-hint" id="pinHint">📍</div>
    <div class="instruction" id="instruction">Tap the map to place your marker</div>

    <!-- Address display -->
    <div class="addr-card" id="addrCard">
        <i class="fas fa-map-marker-alt"></i>
        <span id="addrText">Tap the map or search for a place</span>
    </div>

    <!-- GPS button -->
    <button class="gps-btn" onclick="goToMyLocation()" title="My Location">
        <i class="fas fa-location-crosshairs"></i>
    </button>

    <!-- Confirm -->
    <button class="confirm-btn" onclick="confirmLocation()">
        <i class="fas fa-check"></i> Confirm Location
    </button>

    <script>
        // Default coords (Kerala center)
        let selectedLat = <?= isset($_GET['lat']) ? $_GET['lat'] : 10.8505 ?>;
        let selectedLng = <?= isset($_GET['lng']) ? $_GET['lng'] : 76.2711 ?>;
        let selectedAddress = "<?= isset($_GET['address']) ? htmlspecialchars($_GET['address']) : '' ?>";

        // Map initialization
        const map = L.map('map', {
            center: [selectedLat, selectedLng],
            zoom: 14,
            zoomControl: false
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // Marker (initially null)
        let marker = null;

        // Function to update address text
        function updateAddressText(addr) {
            selectedAddress = addr;
            document.getElementById('addrText').textContent = addr || 'Address not found';
        }

        // Function to place or move marker
        function placeMarker(lat, lng, shouldFetchAddress = true) {
            selectedLat = lat;
            selectedLng = lng;

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                // Listen to drag events
                marker.on('dragend', function() {
                    const pos = marker.getLatLng();
                    selectedLat = pos.lat;
                    selectedLng = pos.lng;
                    reverseGeocode(pos.lat, pos.lng);
                });
                // Hide the hint and instruction
                document.getElementById('pinHint').style.display = 'none';
                document.getElementById('instruction').style.display = 'none';
            }

            if (shouldFetchAddress) {
                reverseGeocode(lat, lng);
            }
        }

        // Reverse geocode helper
        let geocodeTimer;
        function reverseGeocode(lat, lng) {
            clearTimeout(geocodeTimer);
            geocodeTimer = setTimeout(async () => {
                try {
                    const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=en`);
                    const d = await r.json();
                    if (d && d.display_name) {
                        updateAddressText(d.display_name);
                    } else {
                        updateAddressText('Address not available');
                    }
                } catch (e) {
                    updateAddressText('Error fetching address');
                }
            }, 300);
        }

        // Map click event – place marker at tap point
        map.on('click', function(e) {
            placeMarker(e.latlng.lat, e.latlng.lng, true);
        });

        // If we have coordinates from a previous selection, place a marker immediately
        if (selectedAddress || (selectedLat && selectedLng)) {
            placeMarker(selectedLat, selectedLng, true);
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        let searchTimer;
        searchInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 3) return;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(async () => {
                try {
                    const r = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(q)}&limit=1&accept-language=en`);
                    const d = await r.json();
                    if (d && d.length > 0) {
                        const lat = parseFloat(d[0].lat);
                        const lng = parseFloat(d[0].lon);
                        map.setView([lat, lng], 16);
                        placeMarker(lat, lng, false);
                        updateAddressText(d[0].display_name);
                    }
                } catch (e) {}
            }, 500);
        });

        // GPS button
        function goToMyLocation() {
            if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
            navigator.geolocation.getCurrentPosition(pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                map.setView([lat, lng], 16);
                placeMarker(lat, lng, true);
            }, () => alert('Unable to get location.'));
        }

        // Confirm selection
        function confirmLocation() {
            if (!marker) {
                alert('Please tap the map to place a marker first.');
                return;
            }
            const params = new URLSearchParams();
            params.set('store_id', '<?= $storeId ?>');
            params.set('lat', selectedLat.toFixed(6));
            params.set('lng', selectedLng.toFixed(6));
            params.set('address', selectedAddress);
            window.location.href = `checkout.php?${params.toString()}`;
        }
    </script>
</body>
</html>