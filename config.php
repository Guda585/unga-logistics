<?php
$url = getenv('DATABASE_URL');
if (!$url) {
    die("DATABASE_URL not set");
}

$parts = parse_url($url);
$host = $parts['host'];
$port = $parts['port'];
$user = $parts['user'];
$password = $parts['pass'];
$database = ltrim($parts['path'], '/');

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
