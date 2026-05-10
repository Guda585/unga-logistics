<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$delivery_id) {
    die('No delivery ID provided');
}

$sql = "SELECT d.*, c.name as customer_name, c.address, c.phone 
        FROM deliveries d 
        LEFT JOIN customers c ON d.customer_id = c.id 
        WHERE d.id = $delivery_id";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    die('Delivery not found');
}

if ($delivery['driver_id'] != $_SESSION['user_id']) {
    die('You are not authorized to view this delivery');
}

$driver_name = '';
if ($delivery['driver_id']) {
    $drv_res = mysqli_query($conn, "SELECT username FROM users WHERE id = " . $delivery['driver_id']);
    $drv = mysqli_fetch_assoc($drv_res);
    $driver_name = $drv['username'];
}

$plate_number = '';
if ($delivery['vehicle_id']) {
    $veh_res = mysqli_query($conn, "SELECT plate_number FROM vehicles WHERE id = " . $delivery['vehicle_id']);
    $veh = mysqli_fetch_assoc($veh_res);
    $plate_number = $veh['plate_number'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Details - Driver</title>
    <style>
        body { font-family: Arial; background: #f5f7fa; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; max-width: 800px; margin: 0 auto 20px auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .row { padding: 8px 0; border-bottom: 1px solid #eee; display: flex; }
        .label { font-weight: bold; width: 140px; }
        .btn { padding: 8px 16px; background: #4299e1; color: white; text-decoration: none; border-radius: 6px; display: inline-block; margin-right: 10px; }
        .btn-back { background: #718096; }
        .status { padding: 4px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .status-delivered { background: #48bb78; color: white; }
        .status-pending { background: #ed8936; color: white; }
        .status-in_transit { background: #9f7aea; color: white; }
        .status-assigned { background: #4299e1; color: white; }
        h3 { margin-bottom: 15px; color: #2d3748; }
        .back-link { margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <h3>📦 Delivery Details</h3>
            <div class="row"><div class="label">Delivery Code:</div><div><?php echo htmlspecialchars($delivery['delivery_code']); ?></div></div>
            <div class="row"><div class="label">Status:</div><div><span class="status status-<?php echo $delivery['status']; ?>"><?php echo ucfirst($delivery['status']); ?></span></div></div>
            <?php if($delivery['delivered_at']): ?>
            <div class="row"><div class="label">Delivered At:</div><div><?php echo $delivery['delivered_at']; ?></div></div>
            <?php endif; ?>
            <div class="row"><div class="label">Weight:</div><div><?php echo number_format($delivery['weight_kg']); ?> kg</div></div>
            <?php if($delivery['time_window_end']): ?>
            <div class="row"><div class="label">Deadline:</div><div><?php echo date('H:i', strtotime($delivery['time_window_end'])); ?></div></div>
            <?php endif; ?>
            <?php if($delivery['penalty_amount'] > 0): ?>
            <div class="row"><div class="label">Late Penalty:</div><div style="color:#e53e3e;">KES <?php echo number_format($delivery['penalty_amount']); ?></div></div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>👤 Customer Details</h3>
            <div class="row"><div class="label">Name:</div><div><?php echo htmlspecialchars($delivery['customer_name']); ?></div></div>
            <div class="row"><div class="label">Address:</div><div><?php echo htmlspecialchars($delivery['address']); ?></div></div>
            <div class="row"><div class="label">Phone:</div><div><?php echo $delivery['phone'] ?: 'N/A'; ?></div></div>
        </div>
        
        <div class="card">
            <h3>🚚 Delivery By</h3>
            <div class="row"><div class="label">Driver:</div><div><?php echo htmlspecialchars($driver_name); ?></div></div>
            <div class="row"><div class="label">Vehicle:</div><div><?php echo htmlspecialchars($plate_number); ?></div></div>
        </div>
        
        <div class="back-link">
            <a href="driver.php" class="btn btn-back">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>