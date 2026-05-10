<?php
session_start();
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Session username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";
echo "Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
?>