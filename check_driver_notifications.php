<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM driver_notifications WHERE driver_id = $user_id AND is_read = 0");
$row = mysqli_fetch_assoc($result);

echo json_encode(['unread_count' => $row['count']]);
?>