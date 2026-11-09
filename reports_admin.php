<?php
// reports_admin.php - System Reports
require_once 'config.php';
require_once 'sms_queue.php';
requireRole('admin');

$db = getDB();
$stats = array();

// ---- User counts by role ----
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stats['users'] = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['users'][$row['role']] = $row['count'];
}

// ---- Mothers ----
$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$total_mothers = $stmt->fetchColumn();

// ---- Children ----
$stmt = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) as female
    FROM children
");
$children_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ---- Appointments ----
$stmt = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments
");
$appt_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ---- Immunizations ----
$stmt = $db->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as completed
    FROM immunizations
");
$imm_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ---- Health workers ----
$stmt = $db->query("SELECT COUNT(*) FROM health_workers");
$total_workers = $stmt->fetchColumn();

// ---- Notifications ----
$notif_stats = ma_life_queue_stats($db);

// Helper
function val($arr, $key, $default = 0) {
    return isset($arr[$key]) ? $arr[$key] : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: #37474f; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .report-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .report-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .report-card .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .report-card .header h3 { color: #37474f; font-size: 1rem; }
        .report-card .header i { font-size: 1.5rem; color: #00897b; }
        .report-card .stat-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
        .report-card .stat-item:last-child { border-bottom: none; }
        .report-card .stat-item .label { color: #666; font-size: 0.9rem; }
        .report-card .stat-item .value { font-weight: 600; color: #37474f; }
        .report-card .stat-item .value.highlight { color: #00897b; font-size: 1.2rem; }
        .btn { padding: 10px 20px; background: #00897b; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; }
        .btn-primary { background: #00897b; }
        .btn-primary:hover { background: #00695c; color: white; }
        .btn-export { padding: 10px 20px; background: #37474f; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-export.green { background: #00897b; }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="image/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%2300809b%22/%3E%3C/svg%3E'"></div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
                <a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
                <a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php" class="active"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-chart-bar"></i> SYSTEM REPORTS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
            <h2>System Analytics & Reports</h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="btn-export green" onclick="window.print()"><i class="fas fa-file-pdf"></i> Print / PDF</button>
                <a href="admin_notifications.php" class="btn-export"><i class="fas fa-bell"></i> Notifications Log</a>
            </div>
        </div>

        <div class="report-grid">
            <!-- Users -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-users" style="color:#00897b;"></i> User Statistics</h3>
                    <i class="fas fa-users"></i>
                </div>
                <?php if (!empty($stats['users'])): ?>
                    <?php foreach ($stats['users'] as $role => $count): ?>
                        <div class="stat-item">
                            <span class="label"><?php echo ucfirst($role); ?></span>
                            <span class="value"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="stat-item" style="border-top:2px solid #f0f0f0; margin-top:10px; padding-top:10px;">
                        <span class="label"><strong>Total Users</strong></span>
                        <span class="value highlight"><?php echo array_sum($stats['users']); ?></span>
                    </div>
                <?php else: ?>
                    <p style="color:#999;">No users yet.</p>
                <?php endif; ?>
            </div>

            <!-- Mothers & Children -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-female" style="color:#ec407a;"></i> Mothers & Children</h3>
                    <i class="fas fa-female"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Mothers</span>
                    <span class="value highlight"><?php echo $total_mothers; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Total Children</span>
                    <span class="value highlight"><?php echo val($children_stats, 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Male Children</span>
                    <span class="value"><?php echo val($children_stats, 'male', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Female Children</span>
                    <span class="value"><?php echo val($children_stats, 'female', 0); ?></span>
                </div>
            </div>

            <!-- Appointments -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-calendar" style="color:#0d47a1;"></i> Appointments</h3>
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total</span>
                    <span class="value highlight"><?php echo val($appt_stats, 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Scheduled</span>
                    <span class="value"><?php echo val($appt_stats, 'scheduled', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Completed</span>
                    <span class="value"><?php echo val($appt_stats, 'completed', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Cancelled</span>
                    <span class="value" style="color:#c62828;"><?php echo val($appt_stats, 'cancelled', 0); ?></span>
                </div>
            </div>

            <!-- Immunizations -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-syringe" style="color:#2e7d32;"></i> Immunizations</h3>
                    <i class="fas fa-syringe"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Records</span>
                    <span class="value highlight"><?php echo val($imm_stats, 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Completed</span>
                    <span class="value" style="color:#2e7d32;"><?php echo val($imm_stats, 'completed', 0); ?></span>
                </div>
            </div>

            <!-- Health Workers -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-user-md" style="color:#0288d1;"></i> Health Workers</h3>
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Health Workers</span>
                    <span class="value highlight"><?php echo $total_workers; ?></span>
                </div>
            </div>

            <!-- Notifications -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-bell" style="color:#f57c00;"></i> Notifications</h3>
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Sent</span>
                    <span class="value highlight"><?php echo $notif_stats['sent']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Queued</span>
                    <span class="value" style="color:#f57c00;"><?php echo $notif_stats['queued']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Failed</span>
                    <span class="value" style="color:#c62828;"><?php echo $notif_stats['failed']; ?></span>
                </div>
                <div class="stat-item" style="border-top:2px solid #f0f0f0; margin-top:10px; padding-top:10px;">
                    <span class="label">View Details</span>
                    <span class="value"><a href="admin_notifications.php" style="color:#00897b;">Open Log →</a></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>