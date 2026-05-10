<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notification_id > 0) {
    // Verify notification belongs to this driver
    $check = mysqli_query($conn, "SELECT id FROM driver_notifications WHERE id = $notification_id AND driver_id = {$_SESSION['user_id']}");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "DELETE FROM driver_notifications WHERE id = $notification_id");
    }
}

header('Location: driver.php');
exit();
?>