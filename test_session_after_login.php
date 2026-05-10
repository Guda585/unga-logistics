<?php
session_start();
echo "Session after login:<br>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";
echo "role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
?>