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

// Get driver's vehicle
$vehicle_query = mysqli_query($conn, "SELECT id, plate_number FROM vehicles WHERE driver_id = $user_id");
$vehicle = mysqli_fetch_assoc($vehicle_query);

if ($vehicle) {
    // Update vehicle status to available
    mysqli_query($conn, "UPDATE vehicles SET status = 'available' WHERE driver_id = $user_id");
    
    // Send notification to admin
    $admin_notify = "Driver {$username} has returned to depot with vehicle {$vehicle['plate_number']}";
    mysqli_query($conn, "INSERT INTO notifications (message, status, created_at) VALUES ('$admin_notify', 'unread', NOW())");
    
    // Send to admin_notifications table
    mysqli_query($conn, "INSERT INTO admin_notifications (message, status, created_at) VALUES ('$admin_notify', 'unread', NOW())");
    
    // Update driver location to depot
    $depot_lat = -1.3167;
    $depot_lng = 36.8500;
    $check = mysqli_query($conn, "SELECT id FROM driver_locations WHERE driver_id = $user_id");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE driver_locations SET lat = $depot_lat, lng = $depot_lng, last_update = NOW() WHERE driver_id = $user_id");
    } else {
        mysqli_query($conn, "INSERT INTO driver_locations (driver_id, vehicle_id, lat, lng, last_update) VALUES ($user_id, {$vehicle['id']}, $depot_lat, $depot_lng, NOW())");
    }
    
    $_SESSION['return_message'] = "✅ You have been marked as returned to depot. Vehicle status updated to Available. Admin has been notified.";
} else {
    $_SESSION['return_message'] = "⚠️ No vehicle found assigned to you.";
}

header('Location: driver.php');
exit();
?>