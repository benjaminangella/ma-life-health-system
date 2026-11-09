<?php
// mothers_admin.php - View Mother Records
require_once 'config.php';
requireRole('admin');

$db = getDB();

// Get all mothers with user details and child count
// NOTE: users table uses 'status' not 'is_active'
$stmt = $db->query("
    SELECT
        m.*,
        u.full_name,
        u.email,
        u.phone,
        u.date_of_birth,
        u.status AS user_status,
        u.created_at,
        (SELECT COUNT(*) FROM children WHERE mother_id = m.id) as child_count
    FROM mothers m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.created_at DESC
");
$mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$total_mothers = $stmt->fetchColumn();

// Get total children
$stmt = $db->query("SELECT COUNT(*) FROM children");
$total_children = $stmt->fetchColumn();

// Count married mothers
$married_count = 0;
foreach ($mothers as $m) {
    if (isset($m['marital_status']) && $m['marital_status'] === 'married') {
        $married_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother Records - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #ec407a; }
        .stat-box .label { color: #666; font-size: 0.9rem; }
        .stat-box .icon { font-size: 2rem; color: #ec407a; margin-bottom: 5px; }
        .mother-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .mother-table table { width: 100%; border-collapse: collapse; }
        .mother-table th { background: #37474f; color: white; padding: 15px 20px; text-align: left; font-weight: 600; }
        .mother-table td { padding: 12px 20px; border-bottom: 1px solid #f0f0f0; }
        .mother-table tr:hover td { background: #fce4ec; }
        .mother-avatar { width: 40px; height: 40px; border-radius: 50%; background: #fce4ec; display: inline-flex; align-items: center; justify-content: center; color: #ad1457; font-weight: 700; font-size: 1.1rem; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-status.active { background: #e8f5e9; color: #2e7d32; }
        .badge-status.inactive { background: #ffebee; color: #c62828; }
        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btns .btn-sm { padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-view { background: #e0f2f1; color: #00695c; }
        .btn-view:hover { background: #b2dfdb; }
        .btn-edit { background: #e3f2fd; color: #0d47a1; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn { padding: 10px 20px; background: #00897b; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
        .btn-primary { background: #ec407a; }
        .btn-primary:hover { background: #d81b60; color: white; }
        .table-container { overflow-x: auto; }
        .child-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; background: #e8f5e9; color: #2e7d32; font-size: 0.8rem; }
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
                <img src="image/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%2300809b%22/%3E%3C/svg%3E'">
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php" class="active"><i class="fas fa-female"></i> Mothers</a>
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
            <h2><i class="fas fa-female"></i> MOTHER RECORDS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
            <h2>Mother Records Management</h2>
            <a href="register_mother.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Register Mother
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-female"></i></div>
                <div class="number"><?php echo $total_mothers; ?></div>
                <div class="label">Total Mothers</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-child"></i></div>
                <div class="number"><?php echo $total_children; ?></div>
                <div class="label">Total Children</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-heart"></i></div>
                <div class="number"><?php echo $married_count; ?></div>
                <div class="label">Married Mothers</div>
            </div>
        </div>

        <div class="mother-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Mother</th>
                            <th>National ID</th>
                            <th>Marital Status</th>
                            <th>Occupation</th>
                            <th>Children</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mothers)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#666;">
                                    <i class="fas fa-female" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                    No mothers registered.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mothers as $mother): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <span class="mother-avatar">
                                                <?php echo strtoupper(substr($mother['full_name'], 0, 2)); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($mother['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <div style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars($mother['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars(isset($mother['national_id']) && $mother['national_id'] !== '' ? $mother['national_id'] : 'Not provided', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if (!empty($mother['marital_status'])): ?>
                                            <span style="text-transform:capitalize;"><?php echo $mother['marital_status']; ?></span>
                                        <?php else: ?>
                                            <span style="color:#999;">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(isset($mother['occupation']) && $mother['occupation'] !== '' ? $mother['occupation'] : 'Not specified', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="child-badge">
                                            <i class="fas fa-child"></i> <?php echo isset($mother['child_count']) ? $mother['child_count'] : 0; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php $status = isset($mother['user_status']) ? $mother['user_status'] : 'active'; ?>
                                        <span class="badge-status <?php echo $status === 'active' ? 'active' : 'inactive'; ?>">
                                            <?php echo $status === 'active' ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_mother.php?id=<?php echo $mother['id']; ?>" class="btn-sm btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_mother.php?id=<?php echo $mother['id']; ?>" class="btn-sm btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $mother['id']; ?>" class="btn-sm btn-delete" data-confirm="Delete this mother record?" title="Delete">
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