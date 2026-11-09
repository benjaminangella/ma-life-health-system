<?php
// admin_documents.php - Admin view of ALL documents across the system
require_once 'config.php';
require_once 'upload_helper.php';
requireRole('admin');

$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (delete_document($db, (int)$_GET['delete'], 'admin')) {
        $message = 'Document deleted successfully.';
    } else {
        $error = 'Could not delete document.';
    }
}

// Filters
$filter_type = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : '';
$filter_doc_type = isset($_GET['doc_type']) ? trim($_GET['doc_type']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = array();
$params = array();

if ($filter_type !== '') {
    $where[] = "d.entity_type = ?";
    $params[] = $filter_type;
}
if ($filter_doc_type !== '') {
    $where[] = "d.doc_type = ?";
    $params[] = $filter_doc_type;
}
if ($search !== '') {
    $where[] = "(d.doc_title LIKE ? OR d.file_name LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $db->prepare("
    SELECT d.*, u.full_name AS uploader_name
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    $where_sql
    ORDER BY d.uploaded_at DESC
    LIMIT 500
");
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $db->query("SELECT COUNT(*) FROM documents");
$total_docs = $stmt->fetchColumn();

$stmt = $db->query("SELECT entity_type, COUNT(*) AS c FROM documents GROUP BY entity_type");
$by_entity = array();
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $by_entity[$r['entity_type']] = $r['c']; }

$stmt = $db->query("SELECT doc_type, COUNT(*) AS c FROM documents GROUP BY doc_type ORDER BY c DESC");
$by_doc_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total size
$stmt = $db->query("SELECT SUM(file_size) FROM documents");
$total_size = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Documents - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: #37474f; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 1.8rem; font-weight: 700; color: #1976d2; }
        .stat-box .label { color: #666; font-size: 0.85rem; margin-top: 4px; }
        .stat-box .icon { font-size: 1.5rem; color: #1976d2; margin-bottom: 5px; }
        .filter-bar { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-bar input, .filter-bar select { padding: 9px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.9rem; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: #00897b; outline: none; }
        .filter-bar .btn { padding: 9px 20px; background: #00897b; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .filter-bar .btn:hover { background: #00695c; }
        .filter-bar .btn-clear { background: #e0e0e0; color: #333; }
        .filter-bar .btn-clear:hover { background: #bdbdbd; }
        .doc-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .doc-table table { width: 100%; border-collapse: collapse; }
        .doc-table th { background: #37474f; color: white; padding: 14px 16px; text-align: left; font-size: 0.85rem; }
        .doc-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; vertical-align: middle; }
        .doc-table tr:hover td { background: #f5f9fc; }
        .doc-icon { font-size: 1.4rem; color: #1976d2; margin-right: 8px; vertical-align: middle; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #e0f2f1; color: #00695c; text-transform: capitalize; }
        .badge.entity-user { background: #fce4ec; color: #ad1457; }
        .badge.entity-mother { background: #fce4ec; color: #ad1457; }
        .badge.entity-child { background: #e3f2fd; color: #0d47a1; }
        .badge.entity-health_worker { background: #e0f2f1; color: #00695c; }
        .badge.entity-appointment { background: #fff3e0; color: #e65100; }
        .actions { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; border: none; border-radius: 5px; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-view { background: #1976d2; color: white; }
        .btn-view:hover { background: #0d47a1; color: white; }
        .btn-dl { background: #e0f2f1; color: #00695c; }
        .btn-dl:hover { background: #b2dfdb; }
        .btn-del { background: #ffebee; color: #c62828; }
        .btn-del:hover { background: #ffcdd2; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .empty { text-align: center; padding: 50px 20px; color: #999; }
        .empty i { font-size: 3rem; display: block; margin-bottom: 15px; color: #ddd; }
        .table-container { overflow-x: auto; }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: stretch; }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo" style="padding: 20px; display: flex; justify-content: center;">
                <?php include 'get_profile_picture.php'; ?>
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
                <a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
                <a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
                <a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="admin_documents.php" class="active"><i class="fas fa-folder-open"></i> All Documents</a>
                <a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="reports_admin.php"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar"><div class="header"><h2><i class="fas fa-folder-open"></i> ALL DOCUMENTS</h2></div></div>

    <div class="main">
        <div class="page-header">
            <h2>Document Management</h2>
            <a href="administrator.php" class="btn btn-sm" style="background:#00897b; color:white; padding:10px 20px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <div class="number"><?php echo $total_docs; ?></div>
                <div class="label">Total Documents</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-female"></i></div>
                <div class="number"><?php echo $by_entity['mother'] ?? 0; ?></div>
                <div class="label">Mother Documents</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-child"></i></div>
                <div class="number"><?php echo $by_entity['child'] ?? 0; ?></div>
                <div class="label">Child Documents</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-user-md"></i></div>
                <div class="number"><?php echo $by_entity['health_worker'] ?? 0; ?></div>
                <div class="label">Health Worker Docs</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-hdd"></i></div>
                <div class="number"><?php echo round($total_size / 1024 / 1024, 1); ?> MB</div>
                <div class="label">Storage Used</div>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
                <input type="text" name="q" placeholder="Search by title, filename, uploader..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; min-width:220px;">
                <select name="entity_type">
                    <option value="">All Entities</option>
                    <option value="mother" <?php echo $filter_type === 'mother' ? 'selected' : ''; ?>>Mother</option>
                    <option value="child" <?php echo $filter_type === 'child' ? 'selected' : ''; ?>>Child</option>
                    <option value="health_worker" <?php echo $filter_type === 'health_worker' ? 'selected' : ''; ?>>Health Worker</option>
                    <option value="user" <?php echo $filter_type === 'user' ? 'selected' : ''; ?>>User</option>
                </select>
                <select name="doc_type">
                    <option value="">All Types</option>
                    <?php foreach ($by_doc_type as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['doc_type']); ?>" <?php echo $filter_doc_type === $t['doc_type'] ? 'selected' : ''; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $t['doc_type'])); ?> (<?php echo $t['c']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <?php if ($filter_type || $filter_doc_type || $search): ?>
                    <a href="admin_documents.php" class="btn btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="doc-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Belongs To</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr><td colspan="7"><div class="empty"><i class="fas fa-folder-open"></i><h3>No documents found</h3><p>Try adjusting your filters or upload some documents.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <i class="fas <?php echo document_icon($doc['file_path']); ?> doc-icon"></i>
                                        <strong><?php echo htmlspecialchars($doc['doc_title']); ?></strong>
                                        <div style="font-size:0.75rem; color:#999;"><?php echo htmlspecialchars($doc['file_name']); ?></div>
                                    </td>
                                    <td><span class="badge"><?php echo str_replace('_', ' ', $doc['doc_type']); ?></span></td>
                                    <td><span class="badge entity-<?php echo htmlspecialchars($doc['entity_type']); ?>"><?php echo str_replace('_', ' ', $doc['entity_type']); ?> #<?php echo $doc['entity_id']; ?></span></td>
                                    <td><?php echo round($doc['file_size'] / 1024, 1); ?> KB</td>
                                    <td><?php echo htmlspecialchars($doc['uploader_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="uploads/documents/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-sm btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download_document.php?type=document&id=<?php echo $doc['id']; ?>" class="btn-sm btn-dl" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?delete=<?php echo $doc['id']; ?>" class="btn-sm btn-del" data-confirm="Delete this document permanently?" title="Delete">
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

    <!-- SESSION GUARD -->
    <script>
        (function() {
            fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) window.location.replace('login.php?expired=1');
                    else if (data.role !== 'admin') window.location.replace('login.php?security=1');
                })
                .catch(err => console.error('Session check failed:', err));
        })();
    </script>
</body>
</html>