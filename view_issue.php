<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$issue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$issue_query = mysqli_query($conn, "SELECT * FROM driver_issues WHERE id = $issue_id");
$issue = mysqli_fetch_assoc($issue_query);

if (!$issue) {
    header('Location: admin_issues.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Issue - Unga Logistics</title>
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
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .issue-detail {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .issue-label {
            font-weight: bold;
            width: 120px;
            display: inline-block;
            color: #4a5568;
        }
        .issue-value {
            display: inline-block;
            color: #2d3748;
        }
        .btn-back {
            background: #4299e1;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
            .sidebar { display: none; }
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
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php" class="active">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>Issue Details</h2>
        </div>
        
        <div class="card">
            <div class="issue-detail">
                <span class="issue-label">Issue ID:</span>
                <span class="issue-value">#<?php echo $issue['id']; ?></span>
            </div>
            <div class="issue-detail">
                <span class="issue-label">Driver:</span>
                <span class="issue-value"><?php echo htmlspecialchars($issue['driver_name']); ?></span>
            </div>
            <div class="issue-detail">
                <span class="issue-label">Issue Type:</span>
                <span class="issue-value"><?php echo ucfirst(htmlspecialchars($issue['issue_type'])); ?></span>
            </div>
            <div class="issue-detail">
                <span class="issue-label">Description:</span>
                <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
            </div>
            <div class="issue-detail">
                <span class="issue-label">Status:</span>
                <span class="issue-value"><?php echo ucfirst($issue['status'] ?? 'Pending'); ?></span>
            </div>
            <div class="issue-detail">
                <span class="issue-label">Reported:</span>
                <span class="issue-value"><?php echo date('d/m/Y H:i', strtotime($issue['created_at'])); ?></span>
            </div>
            
            <a href="admin_issues.php" class="btn-back">← Back to Issues</a>
        </div>
    </div>
</body>
</html>
