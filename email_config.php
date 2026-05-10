<?php
// Email configuration
$admin_email = "pauline.gichia@unga.com"; // Admin email address
$email_enabled = true; // Set to false to disable emails during testing

// Function to send email
function sendEmail($to, $subject, $message) {
    global $email_enabled;
    
    if (!$email_enabled) {
        return true;
    }
    
    // Headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Unga Logistics <noreply@unga-logistics.com>" . "\r\n";
    
    // Send mail
    return mail($to, $subject, $message, $headers);
}
?>