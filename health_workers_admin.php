<?php
// health_workers_admin.php - Manage Health Workers
require_once 'config.php';
requireRole('admin');

$db = getDB();
$message = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $worker_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM health_workers WHERE id = ?");
        $stmt->execute(array($worker_id));
        $message = "Health worker removed successfully!";
    } elseif ($action === 'activate') {
        $stmt = $db->prepare("UPDATE health_workers SET status = 'active' WHERE id = ?");
        $stmt->execute(array($worker_id));
        $message = "Health worker activated successfully!";
    } elseif ($action === 'deactivate') {
        $stmt = $db->prepare("UPDATE health_workers SET status = 'inactive' WHERE id = ?");
        $stmt->execute(array($worker_id));
        $message = "Health worker deactivated successfully!";
    }
}

// Get all health workers with user details
// NOTE: users table uses 'status' not 'is_active'
$stmt = $db->query("
    SELECT hw.*, u.full_name, u.email, u.phone, u.status AS user_status
    FROM health_workers hw
    JOIN users u ON hw.user_id = u.id
    ORDER BY hw.created_at DESC
");
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $db->query("SELECT COUNT(*) FROM health_workers");
$total_workers = $stmt->fetchColumn();

// Count active workers + facilities
$active_count = 0;
$facilities = array();
foreach ($workers as $w) {
    if (isset($w['status']) && $w['status'] === 'active') {
        $active_count++;
    }
    if (isset($w['facility_name']) && $w['facility_name'] != '') {
        $facilities[] = $w['facility_name'];
    }
}
$facility_count = count(array_unique($facilities));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Workers - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #37474f; }
        .stat-box .label { color: #666; font-size: 0.9rem; }
        .stat-box .icon { font-size: 2rem; color: #00897b; margin-bottom: 5px; }
        .worker-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .worker-table table { width: 100%; border-collapse: collapse; }
        .worker-table th { background: #37474f; color: white; padding: 15px 20px; text-align: left; font-weight: 600; }
        .worker-table td { padding: 12px 20px; border-bottom: 1px solid #f0f0f0; }
        .worker-table tr:hover td { background: #f5f9fc; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-status.active { background: #e8f5e9; color: #2e7d32; }
        .badge-status.inactive { background: #ffebee; color: #c62828; }
        .badge-status.on_leave { background: #fff3e0; color: #e65100; }
        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btns .btn-sm { padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-view { background: #e0f2f1; color: #00695c; }
        .btn-view:hover { background: #b2dfdb; }
        .btn-edit { background: #e3f2fd; color: #0d47a1; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn-activate { background: #e8f5e9; color: #2e7d32; }
        .btn-activate:hover { background: #c8e6c9; }
        .btn-deactivate { background: #fff3e0; color: #e65100; }
        .btn-deactivate:hover { background: #ffe0b2; }
        .btn { padding: 10px 20px; background: #37474f; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
        .btn-primary { background: #00897b; }
        .btn-primary:hover { background: #00695c; color: white; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .table-container { overflow-x: auto; }
        .worker-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e0f2f1; display: inline-flex; align-items: center; justify-content: center; color: #00695c; font-weight: 700; font-size: 1.1rem; }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="img/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%2300809b%22/%3E%3C/svg%3E'">
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php" class="active"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
                <a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
                <a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-user-md"></i> HEALTH WORKERS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
            <h2>Health Worker Management</h2>
            <a href="add_health_worker.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add Health Worker
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-user-md"></i></div>
                <div class="number"><?php echo $total_workers; ?></div>
                <div class="label">Total Health Workers</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo $active_count; ?></div>
                <div class="label">Active Workers</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-hospital"></i></div>
                <div class="number"><?php echo $facility_count; ?></div>
                <div class="label">Facilities</div>
            </div>
        </div>

        <div class="worker-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Worker ID</th>
                            <th>Specialization</th>
                            <th>Facility</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workers)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#666;">
                                    <i class="fas fa-user-md" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                    No health workers registered.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workers as $worker): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <span class="worker-avatar">
                                                <?php echo strtoupper(substr($worker['full_name'], 0, 2)); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($worker['full_name']); ?></strong>
                                                <div style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars($worker['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($worker['worker_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(isset($worker['specialization']) && $worker['specialization'] != '' ? $worker['specialization'] : 'General'); ?></td>
                                    <td><?php echo htmlspecialchars(isset($worker['facility_name']) && $worker['facility_name'] != '' ? $worker['facility_name'] : 'Not assigned'); ?></td>
                                    <td><?php echo isset($worker['years_of_experience']) ? $worker['years_of_experience'] : 0; ?> years</td>
                                    <td>
                                        <?php $status = isset($worker['status']) ? $worker['status'] : 'active'; ?>
                                        <span class="badge-status <?php echo $status; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_health_worker.php?id=<?php echo $worker['id']; ?>" class="btn-sm btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_health_worker.php?id=<?php echo $worker['id']; ?>" class="btn-sm btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($status === 'active'): ?>
                                                <a href="?action=deactivate&id=<?php echo $worker['id']; ?>" class="btn-sm btn-deactivate" data-confirm="Deactivate this worker?" title="Deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=activate&id=<?php echo $worker['id']; ?>" class="btn-sm btn-activate" data-confirm="Activate this worker?" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $worker['id']; ?>" class="btn-sm btn-delete" data-confirm="Remove this health worker?" title="Delete">
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