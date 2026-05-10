<?php
session_start();
session_destroy();
include 'config.php';

// Try to login as Mercy manually
$email = 'mercy.kareko@unga.com';
$password = md5('driver123');

$sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
$result = mysqli_query($conn, $sql);

echo "SQL: $sql<br>";

if (mysqli_num_rows($result) == 1) {
    $user = mysqli_fetch_assoc($result);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    echo "Login successful!<br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Username: " . $_SESSION['username'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
    
    echo '<a href="driver_dashboard.php">Go to Dashboard</a>';
} else {
    echo "Login failed. User not found.";
}
?>