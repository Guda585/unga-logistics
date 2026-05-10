<?php
session_name('DRIVER_SESSION');
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$LATE_PENALTY_PER_HOUR = 500;

$delivery_id = $_POST['delivery_id'];
$customer_signature = $_POST['customer_signature_data'];
$driver_signature = $_POST['driver_signature_data'];
$customer_name = $_POST['customer_name'];

if (!$customer_signature || !$driver_signature) {
    echo json_encode(['success' => false, 'message' => 'Signatures required']);
    exit();
}

$signature_dir = 'signatures/';
if (!file_exists($signature_dir)) {
    mkdir($signature_dir, 0777, true);
}

$customer_sig_file = $signature_dir . 'customer_sig_' . $delivery_id . '_' . time() . '.png';
$customer_sig_data = str_replace('data:image/png;base64,', '', $customer_signature);
$customer_sig_data = str_replace(' ', '+', $customer_sig_data);
file_put_contents($customer_sig_file, base64_decode($customer_sig_data));

$driver_sig_file = $signature_dir . 'driver_sig_' . $delivery_id . '_' . time() . '.png';
$driver_sig_data = str_replace('data:image/png;base64,', '', $driver_signature);
$driver_sig_data = str_replace(' ', '+', $driver_sig_data);
file_put_contents($driver_sig_file, base64_decode($driver_sig_data));

$delivery_query = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    WHERE d.id = $delivery_id");
$delivery_info = mysqli_fetch_assoc($delivery_query);
$time_window_end = $delivery_info['time_window_end'];

$penalty = 0;
$current_time = date('H:i:s');

if ($time_window_end && $current_time > $time_window_end) {
    $deadline_h = (int)substr($time_window_end, 0, 2);
    $deadline_m = (int)substr($time_window_end, 3, 2);
    $current_h = (int)substr($current_time, 0, 2);
    $current_m = (int)substr($current_time, 3, 2);
    
    $deadline_total = ($deadline_h * 60) + $deadline_m;
    $current_total = ($current_h * 60) + $current_m;
    
    $minutes_late = $current_total - $deadline_total;
    
    if ($minutes_late > 0) {
        $hours_late = ceil($minutes_late / 60);
        $penalty = $hours_late * $LATE_PENALTY_PER_HOUR;
    }
}

$update = "UPDATE deliveries SET 
    status = 'delivered', 
    delivered_at = NOW(), 
    signature_path = '$customer_sig_file',
    driver_signature_path = '$driver_sig_file',
    penalty_amount = $penalty
    WHERE id = $delivery_id";
mysqli_query($conn, $update);

$message_text = "✅ Delivery {$delivery_info['delivery_code']} completed by $username for {$delivery_info['customer_name']}" . ($penalty > 0 ? " (Late: KES $penalty)" : "");
mysqli_query($conn, "INSERT INTO notifications (delivery_id, message, status) VALUES ($delivery_id, '$message_text', 'unread')");

echo json_encode(['success' => true, 'penalty' => $penalty, 'delivery_code' => $delivery_info['delivery_code']]);
?>