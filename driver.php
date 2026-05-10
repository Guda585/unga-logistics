<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$LATE_PENALTY_PER_HOUR = 500;

if (isset($_SESSION['return_message'])) {
    $return_message = $_SESSION['return_message'];
    unset($_SESSION['return_message']);
} else {
    $return_message = '';
}

if (isset($_POST['mark_notifications_read'])) {
    mysqli_query($conn, "UPDATE driver_notifications SET is_read = 1 WHERE driver_id = $user_id");
    header('Location: driver.php');
    exit();
}

// Delete single notification without confirmation
if (isset($_GET['delete_notif'])) {
    $notif_id = $_GET['delete_notif'];
    mysqli_query($conn, "DELETE FROM driver_notifications WHERE id = $notif_id AND driver_id = $user_id");
    header('Location: driver.php');
    exit();
}

$vehicle_query = mysqli_query($conn, "SELECT * FROM vehicles WHERE driver_id = $user_id");
$vehicle_data = mysqli_fetch_assoc($vehicle_query);

$deliveries = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address, c.phone, c.lat, c.lng, d.time_window_start, d.time_window_end 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    WHERE d.driver_id = $user_id
    ORDER BY d.id");

$notifications = mysqli_query($conn, "SELECT * FROM driver_notifications WHERE driver_id = $user_id AND is_read = 0 ORDER BY id DESC LIMIT 10");
$notif_count = mysqli_num_rows($notifications);
$total_unread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM driver_notifications WHERE driver_id = $user_id AND is_read = 0"))['count'];

if (isset($_POST['complete_delivery'])) {
    $delivery_id = $_POST['delivery_id'];
    $customer_signature = $_POST['customer_signature_data'];
    $driver_signature = $_POST['driver_signature_data'];
    $customer_name = $_POST['customer_name'];
    
    if ($customer_signature && $driver_signature) {
        $signature_dir = 'signatures/';
        if (!file_exists($signature_dir)) {
            mkdir($signature_dir, 0777, true);
        }
        
        $customer_sig_file = $signature_dir . 'customer_sig_' . $delivery_id . '_' . time() . '.png';
        $customer_sig_data = str_replace('data:image/png;base64,', '', $customer_signature);
        $customer_sig_data = str_replace(' ', '+', $customer_sig_data);
        file_put_contents($customer_sig_file, base64_decode($customer_sig_data));
        
        $driver_sig_file = $signature_dir . 'driver_sig_' . $delivery_id . '_' . time() . '.png';
        $driver_sig_data = str_replace('data:image/png;base64,', '', $driver_signature);
        $driver_sig_data = str_replace(' ', '+', $driver_sig_data);
        file_put_contents($driver_sig_file, base64_decode($driver_sig_data));
        
        $delivery_query = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address 
            FROM deliveries d 
            LEFT JOIN customers c ON d.customer_id = c.id 
            WHERE d.id = $delivery_id");
        $delivery_info = mysqli_fetch_assoc($delivery_query);
        $time_window_end = $delivery_info['time_window_end'];
        
        $penalty = 0;
        $current_time = date('H:i:s');
        
        if ($time_window_end && $current_time > $time_window_end) {
            $deadline_h = (int)substr($time_window_end, 0, 2);
            $deadline_m = (int)substr($time_window_end, 3, 2);
            $current_h = (int)substr($current_time, 0, 2);
            $current_m = (int)substr($current_time, 3, 2);
            
            $deadline_total = ($deadline_h * 60) + $deadline_m;
            $current_total = ($current_h * 60) + $current_m;
            
            $minutes_late = $current_total - $deadline_total;
            
            if ($minutes_late > 0) {
                $hours_late = ceil($minutes_late / 60);
                $penalty = $hours_late * $LATE_PENALTY_PER_HOUR;
            }
        }
        
        $update = "UPDATE deliveries SET 
            status = 'delivered', 
            delivered_at = NOW(), 
            signature_path = '$customer_sig_file',
            driver_signature_path = '$driver_sig_file',
            penalty_amount = $penalty
            WHERE id = $delivery_id";
        mysqli_query($conn, $update);
        
        $message_text = "✅ Delivery {$delivery_info['delivery_code']} completed by $username for {$delivery_info['customer_name']}" . ($penalty > 0 ? " (Late: KES $penalty)" : "");
        mysqli_query($conn, "INSERT INTO notifications (delivery_id, message, status) VALUES ($delivery_id, '$message_text', 'unread')");
        
        header('Location: receipt.php?id=' . $delivery_id . '&download=1');
        exit();
    }
}

if (isset($_POST['update_status'])) {
    $delivery_id = $_POST['delivery_id'];
    $status = $_POST['status'];
    mysqli_query($conn, "UPDATE deliveries SET status = '$status' WHERE id = $delivery_id");
    
    if ($status == 'in_transit') {
        $delivery_query = mysqli_query($conn, "SELECT vehicle_id FROM deliveries WHERE id = $delivery_id");
        $delivery_data = mysqli_fetch_assoc($delivery_query);
        if ($delivery_data && $delivery_data['vehicle_id']) {
            mysqli_query($conn, "UPDATE vehicles SET status = 'on_route' WHERE id = {$delivery_data['vehicle_id']}");
        }
    }
    
    header('Location: driver.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Driver Dashboard - Unga Holdings</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#d4af37">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; position: relative; overflow-x: hidden; }
        .video-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2; object-fit: cover; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.2); z-index: -1; }
        .header { background: rgba(255, 255, 255, 0.95); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); flex-wrap: wrap; gap: 10px; }
        .logo { font-size: 24px; font-weight: bold; color: #d4af37; }
        .content { padding: 2rem; max-width: 1200px; margin: 0 auto; padding-bottom: 5rem; }
        .welcome-card { background: linear-gradient(135deg, rgba(102, 126, 234, 0.85), rgba(118, 75, 162, 0.85)); padding: 2rem; border-radius: 16px; color: white; margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .vehicle-card, .delivery-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(5px); border-radius: 12px; margin-bottom: 1rem; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .delivery-card { margin-bottom: 1rem; padding: 1rem; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #ed8936; color: white; }
        .status-assigned { background: #4299e1; color: white; }
        .status-in_transit { background: #9f7aea; color: white; }
        .status-delivered { background: #48bb78; color: white; }
        .btn { padding: 8px 16px; background: #4299e1; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-success { background: #48bb78; }
        .btn-warning { background: #ed8936; }
        .btn-nav { background: #9f7aea; }
        .btn-depot { background: #48bb78; }
        .btn-issue { background: #e53e3e; }
        .btn-issue:hover { background: #c53030; }
        .logout-btn { background: #e53e3e; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255, 255, 255, 0.95); padding: 1rem; border-radius: 12px; text-align: center; backdrop-filter: blur(5px); }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .notification-banner { background: rgba(235, 248, 255, 0.95); border-left: 4px solid #4299e1; padding: 15px; margin-bottom: 20px; border-radius: 8px; backdrop-filter: blur(5px); }
        .notification-link { text-decoration: none; color: #2d3748; display: block; }
        .gps-status { background: #48bb78; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .install-btn { position: fixed; bottom: 20px; right: 20px; background: #4299e1; color: white; border: none; border-radius: 50px; padding: 12px 20px; display: none; cursor: pointer; z-index: 1000; }
        .no-deliveries { text-align: center; padding: 40px; background: rgba(255,255,255,0.95); border-radius: 12px; color: #718096; }
        h3 { margin-bottom: 1rem; color: #2d3748; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background-color: white; margin: 5% auto; padding: 25px; border-radius: 16px; width: 90%; max-width: 500px; max-height: 85%; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .receipt-header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 15px; }
        .receipt-header h2 { color: #1a1a2e; font-size: 20px; }
        .receipt-header h3 { color: #d4af37; margin-top: 5px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 5px 0; border-bottom: 1px solid #eee; }
        .signature-section { margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; }
        .signature-area { margin: 15px 0; }
        .signature-title { font-weight: bold; margin-bottom: 8px; color: #2d3748; }
        .signature-canvas { border: 2px solid #ddd; border-radius: 8px; background: white; width: 100%; height: 150px; touch-action: none; display: block; }
        .btn-clear { background: #e53e3e; color: white; border: none; border-radius: 6px; padding: 5px 12px; margin-top: 5px; cursor: pointer; font-size: 12px; }
        .receipt-actions { text-align: center; margin-top: 15px; }
        .bottom-space { height: 80px; }
        .delete-notif { background: #e53e3e; color: white; padding: 4px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; margin-left: 10px; }
        .delete-notif:hover { background: #c53030; }
        .notification-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 8px; background: rgba(255,255,255,0.5); border-radius: 8px; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .message-success { background: #c6f6d5; color: #22543d; }
        .return-btn-container { margin-top: 30px; text-align: center; padding: 20px; background: rgba(255,255,255,0.9); border-radius: 12px; }
        .btn-return { background: #48bb78; padding: 12px 24px; font-size: 16px; }
        .btn-return:hover { background: #38a169; }
        .button-group { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        
        .success-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }
        .success-modal-content {
            background: linear-gradient(135deg, #48bb78, #38a169);
            margin: auto;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.5s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .success-modal-content .success-icon { font-size: 80px; margin-bottom: 20px; }
        .success-modal-content h2 { color: white; font-size: 28px; margin-bottom: 15px; }
        .success-modal-content p { color: rgba(255,255,255,0.95); font-size: 18px; margin-bottom: 10px; }
        .success-modal-content .delivery-code { font-weight: bold; font-size: 24px; background: rgba(255,255,255,0.2); display: inline-block; padding: 5px 15px; border-radius: 30px; margin: 15px 0; }
        .success-modal-content .penalty-info { background: rgba(0,0,0,0.2); padding: 10px; border-radius: 10px; margin: 15px 0; }
        .success-modal-content .btn-ok { background: white; color: #48bb78; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 30px; cursor: pointer; margin-top: 20px; transition: transform 0.2s; }
        .success-modal-content .btn-ok:hover { transform: scale(1.05); }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .delivery-card { padding: 12px; }
            .btn { padding: 6px 12px; font-size: 12px; }
            .signature-canvas { height: 120px; }
            .success-modal-content { padding: 25px; }
            .success-modal-content h2 { font-size: 24px; }
            .success-modal-content .delivery-code { font-size: 20px; }
        }
    </style>
</head>
<body>
    <video class="video-background" autoplay muted loop playsinline>
        <source src="videos/truck-bg.mp4" type="video/mp4">
    </video>
    <div class="overlay"></div>

    <div class="header">
        <div class="logo">Unga Logistics</div>
        <div>
            <span>Driver: <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="btn logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="content">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
            <p>Complete deliveries and get signatures. <span class="gps-status" id="gpsStatus">📍 GPS: Starting...</span></p>
        </div>
        
        <?php if($return_message): ?>
            <div class="message message-success"><?php echo $return_message; ?></div>
        <?php endif; ?>
        
        <?php if($notif_count > 0): ?>
        <div class="notification-banner">
            <strong>📬 New Notifications (<?php echo $notif_count; ?>)</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <?php 
                mysqli_data_seek($notifications, 0);
                while($notif = mysqli_fetch_assoc($notifications)): 
                ?>
                <li class="notification-item">
                    <a href="driver_view.php?id=<?php echo $notif['delivery_id']; ?>" class="notification-link" style="flex: 1;">
                        📦 <?php echo htmlspecialchars($notif['message']); ?>
                        <small style="color:#718096; display: block;"><?php echo $notif['created_at']; ?></small>
                    </a>
                    <a href="?delete_notif=<?php echo $notif['id']; ?>" class="delete-notif">Delete</a>
                </li>
                <?php endwhile; ?>
            </ul>
            <form method="POST" style="margin-top: 10px;">
                <input type="hidden" name="mark_notifications_read" value="1">
                <button type="submit" class="btn" style="background:#4299e1; padding:5px 10px; font-size:12px;">✓ Mark all as read</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if($vehicle_data): ?>
        <div class="vehicle-card">
            <h3>🚚 Your Vehicle</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div><strong>Plate:</strong> <?php echo $vehicle_data['plate_number']; ?></div>
                <div><strong>Type:</strong> <?php echo $vehicle_data['vehicle_type']; ?></div>
                <div><strong>Capacity:</strong> <?php echo number_format($vehicle_data['capacity_tonnes']); ?> tonnes</div>
                <div><strong>Status:</strong> <?php echo ucfirst($vehicle_data['status']); ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="vehicle-card">
            <h3>🚚 Your Vehicle</h3>
            <p>No vehicle assigned yet. Please contact admin.</p>
        </div>
        <?php endif; ?>
        
        <?php
        $total = mysqli_num_rows($deliveries);
        $completed = 0;
        $pending = 0;
        $in_transit = 0;
        $deliveries_array = [];
        while($row = mysqli_fetch_assoc($deliveries)) {
            $deliveries_array[] = $row;
            if($row['status'] == 'delivered') $completed++;
            elseif($row['status'] == 'in_transit') $in_transit++;
            else $pending++;
        }
        ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><p>Total</p></div>
            <div class="stat-card"><div class="stat-number" style="color:#48bb78;"><?php echo $completed; ?></div><p>Completed</p></div>
            <div class="stat-card"><div class="stat-number" style="color:#ed8936;"><?php echo $pending; ?></div><p>Pending</p></div>
            <div class="stat-card"><div class="stat-number" style="color:#9f7aea;"><?php echo $in_transit; ?></div><p>In Transit</p></div>
        </div>
        
        <h3>📦 Your Deliveries</h3>
        
        <?php if($total > 0): ?>
            <?php foreach($deliveries_array as $delivery): ?>
            <div class="delivery-card">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <h4><?php echo $delivery['delivery_code']; ?> - <?php echo $delivery['customer_name']; ?></h4>
                        <p>📍 <?php echo $delivery['address']; ?></p>
                        <p>⚖️ <?php echo number_format($delivery['weight_tonnes']); ?> tonnes</p>
                        <?php if($delivery['time_window_end']): ?>
                        <p>⏰ Must deliver before: <?php echo date('H:i', strtotime($delivery['time_window_end'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="status status-<?php echo $delivery['status']; ?>"><?php echo ucfirst($delivery['status']); ?></span>
                    </div>
                </div>
                
                <?php if($delivery['status'] == 'delivered'): ?>
                <div style="margin-top: 15px;">
                    <a href="receipt.php?id=<?php echo $delivery['id']; ?>&download=1" class="btn" style="background:#4299e1;">📄 Download Receipt (PDF)</a>
                    <?php if($delivery['penalty_amount'] > 0): ?>
                    <span style="background:#e53e3e; color:white; padding:4px 12px; border-radius:20px; margin-left:10px;">⚠️ Late Penalty: KES <?php echo number_format($delivery['penalty_amount']); ?></span>
                    <?php endif; ?>
                    <span style="color:#48bb78; margin-left:10px;">✓ Delivered <?php echo date('d M H:i', strtotime($delivery['delivered_at'])); ?></span>
                </div>
                <?php else: ?>
                <div style="margin-top: 15px;">
                    <?php if($delivery['status'] == 'pending' || $delivery['status'] == 'assigned'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                        <input type="hidden" name="status" value="in_transit">
                        <button type="submit" name="update_status" class="btn btn-warning">🚚 Start Delivery</button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if($delivery['status'] == 'in_transit'): ?>
                    <a href="driver_navigate.php?id=<?php echo $delivery['id']; ?>" class="btn btn-nav">🗺️ Navigate</a>
                    <button class="btn btn-success" onclick="openReceiptModal(<?php echo $delivery['id']; ?>, '<?php echo addslashes($delivery['delivery_code']); ?>', '<?php echo addslashes($delivery['customer_name']); ?>', '<?php echo addslashes($delivery['address']); ?>', '<?php echo $delivery['weight_tonnes']; ?>')">
                        ✍️ Complete & Get Signatures
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-deliveries">
                <p>📭 No deliveries assigned yet.</p>
                <p style="margin-top: 10px; font-size: 14px;">Check back later or contact your admin.</p>
            </div>
        <?php endif; ?>
        
        <!-- Return to Depot Section -->
        <div class="return-btn-container">
            <div class="button-group">
                <a href="driver_navigate.php?depot=1" class="btn btn-nav" style="background: #48bb78; padding: 12px 24px; font-size: 16px;">🗺️ Navigate to Depot</a>
                <button class="btn btn-issue" onclick="openIssueModal()" style="background: #e53e3e; padding: 12px 24px; font-size: 16px;">⚠️ Report Issue</button>
                <form method="POST" action="return_to_depot.php" style="display: inline;">
                    <button type="submit" name="return_to_depot" class="btn btn-depot" style="background: #4299e1; padding: 12px 24px; font-size: 16px;" onclick="return confirm('Mark as returned to depot? This will update your vehicle status to Available.')">
                        ✅ Mark as Returned
                    </button>
                </form>
            </div>
            <p style="color: #718096; font-size: 12px; margin-top: 8px;">Use 'Navigate to Depot' for directions | 'Report Issue' for problems | 'Mark as Returned' after arriving</p>
        </div>
        
        <div class="bottom-space"></div>
    </div>
    
    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <div class="receipt-header">
                <h2>UNGA HOLDINGS LIMITED</h2>
                <p>Logistics Management System</p>
                <h3>DELIVERY RECEIPT</h3>
            </div>
            <div id="receiptDetails"></div>
            
            <div class="signature-section">
                <div class="signature-area">
                    <div class="signature-title">👤 DRIVER SIGNATURE (Sign below):</div>
                    <canvas id="driverSignatureCanvas" class="signature-canvas" width="400" height="150"></canvas>
                    <button class="btn-clear" onclick="clearDriverSignature()">Clear Signature</button>
                </div>
                
                <div class="signature-area">
                    <div class="signature-title">🖊️ CUSTOMER SIGNATURE (Sign below):</div>
                    <canvas id="customerSignatureCanvas" class="signature-canvas" width="400" height="150"></canvas>
                    <button class="btn-clear" onclick="clearCustomerSignature()">Clear Signature</button>
                </div>
            </div>
            <div class="receipt-actions">
                <button class="btn btn-success" onclick="saveBothSignaturesAndComplete()">✅ Submit Delivery</button>
                <button class="btn" onclick="closeModal()">Cancel</button>
            </div>
            <input type="hidden" id="currentDeliveryId" value="">
            <input type="hidden" id="currentDeliveryCode" value="">
            <input type="hidden" id="currentCustomerName" value="">
            <input type="hidden" id="currentAddress" value="">
            <input type="hidden" id="currentWeight" value="">
        </div>
    </div>
    
    <!-- Issue Modal -->
    <div id="issueModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="receipt-header">
                <h2>⚠️ Report Issue</h2>
                <p>Report a problem to admin</p>
            </div>
            <form method="POST" action="report_issue.php">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Issue Type</label>
                    <select name="issue_type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" required>
                        <option value="mechanical">🚛 Mechanical Issue (Breakdown)</option>
                        <option value="traffic">🚦 Heavy Traffic</option>
                        <option value="accident">⚠️ Accident</option>
                        <option value="weather">🌧️ Bad Weather</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Description</label>
                    <textarea name="description" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Describe the issue in detail..." required></textarea>
                </div>
                <div class="receipt-actions">
                    <button type="submit" class="btn btn-success">📤 Submit Report</button>
                    <button type="button" class="btn" onclick="closeIssueModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon">🎉</div>
            <h2>Thank You!</h2>
            <p>Your delivery has been completed successfully.</p>
            <div class="delivery-code" id="successDeliveryCode">DEL-XXX</div>
            <div id="penaltyDisplay" style="display: none;" class="penalty-info">
                ⚠️ Late Penalty: KES <span id="penaltyAmount">0</span>
            </div>
            <button class="btn-ok" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>
    
    <button id="installBtn" class="install-btn">📲 Install App</button>

    <!-- Notification Sound -->
    <audio id="notificationSound" preload="auto">
        <source src="/UNGA-LOGISTICS/sounds/notification.wav" type="audio/wav">
    </audio>

    <script>
        let deferredPrompt;
        const installBtn = document.getElementById('installBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'block';
        });

        installBtn.addEventListener('click', () => {
            installBtn.style.display = 'none';
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    }
                    deferredPrompt = null;
                });
            }
        });
        
        // Notification Sound Feature
        var sound = document.getElementById('notificationSound');
        var lastUnreadCount = <?php echo $total_unread; ?>;
        
        function playNotificationSound() {
            sound.play().catch(function(error) {
                console.log('Audio play failed:', error);
            });
        }
        
        function checkDriverNotifications() {
            fetch('check_driver_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > lastUnreadCount) {
                        playNotificationSound();
                        // Reload page to show new notifications
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                    lastUnreadCount = data.unread_count;
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Check every 15 seconds
        setInterval(checkDriverNotifications, 15000);
        
        function updateLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        let lat = position.coords.latitude;
                        let lng = position.coords.longitude;
                        document.getElementById('gpsStatus').innerHTML = '📍 GPS: Active';
                        document.getElementById('gpsStatus').style.background = '#48bb78';
                        
                        fetch('update_location.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'lat=' + lat + '&lng=' + lng
                        });
                    },
                    function(error) {
                        document.getElementById('gpsStatus').innerHTML = '📍 GPS: Waiting for signal';
                        document.getElementById('gpsStatus').style.background = '#ed8936';
                    }
                );
            } else {
                document.getElementById('gpsStatus').innerHTML = '📍 GPS: Not supported';
            }
        }
        
        setInterval(updateLocation, 30000);
        updateLocation();
        
        let driverCanvas = document.getElementById('driverSignatureCanvas');
        let driverCtx = driverCanvas.getContext('2d');
        let driverDrawing = false;
        
        let customerCanvas = document.getElementById('customerSignatureCanvas');
        let customerCtx = customerCanvas.getContext('2d');
        let customerDrawing = false;
        
        function resizeCanvas(canvas) {
            const container = canvas.parentElement;
            const width = container.clientWidth - 20;
            canvas.width = width;
            canvas.height = 150;
        }
        
        function initCanvas(ctx, canvas) {
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }
        
        function getCoordinates(e, canvas) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            
            let clientX, clientY;
            
            if (e.touches) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }
            
            let x = (clientX - rect.left) * scaleX;
            let y = (clientY - rect.top) * scaleY;
            
            x = Math.max(0, Math.min(canvas.width, x));
            y = Math.max(0, Math.min(canvas.height, y));
            
            return { x, y };
        }
        
        driverCanvas.addEventListener('mousedown', (e) => {
            e.preventDefault();
            driverDrawing = true;
            const pos = getCoordinates(e, driverCanvas);
            driverCtx.beginPath();
            driverCtx.moveTo(pos.x, pos.y);
        });
        
        driverCanvas.addEventListener('mousemove', (e) => {
            if (!driverDrawing) return;
            e.preventDefault();
            const pos = getCoordinates(e, driverCanvas);
            driverCtx.lineTo(pos.x, pos.y);
            driverCtx.stroke();
            driverCtx.beginPath();
            driverCtx.moveTo(pos.x, pos.y);
        });
        
        driverCanvas.addEventListener('mouseup', () => {
            driverDrawing = false;
            driverCtx.beginPath();
        });
        
        driverCanvas.addEventListener('mouseleave', () => {
            driverDrawing = false;
            driverCtx.beginPath();
        });
        
        driverCanvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            driverDrawing = true;
            const pos = getCoordinates(e, driverCanvas);
            driverCtx.beginPath();
            driverCtx.moveTo(pos.x, pos.y);
        });
        
        driverCanvas.addEventListener('touchmove', (e) => {
            if (!driverDrawing) return;
            e.preventDefault();
            const pos = getCoordinates(e, driverCanvas);
            driverCtx.lineTo(pos.x, pos.y);
            driverCtx.stroke();
            driverCtx.beginPath();
            driverCtx.moveTo(pos.x, pos.y);
        });
        
        driverCanvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            driverDrawing = false;
            driverCtx.beginPath();
        });
        
        driverCanvas.addEventListener('touchcancel', (e) => {
            e.preventDefault();
            driverDrawing = false;
            driverCtx.beginPath();
        });
        
        customerCanvas.addEventListener('mousedown', (e) => {
            e.preventDefault();
            customerDrawing = true;
            const pos = getCoordinates(e, customerCanvas);
            customerCtx.beginPath();
            customerCtx.moveTo(pos.x, pos.y);
        });
        
        customerCanvas.addEventListener('mousemove', (e) => {
            if (!customerDrawing) return;
            e.preventDefault();
            const pos = getCoordinates(e, customerCanvas);
            customerCtx.lineTo(pos.x, pos.y);
            customerCtx.stroke();
            customerCtx.beginPath();
            customerCtx.moveTo(pos.x, pos.y);
        });
        
        customerCanvas.addEventListener('mouseup', () => {
            customerDrawing = false;
            customerCtx.beginPath();
        });
        
        customerCanvas.addEventListener('mouseleave', () => {
            customerDrawing = false;
            customerCtx.beginPath();
        });
        
        customerCanvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            customerDrawing = true;
            const pos = getCoordinates(e, customerCanvas);
            customerCtx.beginPath();
            customerCtx.moveTo(pos.x, pos.y);
        });
        
        customerCanvas.addEventListener('touchmove', (e) => {
            if (!customerDrawing) return;
            e.preventDefault();
            const pos = getCoordinates(e, customerCanvas);
            customerCtx.lineTo(pos.x, pos.y);
            customerCtx.stroke();
            customerCtx.beginPath();
            customerCtx.moveTo(pos.x, pos.y);
        });
        
        customerCanvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            customerDrawing = false;
            customerCtx.beginPath();
        });
        
        customerCanvas.addEventListener('touchcancel', (e) => {
            e.preventDefault();
            customerDrawing = false;
            customerCtx.beginPath();
        });
        
        function clearDriverSignature() {
            driverCtx.clearRect(0, 0, driverCanvas.width, driverCanvas.height);
            driverCtx.fillStyle = '#fff';
            driverCtx.fillRect(0, 0, driverCanvas.width, driverCanvas.height);
        }
        
        function clearCustomerSignature() {
            customerCtx.clearRect(0, 0, customerCanvas.width, customerCanvas.height);
            customerCtx.fillStyle = '#fff';
            customerCtx.fillRect(0, 0, customerCanvas.width, customerCanvas.height);
        }
        
        function openReceiptModal(id, code, customer, address, weight) {
            document.getElementById('currentDeliveryId').value = id;
            document.getElementById('currentDeliveryCode').value = code;
            document.getElementById('currentCustomerName').value = customer;
            document.getElementById('currentAddress').value = address;
            document.getElementById('currentWeight').value = weight;
            
            let html = `
                <div class="receipt-row"><strong>Receipt No:</strong> <span>${code}</span></div>
                <div class="receipt-row"><strong>Date:</strong> <span>${new Date().toLocaleString()}</span></div>
                <div class="receipt-row"><strong>Customer:</strong> <span>${customer}</span></div>
                <div class="receipt-row"><strong>Address:</strong> <span>${address}</span></div>
                <div class="receipt-row"><strong>Goods:</strong> <span>Maize Flour</span></div>
                <div class="receipt-row"><strong>Weight:</strong> <span>${parseFloat(weight).toLocaleString()} tonnes</span></div>
                <div class="receipt-row"><strong>Driver:</strong> <span><?php echo $username; ?></span></div>
                <?php if($vehicle_data): ?>
                <div class="receipt-row"><strong>Vehicle:</strong> <span><?php echo $vehicle_data['plate_number']; ?></span></div>
                <?php endif; ?>
            `;
            document.getElementById('receiptDetails').innerHTML = html;
            
            resizeCanvas(driverCanvas);
            resizeCanvas(customerCanvas);
            initCanvas(driverCtx, driverCanvas);
            initCanvas(customerCtx, customerCanvas);
            
            document.getElementById('receiptModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('receiptModal').style.display = 'none';
        }
        
        function openIssueModal() {
            document.getElementById('issueModal').style.display = 'block';
        }
        
        function closeIssueModal() {
            document.getElementById('issueModal').style.display = 'none';
        }
        
        function showSuccessModal(deliveryCode, penalty) {
            document.getElementById('successDeliveryCode').innerHTML = deliveryCode;
            if (penalty > 0) {
                document.getElementById('penaltyAmount').innerHTML = penalty;
                document.getElementById('penaltyDisplay').style.display = 'block';
            } else {
                document.getElementById('penaltyDisplay').style.display = 'none';
            }
            document.getElementById('successModal').style.display = 'flex';
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            window.location.href = 'driver.php';
        }
        
        function saveBothSignaturesAndComplete() {
            let driverSignature = driverCanvas.toDataURL('image/png');
            let customerSignature = customerCanvas.toDataURL('image/png');
            let deliveryId = document.getElementById('currentDeliveryId').value;
            let deliveryCode = document.getElementById('currentDeliveryCode').value;
            let customerName = document.getElementById('currentCustomerName').value;
            
            let formData = new FormData();
            formData.append('complete_delivery', '1');
            formData.append('delivery_id', deliveryId);
            formData.append('driver_signature_data', driverSignature);
            formData.append('customer_signature_data', customerSignature);
            formData.append('customer_name', customerName);
            
            fetch('complete_delivery_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    window.open('receipt.php?id=' + deliveryId + '&download=1', '_blank');
                    showSuccessModal(deliveryCode, data.penalty);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(function(registration) {
                    console.log('Service Worker registered successfully');
                })
                .catch(function(error) {
                    console.log('Service Worker registration failed:', error);
                });
        }
        
        window.addEventListener('resize', function() {
            if (document.getElementById('receiptModal').style.display === 'block') {
                resizeCanvas(driverCanvas);
                resizeCanvas(customerCanvas);
                initCanvas(driverCtx, driverCanvas);
                initCanvas(customerCtx, customerCanvas);
            }
        });
    </script>
</body>
</html>