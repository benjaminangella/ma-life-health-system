<?php
// healthworker.php - Health Worker Dashboard
require_once 'config.php';
require_once 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'healthworker') {
    header('Location: login.php');
    exit();
}

$user = getUserById($_SESSION['user_id']);
$db = getDB();

$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$motherCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM children");
$childCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status != 'cancelled'");
$stmt->execute();
$appointmentCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM antenatal_visits");
$antenatalCount = $stmt->fetchColumn();

try {
    $stmt = $db->query("SELECT COUNT(*) FROM documents");
    $documentCount = $stmt->fetchColumn();
} catch (Exception $e) { $documentCount = 0; }

// Unread shared notes count
$unread_notes = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notes WHERE shared_with_id = ? AND is_read = 0");
    $stmt->execute(array($_SESSION['user_id']));
    $unread_notes = $stmt->fetchColumn();
} catch (Exception $e) { $unread_notes = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Worker Dashboard - MA-LIFE</title>
    <link rel="stylesheet" href="health.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #00796b; }
        .stat-card .label { color: #666; font-size: 0.9rem; margin-top: 5px; }
        .stat-card i { font-size: 2rem; color: #00796b; margin-bottom: 10px; }
        .stat-card.docs i { color: #1976d2; }
        .stat-card.docs .number { color: #1976d2; }
        .stat-card.notes i { color: #7b1fa2; }
        .stat-card.notes .number { color: #7b1fa2; }
        .stat-card.notes.has-unread { background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%); border: 2px solid #7b1fa2; }
        .stat-card.notes.has-unread .number { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .welcome-banner { background: linear-gradient(135deg, #00796b, #004d40); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .welcome-banner h2 { font-weight: 400; }
        .welcome-banner h2 strong { font-weight: 700; }
        .health-home-content { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); animation: fadeIn 0.5s ease; }
        .health-home-content .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .health-home-content .page-header h2 { color: #00796b; font-weight: 700; }
        .health-home-content .page-header .date { color: #666; font-size: 0.9rem; }
        .health-home-content .welcome-text { font-size: 1.05rem; color: #555; padding: 20px 25px; background: #e0f2f1; border-radius: 12px; border-left: 4px solid #00796b; margin-bottom: 20px; }
        .health-home-content .welcome-text strong { color: #00796b; }
        .health-home-content .quick-actions { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .health-home-content .quick-action-btn { padding: 10px 20px; background: #00796b; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; position: relative; }
        .health-home-content .quick-action-btn:hover { background: #004d40; transform: translateY(-2px); }
        .health-home-content .quick-action-btn.pink { background: #ad1457; }
        .health-home-content .quick-action-btn.pink:hover { background: #880e4f; }
        .health-home-content .quick-action-btn.blue { background: #1976d2; }
        .health-home-content .quick-action-btn.purple { background: #7b1fa2; }
        .health-home-content .quick-action-btn.purple:hover { background: #4a148c; }
        .health-home-content .quick-action-btn .notif-dot { position: absolute; top: -5px; right: -5px; background: #f57c00; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 10px; border: 2px solid white; }
        .health-home-content .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px; }
        .health-home-content .card { background: #e0f2f1; padding: 25px; border-radius: 12px; text-align: center; transition: all 0.3s ease; cursor: pointer; border: 2px solid transparent; position: relative; }
        .health-home-content .card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); border-color: #00796b; background: white; }
        .health-home-content .card i { font-size: 3rem; color: #00796b; margin-bottom: 12px; background: white; padding: 15px; border-radius: 50%; }
        .health-home-content .card h3 { color: #00796b; margin-bottom: 5px; font-size: 1.1rem; }
        .health-home-content .card p { color: #666; font-size: 0.85rem; line-height: 1.5; }
        .health-home-content .card .count { display: inline-block; background: #00796b; color: white; padding: 2px 12px; border-radius: 20px; font-size: 0.75rem; margin-top: 10px; }
        .health-home-content .card .new-badge { position: absolute; top: 10px; right: 10px; background: #f57c00; color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 12px; animation: pulse 1.5s infinite; }
        .health-home-content .card.notes-card { background: linear-gradient(135deg, #f3e5f5 0%, #e0f2f1 100%); }
        .health-home-content .card.notes-card i { color: #7b1fa2; }
        .health-home-content .card.notes-card h3 { color: #7b1fa2; }
        .health-home-content .card.notes-card .count { background: #7b1fa2; }
        .sidebar nav a .badge-count { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #f57c00; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 1400px) { .dashboard-stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .dashboard-stats { grid-template-columns: repeat(2, 1fr); }
            .health-home-content .page-header { flex-direction: column; gap: 10px; text-align: center; }
            .health-home-content .card-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 550px) { .dashboard-stats { grid-template-columns: 1fr; } }
        .profile-picture-widget { position: relative; display: inline-block; }
        .profile-picture-circle { width: 100px; height: 100px; border-radius: 50%; background: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #00796b; border: 4px solid rgba(255,255,255,0.8); box-shadow: 0 4px 20px rgba(0,0,0,0.2); cursor: pointer; transition: all 0.3s ease; }
        .profile-picture-circle img { width: 100%; height: 100%; object-fit: cover; }
        .profile-picture-widget .camera-btn { position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: #00796b; color: #fff; border: 3px solid #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; transition: 0.3s; }
        .profile-picture-widget .camera-btn:hover { background: #004d40; transform: scale(1.1); }
        .profile-picture-circle:hover { border-color: #ffb74d; }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo" style="padding: 20px; display: flex; justify-content: center;">
                <?php include 'get_profile_picture.php'; ?>
            </div>
            <h2>Health Worker</h2>
            <nav>
                <a href="healthworker.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
                <a href="admin_notes.php" style="position:relative;"><i class="fas fa-sticky-note"></i> My Notes & Files
                    <?php if ($unread_notes > 0): ?><span class="badge-count"><?php echo $unread_notes; ?></span><?php endif; ?>
                </a>
                <a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
                <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Health Records</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="hw_documents.php"><i class="fas fa-folder-open"></i> Documents</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-user-md"></i> HEALTH WORKER DASHBOARD</h2>
        </div>
    </div>

    <div class="main">
        <div class="welcome-banner">
            <h2>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Health Worker'); ?></strong>! 👋</h2>
            <p style="opacity:0.9; margin-top:5px;"><?php echo date('l, F j, Y'); ?> · Luzira Division</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-female"></i>
                <div class="number" id="motherCount"><?php echo $motherCount; ?></div>
                <div class="label">Total Mothers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-child"></i>
                <div class="number" id="childCount"><?php echo $childCount; ?></div>
                <div class="label">Total Children</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <div class="number" id="appointmentCount"><?php echo $appointmentCount; ?></div>
                <div class="label">Today's Appointments</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-heartbeat"></i>
                <div class="number" id="antenatalCount"><?php echo $antenatalCount; ?></div>
                <div class="label">Antenatal Visits</div>
            </div>
            <div class="stat-card docs">
                <i class="fas fa-folder-open"></i>
                <div class="number"><?php echo $documentCount; ?></div>
                <div class="label">Documents</div>
            </div>
            <div class="stat-card notes <?php echo $unread_notes > 0 ? 'has-unread' : ''; ?>">
                <i class="fas fa-sticky-note"></i>
                <div class="number"><?php echo $unread_notes; ?></div>
                <div class="label"><?php echo $unread_notes > 0 ? 'Unread Notes' : 'New Notes'; ?></div>
            </div>
        </div>

        <div class="health-home-content">
            <div class="page-header">
                <h2><i class="fas fa-stethoscope" style="color:#00796b;"></i> Health Worker Overview</h2>
                <span class="date"><i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?></span>
            </div>

            <div class="welcome-text">
                <strong>Welcome to your Health Worker Dashboard — Luzira Division</strong><br>
                Register mothers, manage antenatal care, track child health records, and share notes with admin.
            </div>

            <div class="quick-actions">
                <a href="admin_notes.php" class="quick-action-btn purple">
                    <i class="fas fa-sticky-note"></i> My Notes
                    <?php if ($unread_notes > 0): ?><span class="notif-dot"><?php echo $unread_notes; ?></span><?php endif; ?>
                </a>
                <a href="register_mother.php" class="quick-action-btn">
                    <i class="fas fa-plus-circle"></i> Register Mother
                </a>
                <a href="appointments.php" class="quick-action-btn pink">
                    <i class="fas fa-calendar-plus"></i> Schedule Appointment
                </a>
                <a href="hw_documents.php" class="quick-action-btn blue">
                    <i class="fas fa-folder-open"></i> View Documents
                </a>
            </div>

            <div class="card-grid">
                <div class="card notes-card" onclick="location.href='admin_notes.php'">
                    <?php if ($unread_notes > 0): ?><span class="new-badge"><?php echo $unread_notes; ?> NEW</span><?php endif; ?>
                    <i class="fas fa-sticky-note"></i>
                    <h3>My Notes & Files</h3>
                    <p>Write notes, upload documents & pictures, share with admin.</p>
                    <span class="count"><?php echo $unread_notes > 0 ? $unread_notes . ' Unread' : 'Notes'; ?></span>
                </div>
                <div class="card" onclick="location.href='register_mother.php'">
                    <i class="fas fa-female"></i>
                    <h3>Register Mother</h3>
                    <p>Add new mothers to the system with photo and documents.</p>
                    <span class="count">Total: <?php echo $motherCount; ?></span>
                </div>
                <div class="card" onclick="location.href='antenatal.php'">
                    <i class="fas fa-heartbeat"></i>
                    <h3>Antenatal Care</h3>
                    <p>Manage antenatal visits, track pregnancy progress.</p>
                    <span class="count">Visits: <?php echo $antenatalCount; ?></span>
                </div>
                <div class="card" onclick="location.href='child_records.php'">
                    <i class="fas fa-child"></i>
                    <h3>Child Records</h3>
                    <p>Access child health records with photos and documents.</p>
                    <span class="count">Children: <?php echo $childCount; ?></span>
                </div>
                <div class="card" onclick="location.href='hw_documents.php'">
                    <i class="fas fa-folder-open"></i>
                    <h3>All Documents</h3>
                    <p>Browse and download all uploaded documents.</p>
                    <span class="count">Total: <?php echo $documentCount; ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadStats() {
            try {
                const response = await fetch('api/stats.php');
                if (response.ok) {
                    const data = await response.json();
                    if(document.getElementById('motherCount')) document.getElementById('motherCount').textContent = data.mothers || 0;
                    if(document.getElementById('childCount')) document.getElementById('childCount').textContent = data.children || 0;
                    if(document.getElementById('appointmentCount')) document.getElementById('appointmentCount').textContent = data.appointments || 0;
                    if(document.getElementById('antenatalCount')) document.getElementById('antenatalCount').textContent = data.antenatal || 0;
                }
            } catch(e) {}
        }
        setInterval(loadStats, 30000);

        (function() {
            fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) window.location.replace('login.php?expired=1');
                    else if (data.role !== 'healthworker') window.location.replace('login.php?security=1');
                })
                .catch(err => console.error('Session check failed:', err));
        })();
    </script>
</body>
</html>