<?php
require_once 'config.php';
requireRole('healthworker'); // only healthworkers can access

$db = getDB();
$healthworker_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Stats with error handling - won't crash if table doesn't exist yet
$total_mothers = 0;
$total_children = 0;
$total_visits = 0;
$upcoming_appointments = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) FROM mothers");
    $total_mothers = $stmt->fetchColumn();
} catch(Exception $e) { $total_mothers = 0; }

try {
    $stmt = $db->query("SELECT COUNT(*) FROM children");
    $total_children = $stmt->fetchColumn();
} catch(Exception $e) { $total_children = 0; }

try {
    $stmt = $db->query("SELECT COUNT(*) FROM antenatal_visits");
    $total_visits = $stmt->fetchColumn();
} catch(Exception $e) { $total_visits = 0; }

try {
    $stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= NOW() AND status != 'cancelled'");
    $upcoming_appointments = $stmt->fetchColumn();
} catch(Exception $e) { $upcoming_appointments = 0; }

// Get recent antenatal visits
$recent_visits = array();
try {
    $stmt = $db->prepare("
        SELECT av.*, m.full_name as mother_name 
        FROM antenatal_visits av
        JOIN mothers mo ON av.mother_id = mo.id
        JOIN users m ON mo.user_id = m.id
        ORDER BY av.visit_date DESC LIMIT 10
    ");
    $stmt->execute();
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $recent_visits = array(); // stay empty if table missing
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthworker Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #e8f5e9; font-family: 'Inter', Arial, sans-serif; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #2e7d32; color: white; }
        .sidebar h2 { text-align: center; padding: 20px; background: #1b5e20; }
        .sidebar nav a { display: block; padding: 15px 20px; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar nav a.active { background: #1b5e20; border-left: 4px solid #a5d6a7; }
        .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .topbar h2 { padding: 20px; color: #2e7d32; }
        .main { margin-left: 250px; padding: 25px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h3 { font-size: 2rem; color: #2e7d32; }
        .stat-card p { color: #666; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-title { color: #2e7d32; font-weight: 600; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #e8f5e9; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #eee; }
        .no-data { text-align: center; padding: 30px; color: #999; }
        @media (max-width: 768px) { .sidebar { width: 0; } .main, .topbar { margin-left: 0; } .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <h2>Healthworker</h2>
        <nav>
            <a href="healthworker.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="mothers.php"><i class="fas fa-female"></i> Mothers</a>
            <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Visits</a>
            <a href="children.php"><i class="fas fa-child"></i> Children</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="topbar">
        <h2><i class="fas fa-user-nurse"></i> HEALTHWORKER DASHBOARD</h2>
    </div>

    <div class="main">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_mothers; ?></h3>
                <p>Total Mothers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_children; ?></h3>
                <p>Total Children</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_visits; ?></h3>
                <p>Antenatal Visits</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $upcoming_appointments; ?></h3>
                <p>Upcoming Appointments</p>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-list"></i> Recent Antenatal Visits</div>
            <?php if(empty($recent_visits)): ?>
                <div class="no-data">
                    <i class="fas fa-heartbeat" style="font-size:2rem;"></i>
                    <p>No antenatal visits recorded yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Mother</th>
                        <th>Visit Date</th>
                        <th>GA Weeks</th>
                        <th>BP</th>
                        <th>Weight</th>
                    </tr>
                    <?php foreach($recent_visits as $v): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($v['mother_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($v['visit_date'])); ?></td>
                        <td><?php echo $v['gestational_age_weeks']; ?> wks</td>
                        <td><?php echo $v['bp_systolic']; ?>/<?php echo $v['bp_diastolic']; ?></td>
                        <td><?php echo $v['weight_kg']; ?> kg</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>