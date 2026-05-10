<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    http_response_code(403);
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
$lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

if ($lat == 0 || $lng == 0) {
    exit('Invalid coordinates');
}

// Get driver's vehicle
$vehicle_query = mysqli_query($conn, "SELECT id FROM vehicles WHERE driver_id = $user_id");
$vehicle = mysqli_fetch_assoc($vehicle_query);

if ($vehicle) {
    $vehicle_id = $vehicle['id'];
    
    // Check if record exists
    $check = mysqli_query($conn, "SELECT id FROM driver_locations WHERE driver_id = $user_id");
    
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE driver_locations SET lat = $lat, lng = $lng, last_update = NOW() WHERE driver_id = $user_id");
    } else {
        mysqli_query($conn, "INSERT INTO driver_locations (driver_id, vehicle_id, lat, lng, last_update) VALUES ($user_id, $vehicle_id, $lat, $lng, NOW())");
    }
    
    echo "OK";
} else {
    echo "No vehicle assigned";
}
?>