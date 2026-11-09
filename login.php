<?php
// login.php - Login Page with Role-based Redirect + Fingerprint Link
// Enforces: only the primary administrator can log in as admin
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

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
                session_regenerate_id(true);
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
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | MA-LIFE</title>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body class="ml-auth">
<main class="ml-auth-layout">
    <div class="ml-auth-shell">
        <aside class="ml-auth-brand">
            <div>
                <img src="img/logo.png" alt="">
                <strong>MA-LIFE</strong>
                <p>Maternal and Child Health System</p>
            </div>
            <p>Luzira, Kampala</p>
        </aside>
        <section class="ml-auth-form" aria-labelledby="login-title">
            <a class="ml-auth-home" href="index.php">Back to home</a>
            <h1 id="login-title">Sign in to MA-LIFE</h1>
            <p>Use your MA-LIFE account to open your dashboard.</p>
            <?php if ($error !== ''): ?>
                <p class="ml-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="post" action="login.php">
                <div>
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="username" required value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="password">Password</label>
                    <div class="ml-password">
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="ml-button" data-password-toggle aria-controls="password" aria-pressed="false" hidden>Show</button>
                    </div>
                </div>
                <button class="ml-button ml-button-primary" type="submit">Sign in</button>
            </form>
            <div class="ml-auth-links">
                <a href="login_fingerprint.php">Sign in with a passkey</a>
                <p>New mother or guardian? <a href="signup.php">Create an account</a></p>
            </div>
        </section>
    </div>
</main>
</body>
</html>
