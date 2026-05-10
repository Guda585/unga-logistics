<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        if ($user['role'] == 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: driver.php');
        }
        exit();
    } else {
        $error = 'Invalid email or password';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Unga Logistics</title>
</head>
<body>
    <div style="width: 350px; margin: 100px auto; padding: 30px; border: 1px solid #ccc; border-radius: 10px; text-align: center;">
        <h2>Unga Holdings Limited</h2>
        <p>Logistics Management System</p>
        <?php if($error): ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required style="width:100%; padding:10px; margin:10px 0;">
            <input type="password" name="password" placeholder="Password" required style="width:100%; padding:10px; margin:10px 0;">
            <button type="submit" style="width:100%; padding:10px; background:#4299e1; color:white; border:none; cursor:pointer;">Login</button>
        </form>
    </div>
</body>
</html>