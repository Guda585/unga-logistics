<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$routes = [];

// Calculate straight-line distance (Haversine formula) - FAST
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371; // Earth radius in km
    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($R * $c, 1);
}

// Get traffic factor based on time of day
function getTrafficFactor($hour) {
    if ($hour >= 7 && $hour <= 9) {
        return 1.5; // Morning rush
    } elseif ($hour >= 16 && $hour <= 19) {
        return 1.4; // Evening rush
    } elseif ($hour >= 22 || $hour <= 5) {
        return 0.7; // Night
    } else {
        return 1.0; // Normal
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get active deliveries (pending status)
    $deliveries = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.lat, c.lng, c.address FROM deliveries d LEFT JOIN customers c ON d.customer_id = c.id WHERE d.status = 'pending' OR d.status = 'assigned'");
    $delivery_list = [];
    while ($row = mysqli_fetch_assoc($deliveries)) {
        $delivery_list[] = $row;
    }
    
    // Get available vehicles
    $vehicles = mysqli_query($conn, "SELECT * FROM vehicles WHERE status = 'available'");
    $vehicle_list = [];
    while ($row = mysqli_fetch_assoc($vehicles)) {
        $vehicle_list[] = $row;
    }
    
    if (count($delivery_list) == 0) {
        $message = "No pending deliveries to optimize.";
    } elseif (count($vehicle_list) == 0) {
        $message = "No available vehicles. Please update vehicle status.";
    } else {
        // Depot coordinates
        $depot_lat = -1.3167;
        $depot_lng = 36.8500;
        $current_hour = date('H');
        $traffic_factor = getTrafficFactor($current_hour);
        
        // Calculate distances from depot to each customer
        $dist_to_depot = [];
        foreach ($delivery_list as $d) {
            $dist_to_depot[$d['id']] = calculateDistance($depot_lat, $depot_lng, $d['lat'], $d['lng']);
        }
        
        // Calculate savings between all customer pairs
        $savings = [];
        for ($i = 0; $i < count($delivery_list); $i++) {
            for ($j = $i + 1; $j < count($delivery_list); $j++) {
                $id_i = $delivery_list[$i]['id'];
                $id_j = $delivery_list[$j]['id'];
                
                $dist_ij = calculateDistance($delivery_list[$i]['lat'], $delivery_list[$i]['lng'], 
                                            $delivery_list[$j]['lat'], $delivery_list[$j]['lng']);
                
                $saving = ($dist_to_depot[$id_i] + $dist_to_depot[$id_j] - $dist_ij) / $traffic_factor;
                
                $savings[] = [
                    'i' => $id_i,
                    'j' => $id_j,
                    'saving' => $saving,
                    'dist_ij' => $dist_ij
                ];
            }
        }
        
        // Sort savings in descending order
        usort($savings, function($a, $b) {
            return $b['saving'] <=> $a['saving'];
        });
        
        // Build routes
        $routes = [];
        $assigned = [];
        $vehicle_index = 0;
        
        foreach ($savings as $s) {
            if ($vehicle_index >= count($vehicle_list)) break;
            
            if (!in_array($s['i'], $assigned) && !in_array($s['j'], $assigned)) {
                $vehicle = $vehicle_list[$vehicle_index];
                $total_weight = 0;
                $stop_ids = [$s['i'], $s['j']];
                
                // Find deliveries in array
                $delivery_i = null;
                $delivery_j = null;
                foreach ($delivery_list as $d) {
                    if ($d['id'] == $s['i']) $delivery_i = $d;
                    if ($d['id'] == $s['j']) $delivery_j = $d;
                }
                
                $total_weight = $delivery_i['weight_kg'] + $delivery_j['weight_kg'];
                
                // Try to add more stops
                foreach ($delivery_list as $d) {
                    if (!in_array($d['id'], $stop_ids) && !in_array($d['id'], $assigned)) {
                        if ($total_weight + $d['weight_kg'] <= $vehicle['capacity_kg']) {
                            $stop_ids[] = $d['id'];
                            $total_weight += $d['weight_kg'];
                        }
                    }
                }
                
                // Calculate route distance
                $route_distance = $dist_to_depot[$stop_ids[0]];
                for ($k = 0; $k < count($stop_ids) - 1; $k++) {
                    $from = null;
                    $to = null;
                    foreach ($delivery_list as $d) {
                        if ($d['id'] == $stop_ids[$k]) $from = $d;
                        if ($d['id'] == $stop_ids[$k+1]) $to = $d;
                    }
                    if ($from && $to) {
                        $route_distance += calculateDistance($from['lat'], $from['lng'], $to['lat'], $to['lng']);
                    }
                }
                $route_distance += $dist_to_depot[end($stop_ids)];
                
                $route_cost = $vehicle['fixed_cost'] + ($vehicle['cost_per_km'] * $route_distance);
                
                $routes[] = [
                    'vehicle' => $vehicle,
                    'stops' => $stop_ids,
                    'total_weight' => $total_weight,
                    'total_distance' => round($route_distance, 1),
                    'total_cost' => round($route_cost, 0)
                ];
                
                $assigned = array_merge($assigned, $stop_ids);
                $vehicle_index++;
            }
        }
        
        // Assign remaining deliveries
        foreach ($delivery_list as $d) {
            if (!in_array($d['id'], $assigned) && $vehicle_index < count($vehicle_list)) {
                $vehicle = $vehicle_list[$vehicle_index];
                $route_distance = 2 * $dist_to_depot[$d['id']];
                $route_cost = $vehicle['fixed_cost'] + ($vehicle['cost_per_km'] * $route_distance);
                
                $routes[] = [
                    'vehicle' => $vehicle,
                    'stops' => [$d['id']],
                    'total_weight' => $d['weight_kg'],
                    'total_distance' => round($route_distance, 1),
                    'total_cost' => round($route_cost, 0)
                ];
                $assigned[] = $d['id'];
                $vehicle_index++;
            }
        }
        
        // ============================================
        // ASSIGN ROUTES TO DRIVERS - UPDATE DATABASE
        // ============================================
        $assigned_count = 0;
        foreach ($routes as $route) {
            $vehicle_id = $route['vehicle']['id'];
            $driver_id = $route['vehicle']['driver_id'];
            
            if ($driver_id) {
                // Assign all deliveries in this route to this driver
                foreach ($route['stops'] as $stop_id) {
                    $update_sql = "UPDATE deliveries SET vehicle_id = $vehicle_id, driver_id = $driver_id, status = 'assigned' WHERE id = $stop_id";
                    if (mysqli_query($conn, $update_sql)) {
                        $assigned_count++;
                    }
                }
            }
        }
        
        $message = "Optimization complete! " . count($routes) . " routes created for " . count($assigned) . " deliveries. " . $assigned_count . " deliveries assigned to drivers. Traffic factor: " . $traffic_factor . "x";
    }
}

// Get all deliveries for display
$all_deliveries = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address FROM deliveries d LEFT JOIN customers c ON d.customer_id = c.id WHERE d.status = 'pending' OR d.status = 'assigned' ORDER BY d.id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Route Optimization - Unga Logistics</title>
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
        .btn {
            padding: 8px 16px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-success { background: #48bb78; }
        .message {
            background: #c6f6d5;
            color: #22543d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .route-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .route-title {
            background: #f0f2f5;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .stop-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .summary {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }
        .badge-success { background: #48bb78; color: white; }
        .badge-warning { background: #ed8936; color: white; }
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f2f5; }
        .warning {
            background: #fef5e7;
            padding: 10px;
            border-left: 4px solid #ed8936;
            margin-bottom: 20px;
        }
        .driver-info {
            font-size: 12px;
            color: #48bb78;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php">Vehicles</a>
        <a href="deliveries.php">Deliveries</a>
        <a href="assign_route.php" class="active">Route Optimization</a>
        <a href="logout.php" style="margin-top: 2rem;">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>Route Optimization (Clarke-Wright Algorithm)</h2>
            <p style="color: #718096; margin-top: 5px;">Using straight-line distances + Traffic factors + Driver Assignment</p>
        </div>
        
        <?php if($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="warning">
            ⚠️ <strong>Note:</strong> Running this optimization will assign deliveries to drivers based on vehicle availability.
        </div>
        
        <form method="POST">
            <button type="submit" class="btn btn-success">Run Clarke-Wright Optimization & Assign Drivers</button>
        </form>
        
        <h3 style="margin: 20px 0 10px;">Pending Deliveries (<?php echo mysqli_num_rows($all_deliveries); ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Delivery Code</th>
                    <th>Customer</th>
                    <th>Weight (kg)</th>
                    <th>Current Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($all_deliveries)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['delivery_code']; ?></td>
                    <td><?php echo $row['customer_name']; ?></td>
                    <td><?php echo $row['weight_kg']; ?></td>
                    <td>
                        <span class="badge" style="background: <?php echo $row['status'] == 'assigned' ? '#4299e1' : '#ed8936'; ?>;">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php if(count($routes) > 0): ?>
            <h3 style="margin: 20px 0 10px;">Optimized Routes (<?php echo count($routes); ?> routes)</h3>
            <?php foreach($routes as $index => $route): ?>
                <div class="route-card">
                    <div class="route-title">
                        Route <?php echo $index + 1; ?> - Vehicle: <?php echo $route['vehicle']['plate_number']; ?> (<?php echo $route['vehicle']['vehicle_type']; ?>)
                        <?php if($route['vehicle']['driver_id']): ?>
                            <span class="badge badge-success">Driver: <?php 
                                $driver_query = mysqli_query($conn, "SELECT username FROM users WHERE id = {$route['vehicle']['driver_id']}");
                                $driver = mysqli_fetch_assoc($driver_query);
                                echo $driver['username'];
                            ?></span>
                        <?php endif; ?>
                    </div>
                    <div><strong>Stops (<?php echo count($route['stops']); ?> deliveries):</strong></div>
                    <?php foreach($route['stops'] as $stop_id): 
                        $delivery = mysqli_fetch_assoc(mysqli_query($conn, "SELECT d.*, c.name as customer_name FROM deliveries d LEFT JOIN customers c ON d.customer_id = c.id WHERE d.id = $stop_id"));
                    ?>
                        <div class="stop-item">
                            📍 <?php echo $delivery['delivery_code']; ?> - <?php echo $delivery['customer_name']; ?> 
                            (<?php echo $delivery['weight_kg']; ?> kg)
                        </div>
                    <?php endforeach; ?>
                    <div class="summary">
                        Total Weight: <?php echo $route['total_weight']; ?> kg / <?php echo $route['vehicle']['capacity_kg']; ?> kg<br>
                        Total Distance: <?php echo $route['total_distance']; ?> km<br>
                        Total Cost: KES <?php echo number_format($route['total_cost']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="warning" style="margin-top: 20px;">
            💡 <strong>Next Step:</strong> Drivers can now log in to view their assigned deliveries and update delivery status.
        </div>
    </div>
</body>
</html>