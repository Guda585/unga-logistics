<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

if (isset($_GET['resolve'])) {
    $id = intval($_GET['resolve']);
    mysqli_query($conn, "UPDATE driver_issues SET status = 'resolved' WHERE id = $id");
    header('Location: admin_issues.php');
    exit();
}

$issues = mysqli_query($conn, "SELECT i.*, u.username as driver_name 
    FROM driver_issues i
    LEFT JOIN users u ON i.driver_id = u.id
    ORDER BY i.created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Driver Issues - Admin</title>
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
        .sidebar a:hover { background: #4a5568; }
        .content { margin-left: 250px; padding: 2rem; }
        .header {
            background: white;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .status-pending { background: #ed8936; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 12px; }
        .status-resolved { background: #48bb78; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 12px; }
        .btn { padding: 6px 12px; background: #48bb78; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; display: inline-block; }
        .issue-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .type-mechanical { background: #e53e3e; color: white; }
        .type-traffic { background: #ed8936; color: white; }
        .type-accident { background: #e53e3e; color: white; }
        .type-weather { background: #4299e1; color: white; }
        .type-other { background: #718096; color: white; }
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
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>⚠️ Driver Issue Reports</h2>
        </div>
        
        <?php if(mysqli_num_rows($issues) == 0): ?>
            <div class="card" style="text-align: center;">No issues reported yet.</div>
        <?php else: ?>
            <?php while($row = mysqli_fetch_assoc($issues)): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span class="issue-type type-<?php echo $row['issue_type']; ?>"><?php echo ucfirst($row['issue_type']); ?></span>
                        <span class="status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                        <div style="margin-top: 10px;"><strong>Driver:</strong> <?php echo htmlspecialchars($row['driver_name']); ?></div>
                        <div style="margin-top: 5px;"><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($row['description'])); ?></div>
                        <div style="margin-top: 5px; font-size: 11px; color: #718096;">Reported: <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></div>
                    </div>
                    <div>
                        <?php if($row['status'] == 'pending'): ?>
                        <a href="?resolve=<?php echo $row['id']; ?>" class="btn" onclick="return confirm('Mark as resolved?')">✓ Mark Resolved</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</body>
</html>