<?php
// login.php - Login Page with Role-based Redirect + Fingerprint Link
// Enforces: only the primary administrator can log in as admin
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // ---- Pre-check: is this a non-primary admin? ----
        $db = getDB();
        $stmt = $db->prepare("SELECT id, role, is_primary, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute(array($email));
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

        $blocked = false;
        if ($candidate) {
            // Non-primary admin → deny login outright
            if ($candidate['role'] === 'admin' && empty($candidate['is_primary'])) {
                $blocked = true;
                $error = "This administrator account is disabled. MA-LIFE allows only one active administrator.";
                error_log("MA-LIFE: Blocked non-primary admin login attempt for {$email}");
            }
            // Inactive user
            if (isset($candidate['status']) && $candidate['status'] !== 'active') {
                $blocked = true;
                $error = "Your account is inactive. Contact the system administrator.";
            }
        }

        if (!$blocked) {
            if (loginUser($email, $password)) {
                $role = $_SESSION['user_role'];
                header('Location: ' . BASE_URL . getDashboardUrl($role));
                exit();
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}

if (isLoggedIn()) {
    header("Location: " . BASE_URL . getDashboardUrl($_SESSION['user_role']));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container { min-height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(135deg, #0A4C54 0%, #146b76 50%, #1a8a96 100%); padding: 20px; }
        .auth-card { background: white; padding: 3rem; border-radius: 20px; width: 100%; max-width: 450px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.6s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header .logo-img { width: 80px; height: 80px; border-radius: 50%; background: #0A4C54; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; overflow: hidden; }
        .auth-header .logo-img img { width: 100%; height: 100%; object-fit: cover; }
        .auth-header h1 { font-family: 'Poppins', sans-serif; color: #0A4C54; font-size: 1.8rem; }
        .auth-header p { color: #666; margin-top: 5px; }
        .input-group { margin-bottom: 1.5rem; position: relative; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #333; }
        .input-group .input-icon { position: absolute; left: 12px; top: 42px; color: #999; }
        .input-group input { width: 100%; padding: 12px 12px 12px 42px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s; background: #f8f9fa; box-sizing: border-box; }
        .input-group input:focus { border-color: #0A4C54; outline: none; background: white; box-shadow: 0 0 0 4px rgba(10, 76, 84, 0.1); }
        .btn-primary { width: 100%; padding: 14px; background: #0A4C54; color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary:hover { background: #146b76; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(10, 76, 84, 0.3); }
        .error-message { background: #fee; color: #c0392b; padding: 10px 15px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; border-left: 4px solid #c0392b; }
        .success-message { background: #e8f5e9; color: #2e7d32; padding: 10px 15px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; border-left: 4px solid #2e7d32; }
        .auth-footer { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee; }
        .auth-footer a { color: #0A4C54; text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .fingerprint-link { display: block; text-align: center; margin-top: 15px; padding: 12px; background: #e0f2f1; color: #00695c; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .fingerprint-link:hover { background: #b2dfdb; }
        .fingerprint-link i { margin-right: 8px; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-img">
                    <img src="img/logo.png" alt="MA-LIFE Logo" onerror="this.style.display='none'" />
                </div>
                <h1>MA-LIFE</h1>
                <p>Maternal & Child Health System · Luzira</p>
            </div>

            <h2 style="text-align:center; color:#333; margin-bottom:1.5rem;">Welcome Back</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                </button>
            </form>

            <a href="login_fingerprint.php" class="fingerprint-link">
                <i class="fas fa-fingerprint"></i> Login with Fingerprint
            </a>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up Here</a></p>
                <p style="margin-top:10px; font-size:0.85rem; color:#999;">
                    <i class="fas fa-shield-alt"></i> Secured & Encrypted Connection
                </p>
            </div>
        </div>
    </div>
</body>
</html>