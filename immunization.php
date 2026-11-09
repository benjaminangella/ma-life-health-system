<?php
// immunization.php - View and Track Immunizations
require_once 'config.php';
require_once 'sms_queue.php';

// Allow both mother and healthworker
$allowed = array('mother', 'healthworker');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}
$user_role = strtolower($_SESSION['user_role']);
if (!in_array($user_role, $allowed)) {
    die('Access Denied.');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get mother ID if user is mother
$mother_id = 0;
if ($user_role == 'mother') {
    $stmt = $db->prepare("SELECT id FROM mothers WHERE user_id = ?");
    $stmt->execute(array($user_id));
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mother) $mother_id = $mother['id'];
    else die('Mother profile not found.');
}

// Children list
$children_list = array();
if ($user_role == 'mother') {
    $stmt = $db->prepare("SELECT id, full_name FROM children WHERE mother_id = ? ORDER BY full_name");
    $stmt->execute(array($mother_id));
    $children_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("
        SELECT c.id, c.full_name, u.full_name AS mother_name
        FROM children c
        JOIN mothers m ON c.mother_id = m.id
        JOIN users u ON m.user_id = u.id
        ORDER BY c.full_name
    ");
    $children_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Selected child
$child_id = 0;
if (isset($_GET['child_id'])) $child_id = (int)$_GET['child_id'];
elseif (isset($_POST['child_id'])) $child_id = (int)$_POST['child_id'];
elseif (!empty($children_list)) $child_id = $children_list[0]['id'];

$immunizations = array();
$child_name = '';
if ($child_id) {
    if ($user_role == 'mother') {
        $stmt = $db->prepare("SELECT id FROM children WHERE id = ? AND mother_id = ?");
        $stmt->execute(array($child_id, $mother_id));
        if (!$stmt->fetch()) die('Access denied.');
    }
    // Uses due_date (not administration_date)
    $stmt = $db->prepare("
        SELECT i.*, c.full_name AS child_name
        FROM immunizations i
        JOIN children c ON i.child_id = c.id
        WHERE i.child_id = ?
        ORDER BY i.due_date DESC
    ");
    $stmt->execute(array($child_id));
    $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT full_name FROM children WHERE id = ?");
    $stmt->execute(array($child_id));
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    $child_name = isset($child['full_name']) ? $child['full_name'] : '';
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_immunization'])) {
    $vaccine_name = isset($_POST['vaccine_name']) ? trim($_POST['vaccine_name']) : '';
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
    $date_given = isset($_POST['date_given']) && $_POST['date_given'] != '' ? $_POST['date_given'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    $child_id = isset($_POST['child_id']) ? (int)$_POST['child_id'] : 0;

    if ($user_role == 'mother' && $child_id) {
        $stmt = $db->prepare("SELECT id FROM children WHERE id = ? AND mother_id = ?");
        $stmt->execute(array($child_id, $mother_id));
        if (!$stmt->fetch()) $error = 'Access denied.';
    }

    if ($child_id && $vaccine_name && $due_date && empty($error)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO immunizations (child_id, vaccine_name, due_date, date_given, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(array($child_id, $vaccine_name, $due_date, $date_given, $status));

            // SMS to mother
            $stmt = $db->prepare("
                SELECT u.phone, u.id AS mother_user_id, c.full_name AS child_name
                FROM children c
                JOIN mothers m ON c.mother_id = m.id
                JOIN users u ON m.user_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute(array($child_id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['phone'])) {
                $msg = "MA-LIFE: Immunization recorded for {$row['child_name']}.\nVaccine: $vaccine_name\nStatus: " . ucfirst($status);
                ma_life_queue_sms($db, $row['phone'], $msg, 'immunization', $row['mother_user_id']);
            }

            $message = "Immunization record added successfully!";

            $stmt = $db->prepare("
                SELECT i.*, c.full_name AS child_name
                FROM immunizations i
                JOIN children c ON i.child_id = c.id
                WHERE i.child_id = ?
                ORDER BY i.due_date DESC
            ");
            $stmt->execute(array($child_id));
            $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Error adding immunization: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $record_id = (int)$_GET['id'];
    if ($user_role == 'mother') {
        $stmt = $db->prepare("DELETE FROM immunizations WHERE id = ? AND child_id IN (SELECT id FROM children WHERE mother_id = ?)");
        $stmt->execute(array($record_id, $mother_id));
    } else {
        $stmt = $db->prepare("DELETE FROM immunizations WHERE id = ?");
        $stmt->execute(array($record_id));
    }
    $message = "Immunization record deleted successfully!";
    header('Location: immunization.php?child_id=' . $child_id);
    exit();
}

// Handle mark done
if (isset($_GET['done']) && isset($_GET['id'])) {
    $record_id = (int)$_GET['id'];
    $today = date('Y-m-d');
    if ($user_role == 'mother') {
        $stmt = $db->prepare("UPDATE immunizations SET status='done', date_given=? WHERE id=? AND child_id IN (SELECT id FROM children WHERE mother_id=?)");
        $stmt->execute(array($today, $record_id, $mother_id));
    } else {
        $stmt = $db->prepare("UPDATE immunizations SET status='done', date_given=? WHERE id=?");
        $stmt->execute(array($today, $record_id));
    }
    $message = "Immunization marked as done!";
    header('Location: immunization.php?child_id=' . $child_id);
    exit();
}

$vaccine_schedule = array(
    'BCG' => 'At birth', 'Polio 0' => 'At birth',
    'DPT-HepB-Hib 1' => '6 weeks', 'Polio 1' => '6 weeks',
    'DPT-HepB-Hib 2' => '10 weeks', 'Polio 2' => '10 weeks',
    'DPT-HepB-Hib 3' => '14 weeks', 'Polio 3' => '14 weeks',
    'Measles-Rubella 1' => '9 months', 'Yellow Fever' => '9 months',
    'Measles-Rubella 2' => '18 months', 'DPT Booster' => '18 months'
);

$theme = ($user_role == 'mother') ? 'mother' : 'healthworker';
$primary = ($user_role == 'mother') ? '#ad1457' : '#00796b';
$sidebar = ($user_role == 'mother') ? '#ad1457' : '#00796b';
$hover = ($user_role == 'mother') ? '#880e4f' : '#004d40';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization - MA-LIFE</title>
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
        .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
        .page-header { margin-bottom: 25px; }
        .page-header h2 { color: #333; }
        .filter-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-section label { font-weight: 600; color: #333; }
        .filter-section select { padding: 8px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: #fafafa; min-width: 200px; }
        .filter-section select:focus { border-color: <?php echo $primary; ?>; outline: none; }
        .card-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .card .card-title { color: <?php echo $primary; ?>; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid <?php echo $theme === 'mother' ? '#fce4ec' : '#e0f2f1'; ?>; }
        .immunization-list { max-height: 500px; overflow-y: auto; }
        .immunization-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .immunization-item:last-child { border-bottom: none; }
        .immunization-item .name { font-weight: 600; color: #333; }
        .immunization-item .due-date { color: #666; font-size: 0.85rem; }
        .immunization-item .date-given { color: #2e7d32; font-size: 0.85rem; }
        .status { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-done { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-overdue { background: #ffebee; color: #c62828; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.85rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px;
            font-size: 0.9rem; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus { border-color: <?php echo $primary; ?>; outline: none; background: white; }
        .btn-submit { padding: 10px 25px; background: <?php echo $primary; ?>; color: white; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: <?php echo $hover; ?>; }
        .btn-sm { padding: 5px 12px; border: none; border-radius: 5px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-success-sm { background: #2e7d32; color: white; }
        .btn-danger-sm { background: #ffebee; color: #c62828; }
        .message { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .no-data { text-align: center; padding: 30px; color: #999; }
        .no-data i { font-size: 2rem; display: block; margin-bottom: 10px; }
        .schedule-list { margin-top: 15px; }
        .schedule-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; }
        .schedule-item:last-child { border-bottom: none; }
        @media (max-width: 1024px) { .card-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar, .main { margin-left: 200px; }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar, .main { margin-left: 0; }
        }
    </style>
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
                    <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                    <a href="immunization.php" class="active"><i class="fas fa-syringe"></i> Immunization</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="pair_device.php"><i class="fas fa-watch"></i> Pair Device</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php else: ?>
                    <a href="healthworker.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
                    <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                    <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                    <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                    <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                    <a href="immunization.php" class="active"><i class="fas fa-syringe"></i> Immunization</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar"><div class="header"><h2><i class="fas fa-syringe"></i> IMMUNIZATION</h2></div></div>

    <div class="main">
        <?php if ($message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="filter-section">
            <label><i class="fas fa-child"></i> Select Child:</label>
            <form method="GET" style="display:flex; gap:10px;">
                <select name="child_id" onchange="this.form.submit()">
                    <?php foreach ($children_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $child_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($child_id && !empty($children_list)): ?>
            <div class="card-grid">
                <div class="card">
                    <div class="card-title"><i class="fas fa-list"></i> Immunization Records</div>
                    <div class="immunization-list">
                        <?php if (empty($immunizations)): ?>
                            <div class="no-data"><i class="fas fa-syringe"></i><p>No records yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($immunizations as $im): ?>
                                <?php
                                $status = $im['status'];
                                if ($status === 'pending' && strtotime($im['due_date']) < time()) $status = 'overdue';
                                ?>
                                <div class="immunization-item">
                                    <div>
                                        <div class="name"><?php echo htmlspecialchars($im['vaccine_name']); ?></div>
                                        <div class="due-date"><i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($im['due_date'])); ?></div>
                                        <?php if (!empty($im['date_given'])): ?>
                                            <div class="date-given"><i class="fas fa-check-circle"></i> Given: <?php echo date('M d, Y', strtotime($im['date_given'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="status status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                                        <div style="margin-top:5px;">
                                            <?php if ($status === 'pending' || $status === 'overdue'): ?>
                                                <a href="?done=1&id=<?php echo $im['id']; ?>&child_id=<?php echo $child_id; ?>" class="btn-sm btn-success-sm" onclick="return confirm('Mark as done?')"><i class="fas fa-check"></i></a>
                                            <?php endif; ?>
                                            <a href="?delete=1&id=<?php echo $im['id']; ?>&child_id=<?php echo $child_id; ?>" class="btn-sm btn-danger-sm" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add Immunization</div>
                    <form method="POST">
                        <input type="hidden" name="child_id" value="<?php echo $child_id; ?>">
                        <input type="hidden" name="add_immunization" value="1">
                        <div class="form-group">
                            <label>Vaccine Name *</label>
                            <input type="text" name="vaccine_name" placeholder="e.g., BCG, Polio" required>
                        </div>
                        <div class="form-group">
                            <label>Due Date *</label>
                            <input type="date" name="due_date" required>
                        </div>
                        <div class="form-group">
                            <label>Date Given</label>
                            <input type="date" name="date_given">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="pending">Pending</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Add Record</button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-top:25px;">
                <div class="card-title"><i class="fas fa-calendar-alt"></i> Recommended Schedule</div>
                <div class="schedule-list">
                    <?php foreach ($vaccine_schedule as $v => $t): ?>
                        <div class="schedule-item">
                            <span><?php echo $v; ?></span>
                            <span style="color:#666;"><?php echo $t; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data" style="background:white; border-radius:12px; padding:40px;">
                <i class="fas fa-child" style="font-size:3rem;"></i>
                <h3>No Child Selected</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>