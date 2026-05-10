<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Get all driver locations from last 2 minutes
$drivers = mysqli_query($conn, "SELECT u.id, u.username, v.plate_number, dl.lat, dl.lng, dl.last_update 
    FROM driver_locations dl
    JOIN users u ON dl.driver_id = u.id
    JOIN vehicles v ON dl.vehicle_id = v.id
    WHERE dl.last_update > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY u.username");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Live Driver Tracking - Unga Logistics</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; }
        .sidebar {
            background: #2d3748;
            width: 250px;
            position: fixed;
            height: 100%;
            padding: 2rem 1rem;
        }
        .sidebar h2 { color: #d4af37; margin-bottom: 2rem; }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
        }
        .sidebar a:hover, .sidebar a.active { background: #4a5568; }
        .content { margin-left: 250px; padding: 2rem; }
        .header {
            background: white;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .map-container {
            background: white;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        #map {
            height: 550px;
            width: 100%;
            border-radius: 8px;
        }
        .driver-list {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .driver-item {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            background: #48bb78;
            color: white;
            border-radius: 20px;
            font-size: 14px;
        }
        .refresh-btn {
            background: #4299e1;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .no-data {
            color: #e53e3e;
            padding: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php">Vehicles</a>
        <a href="deliveries.php">Deliveries</a>
        <a href="ga_optimize.php">GA Optimization</a>
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="driver_tracking.php" class="active">Live Tracking</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>📍 Live Driver Tracking</h2>
            <p>Real-time GPS locations of active drivers</p>
        </div>
        
        <div class="driver-list">
            <h3>Active Drivers (last 2 minutes)</h3>
            <?php if(mysqli_num_rows($drivers) > 0): ?>
                <?php while($driver = mysqli_fetch_assoc($drivers)): ?>
                    <div class="driver-item">
                        🚚 <?php echo htmlspecialchars($driver['username']); ?> 
                        (<?php echo $driver['plate_number']; ?>)
                        <small>Updated: <?php echo date('H:i:s', strtotime($driver['last_update'])); ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-data">⚠️ No active drivers. Make sure driver is logged in and GPS is enabled.</p>
            <?php endif; ?>
        </div>
        
        <div class="map-container">
            <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Map</button>
            <div id="map"></div>
        </div>
    </div>
    
    <script>
        // Get driver locations from PHP
        const drivers = <?php 
            mysqli_data_seek($drivers, 0);
            $data = [];
            while($d = mysqli_fetch_assoc($drivers)) {
                $data[] = $d;
            }
            echo json_encode($data);
        ?>;
        
        // Initialize map
        var map = L.map('map').setView([-1.2864, 36.8172], 8);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Add markers for each driver
        if (drivers.length > 0) {
            var bounds = [];
            drivers.forEach(function(driver) {
                if (driver.lat && driver.lng) {
                    var marker = L.marker([driver.lat, driver.lng]).addTo(map);
                    marker.bindPopup(`
                        <b>${driver.username}</b><br>
                        Vehicle: ${driver.plate_number}<br>
                        Last update: ${driver.last_update}
                    `);
                    bounds.push([driver.lat, driver.lng]);
                }
            });
            if (bounds.length > 0) {
                map.fitBounds(bounds);
            }
        } else {
            // Show Nairobi region if no drivers
            map.setView([-1.2864, 36.8172], 8);
        }
        
        // Auto refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>