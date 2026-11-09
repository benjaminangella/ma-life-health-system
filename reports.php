<?php
// health_reports.php - Health Reports for PHP 5.3.5
require_once 'config.php';

// session_start() is in config.php
$db = getDB();
$user_id = $_SESSION['user_id'];

// Get all health reports for this user
$stmt = $db->prepare("
    SELECT * FROM health_reports 
    WHERE user_id = ? 
    ORDER BY report_date DESC
");
$stmt->execute(array($user_id));
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get children for statistics
$stmt = $db->prepare("
    SELECT id, full_name, date_of_birth,
        (SELECT COUNT(*) FROM immunizations WHERE child_id = children.id) as immunization_count,
        (SELECT COUNT(*) FROM growth_records WHERE child_id = children.id) as growth_count
    FROM children 
    WHERE mother_id = (SELECT id FROM mothers WHERE user_id = ?)
");
$stmt->execute(array($user_id));
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get appointment statistics
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE user_id = ? 
    GROUP BY status
");
$stmt->execute(array($user_id));
$appointment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PHP 5.3 compatible: manual array_column + array_sum
$total_appointments = 0;
foreach ($appointment_stats as $stat) {
    $total_appointments += $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Reports - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: #fce4ec; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #ad1457; color: white; overflow-y: auto; z-index: 100; }
        .nav h2 { text-align: center; padding: 20px; background: #880e4f; font-size: 20px; }
        .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
        .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 15px 20px; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.15); transition: 0.3s; }
        nav a:hover { background: #880e4f; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: #880e4f; border-left: 4px solid #ffb74d; }
        .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h2 { padding: 20px; color: #ad1457; }
        .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
        .page-header { margin-bottom: 25px; }
        .page-header h2 { color: #333; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #ad1457; }
        .stat-card .label { color: #666; font-size: 0.85rem; }
        .stat-card i { font-size: 1.5rem; color: #ad1457; margin-bottom: 5px; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card .card-title { color: #ad1457; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #fce4ec; }
        .report-item { padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .report-item:last-child { border-bottom: none; }
        .report-item .header { display: flex; justify-content: space-between; align-items: center; }
        .report-item .header .title { font-weight: 600; color: #333; }
        .report-item .header .date { color: #999; font-size: 0.85rem; }
        .report-item .content { margin-top: 5px; color: #666; font-size: 0.9rem; }
        .report-item .content .findings { background: #f5f5f5; padding: 10px; border-radius: 6px; margin-top: 5px; }
        .badge-status { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-draft { background: #fff3e0; color: #e65100; }
        .badge-final { background: #e8f5e9; color: #2e7d32; }
        .badge-archived { background: #f5f5f5; color: #666; }
        .badge-pending { background: #e3f2fd; color: #1565c0; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        .no-data { text-align: center; padding: 30px; color: #999; }
        .no-data i { font-size: 2rem; display: block; margin-bottom: 10px; }
        .child-stat { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
        .child-stat:last-child { border-bottom: none; }
        .child-stat .name { font-weight: 500; color: #333; }
        .child-stat .stats { color: #666; font-size: 0.9rem; }
        .appointment-stat { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
        .appointment-stat:last-child { border-bottom: none; }
        .appointment-stat .status { font-weight: 500; color: #333; }
        .appointment-stat .count { font-weight: 600; color: #ad1457; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar { margin-left: 200px; }
            .main { margin-left: 200px; padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar { margin-left: 0; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
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
            <h2>Mother Portal</h2>
            <nav>
                <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="health_reports.php" class="active"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-chart-bar"></i> HEALTH REPORTS</h2>
        </div>
    </div>

    <div class="main">
        
        <div class="page-header">
            <h2>My Health Summary</h2>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-child"></i>
                <div class="number"><?php echo count($children); ?></div>
                <div class="label">Children</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-syringe"></i>
                <div class="number">
                    <?php 
                    $total_immunizations = 0;
                    foreach ($children as $c) { $total_immunizations += $c['immunization_count']; }
                    echo $total_immunizations;
                    ?>
                </div>
                <div class="label">Total Immunizations</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar"></i>
                <div class="number"><?php echo $total_appointments; ?></div>
                <div class="label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-file-medical"></i>
                <div class="number"><?php echo count($reports); ?></div>
                <div class="label">Health Reports</div>
            </div>
        </div>

        <!-- Children Summary -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-child"></i> Children Summary
            </div>
            <?php if (empty($children)): ?>
                <div class="no-data">
                    <i class="fas fa-child"></i>
                    <p>No children registered.</p>
                </div>
            <?php else: ?>
                <?php foreach ($children as $child): ?>
                    <div class="child-stat">
                        <span class="name">
                            <?php echo htmlspecialchars($child['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                            <span style="color:#999; font-size:0.8rem;">
                                (<?php echo floor((time() - strtotime($child['date_of_birth'])) / (365.25 * 24 * 60 * 60)); ?> years)
                            </span>
                        </span>
                        <span class="stats">
                            <i class="fas fa-syringe"></i> <?php echo $child['immunization_count']; ?> immunizations
                            &nbsp;|&nbsp;
                            <i class="fas fa-chart-line"></i> <?php echo $child['growth_count']; ?> growth records
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Appointment Statistics -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-calendar-check"></i> Appointment Statistics
            </div>
            <?php if (empty($appointment_stats)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar"></i>
                    <p>No appointment data available.</p>
                </div>
            <?php else: ?>
                <?php foreach ($appointment_stats as $stat): ?>
                    <div class="appointment-stat">
                        <span class="status">
                            <span class="badge-status badge-<?php echo $stat['status']; ?>">
                                <?php echo ucfirst($stat['status']); ?>
                            </span>
                        </span>
                        <span class="count"><?php echo $stat['count']; ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="appointment-stat" style="border-top:2px solid #f0f0f0; margin-top:10px; padding-top:10px;">
                    <span class="status" style="font-weight:700;">Total</span>
                    <span class="count" style="font-size:1.2rem;"><?php echo $total_appointments; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Health Reports -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-file-medical-alt"></i> Health Reports
            </div>
            <?php if (empty($reports)): ?>
                <div class="no-data">
                    <i class="fas fa-file-medical"></i>
                    <p>No health reports available.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <div class="report-item">
                        <div class="header">
                            <span class="title">
                                <?php echo ucfirst($report['report_type']); ?>
                                <span class="badge-status badge-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                            </span>
                            <span class="date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('M d, Y', strtotime($report['report_date'])); ?>
                            </span>
                        </div>
                        <?php if (!empty($report['findings'])): ?>
                            <div class="content">
                                <div class="findings">
                                    <strong>Findings:</strong> <?php echo nl2br(htmlspecialchars($report['findings'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($report['recommendations'])): ?>
                            <div class="content">
                                <strong>Recommendations:</strong> <?php echo nl2br(htmlspecialchars($report['recommendations'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($report['doctor_name'])): ?>
                            <div class="content" style="font-size:0.85rem; color:#999; margin-top:5px;">
                                <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($report['doctor_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>