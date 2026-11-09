<?php
// admin_report.php - System Reports for PHP 5.3.5
require_once 'config.php';
requireRole('admin');

session_start();
$db = getDB();

// Get system statistics
$stats = array();

// User counts by role
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['users'][$row['role']] = $row['count'];
}

// Mother stats
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT marital_status) as statuses
    FROM mothers
");
$stats['mothers'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Child stats
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
        AVG(birth_weight) as avg_weight
    FROM children
");
$stats['children'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Appointment stats
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments
");
$stats['appointments'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent activities (last 30 days)
$stmt = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM audit_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stats['activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Immunization stats
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT vaccine_name) as vaccine_types
    FROM immunizations
");
$stats['immunizations'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper for safe array access
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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
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
        .btn-export { padding: 10px 20px; background: #37474f; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-export:hover { background: #263238; transform: translateY(-2px); }
        .btn-export.green { background: #00897b; }
        .btn-export.green:hover { background: #00695c; }
        .activity-list { max-height: 300px; overflow-y: auto; }
        .activity-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; }
        .activity-item .date { color: #666; font-size: 0.8rem; }
        .activity-item .count { font-weight: 600; color: #00897b; }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
            .report-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="img/logo.png" alt="MA-Life Logo">
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="admin_report.php" class="active"><i class="fas fa-chart-bar"></i> System Reports</a>
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
                <div>
                    <a href="administrator.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
        </div>
        <div class="page-header">
            <h2>System Analytics & Reports</h2>
            <div>
                <button class="btn-export green" data-notice="PDF report generation...">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn-export" data-notice="CSV export...">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="report-grid">
            <!-- User Statistics -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-users" style="color:#00897b;"></i> User Statistics</h3>
                    <i class="fas fa-users"></i>
                </div>
                <?php if(isset($stats['users'])): ?>
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
                    <span class="value highlight"><?php echo val($stats['mothers'], 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Total Children</span>
                    <span class="value highlight"><?php echo val($stats['children'], 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Male Children</span>
                    <span class="value"><?php echo val($stats['children'], 'male', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Female Children</span>
                    <span class="value"><?php echo val($stats['children'], 'female', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Avg Birth Weight</span>
                    <span class="value">
                        <?php 
                        $avg = val($stats['children'], 'avg_weight', null);
                        echo $avg ? number_format($avg, 2) . ' kg' : 'N/A'; 
                        ?>
                    </span>
                </div>
            </div>

            <!-- Appointments -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-calendar" style="color:#0d47a1;"></i> Appointments</h3>
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Appointments</span>
                    <span class="value highlight"><?php echo val($stats['appointments'], 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Scheduled</span>
                    <span class="value"><?php echo val($stats['appointments'], 'scheduled', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Confirmed</span>
                    <span class="value"><?php echo val($stats['appointments'], 'confirmed', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Completed</span>
                    <span class="value"><?php echo val($stats['appointments'], 'completed', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Cancelled</span>
                    <span class="value" style="color:#c62828;"><?php echo val($stats['appointments'], 'cancelled', 0); ?></span>
                </div>
            </div>

            <!-- Immunizations -->
            <div class="report-card">
                <div class="header">
                    <h3><i class="fas fa-syringe" style="color:#2e7d32;"></i> Immunizations</h3>
                    <i class="fas fa-syringe"></i>
                </div>
                <div class="stat-item">
                    <span class="label">Total Immunizations</span>
                    <span class="value highlight"><?php echo val($stats['immunizations'], 'total', 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Vaccine Types</span>
                    <span class="value"><?php echo val($stats['immunizations'], 'vaccine_types', 0); ?></span>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="report-card" style="grid-column: span 2;">
                <div class="header">
                    <h3><i class="fas fa-clock" style="color:#e65100;"></i> Recent Activity (Last 30 Days)</h3>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="activity-list">
                    <?php if (empty($stats['activities'])): ?>
                        <div style="text-align:center; padding:20px; color:#999;">
                            <i class="fas fa-chart-line" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                            No recent activity data available.
                        </div>
                    <?php else: ?>
                        <?php foreach ($stats['activities'] as $activity): ?>
                            <div class="activity-item">
                                <span><?php echo date('M d, Y', strtotime($activity['date'])); ?></span>
                                <span class="count"><?php echo $activity['count']; ?> actions</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>