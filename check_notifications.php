<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE status = 'unread'");
$row = mysqli_fetch_assoc($result);

echo json_encode(['unread_count' => $row['count']]);
?>