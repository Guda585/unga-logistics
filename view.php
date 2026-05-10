<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id == 0) {
    die('Invalid delivery ID.');
}

$query = "SELECT d.*, c.name as customer_name, c.address, u.username as driver_name, v.plate_number 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    LEFT JOIN users u ON d.driver_id = u.id 
    LEFT JOIN vehicles v ON d.vehicle_id = v.id
    WHERE d.id = $delivery_id";

$result = mysqli_query($conn, $query);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    die('Delivery not found.');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Details - Admin</title>
    <style>
        body { font-family: Arial; background: #f5f7fa; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .row { padding: 8px 0; border-bottom: 1px solid #eee; display: flex; }
        .label { font-weight: bold; width: 140px; }
        .btn { padding: 8px 16px; background: #4299e1; color: white; text-decoration: none; border-radius: 6px; display: inline-block; margin-right: 10px; }
        .btn-back { background: #718096; }
        h3 { margin-bottom: 15px; }
        .status-delivered { background: #48bb78; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .status-pending { background: #ed8936; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .status-in_transit { background: #9f7aea; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .status-assigned { background: #4299e1; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .penalty { color: #e53e3e; font-weight: bold; }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <h3>📦 Delivery Information</h3>
            <div class="row"><div class="label">Delivery Code:</div><div><?php echo htmlspecialchars($delivery['delivery_code']); ?></div></div>
            <div class="row"><div class="label">Status:</div><div><span class="status-<?php echo $delivery['status']; ?>"><?php echo ucfirst($delivery['status']); ?></span></div></div>
            <?php if($delivery['delivered_at']): ?>
            <div class="row"><div class="label">Delivered At:</div><div><?php echo $delivery['delivered_at']; ?></div></div>
            <?php endif; ?>
            <div class="row"><div class="label">Weight:</div><div><?php echo number_format($delivery['weight_tonnes'], 2); ?> tonnes</div></div>
            <div class="row"><div class="label">Delivery Date:</div><div><?php echo $delivery['delivery_date']; ?></div></div>
            <div class="row"><div class="label">Time Window:</div><div><?php echo date('H:i', strtotime($delivery['time_window_start'])) . ' - ' . date('H:i', strtotime($delivery['time_window_end'])); ?></div></div>
            <?php if($delivery['time_window_end']): ?>
            <div class="row"><div class="label">Deadline:</div><div><?php echo date('H:i', strtotime($delivery['time_window_end'])); ?></div></div>
            <?php endif; ?>
            <?php if($delivery['penalty_amount'] > 0): ?>
            <div class="row"><div class="label">Late Penalty:</div><div class="penalty">KES <?php echo number_format($delivery['penalty_amount']); ?></div></div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>👤 Customer Details</h3>
            <div class="row"><div class="label">Name:</div><div><?php echo htmlspecialchars($delivery['customer_name']); ?></div></div>
            <div class="row"><div class="label">Address:</div><div><?php echo htmlspecialchars($delivery['address']); ?></div></div>
        </div>
        
        <div class="card">
            <h3>🚚 Delivery By</h3>
            <div class="row"><div class="label">Driver:</div><div><?php echo $delivery['driver_name'] ?: 'Not assigned'; ?></div></div>
            <div class="row"><div class="label">Vehicle:</div><div><?php echo $delivery['plate_number'] ?: 'Not assigned'; ?></div></div>
        </div>
        
        <div class="card">
            <a href="deliveries.php" class="btn btn-back">← Back to Deliveries</a>
            <?php if($delivery['status'] == 'delivered'): ?>
            <a href="receipt.php?id=<?php echo $delivery_id; ?>&download=1" class="btn">📄 Download Receipt (PDF)</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>