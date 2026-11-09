<?php
// mother_dashboard.php - Mother Dashboard
require_once 'config.php';
require_once 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mother') {
    header('Location: login.php');
    exit();
}

$user = getUserById($_SESSION['user_id']);
$db = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT id FROM mothers WHERE user_id = ?");
$stmt->execute([$user_id]);
$mother = $stmt->fetch(PDO::FETCH_ASSOC);

$stats = ['children' => 0, 'immunizations' => 0, 'appointments' => 0, 'notes' => 0];

if ($mother) {
    $mother_id = $mother['id'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM children WHERE mother_id = ?");
    $stmt->execute([$mother_id]);
    $stats['children'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM immunizations i JOIN children c ON i.child_id = c.id WHERE c.mother_id = ?");
    $stmt->execute([$mother_id]);
    $stats['immunizations'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND appointment_date >= NOW() AND status NOT IN ('completed', 'cancelled')");
    $stmt->execute([$user_id]);
    $stats['appointments'] = $stmt->fetchColumn();
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['notes'] = $stmt->fetchColumn();
} catch (Exception $e) { $stats['notes'] = 0; }

// Unread shared notes count
$unread_notes = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_notes WHERE shared_with_id = ? AND is_read = 0");
    $stmt->execute(array($user_id));
    $unread_notes = $stmt->fetchColumn();
} catch (Exception $e) { $unread_notes = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother Dashboard - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { min-height: 100vh; background: #fce4ec; }
        .dashboard-wrapper { min-height: 100vh; background: linear-gradient(135deg, rgba(173, 20, 87, 0.35), rgba(136, 14, 79, 0.50)), url('img/new-cp.png') center/cover no-repeat fixed; display: flex; flex-direction: column; position: relative; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: rgba(173, 20, 87, 0.92); backdrop-filter: blur(12px); color: white; overflow-y: auto; z-index: 100; box-shadow: 4px 0 30px rgba(0,0,0,0.2); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(136, 14, 79, 0.5); }
        .sidebar::-webkit-scrollbar-thumb { background: #f06292; border-radius: 10px; }
        .nav h2 { text-align: center; padding: 20px; background: rgba(136, 14, 79, 0.6); font-size: 20px; font-weight: 600; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .nav h2 i { color: #ffb74d; }
        .logo { display: flex; justify-content: center; align-items: center; padding: 20px; background: rgba(136, 14, 79, 0.3); }
        nav a { display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.05); transition: all 0.3s ease; font-size: 0.95rem; position: relative; }
        nav a:hover { background: rgba(136, 14, 79, 0.6); color: white; padding-left: 28px; }
        nav a i { width: 25px; margin-right: 10px; }
        nav a.active { background: rgba(136, 14, 79, 0.7); color: white; border-left: 4px solid #ffb74d; }
        nav a .badge-count { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #f57c00; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
        .topbar { margin-left: 250px; height: 70px; background: rgba(255,255,255,0.92); backdrop-filter: blur(12px); box-shadow: 0 2px 20px rgba(0,0,0,0.08); display: flex; align-items: center; padding: 0 30px; position: sticky; top: 0; z-index: 50; border-bottom: 1px solid rgba(173, 20, 87, 0.1); }
        .header { display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .header h2 { color: #ad1457; font-weight: 600; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        .header h2 i { background: #fce4ec; padding: 8px; border-radius: 50%; font-size: 1rem; }
        .header .user-info { display: flex; align-items: center; gap: 15px; color: #666; font-size: 0.9rem; }
        .header .user-info .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #ad1457, #880e4f); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; box-shadow: 0 2px 10px rgba(173, 20, 87, 0.3); }
        .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); position: relative; z-index: 1; }
        .welcome-banner { background: rgba(173, 20, 87, 0.85); backdrop-filter: blur(10px); color: white; padding: 30px 35px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 8px 40px rgba(0,0,0,0.15); position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); }
        .welcome-banner::after { content: '👶'; position: absolute; right: 40px; bottom: 10px; font-size: 4rem; opacity: 0.15; }
        .welcome-banner h2 { font-weight: 400; font-size: 1.5rem; position: relative; z-index: 1; }
        .welcome-banner h2 strong { font-weight: 700; }
        .welcome-banner p { opacity: 0.9; margin-top: 5px; position: relative; z-index: 1; }
        .welcome-banner .date-badge { display: inline-block; background: rgba(255,255,255,0.15); padding: 4px 16px; border-radius: 20px; font-size: 0.85rem; margin-top: 8px; backdrop-filter: blur(5px); }
        .dashboard-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.88); backdrop-filter: blur(10px); padding: 20px; border-radius: 16px; box-shadow: 0 4px 25px rgba(0,0,0,0.06); text-align: center; transition: all 0.4s ease; border: 1px solid rgba(255,255,255,0.3); position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 12px 50px rgba(173, 20, 87, 0.15); border-color: rgba(173, 20, 87, 0.2); }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #ad1457; line-height: 1.2; }
        .stat-card .label { color: #666; font-size: 0.9rem; margin-top: 5px; font-weight: 500; }
        .stat-card i { font-size: 2rem; color: #ad1457; margin-bottom: 10px; background: #fce4ec; padding: 12px; border-radius: 50%; display: inline-block; transition: all 0.4s; }
        .stat-card:hover i { transform: scale(1.1); background: #ad1457; color: white; }
        .stat-card.notes i { color: #7b1fa2; background: #f3e5f5; }
        .stat-card.notes .number { color: #7b1fa2; }
        .mother-home-content { background: rgba(255, 255, 255, 0.88); backdrop-filter: blur(10px); border-radius: 16px; padding: 25px; box-shadow: 0 4px 30px rgba(0,0,0,0.06); animation: fadeIn 0.6s ease; border: 1px solid rgba(255,255,255,0.3); }
        .mother-home-content .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .mother-home-content .page-header h2 { color: #ad1457; font-weight: 700; font-size: 1.3rem; }
        .mother-home-content .page-header .date { color: #666; font-size: 0.9rem; background: rgba(245, 245, 245, 0.8); padding: 5px 15px; border-radius: 20px; }
        .mother-home-content .welcome-text { font-size: 1.05rem; color: #555; padding: 20px 25px; background: rgba(252, 228, 236, 0.6); border-radius: 12px; border-left: 4px solid #ad1457; margin-bottom: 20px; backdrop-filter: blur(5px); }
        .mother-home-content .welcome-text strong { color: #ad1457; }
        .mother-home-content .quick-actions { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .mother-home-content .quick-action-btn { padding: 10px 24px; background: linear-gradient(135deg, #ad1457, #880e4f); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.95rem; box-shadow: 0 4px 15px rgba(173, 20, 87, 0.2); }
        .mother-home-content .quick-action-btn:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 8px 30px rgba(173, 20, 87, 0.3); }
        .mother-home-content .quick-action-btn.green { background: linear-gradient(135deg, #2e7d32, #1b5e20); box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2); }
        .mother-home-content .quick-action-btn.purple { background: linear-gradient(135deg, #7b1fa2, #4a148c); box-shadow: 0 4px 15px rgba(123, 31, 162, 0.2); }
        .mother-home-content .quick-action-btn.blue { background: linear-gradient(135deg, #1976d2, #0d47a1); box-shadow: 0 4px 15px rgba(25, 118, 210, 0.2); }
        .mother-home-content .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px; }
        .mother-home-content .card { background: rgba(252, 228, 236, 0.6); padding: 25px 20px; border-radius: 12px; text-align: center; transition: all 0.4s ease; cursor: pointer; border: 2px solid transparent; backdrop-filter: blur(5px); position: relative; }
        .mother-home-content .card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); border-color: #ad1457; background: rgba(255, 255, 255, 0.9); }
        .mother-home-content .card i { font-size: 2.8rem; color: #ad1457; margin-bottom: 12px; background: rgba(255, 255, 255, 0.8); padding: 15px; border-radius: 50%; display: inline-block; transition: all 0.4s; }
        .mother-home-content .card:hover i { transform: scale(1.1) rotate(-5deg); background: #ad1457; color: white; }
        .mother-home-content .card h3 { color: #ad1457; margin-bottom: 5px; font-size: 1.1rem; font-weight: 700; }
        .mother-home-content .card p { color: #666; font-size: 0.85rem; line-height: 1.5; }
        .mother-home-content .card .count { display: inline-block; background: #ad1457; color: white; padding: 3px 14px; border-radius: 20px; font-size: 0.75rem; margin-top: 10px; font-weight: 600; }
        .mother-home-content .card .new-badge { position: absolute; top: 10px; right: 10px; background: #f57c00; color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 12px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 992px) { .dashboard-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { width: 200px; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .topbar { margin-left: 0; padding: 0 20px; height: 60px; }
            .main { margin-left: 0; padding: 15px; }
            .dashboard-stats { grid-template-columns: 1fr; }
            .mother-home-content .page-header { flex-direction: column; gap: 10px; text-align: center; }
            .mother-home-content .card-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 550px) { .main { padding: 10px; } .stat-card .number { font-size: 1.8rem; } .mother-home-content { padding: 15px; } }
        .menu-toggle { display: none; background: none; border: none; color: #ad1457; font-size: 1.5rem; cursor: pointer; padding: 5px 10px; }
        @media (max-width: 768px) { .menu-toggle { display: block; } }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="sidebar" id="sidebar">
            <div class="nav">
                <div class="logo"><?php include 'get_profile_picture.php'; ?></div>
                <h2><i class="fas fa-heart"></i> Mother Portal</h2>
                <nav>
                    <a href="mother_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
                    <a href="admin_notes.php"><i class="fas fa-sticky-note"></i> My Notes & Files
                        <?php if ($unread_notes > 0): ?><span class="badge-count"><?php echo $unread_notes; ?></span><?php endif; ?>
                    </a>
                    <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                    
                    <!-- NEW LINK ADDED HERE -->
                    <a href="link_devices.php"><i class="fas fa-link"></i> Link Smart Device</a>
                    
                    <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                    <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                    <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>
        </div>

        <div class="topbar">
            <div class="header">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h2><i class="fas fa-female"></i> MOTHER DASHBOARD</h2>
                </div>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Mother') ?></span>
                    <span class="avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)) ?></span>
                </div>
            </div>
        </div>

        <div class="main">
            <div class="welcome-banner">
                <h2>👋 Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Mother') ?></strong>!</h2>
                <p>Empowering mothers in Luzira Division with quality healthcare for you and your children.</p>
                <div class="date-badge"><i class="far fa-calendar"></i> <?= date('l, F j, Y') ?></div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-child"></i>
                    <div class="number"><?= $stats['children'] ?></div>
                    <div class="label">My Children</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-syringe"></i>
                    <div class="number"><?= $stats['immunizations'] ?></div>
                    <div class="label">Immunizations</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <div class="number"><?= $stats['appointments'] ?></div>
                    <div class="label">Upcoming Appointments</div>
                </div>
                <div class="stat-card notes">
                    <i class="fas fa-sticky-note"></i>
                    <div class="number"><?= $stats['notes'] ?></div>
                    <div class="label">My Notes</div>
                </div>
            </div>

            <div class="mother-home-content">
                <div class="page-header">
                    <h2><i class="fas fa-female" style="color:#ad1457;"></i> Mother's Overview</h2>
                    <span class="date"><i class="far fa-calendar"></i> <?= date('l, F j, Y') ?></span>
                </div>

                <div class="welcome-text">
                    <strong>Welcome to your Maternal Health Portal — Luzira Division</strong><br>
                    Access your children's records, track immunizations, manage appointments, and save personal notes.
                </div>

                <div class="quick-actions">
                    <a href="admin_notes.php" class="quick-action-btn purple"><i class="fas fa-sticky-note"></i> My Notes</a>
                    <a href="register_child.php" class="quick-action-btn"><i class="fas fa-plus-circle"></i> Register Child</a>
                    <a href="appointments.php" class="quick-action-btn green"><i class="fas fa-calendar"></i> My Appointments</a>
                    <!-- NEW QUICK ACTION BUTTON ADDED HERE -->
                    <a href="link_devices.php" class="quick-action-btn blue"><i class="fas fa-link"></i> Link Smartwatch</a>
                </div>

                <div class="card-grid">
                    <div class="card" onclick="location.href='admin_notes.php'">
                        <?php if ($unread_notes > 0): ?><span class="new-badge"><?= $unread_notes ?> NEW</span><?php endif; ?>
                        <i class="fas fa-sticky-note"></i>
                        <h3>My Notes & Files</h3>
                        <p>Write personal notes, upload documents & pictures, share with your health worker.</p>
                        <span class="count">Total: <?= $stats['notes'] ?></span>
                    </div>
                    <div class="card" onclick="location.href='child_records.php'">
                        <i class="fas fa-child"></i>
                        <h3>My Children</h3>
                        <p>View all registered children, their health records, and growth milestones.</p>
                        <span class="count">Active: <?= $stats['children'] ?></span>
                    </div>
                    <div class="card" onclick="location.href='immunization.php'">
                        <i class="fas fa-syringe"></i>
                        <h3>Immunization</h3>
                        <p>Track immunization schedules, vaccine history, and upcoming due dates.</p>
                        <span class="count">Total: <?= $stats['immunizations'] ?></span>
                    </div>
                    <div class="card" onclick="location.href='appointments.php'">
                        <i class="fas fa-calendar"></i>
                        <h3>Appointments</h3>
                        <p>Schedule and manage health appointments for you and your children.</p>
                        <span class="count">Upcoming: <?= $stats['appointments'] ?></span>
                    </div>
                    <!-- NEW DASHBOARD CARD ADDED HERE -->
                    <div class="card" onclick="location.href='link_devices.php'">
                        <i class="fas fa-link" style="color:#1976d2; background:#e3f2fd;"></i>
                        <h3>Smart Devices</h3>
                        <p>Link your smartwatch, phone, or tablet to sync your health data.</p>
                        <span class="count">Connect Now</span>
                    </div>
                    <div class="card" onclick="location.href='reports.php'">
                        <i class="fas fa-heartbeat"></i>
                        <h3>Health Reports</h3>
                        <p>Access medical reports, test results, and health recommendations.</p>
                        <span class="count">View Reports</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) sidebar.classList.remove('open');
            }
        });
        setTimeout(function() { location.reload(); }, 300000);
        document.addEventListener('DOMContentLoaded', function() {
            const numbers = document.querySelectorAll('.stat-card .number');
            numbers.forEach(num => {
                const target = parseInt(num.textContent);
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) { num.textContent = target; clearInterval(timer); }
                    else { num.textContent = Math.floor(current); }
                }, 30);
            });
        });
        (function() {
            fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) window.location.replace('login.php?expired=1');
                    else if (data.role !== 'mother') window.location.replace('login.php?security=1');
                })
                .catch(err => console.error('Session check failed:', err));
        })();
    </script>
</body>
</html>