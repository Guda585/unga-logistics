<?php
session_name('ADMIN_SESSION');
session_start();
session_destroy();

session_name('DRIVER_SESSION');
session_start();
session_destroy();

header('Location: admin_login.php');
exit();
?>