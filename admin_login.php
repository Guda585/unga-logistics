<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$password' AND role='admin'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: admin_dashboard.php');
        exit();
    } else {
        $error = 'Invalid admin credentials';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - Unga Holdings</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(0,0,0,0.65), rgba(0,0,0,0.5)), 
                        url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: linear-gradient(145deg, #ffffff 0%, #f1f5f9 100%);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 45px 40px;
            width: 450px;
            text-align: center;
            position: relative;
            transition: all 0.3s ease-in-out;
        }
        
        /* Continuous glowing border animation */
        .login-card::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(45deg, 
                #d4af37, #b8860b, #ffd700, #d4af37, #b8860b, #ffd700, #d4af37);
            background-size: 300% 300%;
            border-radius: 27px;
            z-index: -1;
            animation: borderGlow 3s ease infinite;
            opacity: 0.7;
        }
        
        @keyframes borderGlow {
            0% {
                background-position: 0% 50%;
                opacity: 0.5;
            }
            50% {
                background-position: 100% 50%;
                opacity: 1;
            }
            100% {
                background-position: 0% 50%;
                opacity: 0.5;
            }
        }
        
        /* Inner card to cover the glowing border effect properly */
        .login-card {
            position: relative;
            z-index: 1;
        }
        
        .logo {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #d4af37, #b8860b);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        h2 {
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 35px;
            letter-spacing: 0.5px;
        }
        
        .input-group {
            margin-bottom: 22px;
            text-align: left;
        }
        
        .input-group label {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
            font-weight: 500;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #d4af37, #b8860b);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(184, 134, 11, 0.3);
        }
        
        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #dc2626;
            font-weight: 500;
        }
        
        .company-badge {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .company-badge span {
            color: #d4af37;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">U</div>
            <h2>Unga Holdings Limited</h2>
            <p class="subtitle">Administrator Portal</p>
            
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="admin@unga.com" required>
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit">Login to Dashboard</button>
            </form>
            
            <div class="company-badge">
                <span>Unga Holdings Limited</span> | Since 1908
            </div>
        </div>
    </div>
</body>
</html>