<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    header('Location: driver_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

mysqli_query($conn, "DELETE FROM driver_notifications WHERE driver_id = $user_id");

header('Location: driver.php');
exit();
?>