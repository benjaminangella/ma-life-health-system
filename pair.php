<?php
// pair.php - Register a device when it scans the QR code OR types a short code
require_once 'config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$submitted_code = isset($_POST['short_code']) ? trim($_POST['short_code']) : '';
$error = '';
$success = false;
$device_name = 'Unknown Device';
$device_type = 'other';
$show_form = false;

$db = getDB();

// 1. If they submitted a short code (e.g. from a smartwatch without a camera)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($submitted_code)) {
    $stmt = $db->prepare("SELECT pairing_token FROM linked_devices WHERE short_code = ? AND status = 'active' LIMIT 1");
    $stmt->execute(array(strtoupper($submitted_code)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $token = $row['pairing_token'];
    } else {
        $error = 'Invalid code. Please check the code on your MA-LIFE dashboard and try again.';
    }
}

// 2. If no token is provided at all (they just visited pair.php directly)
if (empty($token) && empty($submitted_code) && !$success) {
    $show_form = true;
}

// 3. Process the pairing
if (!empty($token) && !preg_match('/^[a-f0-9]{32}$/', $token)) {
    $error = 'Invalid or missing pairing token.';
} elseif (!empty($token) && !$error) {
    // Find the owner of this token
    $stmt = $db->prepare("
        SELECT id, user_id, device_name 
        FROM linked_devices 
        WHERE pairing_token = ? AND status = 'active' 
        LIMIT 1
    ");
    $stmt->execute(array($token));
    $token_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_row) {
        $error = 'This pairing link is not valid or has expired.';
    } else {
        // ---- Detect device type from User-Agent ----
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/iPad|Tablet|Nexus 7|Nexus 10/i', $ua))       $device_type = 'tablet';
        elseif (preg_match('/Watch|watchOS/i', $ua))                   $device_type = 'watch';
        elseif (preg_match('/Mobile|Android|iPhone|iPod/i', $ua))      $device_type = 'phone';
        elseif (preg_match('/Macintosh|Windows|Linux/i', $ua))         $device_type = 'desktop';

        // ---- Simple device name guess from UA ----
        $device_name = 'New Device';
        if (preg_match('/Watch|watchOS/i', $ua))                     $device_name = 'Smart Watch';
        elseif (preg_match('/iPhone/i', $ua))                        $device_name = 'iPhone';
        elseif (preg_match('/iPad/i', $ua))                          $device_name = 'iPad';
        elseif (preg_match('/Samsung/i', $ua))                       $device_name = 'Samsung Device';
        elseif (preg_match('/Android/i', $ua))                       $device_name = 'Android Device';
        elseif (preg_match('/Macintosh/i', $ua))                     $device_name = 'Mac';
        elseif (preg_match('/Windows/i', $ua))                       $device_name = 'Windows PC';
        elseif (preg_match('/Linux/i', $ua))                         $device_name = 'Linux Device';

        // ---- Claim the placeholder OR add a new device ----
        if ($token_row['device_name'] === 'Pending device') {
            $stmt = $db->prepare("
                UPDATE linked_devices 
                SET device_name = ?, device_type = ?, user_agent = ?, ip_address = ?, linked_at = NOW(), last_seen = NOW()
                WHERE id = ?
            ");
            $stmt->execute(array(
                $device_name,
                $device_type,
                $ua,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $token_row['id']
            ));
        } else {
            $stmt = $db->prepare("
                INSERT INTO linked_devices (user_id, device_name, device_type, pairing_token, user_agent, ip_address, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute(array(
                $token_row['user_id'],
                $device_name,
                $device_type,
                $token,
                $ua,
                $_SERVER['REMOTE_ADDR'] ?? null
            ));
        }

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Linked - MA-LIFE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:system-ui, sans-serif; }
        body { background: linear-gradient(135deg, #0A4C54 0%, #146b76 50%, #1a8a96 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:#fff; max-width:440px; width:100%; padding:40px; border-radius:16px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .card .icon { font-size:4rem; margin-bottom:20px; }
        .card .icon.ok { color:#2e7d32; }
        .card .icon.err { color:#c62828; }
        .card .icon.watch { color:#1976d2; }
        .card h1 { font-size:1.4rem; color:#333; margin-bottom:10px; }
        .card p { color:#666; margin-bottom:15px; line-height:1.5; }
        .card .info { background:#f5f5f5; padding:12px; border-radius:8px; margin:15px 0; font-size:0.9rem; text-align:left; color:#555; }
        .card .info strong { color:#333; }
        
        .input-group { margin: 20px 0; text-align: left; }
        .input-group label { display:block; font-weight:600; margin-bottom:8px; color:#333; font-size:0.95rem; }
        .input-group input { width:100%; padding:15px; font-size:1.5rem; text-align:center; letter-spacing:6px; text-transform:uppercase; border:2px solid #e0e0e0; border-radius:8px; font-weight:700; }
        .input-group input:focus { border-color:#0A4C54; outline:none; box-shadow: 0 0 0 4px rgba(10,76,84,0.1); }
        .btn-submit { width:100%; padding:15px; background:#0A4C54; color:#fff; border:none; border-radius:8px; font-size:1.1rem; font-weight:600; cursor:pointer; transition: 0.3s; }
        .btn-submit:hover { background:#146b76; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($show_form): ?>
            <!-- Smartwatch Input Form -->
            <div class="icon watch"><i class="fas fa-clock"></i></div>
            <h1>Link Smartwatch</h1>
            <p>Enter the 6-character code shown on your MA-LIFE dashboard to connect this device.</p>
            
            <?php if ($error): ?>
                <div style="color:#c62828; background:#ffebee; padding:10px; border-radius:8px; margin-bottom:15px; font-size:0.9rem; text-align:left;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Pairing Code</label>
                    <input type="text" name="short_code" maxlength="6" placeholder="A1B2C3" required autocomplete="off">
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-link"></i> Link Device</button>
            </form>

        <?php elseif ($success): ?>
            <!-- Success Screen -->
            <div class="icon ok"><i class="fas fa-check-circle"></i></div>
            <h1>Device Linked!</h1>
            <p>Your device has been successfully connected to MA-LIFE.</p>
            <div class="info">
                <p><strong>Device:</strong> <?= htmlspecialchars($device_name) ?></p>
                <p><strong>Type:</strong> <?= htmlspecialchars($device_type) ?></p>
                <p><strong>Linked at:</strong> <?= date('d M Y, H:i') ?></p>
            </div>
            <p style="font-size:0.85rem; color:#999;">You can close this tab now.</p>

        <?php else: ?>
            <!-- Error Screen -->
            <div class="icon err"><i class="fas fa-times-circle"></i></div>
            <h1>Linking Failed</h1>
            <p><?= htmlspecialchars($error ?: 'Something went wrong.') ?></p>
            <a href="pair.php" style="color:#0A4C54; font-weight:600; text-decoration:none; display:inline-block; margin-top:15px;">Try entering a code manually</a>
        <?php endif; ?>
    </div>
</body>
</html>