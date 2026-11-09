<?php
// view_child.php - Child details with photo + document uploads
require_once 'config.php';

$allowed = array('mother', 'healthworker', 'admin');
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed)) {
    header('Location: login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($child_id == 0) {
    header('Location: child_records.php');
    exit();
}

$message = '';
$error = '';

// ==========================================
// FETCH DATA
// ==========================================

$stmt = $db->prepare("
    SELECT
        c.*,
        u.full_name AS mother_name,
        u.email AS mother_email,
        u.phone AS mother_phone,
        u.address AS mother_address,
        m.national_id AS mother_national_id
    FROM children c
    JOIN mothers m ON c.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute(array($child_id));
$child = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) { http_response_code(404); exit('Child not found.'); }

if ($user_role === 'mother') {
    $stmt = $db->prepare("SELECT id FROM mothers WHERE user_id = ?");
    $stmt->execute(array($user_id));
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mother || $mother['id'] != $child['mother_id']) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

// ---- Upload profile photo ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!empty($_FILES['child_photo']['name'])) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $max_size = 3 * 1024 * 1024;

        $file = $_FILES['child_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error code: ' . $file['error'];
        } elseif (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPG, PNG, GIF or WebP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'Photo must be less than 3 MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_name = 'child_' . $child_id . '_' . time() . '.' . strtolower($ext);
            $directory = __DIR__ . '/uploads/children';
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            $dest = $directory . '/' . $new_name;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Delete old photo
                $stmt = $db->prepare("SELECT photo FROM children WHERE id = ?");
                $stmt->execute(array($child_id));
                $old = $stmt->fetchColumn();
                if ($old && file_exists(__DIR__ . '/uploads/children/' . $old)) {
                    @unlink(__DIR__ . '/uploads/children/' . $old);
                }

                $stmt = $db->prepare("UPDATE children SET photo = ? WHERE id = ?");
                $stmt->execute(array($new_name, $child_id));
                $message = 'Profile photo updated!';
            } else {
                $error = 'Failed to move uploaded file. Check folder permissions on uploads/children/.';
            }
        }
    }
}

// ---- Upload document ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    if (!empty($_FILES['document_file']['name'])) {
        $doc_type = isset($_POST['doc_type']) ? trim($_POST['doc_type']) : 'other';
        $doc_title = isset($_POST['doc_title']) ? trim($_POST['doc_title']) : '';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

        $allowed_types = array(
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $max_size = 10 * 1024 * 1024;

        $file = $_FILES['document_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error code: ' . $file['error'];
        } elseif (!in_array($file['type'], $allowed_types)) {
            $error = 'Allowed types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File must be less than 10 MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
            $new_name = 'doc_' . $child_id . '_' . time() . '_' . $safe_name;
            $directory = __DIR__ . '/uploads/documents';
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            $dest = $directory . '/' . $new_name;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $db->prepare("
                    INSERT INTO child_documents
                    (child_id, uploaded_by, doc_type, doc_title, file_name, file_path, file_size, file_mime, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array(
                    $child_id,
                    $user_id,
                    $doc_type,
                    $doc_title != '' ? $doc_title : $safe_name,
                    $safe_name,
                    $new_name,
                    $file['size'],
                    $file['type'],
                    $notes != '' ? $notes : null
                ));
                $message = 'Document uploaded successfully!';
            } else {
                $error = 'Failed to move uploaded file. Check folder permissions on uploads/documents/.';
            }
        }
    }
}

// ---- Delete document ----
if (isset($_GET['delete_doc']) && is_numeric($_GET['delete_doc'])) {
    $doc_id = (int)$_GET['delete_doc'];
    $stmt = $db->prepare("SELECT * FROM child_documents WHERE id = ? AND child_id = ?");
    $stmt->execute(array($doc_id, $child_id));
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($doc) {
        @unlink(__DIR__ . '/uploads/documents/' . $doc['file_path']);
        $db->prepare("DELETE FROM child_documents WHERE id = ?")->execute(array($doc_id));
        $message = 'Document deleted.';
    }
}

$stmt = $db->prepare("SELECT d.*, u.full_name AS uploader_name FROM child_documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE d.child_id = ? ORDER BY d.uploaded_at DESC");
$stmt->execute(array($child_id));
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM immunizations WHERE child_id = ? ORDER BY due_date DESC");
$stmt->execute(array($child_id));
$immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM growth_records WHERE child_id = ? ORDER BY record_date DESC");
$stmt->execute(array($child_id));
$growth_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

function calculateAge($birth) {
    if (empty($birth)) return 'N/A';
    $b = new DateTime($birth); $n = new DateTime(); $d = $b->diff($n);
    $txt = '';
    if ($d->y > 0) $txt .= $d->y . ' year' . ($d->y > 1 ? 's ' : ' ');
    if ($d->m > 0) $txt .= $d->m . ' month' . ($d->m > 1 ? 's ' : ' ');
    $txt .= $d->d . ' day' . ($d->d != 1 ? 's' : '');
    return trim($txt);
}

$theme = ($user_role === 'mother') ? 'mother' : 'healthworker';
$primary = ($user_role === 'mother') ? '#ad1457' : '#00796b';
$sidebar = ($user_role === 'mother') ? '#ad1457' : '#00796b';
$hover = ($user_role === 'mother') ? '#880e4f' : '#004d40';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Child - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: <?php echo $theme === 'mother' ? '#fce4ec' : '#e0f7fa'; ?>; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: <?php echo $sidebar; ?>; color: white; overflow-y: auto; z-index: 100; }
        .nav h2 { text-align: center; padding: 20px; background: <?php echo $hover; ?>; font-size: 20px; }
        .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
        .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 14px 20px; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.15); transition: 0.3s; }
        nav a:hover { background: <?php echo $hover; ?>; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: <?php echo $hover; ?>; border-left: 4px solid #ffb74d; }
        .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 30px; }
        .header h2 { color: <?php echo $primary; ?>; font-weight: 500; font-size: 1.2rem; }
        .main { margin-left: 250px; padding: 25px 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px; }
        .page-header h2 { color: #333; }
        .btn { padding: 10px 20px; background: <?php echo $primary; ?>; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn:hover { background: <?php echo $hover; ?>; color: white; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h3 { color: <?php echo $primary; ?>; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid <?php echo $theme === 'mother' ? '#fce4ec' : '#e0f2f1'; ?>; }
        .profile-header { display: flex; align-items: center; gap: 25px; padding: 25px; background: white; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .profile-photo-wrap { position: relative; }
        .profile-photo {
            width: 120px; height: 120px; border-radius: 50%;
            background: <?php echo $theme === 'mother' ? '#fce4ec' : '#e0f2f1'; ?>;
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 700; color: <?php echo $primary; ?>;
            overflow: hidden; border: 4px solid <?php echo $primary; ?>;
        }
        .profile-photo img { width: 100%; height: 100%; object-fit: cover; }
        .profile-photo .camera-btn {
            position: absolute; bottom: 0; right: 0;
            width: 40px; height: 40px; border-radius: 50%;
            background: <?php echo $primary; ?>; color: white;
            border: 3px solid white; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
        }
        .profile-photo .camera-btn:hover { background: <?php echo $hover; ?>; }
        .profile-header h1 { color: #333; font-size: 1.5rem; }
        .profile-header .subtitle { color: #666; font-size: 0.9rem; margin-top: 5px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-male { background: #e3f2fd; color: #0d47a1; }
        .badge-female { background: #fce4ec; color: #ad1457; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
        .detail-item { padding: 12px 15px; background: #f9f9f9; border-radius: 8px; }
        .detail-item .label { font-size: 0.75rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-item .value { font-weight: 600; color: #333; font-size: 0.95rem; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: <?php echo $theme === 'mother' ? '#fce4ec' : '#e0f2f1'; ?>; color: <?php echo $primary; ?>; padding: 10px; text-align: left; font-size: 0.85rem; }
        td { padding: 10px; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
        .no-data { text-align: center; padding: 30px; color: #999; }
        .no-data i { font-size: 2rem; display: block; margin-bottom: 10px; }
        .status-pill { padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
        .status-done { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-overdue { background: #ffebee; color: #c62828; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-top: 15px; }
        .doc-card { background: #f9f9f9; border-radius: 10px; padding: 15px; border: 2px solid #f0f0f0; position: relative; }
        .doc-card:hover { border-color: <?php echo $primary; ?>; }
        .doc-card .icon { font-size: 2rem; color: <?php echo $primary; ?>; margin-bottom: 8px; }
        .doc-card .name { font-weight: 600; color: #333; font-size: 0.9rem; word-break: break-all; }
        .doc-card .meta { color: #999; font-size: 0.75rem; margin-top: 3px; }
        .doc-card .type-badge { display: inline-block; background: <?php echo $primary; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.65rem; margin-top: 5px; }
        .doc-card .actions { display: flex; gap: 5px; margin-top: 10px; }
        .doc-card .actions a { padding: 4px 10px; border-radius: 5px; text-decoration: none; font-size: 0.75rem; font-weight: 600; }
        .doc-card .btn-view { background: <?php echo $primary; ?>; color: white; }
        .doc-card .btn-dl { background: #e0f2f1; color: #00695c; }
        .doc-card .btn-del { background: #ffebee; color: #c62828; }
        .upload-form { background: #f9f9f9; padding: 20px; border-radius: 10px; border: 2px dashed #ddd; }
        .upload-form label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.85rem; }
        .upload-form input, .upload-form select, .upload-form textarea { width: 100%; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem; margin-bottom: 12px; background: white; }
        .upload-form input:focus, .upload-form select:focus, .upload-form textarea:focus { border-color: <?php echo $primary; ?>; outline: none; }
        .upload-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .upload-form .submit-btn { padding: 10px 25px; background: <?php echo $primary; ?>; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar, .main { margin-left: 200px; }
            .profile-header { flex-direction: column; text-align: center; }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar, .main { margin-left: 0; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="img/logo.png" alt="MA-LIFE" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3C/svg%3E'"></div>
            <h2><?php echo $theme === 'mother' ? 'Mother Portal' : 'Health Worker'; ?></h2>
            <nav>
                <?php if ($theme === 'mother'): ?>
                    <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                    <a href="child_records.php" class="active"><i class="fas fa-child"></i> Child Records</a>
                    <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="pair_device.php"><i class="fas fa-watch"></i> Pair Device</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php else: ?>
                    <a href="healthworker.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
                    <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                    <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                    <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                    <a href="child_records.php" class="active"><i class="fas fa-child"></i> Child Records</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar"><div class="header"><h2><i class="fas fa-child"></i> CHILD DETAILS</h2></div></div>

    <div class="main">
        <?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="page-header">
            <h2><?php echo htmlspecialchars($child['full_name']); ?></h2>
            <a href="child_records.php" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <!-- Profile with photo -->
        <div class="profile-header">
            <div class="profile-photo-wrap">
                <div class="profile-photo">
                    <?php if (!empty($child['photo']) && file_exists(__DIR__ . '/uploads/children/' . $child['photo'])): ?>
                        <img src="uploads/children/<?php echo htmlspecialchars($child['photo']); ?>" alt="Child Photo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($child['full_name'], 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" id="photoForm" style="display:none;">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="child_photo" id="photoInput" accept="image/*">
                </form>
                <label for="photoInput" class="camera-btn" title="Upload photo">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
            <div>
                <h1><?php echo htmlspecialchars($child['full_name']); ?></h1>
                <div class="subtitle">
                    <i class="fas fa-birthday-cake"></i> <?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?>
                    &nbsp;·&nbsp;
                    <span class="badge badge-<?php echo strtolower($child['gender']); ?>"><?php echo $child['gender']; ?></span>
                    &nbsp;·&nbsp;
                    <i class="fas fa-clock"></i> <?php echo calculateAge($child['date_of_birth']); ?> old
                </div>
            </div>
        </div>

        <!-- Basic info -->
        <div class="card">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            <div class="detail-grid">
                <div class="detail-item"><div class="label">Child ID</div><div class="value"><?php echo htmlspecialchars($child['child_id'] ? $child['child_id'] : 'Pending'); ?></div></div>
                <div class="detail-item"><div class="label">Date of Birth</div><div class="value"><?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?></div></div>
                <div class="detail-item"><div class="label">Gender</div><div class="value"><?php echo $child['gender']; ?></div></div>
                <div class="detail-item"><div class="label">Birth Weight</div><div class="value"><?php echo $child['birth_weight'] ? $child['birth_weight'] . ' kg' : 'N/A'; ?></div></div>
                <div class="detail-item"><div class="label">Birth Length</div><div class="value"><?php echo $child['birth_length'] ? $child['birth_length'] . ' cm' : 'N/A'; ?></div></div>
                <div class="detail-item"><div class="label">Place of Birth</div><div class="value"><?php echo htmlspecialchars($child['place_of_birth'] ? $child['place_of_birth'] : 'N/A'); ?></div></div>
                <div class="detail-item"><div class="label">Delivery Type</div><div class="value"><?php echo htmlspecialchars($child['delivery_type'] ? $child['delivery_type'] : 'N/A'); ?></div></div>
            </div>
        </div>

        <!-- Documents -->
        <div class="card">
            <h3><i class="fas fa-folder-open"></i> Documents (<?php echo count($documents); ?>)</h3>

            <div class="upload-form">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    <div class="form-row">
                        <div>
                            <label>Document Type *</label>
                            <select name="doc_type" required>
                                <option value="">Select type...</option>
                                <option value="birth_certificate">Birth Certificate</option>
                                <option value="immunization_card">Immunization Card</option>
                                <option value="medical_report">Medical Report</option>
                                <option value="lab_result">Lab Result</option>
                                <option value="growth_chart">Growth Chart</option>
                                <option value="consent_form">Consent Form</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Document Title</label>
                            <input type="text" name="doc_title" placeholder="e.g., Birth Certificate 2024">
                        </div>
                    </div>
                    <label>Choose File * (PDF, JPG, PNG, DOC, DOCX — max 10 MB)</label>
                    <input type="file" name="document_file" required accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Any notes about this document..."></textarea>
                    <button type="submit" class="submit-btn"><i class="fas fa-upload"></i> Upload Document</button>
                </form>
            </div>

            <?php if (empty($documents)): ?>
                <div class="no-data" style="margin-top:20px;"><i class="fas fa-folder-open"></i><p>No documents uploaded yet.</p></div>
            <?php else: ?>
                <div class="doc-grid">
                    <?php foreach ($documents as $doc): ?>
                        <?php
                        $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                        $icon = 'fa-file';
                        if ($ext === 'pdf') $icon = 'fa-file-pdf';
                        elseif (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) $icon = 'fa-file-image';
                        elseif (in_array($ext, array('doc', 'docx'))) $icon = 'fa-file-word';
                        ?>
                        <div class="doc-card">
                            <div class="icon"><i class="fas <?php echo $icon; ?>"></i></div>
                            <div class="name"><?php echo htmlspecialchars($doc['doc_title']); ?></div>
                            <div class="meta"><?php echo round($doc['file_size'] / 1024); ?> KB · <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></div>
                            <span class="type-badge"><?php echo ucwords(str_replace('_', ' ', $doc['doc_type'])); ?></span>
                            <?php if (!empty($doc['notes'])): ?>
                                <div class="meta" style="margin-top:5px;"><em><?php echo htmlspecialchars($doc['notes']); ?></em></div>
                            <?php endif; ?>
                            <div class="actions">
                                <a href="download_document.php?type=child_document&amp;inline=1&amp;id=<?php echo (int) $doc['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                                <a href="download_document.php?type=child_document&amp;id=<?php echo (int) $doc['id']; ?>" class="btn-dl"><i class="fas fa-download"></i></a>
                                <a href="?id=<?php echo $child_id; ?>&delete_doc=<?php echo $doc['id']; ?>" class="btn-del" data-confirm="Delete this document?"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mother's info -->
        <div class="card">
            <h3><i class="fas fa-female"></i> Mother's Information</h3>
            <div class="detail-grid">
                <div class="detail-item"><div class="label">Mother's Name</div><div class="value"><?php echo htmlspecialchars($child['mother_name']); ?></div></div>
                <div class="detail-item"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($child['mother_email']); ?></div></div>
                <div class="detail-item"><div class="label">Phone</div><div class="value"><?php echo htmlspecialchars($child['mother_phone'] ? $child['mother_phone'] : 'N/A'); ?></div></div>
                <div class="detail-item"><div class="label">National ID</div><div class="value"><?php echo htmlspecialchars($child['mother_national_id'] ? $child['mother_national_id'] : 'N/A'); ?></div></div>
                <div class="detail-item"><div class="label">Address</div><div class="value"><?php echo htmlspecialchars($child['mother_address'] ? $child['mother_address'] : 'N/A'); ?></div></div>
            </div>
        </div>

        <!-- Immunizations -->
        <div class="card">
            <h3><i class="fas fa-syringe"></i> Immunizations (<?php echo count($immunizations); ?>)</h3>
            <?php if (empty($immunizations)): ?>
                <div class="no-data"><i class="fas fa-syringe"></i><p>No immunization records yet.</p></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Vaccine</th><th>Due Date</th><th>Date Given</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($immunizations as $imm): ?>
                            <?php
                            $status = $imm['status'];
                            if ($status === 'pending' && strtotime($imm['due_date']) < time()) {
                                $status = 'overdue';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($imm['vaccine_name']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($imm['due_date'])); ?></td>
                                <td><?php echo $imm['date_given'] ? date('M d, Y', strtotime($imm['date_given'])) : '—'; ?></td>
                                <td><span class="status-pill status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Growth records -->
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Growth Records (<?php echo count($growth_records); ?>)</h3>
            <?php if (empty($growth_records)): ?>
                <div class="no-data"><i class="fas fa-chart-line"></i><p>No growth records yet.</p></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Date</th><th>Weight</th><th>Height</th><th>Head</th><th>BMI</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($growth_records as $g): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($g['record_date'])); ?></td>
                                <td><?php echo $g['weight'] ? $g['weight'] . ' kg' : '—'; ?></td>
                                <td><?php echo $g['height'] ? $g['height'] . ' cm' : '—'; ?></td>
                                <td><?php echo $g['head_circumference_cm'] ? $g['head_circumference_cm'] . ' cm' : '—'; ?></td>
                                <td><?php echo $g['bmi'] ? $g['bmi'] : '—'; ?></td>
                                <td><?php echo $g['nutritional_status'] ? ucfirst($g['nutritional_status']) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Auto-submit photo form when a file is chosen -->
    <script>
        document.getElementById('photoInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('photoForm').submit();
            }
        });
    </script>
</body>
</html>