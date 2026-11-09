<?php
// administrator.php - Admin Dashboard
require_once 'config.php';
require_once 'upload_helper.php';
require_once 'sms_queue.php';
requireRole('admin');

$user = getUserById($_SESSION['user_id']);
$db = getDB();

$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$motherCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM children");
$childCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM health_workers");
$workerCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status != 'cancelled'");
$stmt->execute();
$appointmentCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

try {
    $stmt = $db->query("SELECT COUNT(*) FROM notifications_log WHERE DATE(created_at) = CURDATE()");
    $notificationsToday = $stmt->fetchColumn();
} catch (Exception $e) { $notificationsToday = 0; }

try {
    $stmt = $db->query("SELECT COUNT(*) FROM documents");
    $documentCount = $stmt->fetchColumn();
} catch (Exception $e) { $documentCount = 0; }

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notes WHERE user_id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    $notesCount = $stmt->fetchColumn();
} catch (Exception $e) { $notesCount = 0; }

// Unread shared notes count
$unread_notes = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notes WHERE shared_with_id = ? AND is_read = 0");
    $stmt->execute(array($_SESSION['user_id']));
    $unread_notes = $stmt->fetchColumn();
} catch (Exception $e) { $unread_notes = 0; }

$queue_stats = ma_life_queue_stats($db);
$session_remaining = getSessionRemaining();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(7, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #37474f; }
        .stat-card .label { color: #666; font-size: 0.85rem; margin-top: 5px; }
        .stat-card i { font-size: 2rem; color: #00897b; margin-bottom: 10px; }
        .stat-card.queued .number { color: #f57c00; }
        .stat-card.queued i { color: #f57c00; }
        .stat-card.docs i { color: #1976d2; }
        .stat-card.docs .number { color: #1976d2; }
        .stat-card.notes i { color: #f57c00; }
        .stat-card.notes .number { color: #f57c00; }
        .welcome-banner { background: linear-gradient(135deg, #00897b, #00695c); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .welcome-banner h2 { font-weight: 400; }
        .welcome-banner h2 strong { font-weight: 700; }
        .admin-home-content { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .admin-home-content .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .admin-home-content .page-header h2 { color: #0A4C54; font-weight: 700; }
        .admin-home-content .welcome-text { font-size: 1.05rem; color: #555; padding: 20px 25px; background: #e0f2f1; border-radius: 12px; border-left: 4px solid #0A4C54; margin-bottom: 20px; }
        .admin-home-content .welcome-text strong { color: #0A4C54; }
        .admin-home-content .quick-actions { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .admin-home-content .quick-action-btn { padding: 10px 20px; background: #0A4C54; color: white; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600; }
        .admin-home-content .quick-action-btn:hover { background: #146b76; color: white; }
        .admin-home-content .quick-action-btn.green { background: #2e7d32; }
        .admin-home-content .quick-action-btn.orange { background: #f57c00; }
        .admin-home-content .quick-action-btn.blue { background: #1976d2; }
        .admin-home-content .quick-action-btn.purple { background: #7b1fa2; }
        .admin-home-content .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px; }
        .admin-home-content .card { background: #e0f2f1; padding: 25px; border-radius: 12px; text-align: center; transition: all 0.3s ease; cursor: pointer; border: 2px solid transparent; position: relative; }
        .admin-home-content .card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); border-color: #0A4C54; background: white; }
        .admin-home-content .card i { font-size: 3rem; color: #0A4C54; margin-bottom: 12px; background: white; padding: 15px; border-radius: 50%; }
        .admin-home-content .card h3 { color: #0A4C54; margin-bottom: 5px; font-size: 1.1rem; }
        .admin-home-content .card p { color: #666; font-size: 0.85rem; line-height: 1.5; }
        .admin-home-content .card .count { display: inline-block; background: #0A4C54; color: white; padding: 2px 12px; border-radius: 20px; font-size: 0.75rem; margin-top: 10px; }
        .admin-home-content .card .new-badge { position: absolute; top: 10px; right: 10px; background: #f57c00; color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 12px; }
        .sidebar nav a .badge-count { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #f57c00; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
        .session-timer { position: fixed; top: 75px; right: 20px; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-size: 0.8rem; color: #666; z-index: 60; }
        .session-timer i { color: #00897b; margin-right: 5px; }
        @media (max-width: 1600px) { .dashboard-stats { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1200px) { .dashboard-stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .dashboard-stats { grid-template-columns: repeat(2, 1fr); }
            .admin-home-content .page-header { flex-direction: column; gap: 10px; }
            .admin-home-content .card-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 550px) { .dashboard-stats { grid-template-columns: 1fr; } }
        .profile-picture-widget { position: relative; display: inline-block; }
        .profile-picture-circle { width: 100px; height: 100px; border-radius: 50%; background: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #0A4C54; border: 4px solid rgba(255,255,255,0.8); box-shadow: 0 4px 20px rgba(0,0,0,0.2); cursor: pointer; transition: all 0.3s ease; }
        .profile-picture-circle img { width: 100%; height: 100%; object-fit: cover; }
        .profile-picture-widget .camera-btn { position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: #0A4C54; color: #fff; border: 3px solid #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; transition: 0.3s; }
        .profile-picture-widget .camera-btn:hover { background: #146b76; transform: scale(1.1); }
        .profile-picture-circle:hover { border-color: #ffb74d; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo" style="padding: 20px; display: flex; justify-content: center;">
                <?php include 'get_profile_picture.php'; ?>
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
                <a href="admin_notes.php" style="position:relative;"><i class="fas fa-sticky-note"></i> My Notes & Files
                    <?php if ($unread_notes > 0): ?><span class="badge-count"><?php echo $unread_notes; ?></span><?php endif; ?>
                </a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
                <a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
                <a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_documents.php"><i class="fas fa-folder-open"></i> All Documents</a>
                <a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-user-shield"></i> ADMINISTRATOR DASHBOARD</h2>
        </div>
    </div>

    <div class="session-timer" id="sessionTimer">
        <i class="fas fa-clock"></i> Session: <span id="timerText">--:--</span>
    </div>

    <div class="main">
        <div class="welcome-banner">
            <h2>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>! 👋</h2>
            <p style="opacity:0.9; margin-top:5px;"><?php echo date('l, F j, Y'); ?> · Luzira Division</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-female"></i>
                <div class="number"><?php echo $motherCount; ?></div>
                <div class="label">Mothers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-child"></i>
                <div class="number"><?php echo $childCount; ?></div>
                <div class="label">Children</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-md"></i>
                <div class="number"><?php echo $workerCount; ?></div>
                <div class="label">Health Workers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <div class="number"><?php echo $appointmentCount; ?></div>
                <div class="label">Today's Appointments</div>
            </div>
            <div class="stat-card docs">
                <i class="fas fa-folder-open"></i>
                <div class="number"><?php echo $documentCount; ?></div>
                <div class="label">Documents</div>
            </div>
            <div class="stat-card notes">
                <i class="fas fa-sticky-note"></i>
                <div class="number"><?php echo $notesCount + $unread_notes; ?></div>
                <div class="label">Notes</div>
            </div>
            <div class="stat-card queued">
                <i class="fas fa-hourglass-half"></i>
                <div class="number"><?php echo $queue_stats['queued']; ?></div>
                <div class="label">SMS Queued</div>
            </div>
        </div>

        <div class="admin-home-content">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Admin Overview</h2>
                <span style="color:#666; font-size:0.9rem;"><i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?></span>
            </div>

            <div class="welcome-text">
                <strong>Welcome to the MA-LIFE Administration Panel — Luzira Division</strong><br>
                Manage users, health workers, mothers, children, appointments, notes, documents, and system settings.
            </div>

            <div class="quick-actions">
                <a href="admin_notes.php" class="quick-action-btn purple"><i class="fas fa-sticky-note"></i> My Notes
                    <?php if ($unread_notes > 0): ?>(<?php echo $unread_notes; ?> new)<?php endif; ?>
                </a>
                <a href="manage_users_admin.php" class="quick-action-btn"><i class="fas fa-user-plus"></i> Manage Users</a>
                <a href="health_workers_admin.php" class="quick-action-btn green"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="admin_documents.php" class="quick-action-btn blue"><i class="fas fa-folder-open"></i> All Documents</a>
                <a href="reports_admin.php" class="quick-action-btn orange"><i class="fas fa-file-pdf"></i> Generate Report</a>
            </div>

            <div class="card-grid">
                <div class="card" onclick="location.href='admin_notes.php'">
                    <?php if ($unread_notes > 0): ?><span class="new-badge"><?php echo $unread_notes; ?> NEW</span><?php endif; ?>
                    <i class="fas fa-sticky-note"></i>
                    <h3>My Notes & Files</h3>
                    <p>Write notes, upload documents & pictures, receive from health workers.</p>
                    <span class="count">Total: <?php echo $notesCount + $unread_notes; ?></span>
                </div>
                <div class="card" onclick="location.href='manage_users_admin.php'">
                    <i class="fas fa-users"></i>
                    <h3>Manage Users</h3>
                    <p>View, edit, and manage all system users.</p>
                    <span class="count">Total: <?php echo $totalUsers; ?></span>
                </div>
                <div class="card" onclick="location.href='health_workers_admin.php'">
                    <i class="fas fa-user-md"></i>
                    <h3>Health Workers</h3>
                    <p>Manage health worker profiles and assignments.</p>
                    <span class="count">Active: <?php echo $workerCount; ?></span>
                </div>
                <div class="card" onclick="location.href='mothers_admin.php'">
                    <i class="fas fa-female"></i>
                    <h3>Mothers</h3>
                    <p>View and manage all registered mothers.</p>
                    <span class="count">Total: <?php echo $motherCount; ?></span>
                </div>
                <div class="card" onclick="location.href='children_admin.php'">
                    <i class="fas fa-child"></i>
                    <h3>Children</h3>
                    <p>View and manage all registered children.</p>
                    <span class="count">Total: <?php echo $childCount; ?></span>
                </div>
                <div class="card" onclick="location.href='appointments_admin.php'">
                    <i class="fas fa-calendar"></i>
                    <h3>Appointments</h3>
                    <p>Manage all scheduled appointments.</p>
                    <span class="count">Today: <?php echo $appointmentCount; ?></span>
                </div>
                <div class="card" onclick="location.href='admin_documents.php'">
                    <i class="fas fa-folder-open"></i>
                    <h3>All Documents</h3>
                    <p>Browse and download all uploaded documents.</p>
                    <span class="count">Total: <?php echo $documentCount; ?></span>
                </div>
                <div class="card" onclick="location.href='admin_notifications.php'">
                    <i class="fas fa-bell"></i>
                    <h3>Notifications</h3>
                    <p>Monitor SMS and push notifications.</p>
                    <span class="count">Queued: <?php echo $queue_stats['queued']; ?></span>
                </div>
                <div class="card" onclick="location.href='reports_admin.php'">
                    <i class="fas fa-chart-bar"></i>
                    <h3>System Reports</h3>
                    <p>View system analytics and statistics.</p>
                    <span class="count">Reports</span>
                </div>
                <div class="card" onclick="location.href='settings.php'">
                    <i class="fas fa-cog"></i>
                    <h3>System Settings</h3>
                    <p>Configure system preferences.</p>
                    <span class="count">v1.0.0</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        var remainingSeconds = <?php echo $session_remaining; ?>;
        function updateTimer() {
            if (remainingSeconds <= 0) {
                document.getElementById('timerText').textContent = 'Expired';
                document.getElementById('sessionTimer').style.background = '#ffebee';
                setTimeout(function() { window.location.href = 'login.php?expired=1'; }, 2000);
                return;
            }
            var mins = Math.floor(remainingSeconds / 60);
            var secs = remainingSeconds % 60;
            document.getElementById('timerText').textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            if (remainingSeconds < 120) {
                document.getElementById('sessionTimer').style.background = '#ffebee';
                document.getElementById('sessionTimer').style.color = '#c62828';
            }
            remainingSeconds--;
        }
        updateTimer();
        setInterval(updateTimer, 1000);
        (function() {
            fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) window.location.replace('login.php?expired=1');
                    else if (data.role !== 'admin') window.location.replace('login.php?security=1');
                })
                .catch(err => console.error('Session check failed:', err));
        })();
    </script>
</body>
</html>