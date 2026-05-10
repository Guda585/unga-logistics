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
$issue_type = $_POST['issue_type'];
$description = mysqli_real_escape_string($conn, $_POST['description']);

$insert = "INSERT INTO driver_issues (driver_id, issue_type, description, status, created_at) 
           VALUES ('$user_id', '$issue_type', '$description', 'pending', NOW())";

if (mysqli_query($conn, $insert)) {
    $admin_message = "⚠️ Issue reported by driver {$username}. Type: {$issue_type}. Description: {$description}";
    mysqli_query($conn, "INSERT INTO notifications (message, status, created_at) VALUES ('$admin_message', 'unread', NOW())");
    
    $_SESSION['return_message'] = "✅ Issue reported to admin successfully.";
} else {
    $_SESSION['return_message'] = "❌ Failed to report issue. Please try again.";
}

header('Location: driver.php');
exit();
?>