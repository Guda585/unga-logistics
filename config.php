<?php
$host = getenv('MYSQLHOST') ?: 'localhost';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'unga_logistics';
$port = getenv('MYSQLPORT') ?: 3306;

$conn = mysqli_connect($host, $username, $password, $database, (int)$port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
