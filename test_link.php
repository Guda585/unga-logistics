<?php
include 'config.php';
$result = mysqli_query($conn, "SELECT id, delivery_code FROM deliveries LIMIT 5");
while($row = mysqli_fetch_assoc($result)) {
    echo '<a href="view_delivery.php?id=' . $row['id'] . '">View Delivery ' . $row['id'] . '</a><br>';
}
?>