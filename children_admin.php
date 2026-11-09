<?php
// children_admin.php - View Child Records
require_once 'config.php';
requireRole('admin');

$db = getDB();

$stmt = $db->query("
    SELECT
        c.*,
        u.full_name as mother_name,
        u.email as mother_email,
        u.phone as mother_phone
    FROM children c
    JOIN mothers m ON c.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    ORDER BY c.id DESC
");
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT COUNT(*) FROM children");
$total_children = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$total_mothers = $stmt->fetchColumn();

$male_count = 0;
$female_count = 0;
foreach ($children as $c) {
    if (isset($c['gender'])) {
        if ($c['gender'] === 'Male' || $c['gender'] === 'male') $male_count++;
        elseif ($c['gender'] === 'Female' || $c['gender'] === 'female') $female_count++;
    }
}

function calculateAge($birth_date) {
    if (empty($birth_date)) return 'N/A';
    $birth = new DateTime($birth_date);
    $now = new DateTime();
    $diff = $birth->diff($now);
    $age_text = '';
    if ($diff->y > 0) $age_text .= $diff->y . 'y ';
    if ($diff->m > 0) $age_text .= $diff->m . 'm';
    if ($age_text == '') $age_text = $diff->d . 'd';
    return $age_text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Records - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #43a047; }
        .stat-box .label { color: #666; font-size: 0.9rem; }
        .stat-box .icon { font-size: 2rem; color: #43a047; margin-bottom: 5px; }
        .child-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .child-table table { width: 100%; border-collapse: collapse; }
        .child-table th { background: #37474f; color: white; padding: 15px 20px; text-align: left; }
        .child-table td { padding: 12px 20px; border-bottom: 1px solid #f0f0f0; }
        .child-table tr:hover td { background: #e8f5e9; }
        .child-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e8f5e9; display: inline-flex; align-items: center; justify-content: center; color: #2e7d32; font-weight: 700; }
        .badge-gender { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-gender.Male, .badge-gender.male { background: #e3f2fd; color: #0d47a1; }
        .badge-gender.Female, .badge-gender.female { background: #fce4ec; color: #ad1457; }
        .action-btns { display: flex; gap: 5px; }
        .action-btns .btn-sm { padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-view { background: #e0f2f1; color: #00695c; }
        .btn-view:hover { background: #b2dfdb; }
        .btn-edit { background: #e3f2fd; color: #0d47a1; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn { padding: 10px 20px; background: #00897b; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
        .btn-primary { background: #43a047; }
        .btn-primary:hover { background: #2e7d32; color: white; }
        .table-container { overflow-x: auto; }
        .age-badge { font-size: 0.8rem; color: #666; }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; gap: 10px; }
        }
        @media (max-width: 480px) { .stats-row { grid-template-columns: 1fr; } }
    </style>
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
                <a href="children_admin.php" class="active"><i class="fas fa-child"></i> Children</a>
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
            <h2><i class="fas fa-child"></i> CHILD RECORDS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
            <h2>Child Records Management</h2>
            <a href="register_child.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Register Child</a>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-child"></i></div>
                <div class="number"><?php echo $total_children; ?></div>
                <div class="label">Total Children</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-male"></i></div>
                <div class="number"><?php echo $male_count; ?></div>
                <div class="label">Male</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-female"></i></div>
                <div class="number"><?php echo $female_count; ?></div>
                <div class="label">Female</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="number"><?php echo $total_mothers; ?></div>
                <div class="label">Mothers</div>
            </div>
        </div>

        <div class="child-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Child</th>
                            <th>Mother</th>
                            <th>DOB</th>
                            <th>Gender</th>
                            <th>Weight</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($children)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#666;">
                                <i class="fas fa-child" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                No children registered.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($children as $child): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <span class="child-avatar"><?php echo strtoupper(substr($child['full_name'], 0, 2)); ?></span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($child['full_name']); ?></strong>
                                                <?php if (isset($child['child_id']) && $child['child_id']): ?>
                                                    <div style="font-size:0.8rem; color:#666;">ID: <?php echo htmlspecialchars($child['child_id']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(isset($child['mother_name']) ? $child['mother_name'] : 'N/A'); ?>
                                        <div style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars(isset($child['mother_email']) ? $child['mother_email'] : ''); ?></div>
                                    </td>
                                    <td>
                                        <?php if (isset($child['date_of_birth']) && !empty($child['date_of_birth'])): ?>
                                            <?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?>
                                            <br><span class="age-badge"><?php echo calculateAge($child['date_of_birth']); ?></span>
                                        <?php else: ?>N/A<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $gender = isset($child['gender']) ? $child['gender'] : 'Other'; ?>
                                        <span class="badge-gender <?php echo $gender; ?>"><?php echo $gender; ?></span>
                                    </td>
                                    <td><?php echo isset($child['birth_weight']) && !empty($child['birth_weight']) ? $child['birth_weight'] . ' kg' : 'N/A'; ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_child.php?id=<?php echo $child['id']; ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i></a>
                                            <a href="edit_child.php?id=<?php echo $child['id']; ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                            <a href="?action=delete&id=<?php echo $child['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
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