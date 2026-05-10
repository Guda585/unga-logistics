<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $result = mysqli_query($conn, "SELECT status FROM vehicles WHERE id = $id");
    $vehicle = mysqli_fetch_assoc($result);
    $new_status = ($vehicle['status'] == 'available') ? 'maintenance' : 'available';
    mysqli_query($conn, "UPDATE vehicles SET status = '$new_status' WHERE id = $id");
    header('Location: vehicles.php');
    exit();
}

// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $driver = mysqli_fetch_assoc(mysqli_query($conn, "SELECT driver_id FROM vehicles WHERE id = $id"));
    if ($driver && $driver['driver_id']) {
        mysqli_query($conn, "UPDATE users SET vehicle_id = NULL WHERE id = {$driver['driver_id']}");
    }
    mysqli_query($conn, "DELETE FROM vehicles WHERE id = $id");
    header('Location: vehicles.php');
    exit();
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plate = mysqli_real_escape_string($conn, $_POST['plate']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $capacity = $_POST['capacity'];
    $status = $_POST['status'];
    $fixed_cost = $_POST['fixed_cost'];
    $cost_per_km = $_POST['cost_per_km'];
    $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : 'NULL';
    
    if (isset($_POST['id']) && $_POST['id'] != '') {
        $id = $_POST['id'];
        mysqli_query($conn, "UPDATE vehicles SET plate_number='$plate', vehicle_type='$type', capacity_tonnes=$capacity, status='$status', fixed_cost=$fixed_cost, cost_per_km=$cost_per_km, driver_id=$driver_id WHERE id=$id");
    } else {
        mysqli_query($conn, "INSERT INTO vehicles (plate_number, vehicle_type, capacity_tonnes, status, fixed_cost, cost_per_km, driver_id) VALUES ('$plate', '$type', $capacity, '$status', $fixed_cost, $cost_per_km, $driver_id)");
    }
    header('Location: vehicles.php');
    exit();
}

$vehicles = mysqli_query($conn, "SELECT v.*, u.username as driver_name FROM vehicles v LEFT JOIN users u ON v.driver_id = u.id ORDER BY v.id");
$drivers = mysqli_query($conn, "SELECT id, username FROM users WHERE role = 'driver' ORDER BY username");
$edit_vehicle = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $result = mysqli_query($conn, "SELECT * FROM vehicles WHERE id = $edit_id");
    $edit_vehicle = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vehicles - Unga Logistics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; }
        .sidebar {
            background: #1a202c;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn {
            padding: 6px 12px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            display: inline-block;
            font-size: 12px;
        }
        .btn-success { background: #48bb78; }
        .btn-danger { background: #e53e3e; }
        .btn-warning { background: #ed8936; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        .table-container {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 20px;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            white-space: nowrap;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
        }
        .form-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            border: 1px solid #e2e8f0;
        }
        .form-card input, .form-card select {
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        .form-group { margin-bottom: 12px; }
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 150px; }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-available { background: #48bb78; color: white; }
        .status-maintenance { background: #e53e3e; color: white; }
        .status-on_route { background: #4299e1; color: white; }
        .actions-cell {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .sidebar { display: none; }
            th, td { font-size: 10px; padding: 6px 4px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php" class="active">Vehicles</a>
        <a href="deliveries.php">Deliveries</a>
        <a href="drivers.php">Drivers</a>
        <a href="ga_optimize.php">GA Optimization</a>
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>Vehicle Management</h2>
            <button onclick="document.getElementById('addForm').style.display='block'" class="btn btn-success">+ Add Vehicle</button>
        </div>
        
        <div id="addForm" class="form-card" style="display: <?php echo $edit_vehicle ? 'block' : 'none'; ?>">
            <h3><?php echo $edit_vehicle ? 'Edit Vehicle' : 'Add New Vehicle'; ?></h3>
            <form method="POST">
                <?php if($edit_vehicle): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_vehicle['id']; ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate" required value="<?php echo $edit_vehicle['plate_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="text" name="type" required value="<?php echo $edit_vehicle['vehicle_type'] ?? ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Capacity (tonnes)</label>
                        <input type="number" name="capacity" required value="<?php echo $edit_vehicle['capacity_tonnes'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="available" <?php echo ($edit_vehicle['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="maintenance" <?php echo ($edit_vehicle['status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="on_route" <?php echo ($edit_vehicle['status'] ?? '') == 'on_route' ? 'selected' : ''; ?>>On Route</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fixed Cost (KES)</label>
                        <input type="number" name="fixed_cost" required value="<?php echo $edit_vehicle['fixed_cost'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Cost per km (KES)</label>
                        <input type="number" step="0.01" name="cost_per_km" required value="<?php echo $edit_vehicle['cost_per_km'] ?? ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Assign Driver</label>
                    <select name="driver_id">
                        <option value="">-- Unassigned --</option>
                        <?php 
                        mysqli_data_seek($drivers, 0);
                        while($driver = mysqli_fetch_assoc($drivers)): ?>
                            <option value="<?php echo $driver['id']; ?>" <?php echo ($edit_vehicle['driver_id'] ?? '') == $driver['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($driver['username']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Save Vehicle</button>
                <button type="button" onclick="document.getElementById('addForm').style.display='none'" class="btn btn-danger">Cancel</button>
            </form>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plate Number</th>
                        <th>Type</th>
                        <th>Capacity (t)</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Fixed Cost</th>
                        <th>Cost/km</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($vehicles, 0);
                    $sn = 1;
                    while($row = mysqli_fetch_assoc($vehicles)): 
                    ?>
                    <tr>
                        <td><?php echo $sn; ?></td>
                        <td><?php echo $row['plate_number']; ?></td>
                        <td><?php echo $row['vehicle_type']; ?></td>
                        <td><?php echo number_format($row['capacity_tonnes']); ?> t</td>
                        <td><?php echo $row['driver_name'] ?? '—'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>KES <?php echo number_format($row['fixed_cost']); ?></td>
                        <td>KES <?php echo number_format($row['cost_per_km'], 2); ?></td>
                        <td class="actions-cell">
                            <?php if($row['status'] == 'available'): ?>
                                <a href="vehicles.php?toggle=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Maint</a>
                            <?php else: ?>
                                <a href="vehicles.php?toggle=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Active</a>
                            <?php endif; ?>
                            <a href="vehicles.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm">Edit</a>
                            <a href="vehicles.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this vehicle?')">Del</a>
                        </td>
                    </tr>
                    <?php 
                    $sn++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem('scrollPos', window.scrollY);
        });
        window.addEventListener('load', function() {
            var scrollPos = sessionStorage.getItem('scrollPos');
            if (scrollPos) {
                window.scrollTo(0, scrollPos);
                sessionStorage.removeItem('scrollPos');
            }
        });
    </script>
</body>
</html>