<?php
require_once 'config.php';
require_once 'sms_queue.php';
requireRole('admin');

$db = getDB();

// Manual flush
$flush_result = null;
if (isset($_GET['flush']) && $_GET['flush'] === '1') {
    if (!ma_life_has_internet()) {
        $flush_result = array('error' => 'No internet connection.');
    } else {
        $n = ma_life_flush_queue($db, 100);
        $flush_result = array('sent' => $n);
    }
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = array();
$params = array();
if ($filter_type !== '') { $where[] = "notification_type = ?"; $params[] = $filter_type; }
if ($filter_status !== '') { $where[] = "status = ?"; $params[] = $filter_status; }
$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications_log $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pages = $total > 0 ? ceil($total / $limit) : 1;

$stmt = $db->prepare("SELECT n.*, u.full_name FROM notifications_log n LEFT JOIN users u ON n.user_id = u.id $where_sql ORDER BY n.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queue_stats = ma_life_queue_stats($db);
$stmt = $db->query("SELECT notification_type, COUNT(*) as c FROM notifications_log GROUP BY notification_type ORDER BY c DESC");
$by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
$online = ma_life_has_internet();
$session_remaining = getSessionRemaining();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications Log - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:15px; margin-bottom:25px; }
        .stat-box { background:#fff; padding:15px; border-radius:10px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size:1.8rem; font-weight:700; }
        .stat-box .label { color:#666; font-size:0.8rem; }
        .stat-box.sent .number { color:#2e7d32; }
        .stat-box.queued .number { color:#f57c00; }
        .stat-box.failed .number { color:#c62828; }
        .stat-box.total .number { color:#00897b; }
        .table-wrap { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 15px rgba(0,0,0,0.05); }
        table { width:100%; border-collapse:collapse; }
        th { background:#37474f; color:#fff; padding:12px 15px; text-align:left; font-size:0.85rem; }
        td { padding:10px 15px; border-bottom:1px solid #f0f0f0; font-size:0.9rem; vertical-align:top; }
        tr:hover td { background:#f5f9fc; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:600; background:#e0f2f1; color:#00695c; }
        .badge-status { padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700; }
        .badge-status.sent { background:#e8f5e9; color:#2e7d32; }
        .badge-status.queued { background:#fff3e0; color:#e65100; }
        .badge-status.failed { background:#ffebee; color:#c62828; }
        .pagination { display:flex; justify-content:center; gap:8px; margin-top:20px; }
        .pagination a, .pagination span { padding:6px 12px; background:#fff; color:#00897b; text-decoration:none; border-radius:5px; border:1px solid #e0e0e0; }
        .pagination .active { background:#00897b; color:#fff; }
        .btn { padding:10px 20px; background:#00897b; color:#fff; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-weight:600; border:none; cursor:pointer; }
        .btn-orange { background:#f57c00; }
        .btn-disabled { background:#bdbdbd; cursor:not-allowed; }
        .alert { padding:15px 20px; border-radius:8px; margin-bottom:20px; }
        .alert-success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .alert-error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        .empty-state { text-align:center; padding:60px 20px; color:#999; }
        .empty-state i { font-size:3rem; display:block; margin-bottom:15px; color:#ddd; }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="img/logo.png" alt="MA-LIFE" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%2300809b%22/%3E%3C/svg%3E'"></div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
                <a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
                <a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar"><div class="header"><h2><i class="fas fa-bell"></i> NOTIFICATIONS LOG</h2></div></div>

    <div class="main">
        <div class="page-header">
            <h2>Sent Notifications</h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <?php if ($queue_stats['queued'] > 0 && $online): ?>
                    <a href="?flush=1" class="btn btn-orange" data-confirm="Send <?php echo $queue_stats['queued']; ?> queued SMS now?">
                        <i class="fas fa-paper-plane"></i> Send <?php echo $queue_stats['queued']; ?> Queued
                    </a>
                <?php elseif ($queue_stats['queued'] > 0 && !$online): ?>
                    <span class="btn btn-disabled"><i class="fas fa-wifi"></i> Offline — <?php echo $queue_stats['queued']; ?> queued</span>
                <?php endif; ?>
                <a href="administrator.php" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <?php if ($flush_result): ?>
            <?php if (isset($flush_result['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($flush_result['error']); ?></div>
            <?php else: ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Sent <?php echo $flush_result['sent']; ?> queued SMS!</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box total"><div class="number"><?php echo $queue_stats['sent'] + $queue_stats['queued'] + $queue_stats['failed']; ?></div><div class="label">Total</div></div>
            <div class="stat-box sent"><div class="number"><?php echo $queue_stats['sent']; ?></div><div class="label">Sent</div></div>
            <div class="stat-box queued"><div class="number"><?php echo $queue_stats['queued']; ?></div><div class="label">Queued</div></div>
            <div class="stat-box failed"><div class="number"><?php echo $queue_stats['failed']; ?></div><div class="label">Failed</div></div>
            <div class="stat-box" style="background:<?php echo $online ? '#e8f5e9' : '#ffebee'; ?>;">
                <div class="number" style="color:<?php echo $online ? '#2e7d32' : '#c62828'; ?>;"><i class="fas fa-<?php echo $online ? 'wifi' : 'wifi-slash'; ?>"></i></div>
                <div class="label"><?php echo $online ? 'Online' : 'Offline'; ?></div>
            </div>
        </div>

        <div style="margin-bottom:15px;">
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                <select name="type" style="padding:8px 15px; border:2px solid #e0e0e0; border-radius:8px;">
                    <option value="">All Types</option>
                    <?php foreach ($by_type as $t): ?>
                        <option value="<?php echo $t['notification_type']; ?>" <?php echo $filter_type === $t['notification_type'] ? 'selected' : ''; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $t['notification_type'])); ?> (<?php echo $t['c']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" style="padding:8px 15px; border:2px solid #e0e0e0; border-radius:8px;">
                    <option value="">All Status</option>
                    <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="queued" <?php echo $filter_status === 'queued' ? 'selected' : ''; ?>>Queued</option>
                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
                <button type="submit" class="btn">Filter</button>
                <?php if ($filter_type || $filter_status): ?><a href="admin_notifications.php" class="btn" style="background:#e0e0e0; color:#333;">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Date/Time</th><th>Recipient</th><th>Phone</th><th>Type</th><th>Message</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-bell-slash"></i><h3>No notifications yet</h3></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['full_name'] ? $log['full_name'] : 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                <td><span class="badge"><?php echo ucwords(str_replace('_', ' ', $log['notification_type'])); ?></span></td>
                                <td style="max-width:400px;"><?php echo nl2br(htmlspecialchars($log['message'])); ?></td>
                                <td><span class="badge-status <?php echo $log['status']; ?>"><?php echo ucfirst($log['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span>
                    <?php else: ?><a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        var remaining = <?php echo $session_remaining; ?>;
        function tick() {
            if (remaining <= 0) { window.location.href = 'login.php?expired=1'; return; }
            remaining--;
        }
        setInterval(tick, 1000);
    </script>
</body>
</html>