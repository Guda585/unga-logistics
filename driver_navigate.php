<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$goToDepot = isset($_GET['depot']) && $_GET['depot'] == 1;

if (!$goToDepot && $delivery_id > 0) {
    $delivery = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address, c.lat, c.lng, c.phone 
        FROM deliveries d 
        LEFT JOIN customers c ON d.customer_id = c.id 
        WHERE d.id = $delivery_id AND d.driver_id = {$_SESSION['user_id']}");
    $delivery = mysqli_fetch_assoc($delivery);
    
    if (!$delivery) {
        die('Delivery not found or not assigned to you.');
    }
}

$depotLat = -1.3167;
$depotLng = 36.8500;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Navigation - <?php echo $goToDepot ? 'Return to Depot' : $delivery['delivery_code']; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; height: 100vh; display: flex; flex-direction: column; }
        .header {
            background: #2d3748;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h2 { color: #d4af37; font-size: 16px; }
        .btn-back {
            background: #4299e1;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }
        .info-panel {
            background: white;
            padding: 10px 15px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            border-bottom: 1px solid #ddd;
        }
        .info-item {
            flex: 1;
            min-width: 100px;
        }
        .info-label {
            font-weight: bold;
            color: #4a5568;
            font-size: 10px;
        }
        .info-value {
            font-size: 12px;
            color: #2d3748;
        }
        .map-container {
            flex: 1;
            position: relative;
            min-height: 300px;
        }
        #map {
            height: 100%;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
        .gps-status {
            background: #48bb78;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .instructions {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-size: 11px;
            z-index: 1000;
            max-height: 120px;
            overflow-y: auto;
        }
        .instruction-step {
            padding: 3px 0;
            border-bottom: 1px solid #444;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>🗺️ Navigation - <?php echo $goToDepot ? 'Return to Unga Depot' : $delivery['delivery_code']; ?></h2>
        <div>
            <span class="gps-status" id="gpsStatus">📍 Getting location...</span>
            <a href="driver.php" class="btn-back">← Dashboard</a>
        </div>
    </div>
    
    <div class="info-panel">
        <?php if($goToDepot): ?>
        <div class="info-item">
            <div class="info-label">Destination</div>
            <div class="info-value">🏭 Unga Depot, Industrial Area, Nairobi</div>
        </div>
        <?php else: ?>
        <div class="info-item">
            <div class="info-label">Customer</div>
            <div class="info-value"><?php echo htmlspecialchars($delivery['customer_name']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Address</div>
            <div class="info-value"><?php echo htmlspecialchars($delivery['address']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Deadline</div>
            <div class="info-value"><?php echo $delivery['time_window_end'] ? date('H:i', strtotime($delivery['time_window_end'])) : 'N/A'; ?></div>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <div class="info-label">Distance</div>
            <div class="info-value" id="distanceInfo">-- km</div>
        </div>
        <div class="info-item">
            <div class="info-label">Est. Time</div>
            <div class="info-value" id="timeInfo">-- min</div>
        </div>
    </div>
    
    <div class="map-container">
        <div id="map"></div>
        <div id="instructions" class="instructions"></div>
    </div>
    
    <script>
        let map;
        let routingControl;
        let currentLocationMarker;
        let watchId;
        
        const depotLat = <?php echo $depotLat; ?>;
        const depotLng = <?php echo $depotLng; ?>;
        const goToDepot = <?php echo $goToDepot ? 'true' : 'false'; ?>;
        const customerLat = <?php echo !$goToDepot && isset($delivery['lat']) ? $delivery['lat'] : $depotLat; ?>;
        const customerLng = <?php echo !$goToDepot && isset($delivery['lng']) ? $delivery['lng'] : $depotLng; ?>;
        
        function initMap() {
            // Default to Nairobi center
            map = L.map('map').setView([-1.2864, 36.8172], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add destination marker
            if (goToDepot) {
                L.marker([depotLat, depotLng])
                    .bindPopup('🏭 Unga Depot')
                    .addTo(map);
            } else if (customerLat && customerLng && customerLat != 0) {
                L.marker([customerLat, customerLng])
                    .bindPopup('📦 <?php echo addslashes($delivery['customer_name'] ?? 'Customer'); ?>')
                    .addTo(map);
            }
            
            // Start tracking
            startTracking();
        }
        
        function startTracking() {
            if (!navigator.geolocation) {
                document.getElementById('gpsStatus').innerHTML = '📍 GPS not supported';
                return;
            }
            
            // Try to get initial position
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    updateLocation(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    document.getElementById('gpsStatus').innerHTML = '📍 GPS: Waiting for signal';
                    document.getElementById('gpsStatus').style.background = '#ed8936';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
            
            // Watch position for updates
            watchId = navigator.geolocation.watchPosition(
                function(position) {
                    updateLocation(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    document.getElementById('gpsStatus').innerHTML = '📍 GPS: Waiting for signal';
                    document.getElementById('gpsStatus').style.background = '#ed8936';
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 5000
                }
            );
        }
        
        function updateLocation(currentLat, currentLng) {
            document.getElementById('gpsStatus').innerHTML = '📍 GPS: Active';
            document.getElementById('gpsStatus').style.background = '#48bb78';
            
            if (currentLocationMarker) {
                currentLocationMarker.setLatLng([currentLat, currentLng]);
            } else {
                currentLocationMarker = L.marker([currentLat, currentLng], {
                    icon: L.divIcon({
                        html: '<div style="background-color: #4299e1; width: 16px; height: 16px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>',
                        iconSize: [16, 16],
                        className: 'driver-marker'
                    })
                }).addTo(map);
                currentLocationMarker.bindPopup('📍 Your Location').openPopup();
            }
            
            // Set destination
            let destLat, destLng;
            if (goToDepot) {
                destLat = depotLat;
                destLng = depotLng;
            } else {
                destLat = customerLat;
                destLng = customerLng;
            }
            
            updateRoute(currentLat, currentLng, destLat, destLng);
        }
        
        function updateRoute(startLat, startLng, endLat, endLng) {
            if (!endLat || !endLng || endLat == 0) return;
            
            const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson&steps=true`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.routes && data.routes.length > 0) {
                        const route = data.routes[0];
                        const distance = (route.distance / 1000).toFixed(1);
                        const duration = Math.round(route.duration / 60);
                        
                        document.getElementById('distanceInfo').innerHTML = distance + ' km';
                        document.getElementById('timeInfo').innerHTML = duration + ' min';
                        
                        if (routingControl) {
                            map.removeLayer(routingControl);
                        }
                        
                        const routeGeo = route.geometry;
                        routingControl = L.geoJSON(routeGeo, {
                            style: { color: '#48bb78', weight: 5, opacity: 0.8 }
                        }).addTo(map);
                        
                        // Fit map to show entire route
                        try {
                            map.fitBounds(routingControl.getBounds());
                        } catch(e) {
                            map.setView([(startLat+endLat)/2, (startLng+endLng)/2], 10);
                        }
                        
                        // Display turn-by-turn instructions
                        const steps = route.legs[0].steps;
                        let instructionsHtml = '<strong>🔽 Directions</strong><br>';
                        steps.forEach(step => {
                            const instruction = step.maneuver.instruction;
                            const stepDistance = (step.distance / 1000).toFixed(1);
                            instructionsHtml += `<div class="instruction-step">➡️ ${instruction} (${stepDistance} km)</div>`;
                        });
                        document.getElementById('instructions').innerHTML = instructionsHtml;
                    } else {
                        showFallbackRoute(startLat, startLng, endLat, endLng);
                    }
                })
                .catch(error => {
                    console.log('OSRM Error:', error);
                    showFallbackRoute(startLat, startLng, endLat, endLng);
                });
        }
        
        function showFallbackRoute(startLat, startLng, endLat, endLng) {
            if (routingControl) {
                map.removeLayer(routingControl);
            }
            routingControl = L.polyline([[startLat, startLng], [endLat, endLng]], {
                color: '#48bb78', weight: 5, opacity: 0.8
            }).addTo(map);
            map.fitBounds([[startLat, startLng], [endLat, endLng]]);
            document.getElementById('instructions').innerHTML = '<strong>⚠️</strong> Live directions unavailable. Straight line shown.';
        }
        
        // Initialize map when page is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    </script>
</body>
</html>