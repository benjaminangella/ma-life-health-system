<?php
// admin_notes.php - Personal notes + sharing + note detail modal
require_once 'config.php';
require_once 'upload_helper.php';
requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

// ---- Handle new note ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_note') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (empty($title)) {
        $error = 'Note title is required.';
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO admin_notes (user_id, title, content, category, is_pinned) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array($user_id, $title, $content, $category, $is_pinned));
            $note_id = $db->lastInsertId();

            if (!empty($_FILES['attachments']['name'][0])) {
                $count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($_FILES['attachments']['name'][$i])) continue;
                    $single = array(
                        'name' => $_FILES['attachments']['name'][$i],
                        'type' => $_FILES['attachments']['type'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error' => $_FILES['attachments']['error'][$i],
                        'size' => $_FILES['attachments']['size'][$i],
                    );
                    $r = upload_document($single);
                    if ($r['success']) {
                        $is_image = (strpos($r['mime'], 'image/') === 0) ? 1 : 0;
                        $stmt2 = $db->prepare("INSERT INTO admin_note_attachments (note_id, file_name, file_path, file_size, file_mime, is_image) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt2->execute(array($note_id, $r['original'], $r['filename'], $r['size'], $r['mime'], $is_image));
                    }
                }
            }
            $db->commit();
            $message = "Note <strong>" . htmlspecialchars($title) . "</strong> created successfully!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error creating note: ' . $e->getMessage();
        }
    }
}

// ---- Handle sharing ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_note') {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $to_user_id = (int)($_POST['to_user_id'] ?? 0);
    $to_role = trim($_POST['to_role'] ?? '');

    if (!$note_id || !$to_user_id) {
        $error = 'Please select a recipient.';
    } else {
        $stmt = $db->prepare("SELECT id FROM admin_notes WHERE id = ? AND user_id = ? AND shared_with_id IS NULL");
        $stmt->execute(array($note_id, $user_id));
        if (!$stmt->fetch()) {
            $error = 'You can only share your own notes.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE admin_notes 
                    SET shared_with_id = ?, shared_with_role = ?, shared_at = NOW(), 
                        shared_from_id = ?, is_read = 0
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute(array($to_user_id, $to_role, $user_id, $note_id, $user_id));

                $log = $db->prepare("INSERT INTO notes_sharing_log (note_id, from_user_id, to_user_id) VALUES (?, ?, ?)");
                $log->execute(array($note_id, $user_id, $to_user_id));

                $message = "Note shared successfully!";
            } catch (Exception $e) {
                $error = 'Error sharing: ' . $e->getMessage();
            }
        }
    }
}

// ---- Handle pin ----
if (isset($_GET['toggle_pin']) && is_numeric($_GET['toggle_pin'])) {
    $id = (int)$_GET['toggle_pin'];
    $stmt = $db->prepare("UPDATE admin_notes SET is_pinned = 1 - is_pinned WHERE id = ? AND user_id = ?");
    $stmt->execute(array($id, $user_id));
    $message = 'Note updated.';
}

// ---- Handle delete note ----
if (isset($_GET['delete_note']) && is_numeric($_GET['delete_note'])) {
    $id = (int)$_GET['delete_note'];
    $stmt = $db->prepare("SELECT a.* FROM admin_note_attachments a JOIN admin_notes n ON a.note_id = n.id WHERE n.id = ? AND n.user_id = ?");
    $stmt->execute(array($id, $user_id));
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $f) { @unlink(__DIR__ . '/uploads/documents/' . $f['file_path']); }
    $db->prepare("DELETE FROM admin_notes WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
    $message = 'Note deleted.';
}

// ---- Handle delete attachment ----
if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment'])) {
    $aid = (int)$_GET['delete_attachment'];
    $stmt = $db->prepare("SELECT a.* FROM admin_note_attachments a JOIN admin_notes n ON a.note_id = n.id WHERE a.id = ? AND n.user_id = ?");
    $stmt->execute(array($aid, $user_id));
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($f) {
        @unlink(__DIR__ . '/uploads/documents/' . $f['file_path']);
        $db->prepare("DELETE FROM admin_note_attachments WHERE id = ?")->execute(array($aid));
        $message = 'Attachment removed.';
    }
}

// ---- Mark shared note as read ----
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $stmt = $db->prepare("UPDATE admin_notes SET is_read = 1 WHERE id = ? AND shared_with_id = ?");
    $stmt->execute(array($id, $user_id));
}

// ---- Fetch MY notes ----
$stmt = $db->prepare("
    SELECT n.*, 
        (SELECT COUNT(*) FROM admin_note_attachments WHERE note_id = n.id) AS attachment_count,
        u.full_name AS recipient_name
    FROM admin_notes n
    LEFT JOIN users u ON n.shared_with_id = u.id
    WHERE n.user_id = ? AND n.shared_with_id IS NULL
    ORDER BY n.is_pinned DESC, n.updated_at DESC
");
$stmt->execute(array($user_id));
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Fetch notes SHARED WITH ME ----
$stmt = $db->prepare("
    SELECT n.*, u.full_name AS sender_name,
        (SELECT COUNT(*) FROM admin_note_attachments WHERE note_id = n.id) AS attachment_count
    FROM admin_notes n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.shared_with_id = ?
    ORDER BY n.is_read ASC, n.shared_at DESC
");
$stmt->execute(array($user_id));
$shared_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_count = 0;
foreach ($shared_notes as $sn) { if (!$sn['is_read']) $unread_count++; }

// ---- Fetch attachments ----
$all_note_ids = array_merge(array_column($notes, 'id'), array_column($shared_notes, 'id'));
$attachments_by_note = array();
if (!empty($all_note_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_note_ids), '?'));
    $stmt = $db->prepare("SELECT * FROM admin_note_attachments WHERE note_id IN ($placeholders) ORDER BY uploaded_at DESC");
    $stmt->execute($all_note_ids);
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $attachments_by_note[$a['note_id']][] = $a;
    }
}

// ---- Fetch recipients based on role ----
$recipients = array();
if ($user_role === 'mother') {
    $stmt = $db->query("
        SELECT u.id, u.full_name, u.role, hw.specialization, hw.facility_name
        FROM users u
        JOIN health_workers hw ON u.id = hw.user_id
        WHERE u.status = 'active'
        ORDER BY u.full_name
    ");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role === 'healthworker') {
    $stmt = $db->query("SELECT id, full_name, role FROM users WHERE role = 'admin' AND status = 'active' ORDER BY full_name");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats
$total_notes = count($notes);
$total_attachments = 0;
foreach ($notes as $n) { $total_attachments += $n['attachment_count']; }
$sent_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notes_sharing_log WHERE from_user_id = ?");
    $stmt->execute(array($user_id));
    $sent_count = $stmt->fetchColumn();
} catch (Exception $e) {}

// Theme colors
if ($user_role === 'mother') {
    $primary = '#ad1457'; $primary_hover = '#880e4f'; $bg = '#fce4ec'; $sidebar_bg = '#ad1457'; $sidebar_hover = '#880e4f'; $label = 'Mother Portal';
} elseif ($user_role === 'healthworker') {
    $primary = '#00796b'; $primary_hover = '#004d40'; $bg = '#e0f7fa'; $sidebar_bg = '#00796b'; $sidebar_hover = '#004d40'; $label = 'Health Worker';
} else {
    $primary = '#0A4C54'; $primary_hover = '#146b76'; $bg = '#f1f8e9'; $sidebar_bg = '#37474f'; $sidebar_hover = '#263238'; $label = 'Administrator';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Notes & Files - MA-LIFE</title>
<link rel="stylesheet" href="main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',Arial,sans-serif; }
body { background:<?php echo $bg; ?>; }
.sidebar { position:fixed; width:250px; height:100vh; background:<?php echo $sidebar_bg; ?>; color:white; overflow-y:auto; z-index:100; }
.nav h2 { text-align:center; padding:20px; background:<?php echo $sidebar_hover; ?>; font-size:20px; }
.logo { display:flex; justify-content:center; align-items:center; padding:20px; }
nav a { display:block; padding:14px 20px; color:#e0e0e0; text-decoration:none; border-bottom:1px solid rgba(255,255,255,0.08); transition:0.3s; font-size:0.95rem; position:relative; }
nav a:hover { background:<?php echo $sidebar_hover; ?>; color:white; padding-left:28px; }
nav a i { width:25px; margin-right:10px; }
nav a.active { background:<?php echo $sidebar_hover; ?>; color:white; border-left:4px solid #ffb74d; }
nav a .badge-count { position:absolute; right:15px; top:50%; transform:translateY(-50%); background:#f57c00; color:white; font-size:0.7rem; padding:2px 8px; border-radius:10px; font-weight:700; }
.topbar { margin-left:250px; height:70px; background:white; box-shadow:0 2px 5px rgba(0,0,0,0.1); display:flex; align-items:center; padding:0 30px; }
.header h2 { color:<?php echo $primary; ?>; font-weight:500; font-size:1.2rem; }
.main { margin-left:250px; padding:25px 30px; min-height:calc(100vh - 70px); }
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
.page-header h2 { color:#333; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:15px; margin-bottom:25px; }
.stat-box { background:white; padding:18px; border-radius:10px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
.stat-box .number { font-size:1.6rem; font-weight:700; color:<?php echo $primary; ?>; }
.stat-box .label { color:#666; font-size:0.85rem; margin-top:4px; }
.stat-box .icon { font-size:1.4rem; color:<?php echo $primary; ?>; margin-bottom:5px; }
.stat-box.pinned .number, .stat-box.pinned .icon { color:#f57c00; }
.stat-box.files .number, .stat-box.files .icon { color:#00897b; }
.stat-box.inbox .number, .stat-box.inbox .icon { color:#7b1fa2; }
.tabs { display:flex; gap:5px; margin-bottom:20px; border-bottom:2px solid #e0e0e0; }
.tab { padding:12px 24px; background:none; border:none; font-weight:600; color:#666; cursor:pointer; font-size:0.95rem; border-bottom:3px solid transparent; }
.tab.active { color:<?php echo $primary; ?>; border-bottom-color:<?php echo $primary; ?>; }
.tab .badge-count { background:#f57c00; color:white; font-size:0.7rem; padding:2px 8px; border-radius:10px; margin-left:6px; }
.grid-2col { display:grid; grid-template-columns:1fr 1.5fr; gap:25px; align-items:start; }
@media (max-width:992px) { .grid-2col { grid-template-columns:1fr; } }
.card { background:white; border-radius:12px; padding:25px; box-shadow:0 2px 15px rgba(0,0,0,0.05); }
.card-title { color:<?php echo $primary; ?>; font-size:1.1rem; font-weight:600; margin-bottom:20px; padding-bottom:12px; border-bottom:2px solid <?php echo $bg; ?>; }
.form-group { margin-bottom:15px; }
.form-group label { display:block; font-weight:600; color:#333; margin-bottom:5px; font-size:0.9rem; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:0.9rem; font-family:inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:<?php echo $primary; ?>; outline:none; }
.form-group textarea { resize:vertical; min-height:100px; }
.form-group .checkbox-row { display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer; }
.form-group .checkbox-row input { width:auto; }
.upload-zone { border:2px dashed <?php echo $primary; ?>; border-radius:10px; padding:20px; text-align:center; background:white; cursor:pointer; }
.upload-zone:hover { background:<?php echo $bg; ?>; }
.upload-zone i { font-size:2rem; color:<?php echo $primary; ?>; display:block; margin-bottom:8px; }
.upload-zone p { color:#666; font-size:0.85rem; margin:0; }
.btn-submit { padding:12px 28px; background:<?php echo $primary; ?>; color:white; border:none; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
.btn-submit:hover { background:<?php echo $primary_hover; ?>; }
.btn { padding:10px 20px; background:<?php echo $primary; ?>; color:white; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-weight:600; border:none; cursor:pointer; }
.btn:hover { background:<?php echo $primary_hover; ?>; color:white; }
.note-card { background:white; border-radius:12px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,0.05); margin-bottom:15px; border-left:4px solid <?php echo $primary; ?>; transition:0.3s; }
.note-card:hover { box-shadow:0 6px 24px rgba(0,0,0,0.1); transform:translateY(-2px); }
.note-card.pinned { border-left-color:#f57c00; background:linear-gradient(90deg,#fff8e1 0%,white 40%); }
.note-card.unread { border-left-color:#f57c00; background:linear-gradient(90deg,#fff3e0 0%,white 40%); }
.note-card .note-title { font-weight:700; color:<?php echo $primary; ?>; font-size:1.05rem; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.note-card .note-meta { color:#999; font-size:0.75rem; margin-bottom:8px; display:flex; gap:10px; flex-wrap:wrap; }
.note-card .note-meta .cat-badge { display:inline-block; background:<?php echo $bg; ?>; color:<?php echo $primary; ?>; padding:2px 8px; border-radius:12px; font-size:0.7rem; font-weight:600; text-transform:uppercase; }
.note-card .note-meta .shared-badge { display:inline-block; background:#ede7f6; color:#5e35b1; padding:2px 8px; border-radius:12px; font-size:0.7rem; font-weight:700; }
.note-card .note-content { color:#444; font-size:0.9rem; line-height:1.6; white-space:pre-wrap; word-break:break-word; }
.note-card .note-actions { display:flex; gap:5px; margin-top:12px; padding-top:12px; border-top:1px solid #f0f0f0; flex-wrap:wrap; }
.note-card .note-actions a, .note-card .note-actions button { padding:5px 12px; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:5px; border:none; cursor:pointer; }
.btn-pin { background:#fff3e0; color:#e65100; }
.btn-pin.active { background:#f57c00; color:white; }
.btn-del { background:#ffebee; color:#c62828; }
.btn-share { background:#ede7f6; color:#5e35b1; }
.btn-mark { background:#e0f2f1; color:#00695c; }
.attachments { margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; }
.attachments .att { background:#f9f9f9; border:1px solid #eee; border-radius:8px; padding:8px 12px; display:inline-flex; align-items:center; gap:8px; font-size:0.8rem; }
.attachments .att img.att-thumb { width:32px; height:32px; border-radius:5px; object-fit:cover; }
.attachments .att .att-actions { display:flex; gap:4px; margin-left:6px; }
.attachments .att .att-actions a { padding:2px 6px; border-radius:4px; font-size:0.7rem; text-decoration:none; font-weight:600; }
.attachments .att .att-actions .view { background:<?php echo $primary; ?>; color:white; }
.attachments .att .att-actions .del { background:#ffebee; color:#c62828; }
.message { padding:15px 20px; border-radius:8px; margin-bottom:20px; }
.message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
.message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
.empty-state { text-align:center; padding:60px 20px; color:#999; }
.empty-state i { font-size:3rem; color:#ddd; margin-bottom:15px; display:block; }
.empty-state h3 { color:#666; margin-bottom:8px; font-weight:500; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.active { display:flex; }
.modal { background:white; border-radius:12px; padding:30px; max-width:500px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
.modal h3 { color:<?php echo $primary; ?>; margin-bottom:20px; }
.modal .modal-actions { display:flex; gap:10px; margin-top:20px; justify-content:flex-end; }
.modal .btn-cancel { padding:10px 20px; background:#e0e0e0; color:#333; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
.no-recipients { background:#fff3e0; color:#e65100; padding:12px 16px; border-radius:8px; margin-bottom:15px; font-size:0.85rem; border-left:4px solid #f57c00; }
@media (max-width:768px) { .sidebar { width:200px; } .topbar, .main { margin-left:200px; } }
@media (max-width:550px) { .sidebar { width:0; overflow:hidden; } .topbar, .main { margin-left:0; } }
</style>
</head>
<body>
<div class="sidebar">
<div class="nav">
<div class="logo"><?php include 'get_profile_picture.php'; ?></div>
<h2><?php echo $label; ?></h2>
<nav>
<?php if ($user_role === 'admin'): ?>
<a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
<a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
<a href="admin_notes.php" class="active"><i class="fas fa-sticky-note"></i> My Notes & Files<?php if ($unread_count>0): ?><span class="badge-count"><?php echo $unread_count; ?></span><?php endif; ?></a>
<a href="manage_users_admin.php"><i class="fas fa-users"></i> Manage Users</a>
<a href="health_workers_admin.php"><i class="fas fa-user-md"></i> Health Workers</a>
<a href="mothers_admin.php"><i class="fas fa-female"></i> Mothers</a>
<a href="children_admin.php"><i class="fas fa-child"></i> Children</a>
<a href="appointments_admin.php"><i class="fas fa-calendar"></i> Appointments</a>
<a href="admin_documents.php"><i class="fas fa-folder-open"></i> All Documents</a>
<a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
<a href="reports_admin.php"><i class="fas fa-chart-bar"></i> System Reports</a>
<a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
<?php elseif ($user_role === 'healthworker'): ?>
<a href="healthworker.php"><i class="fas fa-home"></i> Dashboard</a>
<a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
<a href="admin_notes.php" class="active"><i class="fas fa-sticky-note"></i> My Notes & Files<?php if ($unread_count>0): ?><span class="badge-count"><?php echo $unread_count; ?></span><?php endif; ?></a>
<a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
<a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
<a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
<a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
<a href="child_records.php"><i class="fas fa-child"></i> Child Health Records</a>
<a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
<a href="hw_documents.php"><i class="fas fa-folder-open"></i> Documents</a>
<a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
<?php else: ?>
<a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
<a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
<a href="admin_notes.php" class="active"><i class="fas fa-sticky-note"></i> My Notes & Files<?php if ($unread_count>0): ?><span class="badge-count"><?php echo $unread_count; ?></span><?php endif; ?></a>
<a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
<a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
<a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
<a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
<a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
<a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
<?php endif; ?>
<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</nav>
</div>
</div>

<div class="topbar"><div class="header"><h2><i class="fas fa-sticky-note"></i> MY NOTES & FILES — <?php echo strtoupper($user_role); ?></h2></div></div>

<div class="main">
<div class="page-header">
<div>
<h2>My Personal Notes</h2>
<p style="color:#666; font-size:0.9rem; margin-top:4px;">
<?php if ($user_role === 'mother'): ?>Share notes with your health worker.
<?php elseif ($user_role === 'healthworker'): ?>Share notes with the administrator.
<?php else: ?>Receive notes from health workers.<?php endif; ?>
</p>
</div>
<a href="<?php echo getDashboardUrl($user_role); ?>" class="btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

<div class="stats-row">
<div class="stat-box"><div class="icon"><i class="fas fa-sticky-note"></i></div><div class="number"><?php echo $total_notes; ?></div><div class="label">My Notes</div></div>
<div class="stat-box inbox"><div class="icon"><i class="fas fa-inbox"></i></div><div class="number"><?php echo count($shared_notes); ?></div><div class="label">Shared With Me</div></div>
<div class="stat-box files"><div class="icon"><i class="fas fa-paperclip"></i></div><div class="number"><?php echo $total_attachments; ?></div><div class="label">My Attachments</div></div>
<div class="stat-box pinned"><div class="icon"><i class="fas fa-paper-plane"></i></div><div class="number"><?php echo $sent_count; ?></div><div class="label">Sent</div></div>
</div>

<div class="tabs">
<button class="tab active" data-tab="mine" onclick="switchTab('mine')"><i class="fas fa-folder"></i> My Notes (<?php echo $total_notes; ?>)</button>
<button class="tab" data-tab="inbox" onclick="switchTab('inbox')"><i class="fas fa-inbox"></i> Shared With Me (<?php echo count($shared_notes); ?>)<?php if ($unread_count>0): ?><span class="badge-count"><?php echo $unread_count; ?> new</span><?php endif; ?></button>
</div>

<div id="tab-mine" class="tab-content">
<?php if (in_array($user_role, ['mother','healthworker']) && empty($recipients)): ?>
<div class="no-recipients"><i class="fas fa-exclamation-triangle"></i> <strong>No recipients available.</strong> You can't share notes yet because there are no <?php echo ($user_role === 'mother') ? 'health workers' : 'administrators'; ?> registered in the system.</div>
<?php endif; ?>

<div class="grid-2col">
<div class="card">
<div class="card-title"><i class="fas fa-plus-circle"></i> Create a New Note</div>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action" value="create_note">
<div class="form-group"><label>Note Title *</label><input type="text" name="title" placeholder="e.g., Test results summary" required></div>
<div class="form-group"><label>Category</label>
<select name="category">
<option value="general">General</option>
<option value="reminder">Reminder</option>
<option value="meeting">Meeting Notes</option>
<option value="task">Task</option>
<option value="report">Report Draft</option>
<option value="personal">Personal</option>
<option value="urgent">Urgent</option>
</select>
</div>
<div class="form-group"><label>Note Content</label><textarea name="content" placeholder="Write your note here..."></textarea></div>
<div class="form-group"><label class="checkbox-row"><input type="checkbox" name="is_pinned" value="1"> <span><i class="fas fa-thumbtack" style="color:#f57c00;"></i> Pin this note to the top</span></label></div>
<div class="form-group">
<label>Attachments (optional)</label>
<label for="attachments" class="upload-zone"><i class="fas fa-cloud-upload-alt"></i><p>Click to attach documents, pictures, or PDFs</p><p style="font-size:0.75rem; color:#999; margin-top:5px;">Max 10 MB per file</p></label>
<input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx" style="display:none;">
<div id="filePreview" style="margin-top:10px; font-size:0.8rem; color:#555;"></div>
</div>
<button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Note</button>
</form>
</div>

<div>
<?php if (empty($notes)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-sticky-note"></i><h3>No notes yet</h3><p>Create your first note using the form on the left.</p></div></div>
<?php else: ?>
<?php foreach ($notes as $note): ?>
<?php
$att_json = [];
if (!empty($attachments_by_note[$note['id']])) {
    foreach ($attachments_by_note[$note['id']] as $a) {
        $att_json[] = [
            'name' => $a['file_name'],
            'url'  => 'uploads/documents/' . $a['file_path'],
            'download' => 'download_document.php?type=note_attachment&id=' . $a['id'],
            'size' => round($a['file_size'] / 1024, 1) . ' KB',
            'is_image' => (int)$a['is_image'],
            'icon' => document_icon($a['file_path']),
        ];
    }
}
?>
<div class="note-card <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>"
     style="cursor:pointer;"
     onclick='openNoteDetail(<?php echo json_encode([
        "title" => $note["title"],
        "content" => $note["content"],
        "category" => $note["category"],
        "date" => date("M d, Y · H:i", strtotime($note["updated_at"])),
        "from" => "You",
        "is_pinned" => (int)$note["is_pinned"],
        "attachments" => $att_json,
     ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
<div class="note-title"><?php if ($note['is_pinned']): ?><i class="fas fa-thumbtack" style="color:#f57c00;"></i><?php endif; ?> <?php echo htmlspecialchars($note['title']); ?></div>
<div class="note-meta"><span><i class="fas fa-clock"></i> <?php echo date('M d, Y · H:i', strtotime($note['updated_at'])); ?></span><span class="cat-badge"><?php echo htmlspecialchars($note['category']); ?></span></div>
<?php if (!empty($note['content'])): ?><div class="note-content"><?php echo nl2br(htmlspecialchars($note['content'])); ?></div><?php endif; ?>
<?php if (!empty($attachments_by_note[$note['id']])): ?>
<div class="attachments">
<?php foreach ($attachments_by_note[$note['id']] as $att): ?>
<div class="att">
<?php if ($att['is_image'] && file_exists(__DIR__ . '/uploads/documents/' . $att['file_path'])): ?>
<img src="uploads/documents/<?php echo htmlspecialchars($att['file_path']); ?>" class="att-thumb" alt="">
<?php else: ?>
<i class="fas <?php echo document_icon($att['file_path']); ?>" style="color:<?php echo $primary; ?>;"></i>
<?php endif; ?>
<span><?php echo htmlspecialchars($att['file_name']); ?></span>
<span style="color:#999;">(<?php echo round($att['file_size']/1024,1); ?> KB)</span>
<div class="att-actions">
<a href="uploads/documents/<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" class="view" onclick="event.stopPropagation();"><i class="fas fa-eye"></i></a>
<a href="download_document.php?type=note_attachment&id=<?php echo $att['id']; ?>" class="view" onclick="event.stopPropagation();"><i class="fas fa-download"></i></a>
<a href="?delete_attachment=<?php echo $att['id']; ?>" class="del" onclick="event.stopPropagation(); return confirm('Remove this attachment?');"><i class="fas fa-times"></i></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="note-actions" onclick="event.stopPropagation();">
<?php if (!empty($recipients)): ?>
<button type="button" class="btn-share" onclick='event.stopPropagation(); openShareModal(<?php echo $note['id']; ?>, "<?php echo htmlspecialchars(addslashes($note["title"])); ?>")'><i class="fas fa-share"></i> Share</button>
<?php endif; ?>
<a href="?toggle_pin=<?php echo $note['id']; ?>" class="btn-pin <?php echo $note['is_pinned'] ? 'active' : ''; ?>" onclick="event.stopPropagation();"><i class="fas fa-thumbtack"></i> <?php echo $note['is_pinned'] ? 'Unpin' : 'Pin'; ?></a>
<a href="?delete_note=<?php echo $note['id']; ?>" class="btn-del" onclick="event.stopPropagation(); return confirm('Delete this note?');"><i class="fas fa-trash"></i> Delete</a>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>

<div id="tab-inbox" class="tab-content" style="display:none;">
<?php if (empty($shared_notes)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-inbox"></i><h3>No shared notes yet</h3><p>Notes shared with you will appear here.</p></div></div>
<?php else: ?>
<?php foreach ($shared_notes as $note): ?>
<?php
$att_json = [];
if (!empty($attachments_by_note[$note['id']])) {
    foreach ($attachments_by_note[$note['id']] as $a) {
        $att_json[] = [
            'name' => $a['file_name'],
            'url'  => 'uploads/documents/' . $a['file_path'],
            'download' => 'download_document.php?type=note_attachment&id=' . $a['id'],
            'size' => round($a['file_size'] / 1024, 1) . ' KB',
            'is_image' => (int)$a['is_image'],
            'icon' => document_icon($a['file_path']),
        ];
    }
}
?>
<div class="note-card <?php echo $note['is_read'] ? '' : 'unread'; ?>"
     style="cursor:pointer;"
     onclick='openNoteDetail(<?php echo json_encode([
        "title" => $note["title"],
        "content" => $note["content"],
        "category" => $note["category"],
        "date" => date("M d, Y · H:i", strtotime($note["shared_at"])),
        "from" => $note["sender_name"],
        "is_pinned" => 0,
        "attachments" => $att_json,
     ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
<div class="note-title"><?php echo htmlspecialchars($note['title']); ?> <?php if (!$note['is_read']): ?><span class="shared-badge">NEW</span><?php endif; ?></div>
<div class="note-meta">
<span><i class="fas fa-user"></i> From: <strong><?php echo htmlspecialchars($note['sender_name']); ?></strong></span>
<span><i class="fas fa-clock"></i> <?php echo date('M d, Y · H:i', strtotime($note['shared_at'])); ?></span>
<span class="cat-badge"><?php echo htmlspecialchars($note['category']); ?></span>
</div>
<?php if (!empty($note['content'])): ?><div class="note-content"><?php echo nl2br(htmlspecialchars($note['content'])); ?></div><?php endif; ?>
<?php if (!empty($attachments_by_note[$note['id']])): ?>
<div class="attachments">
<?php foreach ($attachments_by_note[$note['id']] as $att): ?>
<div class="att">
<?php if ($att['is_image'] && file_exists(__DIR__ . '/uploads/documents/' . $att['file_path'])): ?>
<img src="uploads/documents/<?php echo htmlspecialchars($att['file_path']); ?>" class="att-thumb" alt="">
<?php else: ?>
<i class="fas <?php echo document_icon($att['file_path']); ?>" style="color:<?php echo $primary; ?>;"></i>
<?php endif; ?>
<span><?php echo htmlspecialchars($att['file_name']); ?></span>
<span style="color:#999;">(<?php echo round($att['file_size']/1024,1); ?> KB)</span>
<div class="att-actions">
<a href="uploads/documents/<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" class="view" onclick="event.stopPropagation();"><i class="fas fa-eye"></i></a>
<a href="download_document.php?type=note_attachment&id=<?php echo $att['id']; ?>" class="view" onclick="event.stopPropagation();"><i class="fas fa-download"></i></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="note-actions" onclick="event.stopPropagation();">
<?php if (!$note['is_read']): ?><a href="?mark_read=<?php echo $note['id']; ?>" class="btn-mark" onclick="event.stopPropagation();"><i class="fas fa-check"></i> Mark as Read</a><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="shareModal">
<div class="modal">
<h3><i class="fas fa-share"></i> Share Note</h3>
<p style="color:#666; font-size:0.9rem; margin-bottom:15px;">Sharing: <strong id="shareNoteTitle"></strong></p>
<form method="POST">
<input type="hidden" name="action" value="share_note">
<input type="hidden" name="note_id" id="shareNoteId">
<div class="form-group">
<label>Send To *</label>
<select name="to_user_id" id="shareRecipient" required onchange="updateRoleField(this)">
<option value="">Select recipient...</option>
<?php foreach ($recipients as $r): ?>
<option value="<?php echo $r['id']; ?>" data-role="<?php echo htmlspecialchars($r['role']); ?>">
<?php echo htmlspecialchars($r['full_name']); ?>
<?php if (!empty($r['specialization']) || !empty($r['facility_name'])): ?>
 — <?php echo htmlspecialchars($r['specialization'] ?? ''); ?><?php if (!empty($r['facility_name'])): ?> @ <?php echo htmlspecialchars($r['facility_name']); ?><?php endif; ?>
<?php endif; ?>
</option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" name="to_role" id="shareToRole">
<div class="modal-actions">
<button type="button" class="btn-cancel" onclick="closeShareModal()">Cancel</button>
<button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send</button>
</div>
</form>
</div>
</div>

<!-- Note Detail Modal -->
<div class="modal-overlay" id="noteDetailModal">
<div class="modal" style="max-width:700px; max-height:90vh; overflow-y:auto;">
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
<div>
<h3 style="color:<?php echo $primary; ?>; margin-bottom:5px;" id="detailTitle"></h3>
<div id="detailMeta" style="color:#999; font-size:0.8rem;"></div>
</div>
<button onclick="closeNoteDetail()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999; line-height:1;">&times;</button>
</div>

<div id="detailBody">
<div style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px; white-space:pre-wrap; word-break:break-word; font-size:0.95rem; line-height:1.6;" id="detailContent"></div>

<div id="detailAttachmentsSection">
<h4 style="color:<?php echo $primary; ?>; margin-bottom:10px; font-size:1rem;">
<i class="fas fa-paperclip"></i> Attachments (<span id="detailAttachmentCount">0</span>)
</h4>
<div id="detailAttachments" style="display:flex; flex-direction:column; gap:10px;"></div>
</div>
</div>

<div class="modal-actions" style="margin-top:20px;">
<button type="button" class="btn-cancel" onclick="closeNoteDetail()">Close</button>
</div>
</div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector('.tab[data-tab="' + tab + '"]').classList.add('active');
}

function openShareModal(noteId, noteTitle) {
    document.getElementById('shareNoteId').value = noteId;
    document.getElementById('shareNoteTitle').textContent = noteTitle;
    document.getElementById('shareModal').classList.add('active');
}
function closeShareModal() {
    document.getElementById('shareModal').classList.remove('active');
    document.getElementById('shareRecipient').value = '';
    document.getElementById('shareToRole').value = '';
}
function updateRoleField(sel) {
    const role = sel.options[sel.selectedIndex].getAttribute('data-role') || '';
    document.getElementById('shareToRole').value = role;
}
document.getElementById('shareModal').addEventListener('click', function(e) {
    if (e.target === this) closeShareModal();
});

function openNoteDetail(note) {
    document.getElementById('detailTitle').textContent = note.title || '(No title)';

    let metaHtml = '<i class="fas fa-clock"></i> ' + (note.date || '');
    if (note.category) {
        metaHtml += ' &nbsp;·&nbsp; <span style="background:<?php echo $bg; ?>; color:<?php echo $primary; ?>; padding:2px 8px; border-radius:12px; font-size:0.7rem; font-weight:600; text-transform:uppercase;">' + note.category + '</span>';
    }
    if (note.from) {
        metaHtml += ' &nbsp;·&nbsp; <i class="fas fa-user"></i> From: <strong>' + note.from + '</strong>';
    }
    if (note.is_pinned) {
        metaHtml += ' &nbsp;·&nbsp; <i class="fas fa-thumbtack" style="color:#f57c00;"></i> Pinned';
    }
    document.getElementById('detailMeta').innerHTML = metaHtml;

    document.getElementById('detailContent').textContent = note.content || '(No content)';

    const atts = note.attachments || [];
    document.getElementById('detailAttachmentCount').textContent = atts.length;

    let html = '';
    if (atts.length === 0) {
        html = '<div style="text-align:center; padding:20px; color:#999; font-size:0.9rem;"><i class="fas fa-folder-open" style="font-size:2rem; display:block; margin-bottom:10px;"></i>No attachments on this note.</div>';
    } else {
        atts.forEach(function(a) {
            let thumb = '';
            if (a.is_image) {
                thumb = '<img src="' + a.url + '" style="width:40px; height:40px; border-radius:6px; object-fit:cover;">';
            } else {
                thumb = '<i class="fas ' + (a.icon || 'fa-file') + '" style="font-size:1.4rem; color:<?php echo $primary; ?>;"></i>';
            }
            html += '<div style="display:flex; align-items:center; gap:12px; background:#f9f9f9; padding:10px 14px; border-radius:8px; border:1px solid #eee;">' +
                        thumb +
                        '<div style="flex:1; min-width:0;">' +
                            '<div style="font-weight:600; font-size:0.9rem; word-break:break-all;">' + a.name + '</div>' +
                            '<div style="font-size:0.75rem; color:#999;">' + a.size + '</div>' +
                        '</div>' +
                        '<a href="' + a.url + '" target="_blank" style="padding:6px 12px; background:<?php echo $primary; ?>; color:white; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;"><i class="fas fa-eye"></i> View</a>' +
                        '<a href="' + a.download + '" style="padding:6px 12px; background:#e0f2f1; color:#00695c; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;"><i class="fas fa-download"></i> Download</a>' +
                    '</div>';
        });
    }
    document.getElementById('detailAttachments').innerHTML = html;
    document.getElementById('noteDetailModal').classList.add('active');
}

function closeNoteDetail() {
    document.getElementById('noteDetailModal').classList.remove('active');
}

document.getElementById('noteDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeNoteDetail();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNoteDetail();
        closeShareModal();
    }
});

document.getElementById('attachments').addEventListener('change', function() {
    const preview = document.getElementById('filePreview');
    if (!this.files.length) { preview.innerHTML = ''; return; }
    let html = '<strong>' + this.files.length + ' file(s) selected:</strong><br>';
    for (let i = 0; i < this.files.length; i++) {
        html += '📎 ' + this.files[i].name + ' <span style="color:#999;">(' + (this.files[i].size / 1024).toFixed(1) + ' KB)</span><br>';
    }
    preview.innerHTML = html;
});
document.querySelector('.upload-zone').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('attachments').click();
});

(function() {
    fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
        .then(r => r.json())
        .then(data => { if (!data.logged_in) window.location.replace('login.php?expired=1'); })
        .catch(err => console.error('Session check failed:', err));
})();
</script>
</body>
</html>