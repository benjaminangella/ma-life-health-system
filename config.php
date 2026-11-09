<?php
/**
 * config.php — MA-LIFE Core Configuration
 * Session security, DB connection, user functions
 * UPDATED: Strong anti-cache headers + JS session guard support
 * UPDATED: BASE_URL set to local hotspot IP for mobile testing
 */

// ============================================
// DATABASE
// ============================================
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'malife');
define('DB_PASS', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME') ?: 'malife');

// ============================================
// APP
// ============================================
// Updated to your phone hotspot IP so devices can connect
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'https://malifehs.arofi.net', '/') . '/');
define('SITE_NAME', 'MA-LIFE Health System');

// ============================================
// SESSION CONFIG
// ============================================
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Force session cache limiter to nocache — this is CRITICAL for back-button security
ini_set('session.cache_limiter', 'nocache');
ini_set('session.cache_expire', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_id() == '') {
    session_start();
}

// ============================================
// PREVENT BROWSER CACHE (Aggressive)
// ============================================
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0, private');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Vary: *');
}

// ============================================
// SESSION SECURITY CHECK
// ============================================
$current_script = basename($_SERVER['PHP_SELF']);
$skip_scripts = array(
    'login.php', 'logout.php', 'signup.php', 'index.php',
    'login_fingerprint.php',
    'session_check.php',
    'cron_reminders.php', 'api_vitals.php', 'sms_helper.php',
    'sms_queue.php', 'test_send.php', 'curl_diag.php',
    'pair.php'   // <-- Added this so devices can link without logging in
);

if (!in_array($current_script, $skip_scripts) && isset($_SESSION['user_id'])) {

    // Check inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        $_SESSION = array();
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?expired=1');
        exit();
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    // IP hijack detection
    $current_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $current_ip) {
        $_SESSION = array();
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?security=1');
        exit();
    }
}

// ============================================
// ERRORS + TIMEZONE
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Africa/Kampala');

// Auto-load SMS helper (if present)
if (file_exists(dirname(__FILE__) . '/sms_helper.php')) {
    require_once dirname(__FILE__) . '/sms_helper.php';
}

// ============================================
// DB CONNECTION
// ============================================
function getDB() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
            DB_USER, DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        return $conn;
    } catch (PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

// ============================================
// PASSWORD
// ============================================
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================
// USER HELPERS
// ============================================
function getUserByEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(array($email));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($id));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createUser($data, ?PDO $db = null) {
    $db = $db ?? getDB();
    $hash = hashPassword($data['password']);

    $is_primary = isset($data['is_primary']) ? (int)$data['is_primary'] : 0;
    $status     = isset($data['status']) && $data['status'] !== '' ? $data['status'] : 'active';

    $stmt = $db->prepare("
        INSERT INTO users 
            (full_name, email, password, role, phone, date_of_birth, gender, address, is_primary, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute(array(
        $data['full_name'],
        $data['email'],
        $hash,
        $data['role'],
        isset($data['phone']) ? $data['phone'] : null,
        isset($data['date_of_birth']) ? $data['date_of_birth'] : null,
        isset($data['gender']) ? $data['gender'] : null,
        isset($data['address']) ? $data['address'] : null,
        $is_primary,
        $status
    ));
    return $db->lastInsertId();
}

// ============================================
// PRIMARY ADMIN HELPERS
// ============================================
function ma_life_count_primary_admins($exclude_id = 0) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role='admin' AND is_primary=1 AND id != ?");
    $stmt->execute(array($exclude_id));
    return (int)$stmt->fetchColumn();
}

// ============================================
// AUTH
// ============================================
function loginUser($email, $password) {
    $user = getUserByEmail($email);

    if (!$user) {
        return false;
    }

    if (isset($user['status']) && $user['status'] !== 'active') {
        error_log("MA-LIFE: Blocked login for inactive user: {$email}");
        return false;
    }

    if (verifyPassword($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role'];

        $_SESSION['user_ip']    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $_SESSION['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute(array($user['id']));

        return true;
    }
    return false;
}

function logoutUser() {
    $_SESSION = array();
    if (session_id() != '') {
        session_destroy();
    }
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (!isRole($role)) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }
                body { background:#f5f1eb; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
                .card { background:#fff; max-width:500px; width:100%; padding:40px; border-radius:16px; text-align:center; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
                .card i { font-size:4rem; color:#c62828; margin-bottom:20px; }
                .card h1 { color:#c62828; margin-bottom:10px; font-size:1.5rem; }
                .card p { color:#666; margin-bottom:15px; }
                .card .info { background:#f5f5f5; padding:15px; border-radius:8px; margin:20px 0; text-align:left; font-size:0.9rem; }
                .card .info strong { color:#333; }
                .card a { padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600; display:inline-block; margin:5px; }
                .btn-primary { background:#0A4C54; color:#fff; }
                .btn-outline { background:#fff; color:#0A4C54; border:2px solid #0A4C54; }
            </style>
            <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
        <body>
            <div class="card">
                <i class="fas fa-lock"></i>
                <h1>Access Denied</h1>
                <p>You don't have permission to view this page.</p>
                <div class="info">
                    <p><strong>Your role:</strong> <?php echo htmlspecialchars($_SESSION['user_role']); ?></p>
                    <p><strong>Required role:</strong> <?php echo htmlspecialchars($role); ?></p>
                    <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
                <?php
                $dash = 'index.php';
                if ($_SESSION['user_role'] === 'admin') $dash = 'administrator.php';
                elseif ($_SESSION['user_role'] === 'healthworker') $dash = 'healthworker.php';
                elseif ($_SESSION['user_role'] === 'mother') $dash = 'mother_dashboard.php';
                ?>
                <a href="<?php echo $dash; ?>" class="btn-primary">Go to My Dashboard</a>
                <a href="logout.php" class="btn-outline">Logout</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

function getDashboardUrl($role) {
    $d = array(
        'admin' => 'administrator.php',
        'healthworker' => 'healthworker.php',
        'mother' => 'mother_dashboard.php'
    );
    return isset($d[$role]) ? $d[$role] : 'index.php';
}

function getSessionRemaining() {
    if (!isset($_SESSION['last_activity'])) return 0;
    $elapsed = time() - $_SESSION['last_activity'];
    return max(0, SESSION_TIMEOUT - $elapsed);
}
?>