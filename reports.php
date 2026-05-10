<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$filter_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$stats_query = "SELECT 
    COUNT(*) as total_deliveries,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed,
    COALESCE(SUM(penalty_amount), 0) as total_penalties,
    SUM(CASE WHEN penalty_amount > 0 THEN 1 ELSE 0 END) as late_deliveries
    FROM deliveries 
    WHERE delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$penalty_query = mysqli_query($conn, "SELECT d.*, c.name as customer_name, u.username as driver_name 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    LEFT JOIN users u ON d.driver_id = u.id 
    WHERE d.penalty_amount > 0 
    AND d.delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    ORDER BY d.penalty_amount DESC");

if ($filter_type == 'daily') {
    $group_by = "DATE(delivered_at)";
    $period_label = "Day";
} elseif ($filter_type == 'weekly') {
    $group_by = "YEARWEEK(delivered_at)";
    $period_label = "Week";
} else {
    $group_by = "DATE_FORMAT(delivered_at, '%Y-%m')";
    $period_label = "Month";
}

$summary_query = mysqli_query($conn, "SELECT 
    $group_by as period,
    COUNT(*) as total,
    COALESCE(SUM(penalty_amount), 0) as penalties,
    COUNT(CASE WHEN penalty_amount > 0 THEN 1 END) as late_count
    FROM deliveries 
    WHERE delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    GROUP BY $group_by
    ORDER BY period DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - Unga Logistics</title>
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
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { color: #718096; margin-top: 5px; }
        .penalty-number { color: #e53e3e; }
        .section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f2f5; }
        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar input, .filter-bar select, .filter-bar button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .btn {
            padding: 8px 16px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-download {
            background: #e53e3e;
        }
        .btn-download:hover { background: #c53030; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-late { background: #e53e3e; color: white; }
        .badge-ontime { background: #48bb78; color: white; }
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
        <a href="reports.php" class="active">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>📊 Reports & Analytics</h2>
            <div class="header-buttons">
                <a href="download_report_pdf.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-download">📄 Download Report (PDF)</a>
            </div>
        </div>
        
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <select name="type">
                    <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo $filter_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                </select>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <span>to</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn">Apply Filter</button>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_deliveries'] ?? 0; ?></div>
                <div class="stat-label">Total Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number <?php echo ($stats['late_deliveries'] ?? 0) > 0 ? 'penalty-number' : ''; ?>">
                    <?php echo $stats['late_deliveries'] ?? 0; ?>
                </div>
                <div class="stat-label">Late Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number penalty-number">
                    KES <?php echo number_format($stats['total_penalties'] ?? 0); ?>
                </div>
                <div class="stat-label">Total Penalties</div>
            </div>
        </div>
        
        <div class="section">
            <h3>📈 <?php echo ucfirst($filter_type); ?> Summary</h3>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $period_label; ?></th>
                        <th>Deliveries</th>
                        <th>Late Deliveries</th>
                        <th>Penalties (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($summary_query) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($summary_query)): ?>
                        <tr>
                            <td><?php echo $row['period']; ?></td>
                            <td><?php echo $row['total']; ?></td>
                            <td>
                                <span class="badge <?php echo $row['late_count'] > 0 ? 'badge-late' : 'badge-ontime'; ?>">
                                    <?php echo $row['late_count']; ?>
                                </span>
                            </td>
                            <td class="penalty-number">KES <?php echo number_format($row['penalties']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">No data for selected period</td
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>⚠️ Late Delivery Penalties</h3>
            <table>
                <thead>
                    <tr>
                        <th>Delivery Code</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Delivered At</th>
                        <th>Deadline</th>
                        <th>Penalty (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($penalty_query) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($penalty_query)): ?>
                        <tr>
                            <td><?php echo $row['delivery_code']; ?></td>
                            <td><?php echo $row['customer_name']; ?></td>
                            <td><?php echo $row['driver_name']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['delivered_at'])); ?></td>
                            <td><?php echo $row['time_window_end'] ? date('H:i', strtotime($row['time_window_end'])) : 'N/A'; ?></td>
                            <td class="penalty-number">KES <?php echo number_format($row['penalty_amount']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No penalties recorded in this period</td
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>📝 Summary Note</h3>
            <p><strong>Report Period:</strong> <?php echo date('d/m/Y', strtotime($start_date)); ?> to <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
            <p><strong>Total Penalty Amount:</strong> KES <?php echo number_format($stats['total_penalties'] ?? 0); ?></p>
            <p><strong>On-Time Performance:</strong> 
                <?php 
                $on_time = ($stats['total_deliveries'] ?? 0) - ($stats['late_deliveries'] ?? 0);
                $percentage = ($stats['total_deliveries'] ?? 0) > 0 ? round(($on_time / $stats['total_deliveries']) * 100, 1) : 0;
                ?>
                <?php echo $percentage; ?>% (<?php echo $on_time; ?> out of <?php echo $stats['total_deliveries'] ?? 0; ?> deliveries on time)
            </p>
        </div>
    </div>
</body>
</html>