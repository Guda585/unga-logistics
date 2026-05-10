<?php
// Destroy admin session if exists
if (session_name() !== 'ADMIN_SESSION') {
    session_name('ADMIN_SESSION');
    session_start();
    session_destroy();
}

// Destroy driver session if exists
if (session_name() !== 'DRIVER_SESSION') {
    session_name('DRIVER_SESSION');
    session_start();
    session_destroy();
}

// Check where the user came from and redirect appropriately
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

if (strpos($referrer, 'driver') !== false) {
    header('Location: driver_login.php');
} else {
    header('Location: admin_login.php');
}
exit();
?>
