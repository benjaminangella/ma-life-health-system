<?php
// link_devices.php - Show QR code + list of linked devices
require_once 'config.php';
requireLogin();

$db = getDB();
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$is_mother = ($user_role === 'mother');
$message = '';
$error = '';

// ---- Generate or fetch this user's pairing token AND short code ----
$stmt = $db->prepare("SELECT pairing_token, short_code FROM linked_devices WHERE user_id = ? AND status='active' LIMIT 1");
$stmt->execute(array($user_id));
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $pairing_token = $existing['pairing_token'];
    $short_code = $existing['short_code'];
    
    // If we have a token but no short code, generate one now
    if (empty($short_code)) {
        $short_code = strtoupper(substr(md5(uniqid()), 0, 6));
        $db->prepare("UPDATE linked_devices SET short_code = ? WHERE pairing_token = ?")
           ->execute(array($short_code, $pairing_token));
    }
} else {
    // Create a fresh token and short code
    $pairing_token = bin2hex(random_bytes(16));
    $short_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Insert a placeholder row so the token is reserved
    $stmt = $db->prepare("
        INSERT INTO linked_devices (user_id, pairing_token, short_code, device_name, device_type, status)
        VALUES (?, ?, ?, 'Pending device', 'other', 'active')
    ");
    $stmt->execute(array($user_id, $pairing_token, $short_code));
}

// ---- Handle "remove device" ----
if (isset($_GET['remove']) && ctype_digit($_GET['remove'])) {
    $device_id = (int)$_GET['remove'];
    // Only allow removing the user's own devices; do not allow removing the placeholder
    $stmt = $db->prepare("
        DELETE FROM linked_devices 
        WHERE id = ? AND user_id = ? AND device_name != 'Pending device'
    ");
    $stmt->execute(array($device_id, $user_id));
    if ($stmt->rowCount() > 0) {
        $message = "Device removed successfully.";
    } else {
        $error = "Could not remove that device.";
    }
}

// ---- Fetch all this user's linked devices ----
$stmt = $db->prepare("
    SELECT * FROM linked_devices 
    WHERE user_id = ? AND status='active' AND device_name != 'Pending device'
    ORDER BY linked_at DESC
");
$stmt->execute(array($user_id));
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Build the pairing URL ----
$pair_url = BASE_URL . 'pair.php?token=' . urlencode($pairing_token);

// ---- QR code image (public API, no library needed) ----
$qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pair_url);

// ---- Define dynamic colors and sidebar links based on role ----
$theme_color = $is_mother ? '#ad1457' : '#00897b';
$theme_bg = $is_mother ? 'rgba(173, 20, 87, 0.92)' : '#1a363a'; 
$dash_url = BASE_URL . getDashboardUrl($user_role);
$notes_url = 'admin_notes.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Devices - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', Arial, sans-serif; }
        body { background: <?= $is_mother ? '#fce4ec' : '#f5f1eb' ?>; min-height: 100vh; }
        
        /* Sidebar Override for Mothers */
        <?php if ($is_mother): ?>
        .sidebar { background: <?= $theme_bg ?> !important; backdrop-filter: blur(12px); }
        .nav h2 { background: rgba(136, 14, 79, 0.6) !important; }
        .topbar { border-bottom: 1px solid rgba(173, 20, 87, 0.1) !important; }
        .header h2 { color: #ad1457 !important; }
        .btn { background: #ad1457 !important; box-shadow: 0 4px 15px rgba(173,20,87,0.2); }
        .btn:hover { background: #880e4f !important; }
        .qr-card .pair-url { color: #ad1457 !important; }
        .device-row .icon { color: #ad1457 !important; }
        .badge-type { background: #fce4ec !important; color: #ad1457 !important; }
        <?php else: ?>
        .sidebar { background: <?= $theme_bg ?> !important; }
        .nav h2 { background: #0f2527 !important; }
        <?php endif; ?>

        .link-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
        .link-header h2 { color:#37474f; }
        .link-grid { display:grid; grid-template-columns: 350px 1fr; gap:25px; }
        @media (max-width: 900px) { .link-grid { grid-template-columns: 1fr; } }
        
        .qr-card { background:#fff; border-radius:12px; padding:25px; text-align:center; box-shadow:0 4px 25px rgba(0,0,0,0.06); }
        .qr-card img { width:100%; max-width:260px; border-radius:8px; background:#fff; border: 1px solid #eee; padding: 10px; }
        .qr-card .pair-url { margin-top:15px; font-size:0.8rem; word-break:break-all; font-weight: 600; }
        .qr-card h3 { color:#37474f; margin-bottom:10px; font-size:1.05rem; }
        .qr-card p { color:#666; font-size:0.85rem; margin-bottom:15px; }
        
        /* Code box for smartwatch */
        .watch-code-box { margin-top: 25px; border-top: 1px dashed #ccc; padding-top: 20px; background: #fafafa; border-radius: 8px; padding: 15px; }
        .watch-code-box h4 { color:#37474f; font-size: 0.95rem; margin-bottom: 5px; }
        .watch-code-box .code { font-size: 2.2rem; font-weight: 800; letter-spacing: 5px; margin-top: 10px; color: <?= $theme_color ?>; }

        .device-list { background:#fff; border-radius:12px; padding:25px; box-shadow:0 4px 25px rgba(0,0,0,0.06); }
        .device-list h3 { color:#37474f; margin-bottom:15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        
        .device-row { display:flex; align-items:center; justify-content:space-between; padding:15px 0; border-bottom:1px solid #f0f0f0; gap:15px; }
        .device-row:last-child { border-bottom:none; }
        .device-row .icon { font-size:2rem; width:50px; text-align:center; }
        .device-row .info { flex:1; }
        .device-row .info strong { color:#333; font-size: 1.05rem; }
        .device-row .info small { color:#888; display:block; margin-top:4px; font-size: 0.8rem; }
        
        .btn-remove { background:#ffebee; color:#c62828; padding:8px 14px; border-radius:6px; text-decoration:none; font-size:0.85rem; font-weight:600; transition: 0.3s; }
        .btn-remove:hover { background:#ffcdd2; }
        
        .badge-type { display:inline-block; padding:2px 8px; border-radius:12px; font-size:0.7rem; font-weight:700; margin-left:6px; vertical-align: middle; }
        .empty-state { text-align:center; color:#999; padding:40px 20px; }
        .empty-state i { font-size:3rem; display:block; margin-bottom:15px; color:#ddd; }
        
        .message { padding:15px 20px; border-radius:8px; margin-bottom:20px; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .btn { padding:10px 20px; color:#fff; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-weight:600; border:none; cursor:pointer; transition: 0.3s; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="img/logo.png" alt="MA-Life Logo"></div>
            <h2>
                <?php 
                if ($user_role === 'admin') echo 'Administrator';
                elseif ($user_role === 'healthworker') echo 'Health Worker';
                else echo '<i class="fas fa-heart"></i> Mother Portal'; 
                ?>
            </h2>
            <nav>
                <a href="<?= $dash_url ?>"><i class="fas fa-home"></i> Dashboard</a>
                <a href="<?= $notes_url ?>"><i class="fas fa-sticky-note"></i> My Notes & Files</a>
                <a href="link_devices.php" class="active"><i class="fas fa-link"></i> Link Devices</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <!-- Top Bar -->
    <div class="topbar">
        <div class="header"><h2><i class="fas fa-link"></i> LINK DEVICES</h2></div>
    </div>

    <!-- Main -->
    <div class="main">
        <div class="link-header">
            <h2>Connect a Device</h2>
            <a href="<?= $dash_url ?>" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="link-grid">
            <!-- QR Card -->
            <div class="qr-card">
                <h3><i class="fas fa-qrcode"></i> Scan to Link</h3>
                <p>Point your phone or tablet camera at the code below.</p>
                <img src="<?= htmlspecialchars($qr_src) ?>" alt="Pairing QR code">
                <div class="pair-url"><?= htmlspecialchars($pair_url) ?></div>
                
                <!-- Smartwatch Manual Code -->
                <div class="watch-code-box">
                    <h4><i class="fas fa-clock"></i> Linking a Smartwatch?</h4>
                    <p style="font-size: 0.8rem; margin-bottom: 5px;">On your watch browser, go to:<br><strong><?= BASE_URL ?>pair.php</strong></p>
                    <p style="font-size: 0.8rem; margin: 0;">Then enter this code:</p>
                    <div class="code"><?= htmlspecialchars($short_code) ?></div>
                </div>
            </div>

            <!-- Device List -->
            <div class="device-list">
                <h3><i class="fas fa-mobile-alt"></i> Your Linked Devices (<?= count($devices) ?>)</h3>

                <?php if (empty($devices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-mobile"></i>
                        <p style="margin-top:10px;">No devices linked yet.</p>
                        <p style="font-size:0.85rem; margin-top:5px;">Scan the QR code or use the code to link your first device.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($devices as $dev): ?>
                        <?php
                        $icon = 'fa-mobile-alt';
                        if ($dev['device_type'] === 'tablet') $icon = 'fa-tablet-alt';
                        elseif ($dev['device_type'] === 'watch') $icon = 'fa-clock';
                        elseif ($dev['device_type'] === 'desktop') $icon = 'fa-desktop';
                        ?>
                        <div class="device-row">
                            <div class="icon"><i class="fas <?= $icon ?>"></i></div>
                            <div class="info">
                                <strong><?= htmlspecialchars($dev['device_name'] ?: 'Unnamed Device') ?></strong>
                                <span class="badge-type"><?= ucfirst($dev['device_type']) ?></span>
                                <small>
                                    Linked: <?= date('d M Y, H:i', strtotime($dev['linked_at'])) ?>
                                    &nbsp;•&nbsp; IP: <?= htmlspecialchars($dev['ip_address']) ?>
                                </small>
                            </div>
                            <a href="?remove=<?= (int)$dev['id'] ?>"
                               class="btn-remove"
                               onclick="return confirm('Are you sure you want to remove this device?');">
                                <i class="fas fa-trash"></i> Remove
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>