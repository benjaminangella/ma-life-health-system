<?php
require_once 'config.php';
requireRole('mother');

$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pair'])) {
    $device_type = isset($_POST['device_type']) ? trim($_POST['device_type']) : 'smartwatch';
    $device_name = isset($_POST['device_name']) ? trim($_POST['device_name']) : 'My Device';

    $token = '';
    for ($i = 0; $i < 32; $i++) $token .= dechex(mt_rand(0, 15));

    try {
        $stmt = $db->prepare("
            INSERT INTO ma_devices (user_id, device_type, device_name, device_token, paired_at, last_seen)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute(array($user_id, $device_type, $device_name, $token));
        $message = "Device paired! Copy this token:<br><code style='background:#e0f2f1; padding:10px; display:inline-block; border-radius:6px; font-size:1.1rem; user-select:all;'>$token</code>";
    } catch (Exception $e) {
        $error = 'Error pairing: ' . $e->getMessage();
    }
}

if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $stmt = $db->prepare("DELETE FROM ma_devices WHERE id = ? AND user_id = ?");
    $stmt->execute(array((int)$_GET['remove'], $user_id));
    $message = "Device removed.";
}

$stmt = $db->prepare("SELECT * FROM ma_devices WHERE user_id = ? ORDER BY paired_at DESC");
$stmt->execute(array($user_id));
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
$session_remaining = getSessionRemaining();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pair Device - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }
        body { background:#fce4ec; }
        .sidebar { position:fixed; width:250px; height:100vh; background:#ad1457; color:#fff; overflow-y:auto; z-index:100; }
        .nav h2 { text-align:center; padding:20px; background:#880e4f; font-size:20px; }
        .logo { display:flex; justify-content:center; align-items:center; padding:20px; }
        .logo img { width:100px; height:100px; border-radius:50%; border:4px solid #fff; object-fit:cover; }
        nav a { display:block; padding:14px 20px; color:#fff; text-decoration:none; border-bottom:1px solid rgba(255,255,255,0.1); }
        nav a:hover { background:#880e4f; padding-left:28px; }
        nav a.active { background:#880e4f; border-left:4px solid #ffb74d; }
        .topbar { margin-left:250px; height:70px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.1); display:flex; align-items:center; padding:0 30px; }
        .header h2 { color:#ad1457; }
        .main { margin-left:250px; padding:25px 30px; }
        .card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 2px 15px rgba(0,0,0,0.05); margin-bottom:20px; max-width:700px; }
        .card h3 { color:#ad1457; margin-bottom:15px; padding-bottom:10px; border-bottom:2px solid #fce4ec; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:600; color:#333; margin-bottom:5px; font-size:0.9rem; }
        .form-group input, .form-group select { width:100%; padding:10px 15px; border:2px solid #e0e0e0; border-radius:8px; font-size:0.95rem; }
        .form-group input:focus, .form-group select:focus { border-color:#ad1457; outline:none; }
        .btn { padding:10px 25px; background:#ad1457; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn:hover { background:#880e4f; color:#fff; }
        .btn-danger { background:#c62828; }
        .btn-danger:hover { background:#8e0000; }
        .message { padding:15px 20px; border-radius:8px; margin-bottom:20px; max-width:700px; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#fce4ec; color:#ad1457; padding:10px; text-align:left; font-size:0.85rem; }
        td { padding:10px; border-bottom:1px solid #f0f0f0; font-size:0.9rem; }
        @media (max-width:768px) { .sidebar { width:200px; } .topbar, .main { margin-left:200px; } }
        @media (max-width:550px) { .sidebar { width:0; overflow:hidden; } .topbar, .main { margin-left:0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="img/logo.png" alt="MA-LIFE" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%23ad1457%22/%3E%3C/svg%3E'"></div>
            <h2>Mother Portal</h2>
            <nav>
                <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="pair_device.php" class="active"><i class="fas fa-watch"></i> Pair Device</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>
    <div class="topbar"><div class="header"><h2><i class="fas fa-watch"></i> PAIR DEVICE</h2></div></div>
    <div class="main">
        <?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-plus-circle"></i> Pair a New Device</h3>
            <p style="color:#666; margin-bottom:15px;">Generate a token to link your smartwatch or phone with MA-LIFE.</p>
            <form method="POST">
                <input type="hidden" name="pair" value="1">
                <div class="form-group">
                    <label>Device Type</label>
                    <select name="device_type">
                        <option value="smartwatch">Smartwatch</option>
                        <option value="fitness_band">Fitness Band</option>
                        <option value="smartphone">Smartphone</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Device Name</label>
                    <input type="text" name="device_name" placeholder="e.g., Samsung Galaxy Watch" value="My Device">
                </div>
                <button type="submit" class="btn"><i class="fas fa-link"></i> Generate Token</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fas fa-list"></i> My Paired Devices</h3>
            <?php if (empty($devices)): ?>
                <p style="color:#999; text-align:center; padding:20px;"><i class="fas fa-watch" style="font-size:2rem; display:block; margin-bottom:10px;"></i> No devices paired yet.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Device</th><th>Type</th><th>Paired</th><th>Last Seen</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['device_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['device_type']); ?></td>
                                <td><?php echo date('d M Y', strtotime($d['paired_at'])); ?></td>
                                <td><?php echo $d['last_seen'] ? date('d M Y H:i', strtotime($d['last_seen'])) : 'Never'; ?></td>
                                <td><a href="?remove=<?php echo $d['id']; ?>" class="btn btn-danger" style="padding:5px 12px; font-size:0.8rem;" onclick="return confirm('Remove?');"><i class="fas fa-trash"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>