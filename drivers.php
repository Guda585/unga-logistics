<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Handle add driver
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_driver'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = md5($_POST['password']);
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email already exists!";
    } else {
        mysqli_query($conn, "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password', 'driver')");
        $success = "Driver added successfully!";
        header('Location: drivers.php');
        exit();
    }
}

// Handle delete driver
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "UPDATE vehicles SET driver_id = NULL WHERE driver_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id AND role = 'driver'");
    header('Location: drivers.php');
    exit();
}

// Get all drivers
$drivers = mysqli_query($conn, "SELECT u.*, COUNT(d.id) as delivery_count 
    FROM users u 
    LEFT JOIN deliveries d ON u.id = d.driver_id 
    WHERE u.role = 'driver' 
    GROUP BY u.id 
    ORDER BY u.id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Drivers - Unga Logistics</title>
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
        }
        .btn-success { background: #48bb78; }
        .btn-danger { background: #e53e3e; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f2f5; }
        .form-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }
        .form-card input {
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        .form-group { margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .message { padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .message-success { background: #c6f6d5; color: #22543d; }
        .message-error { background: #fed7d7; color: #742a2a; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            background: #48bb78;
            color: white;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Unga Logistics</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="vehicles.php">Vehicles</a>
        <a href="deliveries.php">Deliveries</a>
        <a href="drivers.php" class="active">Drivers</a>
        <a href="ga_optimize.php">GA Optimization</a>
        <a href="admin_notifications.php">Notifications</a>
        <a href="reports.php">Reports</a>
        <a href="admin_issues.php">Issues</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <div class="content">
        <div class="header">
            <h2>Driver Management</h2>
            <button onclick="document.getElementById('addForm').style.display='block'" class="btn btn-success">+ Add New Driver</button>
        </div>
        
        <div id="addForm" class="form-card">
            <h3>Add New Driver</h3>
            <?php if(isset($error)): ?>
                <div class="message message-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="add_driver" class="btn btn-success">Save Driver</button>
                <button type="button" onclick="document.getElementById('addForm').style.display='none'" class="btn btn-danger">Cancel</button>
            </form>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Deliveries</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $display_id = 1;
                while($row = mysqli_fetch_assoc($drivers)): 
                ?>
                <tr>
                    <td style="white-space: nowrap;"><?php echo $display_id; ?></td>
                    <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['email']); ?></td>
                    <td style="white-space: nowrap;"><span class="badge"><?php echo $row['delivery_count']; ?></span></td>
                    <td style="white-space: nowrap;">
                        <a href="drivers.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this driver?')">Delete</a>
                    </td
                </tr>
                <?php 
                $display_id++;
                endwhile; 
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>