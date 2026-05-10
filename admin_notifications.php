<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET status = 'read' WHERE status = 'unread'");
    header('Location: admin_notifications.php');
    exit();
}

// Delete single notification - NO CONFIRMATION
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM notifications WHERE id = $id");
    header('Location: admin_notifications.php');
    exit();
}

// Mark single as read - NO CONFIRMATION
if (isset($_GET['read'])) {
    $id = $_GET['read'];
    mysqli_query($conn, "UPDATE notifications SET status = 'read' WHERE id = $id");
    header('Location: admin_notifications.php');
    exit();
}

// Get all notifications
$notifications = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC");
$unread_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE status = 'unread'");
$unread = mysqli_fetch_assoc($unread_result);
$unread_count = $unread['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications - Unga Logistics</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            display: inline-block;
            font-size: 13px;
        }
        .btn-danger { background: #e53e3e; }
        .btn-success { background: #48bb78; }
        .badge {
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: 5px;
        }
        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .notification-card.unread {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
        }
        .notification-content {
            flex: 1;
        }
        .notification-message {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .notification-time {
            font-size: 11px;
            color: #718096;
        }
        .notification-actions {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #718096;
        }
        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .sidebar { display: none; }
            .notification-card { flex-direction: column; align-items: flex-start; gap: 10px; }
            .notification-actions { align-self: flex-end; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php">Vehicles</a>
        <a href="deliveries.php">Deliveries</a>
        <a href="drivers.php">Drivers</a>
        <a href="ga_optimize.php">GA Optimization</a>
        <a href="admin_notifications.php" class="active">Notifications 
            <?php if($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>🔔 Notifications</h2>
            <div>
                <?php if($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-success">✓ Mark All as Read</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(mysqli_num_rows($notifications) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($notifications)): ?>
                <div class="notification-card <?php echo $row['status'] == 'unread' ? 'unread' : ''; ?>">
                    <div class="notification-content">
                        <div class="notification-message">
                            <?php echo htmlspecialchars($row['message']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if($row['status'] == 'unread'): ?>
                            <a href="?read=<?php echo $row['id']; ?>" class="btn btn-sm">Mark Read</a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                📭 No notifications yet. When drivers complete deliveries or report issues, they will appear here.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Notification Sound -->
    <audio id="notificationSound" preload="auto">
        <source src="/UNGA-LOGISTICS/sounds/notification.wav" type="audio/wav">
    </audio>
    
    <script>
    var sound = document.getElementById('notificationSound');
    var lastUnreadCount = <?php echo $unread_count; ?>;
    
    function playNotificationSound() {
        sound.play().catch(function(error) {
            console.log('Audio play failed:', error);
        });
    }
    
    function checkNewNotifications() {
        fetch('check_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread_count > lastUnreadCount) {
                    playNotificationSound();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
                lastUnreadCount = data.unread_count;
                
                var badge = document.querySelector('.sidebar .badge');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    setInterval(checkNewNotifications, 15000);
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            checkNewNotifications();
        }
    });
    </script>
</body>
</html>