<?php
// appointments.php - View Appointments (Shared for Mothers and Health Workers)
require_once 'config.php';
requireLogin(); // Must be logged in

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Allow only mothers and health workers
if ($user_role !== 'mother' && $user_role !== 'healthworker') {
    header('Location: login.php?security=1');
    exit();
}

$db = getDB();
$message = '';
$error = '';

// Handle cancellation (Only mothers can cancel their own)
if (isset($_GET['cancel_id']) && $user_role === 'mother') {
    $apt_id = (int)$_GET['cancel_id'];
    $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('scheduled', 'confirmed')");
    $stmt->execute([$apt_id, $user_id]);
    if ($stmt->rowCount() > 0) {
        $message = "Appointment cancelled successfully.";
    } else {
        $error = "Unable to cancel appointment.";
    }
}

// ==========================================
// FETCH APPOINTMENTS BASED ON ROLE
// ==========================================
if ($user_role === 'healthworker') {
    // HEALTH WORKER: Get appointments assigned to them
    $stmt = $db->prepare("
        SELECT a.*, u.full_name AS mother_name, u.phone, c.full_name AS child_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN children c ON a.child_id = c.id
        WHERE a.health_worker_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$user_id]);
} else {
    // MOTHER: Get appointments created for them
    $stmt = $db->prepare("
        SELECT a.*, u.full_name AS mother_name, hw_user.full_name AS worker_name, c.full_name AS child_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN health_workers hw ON a.health_worker_id = hw.user_id
        LEFT JOIN users hw_user ON hw.user_id = hw_user.id
        LEFT JOIN children c ON a.child_id = c.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$user_id]);
}
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dynamic Theme Colors
if ($user_role === 'mother') {
    $theme_bg = '#fce4ec'; $theme_primary = '#ad1457'; $theme_hover = '#880e4f'; $sidebar_bg = '#ad1457'; $sidebar_hover = '#880e4f'; $label = 'Mother Portal';
} else {
    $theme_bg = '#f4f7f6'; $theme_primary = '#0f766e'; $theme_hover = '#115e59'; $sidebar_bg = '#0f766e'; $sidebar_hover = '#115e59'; $label = 'Health Worker';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - MA-LIFE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: <?= $theme_bg ?>; height: 100vh; overflow: hidden; }

        .app-container { display: flex; height: 100vh; width: 100vw; padding: 15px; gap: 20px; }

        /* --- SIDEBAR --- */
        .sidebar { width: 260px; background: <?= $sidebar_bg ?>; border-radius: 16px; display: flex; flex-direction: column; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-y: auto; flex-shrink: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 10px; }
        
        .brand { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .brand h2 { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: #fff; text-transform: uppercase; }

        .nav-group { padding: 20px 10px 0 10px; }
        .nav-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.6); margin-bottom: 10px; padding-left: 10px; font-weight: 700; }
        
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 10px; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; margin-bottom: 3px; }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-link.active { background: #14b8a6; color: #fff; box-shadow: 0 4px 15px rgba(20, 184, 166, 0.4); }
        <?php if ($user_role === 'mother'): ?>
        .nav-link.active { background: #f06292; box-shadow: 0 4px 15px rgba(240, 98, 146, 0.4); }
        <?php endif; ?>
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding-right: 5px; }
        .main-content::-webkit-scrollbar { width: 6px; }
        .main-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 5px; }
        .page-header h2 { color: #1e293b; font-size: 1.6rem; font-weight: 700; }
        
        .btn { padding: 10px 20px; background: <?= $theme_primary ?>; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn:hover { background: <?= $theme_hover ?>; }
        .btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        .btn-outline:hover { background: #f1f5f9; }

        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .message.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .message.error { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* --- CARDS GRID --- */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        
        .apt-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; transition: 0.3s; position: relative; }
        .apt-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        
        .apt-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .apt-date { background: <?= $theme_bg ?>; color: <?= $theme_primary ?>; font-weight: 700; font-size: 0.85rem; padding: 6px 12px; border-radius: 8px; display: flex; align-items: center; gap: 6px; }
        .apt-status { font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
        
        .status-scheduled { background: #e0f2fe; color: #0369a1; }
        .status-confirmed { background: #dcfce7; color: #15803d; }
        .status-completed { background: #f1f5f9; color: #475569; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }

        .apt-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        
        .apt-details { font-size: 0.85rem; color: #64748b; margin-bottom: 15px; display: flex; flex-direction: column; gap: 6px; }
        .apt-details i { width: 16px; color: <?= $theme_primary ?>; margin-right: 5px; }
        .apt-details strong { color: #334155; }

        .apt-actions { border-top: 1px solid #f1f5f9; padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px; }
        
        .empty-state { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: white; border-radius: 16px; border: 1px dashed #cbd5e1; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; }
        .empty-state h3 { color: #475569; margin-bottom: 5px; }
        .empty-state p { color: #94a3b8; font-size: 0.9rem; }

        @media (max-width: 768px) {
            .app-container { padding: 0; gap: 0; }
            .sidebar { display: none; }
            .main-content { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="brand">
            <img src="img/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; background: white;" onerror="this.style.display='none'">
            <h2><?= $label ?></h2>
        </div>

        <?php if ($user_role === 'healthworker'): ?>
            <!-- HEALTH WORKER MENU -->
            <div class="nav-group">
                <div class="nav-label">Main Menu</div>
                <a href="healthworker.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="register_fingerprint.php" class="nav-link"><i class="fas fa-fingerprint"></i> Fingerprint</a>
                <a href="admin_notes.php" class="nav-link"><i class="fas fa-sticky-note"></i> Notes & Files</a>
                <a href="health_worker_details.php" class="nav-link"><i class="fas fa-user-md"></i> My Details</a>
            </div>
            <div class="nav-group">
                <div class="nav-label">Clinical</div>
                <a href="register_mother.php" class="nav-link"><i class="fas fa-female"></i> Register Mother</a>
                <a href="mother_records.php" class="nav-link"><i class="fas fa-folder-open"></i> Mother Records</a>
                <a href="child_records.php" class="nav-link"><i class="fas fa-child"></i> Child Records</a>
                <a href="appointments.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> My Appointments</a>
            </div>
        <?php else: ?>
            <!-- MOTHER MENU -->
            <div class="nav-group">
                <div class="nav-label">Main Menu</div>
                <a href="mother_dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="register_fingerprint.php" class="nav-link"><i class="fas fa-fingerprint"></i> Fingerprint</a>
                <a href="admin_notes.php" class="nav-link"><i class="fas fa-sticky-note"></i> Notes & Files</a>
                <a href="mother_profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
            </div>
            <div class="nav-group">
                <div class="nav-label">Health</div>
                <a href="register_child.php" class="nav-link"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php" class="nav-link"><i class="fas fa-child"></i> Child Records</a>
                <a href="appointments.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> Appointments</a>
                <a href="link_devices.php" class="nav-link"><i class="fas fa-link"></i> Link Devices</a>
            </div>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-label">System</div>
            <a href="<?= $user_role === 'healthworker' ? 'reports.php' : 'reports.php' ?>" class="nav-link"><i class="fas fa-chart-pie"></i> Health Reports</a>
            <a href="logout.php" class="nav-link" style="color: #fca5a5; margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h2><?= $user_role === 'healthworker' ? 'My Assigned Appointments' : 'My Appointments' ?></h2>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">
                    <?= $user_role === 'healthworker' ? 'View appointments assigned to you by the administrator.' : 'View your upcoming and past clinic visits.' ?>
                </p>
            </div>
            <a href="<?= BASE_URL . getDashboardUrl($user_role) ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="cards-grid">
            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p><?= $user_role === 'healthworker' ? 'You have not been assigned any appointments yet.' : 'You do not have any appointments scheduled.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                    <div class="apt-card">
                        <div class="apt-header">
                            <div class="apt-date">
                                <i class="far fa-calendar-alt"></i> <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                                <span style="margin: 0 4px;">·</span>
                                <i class="far fa-clock"></i> <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                            </div>
                            <span class="apt-status status-<?= strtolower($apt['status']) ?>">
                                <?= htmlspecialchars($apt['status']) ?>
                            </span>
                        </div>
                        
                        <div class="apt-title"><?= htmlspecialchars($apt['reason']) ?></div>
                        
                        <div class="apt-details">
                            <?php if ($user_role === 'healthworker'): ?>
                                <span><i class="fas fa-female"></i> Mother: <strong><?= htmlspecialchars($apt['mother_name']) ?></strong></span>
                                <?php if ($apt['child_name']): ?>
                                    <span><i class="fas fa-child"></i> Child: <strong><?= htmlspecialchars($apt['child_name']) ?></strong></span>
                                <?php endif; ?>
                                <span><i class="fas fa-phone"></i> Contact: <strong><?= htmlspecialchars($apt['phone'] ?? 'N/A') ?></strong></span>
                            <?php else: ?>
                                <span><i class="fas fa-user-md"></i> Health Worker: <strong><?= htmlspecialchars($apt['worker_name'] ?? 'Not Assigned') ?></strong></span>
                                <?php if ($apt['child_name']): ?>
                                    <span><i class="fas fa-child"></i> For Child: <strong><?= htmlspecialchars($apt['child_name']) ?></strong></span>
                                <?php endif; ?>
                                <?php if ($apt['facility']): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> Location: <strong><?= htmlspecialchars($apt['facility']) ?></strong></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($apt['notes']): ?>
                                <span><i class="fas fa-info-circle"></i> Notes: <?= htmlspecialchars($apt['notes']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="apt-actions">
                            <?php if ($user_role === 'mother' && in_array($apt['status'], ['scheduled', 'confirmed'])): ?>
                                <a href="?cancel_id=<?= $apt['id'] ?>" class="btn btn-outline" style="color: #ef4444; border-color: #fca5a5;" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                    Cancel
                                </a>
                            <?php elseif ($user_role === 'healthworker' && $apt['status'] === 'scheduled'): ?>
                                <!-- Health workers could potentially have a "Mark as Completed" button here in the future -->
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>