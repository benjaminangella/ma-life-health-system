<?php
// appointments_admin.php - List and manage appointments (admin)
require_once 'config.php';
requireRole('admin');

$db = getDB();
$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute(array((int)$_GET['delete']));
    $message = "Appointment deleted successfully!";
}

// Handle status update
if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowed = array('scheduled','confirmed','in_progress','completed','cancelled','no_show');
    $new_status = $_GET['status'];
    if (in_array($new_status, $allowed)) {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute(array($new_status, (int)$_GET['id']));
        $message = "Status updated successfully!";
    }
}

// Get all appointments with patient + child info
$stmt = $db->query("
    SELECT a.*,
           u.full_name AS patient_name,
           u.email AS patient_email,
           u.phone AS patient_phone,
           c.full_name AS child_name
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN children c ON a.child_id = c.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $db->query("SELECT COUNT(*) FROM appointments");
$total_appointments = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'scheduled'");
$scheduled_count = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'");
$completed_count = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$today_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #1976d2; }
        .stat-box .label { color: #666; font-size: 0.9rem; }
        .stat-box .icon { font-size: 2rem; color: #1976d2; margin-bottom: 5px; }
        .appt-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .appt-table table { width: 100%; border-collapse: collapse; }
        .appt-table th { background: #37474f; color: white; padding: 15px 20px; text-align: left; }
        .appt-table td { padding: 12px 20px; border-bottom: 1px solid #f0f0f0; }
        .appt-table tr:hover td { background: #e3f2fd; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-scheduled { background: #e3f2fd; color: #0d47a1; }
        .badge-confirmed { background: #e8f5e9; color: #2e7d32; }
        .badge-in_progress { background: #f3e5f5; color: #6a1b9a; }
        .badge-completed { background: #e0f2f1; color: #00695c; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        .badge-no_show { background: #fff3e0; color: #e65100; }
        .btn { padding: 10px 20px; background: #00897b; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
        .btn-primary { background: #1976d2; }
        .btn-primary:hover { background: #0d47a1; color: white; }
        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btns .btn-sm { padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-edit { background: #e3f2fd; color: #0d47a1; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn-success { background: #e8f5e9; color: #2e7d32; }
        .btn-success:hover { background: #c8e6c9; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .table-container { overflow-x: auto; }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; gap: 10px; }
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
                <a href="appointments_admin.php" class="active"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-calendar"></i> APPOINTMENTS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
            <h2>All Appointments</h2>
            <a href="add_appointment.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Appointment</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-calendar"></i></div>
                <div class="number"><?php echo $total_appointments; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo $scheduled_count; ?></div>
                <div class="label">Scheduled</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-check"></i></div>
                <div class="number"><?php echo $completed_count; ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-calendar-day"></i></div>
                <div class="number"><?php echo $today_count; ?></div>
                <div class="label">Today</div>
            </div>
        </div>

        <div class="appt-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Child</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:#666;">
                                <i class="fas fa-calendar" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                No appointments found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(isset($a['patient_name']) ? $a['patient_name'] : 'N/A'); ?></strong>
                                        <div style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars(isset($a['patient_phone']) ? $a['patient_phone'] : ''); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars(isset($a['child_name']) && $a['child_name'] != '' ? $a['child_name'] : '-'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($a['reason']); ?></td>
                                    <td><span class="badge-status badge-<?php echo $a['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $a['status'])); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="edit_appointment.php?id=<?php echo $a['id']; ?>" class="btn-sm btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($a['status'] !== 'completed'): ?>
                                                <a href="?status=completed&id=<?php echo $a['id']; ?>" class="btn-sm btn-success" data-confirm="Mark complete?" title="Mark complete">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $a['id']; ?>" class="btn-sm btn-delete" data-confirm="Delete?" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>