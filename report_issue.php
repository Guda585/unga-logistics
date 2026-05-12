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
$issue_type = mysqli_real_escape_string($conn, $_POST['issue_type']);
$description = mysqli_real_escape_string($conn, $_POST['description']);

$insert = "INSERT INTO driver_issues (driver_id, issue_type, description, status, created_at) 
           VALUES ('$user_id', '$issue_type', '$description', 'pending', NOW())";

if (mysqli_query($conn, $insert)) {
    // Get the ID of the newly inserted issue
    $issue_id = mysqli_insert_id($conn);
    
    $admin_message = "⚠️ Issue reported by driver {$username}. Type: {$issue_type}. Description: {$description}";
    // Store issue_id in notifications table
    mysqli_query($conn, "INSERT INTO notifications (message, status, created_at, issue_id) VALUES ('$admin_message', 'unread', NOW(), '$issue_id')");
    
    $_SESSION['return_message'] = "✅ Issue reported to admin successfully.";
} else {
    $_SESSION['return_message'] = "❌ Failed to report issue. Please try again.";
}

header('Location: driver.php');
exit();
?>
