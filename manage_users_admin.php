<?php
// manage_users_admin.php - Manage System Users
// Enforces: only ONE active administrator allowed
require_once 'config.php';
requireRole('admin');

$db = getDB();
$message = '';
$error = '';

// ============================================================
// HANDLE USER ACTIONS
// ============================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action  = $_GET['action'];

    // Load the target user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($user_id));
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $error = "User not found.";
    } else {
        // Identify if the target is the primary admin
        $is_primary_admin = ($target['role'] === 'admin' && !empty($target['is_primary']));

        if ($action === 'activate') {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute(array($user_id));
            $message = "User activated successfully!";

        } elseif ($action === 'deactivate') {
            if ($is_primary_admin) {
                $error = "You cannot deactivate the primary administrator account.";
            } else {
                $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute(array($user_id));
                $message = "User deactivated successfully!";
            }

        } elseif ($action === 'delete') {
            if ($is_primary_admin) {
                $error = "You cannot delete the primary administrator. Transfer the admin role first.";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute(array($user_id));
                if ($stmt->rowCount() > 0) {
                    $message = "User deleted successfully!";
                } else {
                    $error = "Admin accounts cannot be deleted from this page.";
                }
            }
        }
    }
}

// ============================================================
// GET ALL USERS
// ============================================================
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role counts
$roleCounts = [];
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $roleCounts[$row['role']] = $row['count'];
}

// Active users count
$activeCount = 0;
foreach ($users as $user) {
    if (isset($user['status']) && $user['status'] === 'active') {
        $activeCount++;
    }
}

// Identify the primary admin
$primary_admin_id = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin' && !empty($u['is_primary'])) {
        $primary_admin_id = $u['id'];
        break;
    }
}

// Sanity check: how many primary admins are there?
$primary_admin_count = ma_life_count_primary_admins(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - MA-LIFE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CORE RESET & LAYOUT --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f4f7f6; height: 100vh; overflow: hidden; }

        .app-container { display: flex; height: 100vh; width: 100vw; padding: 15px; gap: 20px; }

        /* --- SIDEBAR --- */
        .sidebar { width: 260px; background: #1e293b; border-radius: 16px; display: flex; flex-direction: column; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-y: auto; flex-shrink: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
        
        .brand { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .brand h2 { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: #fff; text-transform: uppercase; }

        .nav-group { padding: 20px 10px 0 10px; }
        .nav-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 10px; padding-left: 10px; font-weight: 700; }
        
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #cbd5e1; text-decoration: none; border-radius: 10px; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; margin-bottom: 3px; position: relative; }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-link.active { background: #0ea5e9; color: #fff; box-shadow: 0 4px 15px rgba(14, 165, 233, 0.4); }
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding-right: 5px; }
        .main-content::-webkit-scrollbar { width: 6px; }
        .main-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* --- PAGE HEADER --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 5px; }
        .page-header h2 { color: #1e293b; font-size: 1.6rem; font-weight: 700; }
        .btn { padding: 10px 20px; background: #0ea5e9; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.2); }
        .btn:hover { background: #0284c7; }
        .btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        .btn-outline:hover { background: #f1f5f9; }

        /* --- STATS ROW --- */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
        .stat-box .number { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .stat-box .label { color: #64748b; font-size: 0.85rem; font-weight: 500; }
        .stat-box .icon { font-size: 1.5rem; color: #0ea5e9; margin-bottom: 10px; background: #e0f2fe; width: 45px; height: 45px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }

        /* --- TABLE & ALERTS --- */
        .admin-notice { background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .message.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .message.error { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        .user-table { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
        .table-container { overflow-x: auto; }
        .user-table table { width: 100%; border-collapse: collapse; }
        .user-table th { background: #f8fafc; color: #475569; padding: 15px 20px; text-align: left; font-weight: 600; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
        .user-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #334155; }
        .user-table tr:hover td { background: #f8fafc; }
        
        .badge-role { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-role.admin { background: #e2e8f0; color: #1e293b; }
        .badge-role.mother { background: #fce7f3; color: #be185d; }
        .badge-role.healthworker { background: #e0f2fe; color: #0369a1; }
        .badge-role.child { background: #d1fae5; color: #047857; }
        
        .badge-primary { display: inline-block; padding: 3px 8px; background: #fef08a; color: #854d0e; border-radius: 12px; font-size: 0.65rem; font-weight: 700; margin-left: 5px; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-status.active { background: #d1fae5; color: #065f46; }
        .badge-status.inactive { background: #fee2e2; color: #991b1b; }

        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btns .btn-sm { padding: 6px 10px; font-size: 0.75rem; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-edit { background: #e0f2fe; color: #0284c7; }
        .btn-edit:hover { background: #bae6fd; }
        .btn-activate { background: #d1fae5; color: #059669; }
        .btn-activate:hover { background: #a7f3d0; }
        .btn-deactivate { background: #fef3c7; color: #d97706; }
        .btn-deactivate:hover { background: #fde68a; }
        .btn-delete { background: #fee2e2; color: #dc2626; }
        .btn-delete:hover { background: #fecaca; }

        @media (max-width: 768px) { 
            .app-container { padding: 0; gap: 0; }
            .sidebar { display: none; }
            .main-content { padding: 15px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); } 
            .page-header { flex-direction: column; gap: 10px; align-items: flex-start; } 
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>

<div class="app-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="brand">
            <img src="img/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; background: white;" onerror="this.style.display='none'">
            <h2>Administrator</h2>
        </div>

        <div class="nav-group">
            <div class="nav-label">Main Menu</div>
            <a href="administrator.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="register_fingerprint.php" class="nav-link"><i class="fas fa-fingerprint"></i> Fingerprint</a>
            <a href="admin_notes.php" class="nav-link"><i class="fas fa-sticky-note"></i> Notes & Files</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">Management</div>
            <a href="manage_users_admin.php" class="nav-link active"><i class="fas fa-users"></i> System Users</a>
            <a href="appointments_admin.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Appointments</a>
            <a href="admin_documents.php" class="nav-link"><i class="fas fa-folder-open"></i> All Documents</a>
            <a href="admin_notifications.php" class="nav-link"><i class="fas fa-bell"></i> Notifications</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">System</div>
            <a href="reports_admin.php" class="nav-link"><i class="fas fa-chart-pie"></i> System Reports</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>User Management</h2>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">View, edit, and manage all system accounts.</p>
            </div>
            <a href="add_user.php" class="btn">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
        </div>

        <div class="admin-notice">
            <i class="fas fa-shield-alt"></i>
            <strong>Single-Administrator Policy:</strong> This system allows only <strong>one active administrator</strong>.
            <?php if ($primary_admin_id): ?>
                The primary admin (ID #<?= $primary_admin_id ?>) cannot be deactivated or deleted.
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="number"><?= array_sum($roleCounts) ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-box">
                <div class="icon" style="color: #8b5cf6; background: #f3e8ff;"><i class="fas fa-user-shield"></i></div>
                <div class="number"><?= $roleCounts['admin'] ?? 0 ?></div>
                <div class="label">Administrators</div>
            </div>
            <div class="stat-box">
                <div class="icon" style="color: #f59e0b; background: #fef3c7;"><i class="fas fa-user-md"></i></div>
                <div class="number"><?= $roleCounts['healthworker'] ?? 0 ?></div>
                <div class="label">Health Workers</div>
            </div>
            <div class="stat-box">
                <div class="icon" style="color: #ec407a; background: #fce7f3;"><i class="fas fa-female"></i></div>
                <div class="number"><?= $roleCounts['mother'] ?? 0 ?></div>
                <div class="label">Mothers</div>
            </div>
        </div>

        <!-- User Table -->
        <div class="user-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Full Name</th><th>Email</th><th>Role</th>
                            <th>Status</th><th>Joined</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:#666;">
                                <i class="fas fa-user-slash" style="font-size:2rem; display:block; margin-bottom:10px; color:#cbd5e1;"></i>
                                No users found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php
                                    $is_primary = ($user['role'] === 'admin' && !empty($user['is_primary']));
                                    $status = isset($user['status']) ? $user['status'] : 'inactive';
                                ?>
                                <tr>
                                    <td>#<?= $user['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                        <?php if ($is_primary): ?>
                                            <span class="badge-primary">PRIMARY ADMIN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><span class="badge-role <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span></td>
                                    <td>
                                        <span class="badge-status <?= $status === 'active' ? 'active' : 'inactive' ?>">
                                            <?= $status === 'active' ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                            <?php if (!$is_primary): ?>
                                                <?php if ($status === 'active'): ?>
                                                    <a href="?action=deactivate&id=<?= $user['id'] ?>" class="btn-sm btn-deactivate" data-confirm="Deactivate this user?"><i class="fas fa-ban"></i></a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?= $user['id'] ?>" class="btn-sm btn-activate" data-confirm="Activate this user?"><i class="fas fa-check"></i></a>
                                                <?php endif; ?>
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <a href="?action=delete&id=<?= $user['id'] ?>" class="btn-sm btn-delete" data-confirm="Delete this user permanently?"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="font-size:0.75rem; color:#94a3b8; padding:4px 8px; font-weight: 600;">Protected</span>
                                            <?php endif; ?>
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
</div>

</body>
</html>