<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle single delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM driver_notifications WHERE id = $delete_id AND driver_id = $user_id");
    header('Location: all_notifications.php');
    exit();
}

// Handle delete all
if (isset($_GET['delete_all'])) {
    mysqli_query($conn, "DELETE FROM driver_notifications WHERE driver_id = $user_id");
    header('Location: all_notifications.php');
    exit();
}

// Get all notifications
$notifications = mysqli_query($conn, "SELECT * FROM driver_notifications WHERE driver_id = $user_id ORDER BY id DESC");
$notif_count = mysqli_num_rows($notifications);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Notifications - Unga Logistics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; }
        .header {
            background: #2d3748;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h2 { color: #d4af37; }
        .btn {
            padding: 8px 16px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-danger { background: #e53e3e; }
        .content { padding: 20px; max-width: 800px; margin: 0 auto; }
        .notification-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-link {
            text-decoration: none;
            color: #2d3748;
            flex: 1;
        }
        .notification-link strong { display: block; }
        .notification-time {
            font-size: 11px;
            color: #718096;
            margin-top: 5px;
        }
        .delete-btn {
            background: #e53e3e;
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
        }
        .delete-all {
            margin-bottom: 15px;
            text-align: right;
        }
        .empty {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .back-btn {
            margin-bottom: 15px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>📬 All Notifications</h2>
        <a href="driver.php" class="btn">← Back to Dashboard</a>
    </div>
    
    <div class="content">
        <div class="delete-all">
            <?php if($notif_count > 0): ?>
            <a href="?delete_all=1" class="btn btn-danger" onclick="return confirm('Delete ALL notifications?')">🗑️ Delete All</a>
            <?php endif; ?>
        </div>
        
        <div class="notification-list">
            <?php if($notif_count > 0): ?>
                <?php while($row = mysqli_fetch_assoc($notifications)): ?>
                <div class="notification-item">
                    <a href="driver_view.php?id=<?php echo $row['delivery_id']; ?>" class="notification-link">
                        <strong>📦 <?php echo htmlspecialchars($row['message']); ?></strong>
                        <div class="notification-time"><?php echo $row['created_at']; ?></div>
                    </a>
                    <a href="?delete_id=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Delete this notification?')">Delete</a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">No notifications found.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>