<?php
include 'config.php';
session_start();

$email = 'pauline.gichia@unga.com';
$password = md5('admin123');

$sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 1) {
    $user = mysqli_fetch_assoc($result);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    echo "Session set!<br>";
    echo '<a href="admin_dashboard.php">Go to Admin Dashboard</a>';
} else {
    echo "Login failed";
}
?>