<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

$sql = "SELECT u.username as driver_name, v.plate_number, dl.lat, dl.lng, dl.last_update 
        FROM driver_locations dl
        JOIN users u ON dl.driver_id = u.id
        JOIN vehicles v ON dl.vehicle_id = v.id
        WHERE dl.last_update > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.username";

$result = mysqli_query($conn, $sql);
$drivers = [];

while ($row = mysqli_fetch_assoc($result)) {
    $drivers[] = [
        'driver_name' => $row['driver_name'],
        'plate_number' => $row['plate_number'],
        'lat' => (float)$row['lat'],
        'lng' => (float)$row['lng'],
        'last_update' => $row['last_update']
    ];
}

echo json_encode($drivers);
?>