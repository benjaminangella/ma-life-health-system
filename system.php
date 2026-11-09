<?php
// system.php - System Access Point
require_once 'config.php';

// If user is logged in, redirect to their dashboard
if(isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: " . BASE_URL . getDashboardUrl($role));
    exit();
}

// If not logged in, show a system access page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Access - MA-LIFE</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .access-container {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0A4C54 0%, #146b76 50%, #1a8a96 100%);
            padding: 20px;
        }
        .access-card {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .access-card .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #0A4C54;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
        }
        .access-card h1 {
            font-family: 'Poppins', sans-serif;
            color: #0A4C54;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .access-card p {
            color: #666;
            margin-bottom: 2rem;
        }
        .btn-access {
            display: inline-block;
            padding: 14px 40px;
            background: #0A4C54;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            margin: 5px;
        }
        .btn-access:hover {
            background: #146b76;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 76, 84, 0.3);
        }
        .btn-access.outline {
            background: transparent;
            color: #0A4C54;
            border: 2px solid #0A4C54;
        }
        .btn-access.outline:hover {
            background: #0A4C54;
            color: white;
        }
        .access-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="access-container">
        <div class="access-card">
            <div class="logo">👶</div>
            <h1>MA-LIFE System</h1>
            <p>Maternal & Child Health Electronic System</p>
            
            <div class="access-options">
                <a href="login.php" class="btn-access">
                    <i class="fas fa-sign-in-alt"></i> Login to System
                </a>
                <a href="signup.php" class="btn-access outline">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
                <a href="index.php" class="btn-access outline" style="background:#f5f5f5; border-color:#ddd; color:#666;">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>