<?php
session_start();
echo "Session user_id: " . $_SESSION['user_id'] . "<br>";
echo "Session username: " . $_SESSION['username'] . "<br>";
echo "Session role: " . $_SESSION['role'] . "<br>";
?>