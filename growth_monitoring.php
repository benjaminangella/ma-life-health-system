<?php
// growth_monitoring.php - Child Growth Monitoring for PHP 5.3.5
require_once 'config.php';
requireRole('mother');

session_start();
$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get mother's ID
$stmt = $db->prepare("SELECT id FROM mothers WHERE user_id =?");
$stmt->execute(array($user_id));
$mother = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mother) {
    die('Mother profile not found.');
}

$mother_id = $mother['id'];

// Get all children for dropdown
$stmt = $db->prepare("SELECT id, full_name, date_of_birth FROM children WHERE mother_id =? ORDER BY full_name");
$stmt->execute(array($mother_id));
$children_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected child ID
$child_id = 0;
if (isset($_GET['child_id'])) {
    $child_id = (int)$_GET['child_id'];
} elseif (isset($_POST['child_id'])) {
    $child_id = (int)$_POST['child_id'];
} elseif (!empty($children_list)) {
    $child_id = $children_list[0]['id'];
}

// Get child details
$child_name = '';
$child_dob = '';
$child_gender = '';
if ($child_id) {
    $stmt = $db->prepare("SELECT full_name, date_of_birth, gender FROM children WHERE id =? AND mother_id =?");
    $stmt->execute(array($child_id, $mother_id));
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($child) {
        $child_name = $child['full_name'];
        $child_dob = $child['date_of_birth'];
        $child_gender = $child['gender'];
    }
}

// Handle adding growth record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_growth'])) {
    $record_date = isset($_POST['record_date'])? $_POST['record_date'] : '';
    $weight = isset($_POST['weight'])? $_POST['weight'] : null;
    $height = isset($_POST['height'])? $_POST['height'] : null;
    $head_circumference = isset($_POST['head_circumference'])? $_POST['head_circumference'] : null;
    $notes = isset($_POST['notes'])? trim($_POST['notes']) : '';
    $child_id = isset($_POST['child_id'])? (int)$_POST['child_id'] : 0;

    // Calculate BMI
    $bmi = null;
    if ($weight && $height && $height > 0) {
        $height_m = $height / 100; // Convert cm to meters
        $bmi = round($weight / ($height_m * $height_m), 2);
    }

    // Determine nutritional status
    $nutritional_status = 'normal';
    if ($bmi) {
        if ($bmi < 18.5) $nutritional_status = 'underweight';
        elseif ($bmi >= 25 && $bmi < 30) $nutritional_status = 'overweight';
        elseif ($bmi >= 30) $nutritional_status = 'obese';
    }

    if ($child_id && $record_date) {
        try {
            $stmt = $db->prepare("
                INSERT INTO growth_records (
                    child_id, record_date, weight, height,
                    head_circumference, bmi, nutritional_status, notes
                ) VALUES (?,?,?)
            ");
            $stmt->execute(array(
                $child_id,
                $record_date,
                $weight!= ''? $weight : null,
                $height!= ''? $height : null,
                $head_circumference!= ''? $head_circumference : null,
                $bmi,
                $nutritional_status,
                $notes!= ''? $notes : null
            ));
            $message = "Growth record added successfully!";

            // Clear form data
            $_POST = array();

        } catch (Exception $e) {
            $error = 'Error adding growth record: '. $e->getMessage();
        }
    } else {
        $error = 'Please select a child and enter a date.';
    }
}

// Handle delete growth record
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $record_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM growth_records WHERE id =? AND child_id IN (SELECT id FROM children WHERE mother_id =?)");
    $stmt->execute(array($record_id, $mother_id));
    $message = "Growth record deleted successfully!";
}

// Get growth records for selected child
$growth_records = array();
if ($child_id) {
    $stmt = $db->prepare("
        SELECT * FROM growth_records
        WHERE child_id =?
        ORDER BY record_date DESC
    ");
    $stmt->execute(array($child_id));
    $growth_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate age
function calculateAge($birth_date) {
    $birth = new DateTime($birth_date);
    $now = new DateTime();
    $diff = $birth->diff($now);
    $text = '';
    if ($diff->y > 0) $text.= $diff->y. ' years, ';
    if ($diff->m > 0) $text.= $diff->m. ' months, ';
    $text.= $diff->d. ' days';
    return array(
        'years' => $diff->y,
        'months' => $diff->m,
        'days' => $diff->d,
        'text' => $text
    );
}

// Get latest growth record
$latest_record =!empty($growth_records)? $growth_records[0] : null;

// Get growth statistics - PHP 5.3 doesn't have array_column
$stats = array('total' => 0, 'avg_weight' => 0, 'avg_height' => 0, 'last_weight' => 0, 'last_height' => 0);
if ($growth_records) {
    $stats['total'] = count($growth_records);
    $weights = array();
    $heights = array();
    foreach($growth_records as $r) {
        if ($r['weight']!= '' && $r['weight']!== null) $weights[] = $r['weight'];
        if ($r['height']!= '' && $r['height']!== null) $heights[] = $r['height'];
    }
    if (count($weights) > 0) {
        $stats['avg_weight'] = round(array_sum($weights) / count($weights), 2);
    }
    if (count($heights) > 0) {
        $stats['avg_height'] = round(array_sum($heights) / count($heights), 2);
    }
    $stats['last_weight'] = isset($weights[0])? $weights[0] : 0;
    $stats['last_height'] = isset($heights[0])? $heights[0] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growth Monitoring - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: #fce4ec; }
       .sidebar { position: fixed; width: 250px; height: 100vh; background: #ad1457; color: white; overflow-y: auto; z-index: 100; }
       .sidebar::-webkit-scrollbar { width: 5px; }
       .sidebar::-webkit-scrollbar-track { background: #880e4f; }
       .sidebar::-webkit-scrollbar-thumb { background: #f06292; border-radius: 10px; }
       .nav h2 { text-align: center; padding: 20px; background: #880e4f; font-size: 20px; }
       .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
       .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 14px 20px; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.1); transition: 0.3s; }
        nav a:hover { background: #880e4f; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: #880e4f; border-left: 4px solid #ffb74d; }
       .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 30px; }
       .header h2 { color: #ad1457; font-weight: 500; font-size: 1.2rem; }
       .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
       .page-header { margin-bottom: 25px; }
       .page-header h2 { color: #333; }
       .filter-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
       .filter-section label { font-weight: 600; color: #333; }
       .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: #fafafa; min-width: 200px; }
       .filter-section select:focus { border-color: #ad1457; outline: none; }
       .filter-section.child-info { color: #666; font-size: 0.9rem; margin-left: auto; }
       .filter-section.child-info strong { color: #ad1457; }
       .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
       .stat-box { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: 0.3s; }
       .stat-box:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
       .stat-box.number { font-size: 1.5rem; font-weight: 700; color: #ad1457; }
       .stat-box.label { color: #666; font-size: 0.8rem; margin-top: 3px; }
       .stat-box i { font-size: 1.2rem; color: #ad1457; margin-bottom: 5px; }
       .growth-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
       .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
       .card.card-title { color: #ad1457; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #fce4ec; }
       .card.card-title i { margin-right: 8px; }
       .record-list { max-height: 400px; overflow-y: auto; }
       .record-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
       .record-item:last-child { border-bottom: none; }
       .record-item.date { font-weight: 600; color: #333; min-width: 100px; }
       .record-item.measurements { display: flex; gap: 15px; font-size: 0.9rem; color: #666; }
       .record-item.measurements span { display: flex; align-items: center; gap: 3px; }
       .record-item.measurements.weight { color: #0d47a1; }
       .record-item.measurements.height { color: #2e7d32; }
       .record-item.measurements.head { color: #e65100; }
       .record-item.status-badge { padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
       .status-normal { background: #e8f5e9; color: #2e7d32; }
       .status-underweight { background: #fff3e0; color: #e65100; }
       .status-overweight { background: #fff8e1; color: #f57f17; }
       .status-obese { background: #ffebee; color: #c62828; }
       .record-item.actions { display: flex; gap: 5px; }
       .btn-sm { padding: 4px 10px; border: none; border-radius: 5px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
       .btn-danger-sm { background: #ffebee; color: #c62828; }
       .btn-danger-sm:hover { background: #ffcdd2; }
       .form-group { margin-bottom: 15px; }
       .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.85rem; }
       .form-group label.required { color: #c62828; }
       .form-group input,.form-group select,.form-group textarea {
            width: 100%; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px;
            font-size: 0.9rem; transition: all 0.3s ease; background: #fafafa;
        }
       .form-group input:focus,.form-group select:focus,.form-group textarea:focus {
            border-color: #ad1457; outline: none; background: white; box-shadow: 0 0 0 4px rgba(173, 20, 87, 0.1);
        }
       .form-group textarea { resize: vertical; min-height: 60px; }
       .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
       .btn-submit { padding: 10px 25px; background: #ad1457; color: white; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: 0.3s; width: 100%; }
       .btn-submit:hover { background: #880e4f; transform: translateY(-2px); }
       .message { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; }
       .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
       .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
       .no-data { text-align: center; padding: 30px; color: #999; }
       .no-data i { font-size: 2rem; display: block; margin-bottom: 10px; }
       .status-indicator { display: flex; align-items: center; gap: 10px; padding: 10px 15px; border-radius: 8px; margin-top: 15px; }
       .status-indicator.normal { background: #e8f5e9; }
       .status-indicator.underweight { background: #fff3e0; }
       .status-indicator.overweight { background: #fff8e1; }
       .status-indicator.obese { background: #ffebee; }
       .status-indicator.icon { font-size: 1.5rem; }
       .status-indicator.info { font-weight: 600; }
       .status-indicator.info small { font-weight: 400; color: #666; }
        @media (max-width: 1024px) {.growth-grid { grid-template-columns: 1fr; }.stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {.sidebar { width: 200px; }.topbar { margin-left: 200px; padding: 0 20px; }.main { margin-left: 200px; padding: 15px; }.filter-section { flex-direction: column; align-items: stretch; }.filter-section select { width: 100%; }.filter-section.child-info { margin-left: 0; }.form-row { grid-template-columns: 1fr; }.stats-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 550px) {.sidebar { width: 0; overflow: hidden; }.topbar { margin-left: 0; padding: 0 15px; }.main { margin-left: 0; padding: 10px; }.stats-row { grid-template-columns: 1fr; }.header h2 { font-size: 1rem; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="image/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%23ad1457%22/%3E%3C/svg%3E'">
            </div>
            <h2>Mother Portal</h2>
            <nav>
                <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                <a href="growth_monitoring.php" class="active"><i class="fas fa-chart-line"></i> Growth Monitoring</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-chart-line"></i> GROWTH MONITORING</h2>
        </div>
    </div>

    <div class="main">
        <?php if (!empty($message)):?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');?></div>
        <?php endif;?>
        <?php if (!empty($error)):?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></div>
        <?php endif;?>

        <!-- Child Selector -->
        <div class="filter-section">
            <label for="child_select"><i class="fas fa-child"></i> Select Child:</label>
            <form method="GET" action="" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <select id="child_select" name="child_id" onchange="this.form.submit()">
                    <?php foreach ($children_list as $child):?>
                        <option value="<?php echo $child['id'];?>" <?php echo $child_id == $child['id']? 'selected' : '';?>>
                            <?php echo htmlspecialchars($child['full_name'], ENT_QUOTES, 'UTF-8');?>
                        </option>
                    <?php endforeach;?>
                </select>
                <noscript><button type="submit" class="btn-sm btn-primary-sm">Go</button></noscript>
            </form>
            <?php if ($child_id && $child_name):?>
                <span class="child-info">
                    <i class="fas fa-info-circle"></i> Tracking: <strong><?php echo htmlspecialchars($child_name, ENT_QUOTES, 'UTF-8');?></strong>
                    <?php if ($child_dob):
                        $age = calculateAge($child_dob);
                   ?>
                        (Age: <?php echo $age['text'];?>)
                    <?php endif;?>
                </span>
            <?php endif;?>
        </div>

        <?php if ($child_id &&!empty($children_list)):?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <i class="fas fa-chart-simple"></i>
                    <div class="number"><?php echo $stats['total'];?></div>
                    <div class="label">Total Records</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-weight-scale"></i>
                    <div class="number"><?php echo $stats['avg_weight']? $stats['avg_weight']. ' kg' : 'N/A';?></div>
                    <div class="label">Avg Weight</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-ruler-vertical"></i>
                    <div class="number"><?php echo $stats['avg_height']? $stats['avg_height']. ' cm' : 'N/A';?></div>
                    <div class="label">Avg Height</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-clock"></i>
                    <div class="number"><?php echo $latest_record? date('M d', strtotime($latest_record['record_date'])) : 'N/A';?></div>
                    <div class="label">Latest Record</div>
                </div>
            </div>

            <!-- Nutritional Status -->
            <?php if ($latest_record && $latest_record['nutritional_status']):?>
                <div class="status-indicator <?php echo $latest_record['nutritional_status'];?>">
                    <span class="icon">
                        <?php if ($latest_record['nutritional_status'] === 'normal'):?>
                            <i class="fas fa-check-circle" style="color:#2e7d32;"></i>
                        <?php elseif ($latest_record['nutritional_status'] === 'underweight'):?>
                            <i class="fas fa-exclamation-triangle" style="color:#e65100;"></i>
                        <?php elseif ($latest_record['nutritional_status'] === 'overweight'):?>
                            <i class="fas fa-exclamation-circle" style="color:#f57f17;"></i>
                        <?php elseif ($latest_record['nutritional_status'] === 'obese'):?>
                            <i class="fas fa-exclamation-triangle" style="color:#c62828;"></i>
                        <?php endif;?>
                    </span>
                    <span class="info">
                        Nutritional Status: <strong><?php echo ucfirst($latest_record['nutritional_status']);?></strong>
                        <?php if ($latest_record['bmi']):?>
                            <small>(BMI: <?php echo $latest_record['bmi'];?>)</small>
                        <?php endif;?>
                    </span>
                </div>
            <?php endif;?>

            <!-- Growth Grid -->
            <div class="growth-grid">
                <!-- Growth Records List -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-list"></i> Growth History
                    </div>
                    <div class="record-list">
                        <?php if (empty($growth_records)):?>
                            <div class="no-data">
                                <i class="fas fa-chart-line"></i>
                                <p>No growth records found for this child.</p>
                                <p style="font-size:0.85rem; margin-top:5px;">Add a record using the form on the right.</p>
                            </div>
                        <?php else:?>
                            <?php foreach ($growth_records as $record):?>
                                <div class="record-item">
                                    <div>
                                        <div class="date"><?php echo date('M d, Y', strtotime($record['record_date']));?></div>
                                        <div class="measurements">
                                            <?php if ($record['weight']):?>
                                                <span class="weight"><i class="fas fa-weight-scale"></i> <?php echo $record['weight'];?> kg</span>
                                            <?php endif;?>
                                            <?php if ($record['height']):?>
                                                <span class="height"><i class="fas fa-ruler-vertical"></i> <?php echo $record['height'];?> cm</span>
                                            <?php endif;?>
                                            <?php if ($record['head_circumference']):?>
                                                <span class="head"><i class="fas fa-circle"></i> <?php echo $record['head_circumference'];?> cm</span>
                                            <?php endif;?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($record['nutritional_status']):?>
                                            <span class="status-badge status-<?php echo $record['nutritional_status'];?>">
                                                <?php echo ucfirst($record['nutritional_status']);?>
                                            </span>
                                        <?php endif;?>
                                        <div class="actions">
                                            <a href="?delete=1&id=<?php echo $record['id'];?>&child_id=<?php echo $child_id;?>" class="btn-sm btn-danger-sm" onclick="return confirm('Delete this growth record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;?>
                        <?php endif;?>
                    </div>
                </div>

                <!-- Add Growth Record Form -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-plus-circle"></i> Add Growth Record
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="child_id" value="<?php echo $child_id;?>">
                        <input type="hidden" name="add_growth" value="1">

                        <div class="form-group">
                            <label for="record_date">Record Date <span class="required">*</span></label>
                            <input type="date" id="record_date" name="record_date" value="<?php echo date('Y-m-d');?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.01" placeholder="e.g., 5.5" value="<?php echo htmlspecialchars(isset($_POST['weight'])? $_POST['weight'] : '', ENT_QUOTES, 'UTF-8');?>">
                            </div>
                            <div class="form-group">
                                <label for="height">Height (cm)</label>
                                <input type="number" id="height" name="height" step="0.1" placeholder="e.g., 65.5" value="<?php echo htmlspecialchars(isset($_POST['height'])? $_POST['height'] : '', ENT_QUOTES, 'UTF-8');?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="head_circumference">Head Circumference (cm)</label>
                            <input type="number" id="head_circumference" name="head_circumference" step="0.1" placeholder="e.g., 42.5" value="<?php echo htmlspecialchars(isset($_POST['head_circumference'])? $_POST['head_circumference'] : '', ENT_QUOTES, 'UTF-8');?>">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Any observations or comments..."><?php echo htmlspecialchars(isset($_POST['notes'])? $_POST['notes'] : '', ENT_QUOTES, 'UTF-8');?></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Add Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Growth Chart Placeholder -->
            <div class="card" style="margin-top:25px;">
                <div class="card-title">
                    <i class="fas fa-chart-line"></i> Growth Chart
                </div>
                <?php if (count($growth_records) < 2):?>
                    <div class="no-data">
                        <i class="fas fa-chart-bar"></i>
                        <p>Need at least 2 records to display a growth chart.</p>
                        <p style="font-size:0.85rem; color:#999;">Add more growth records to see the trend.</p>
                    </div>
                <?php else:?>
                    <div style="padding:20px; background:#fafafa; border-radius:8px; text-align:center;">
                        <i class="fas fa-chart-line" style="font-size:4rem; color:#ad1457; opacity:0.3;"></i>
                        <p style="color:#666; margin-top:10px;">
                            <strong><?php echo count($growth_records);?></strong> records available
                        </p>
                        <p style="color:#999; font-size:0.85rem;">
                            <i class="fas fa-weight-scale" style="color:#0d47a1;"></i> Weight: <?php echo $stats['avg_weight'];?> kg avg
                            &nbsp;|&nbsp;
                            <i class="fas fa-ruler-vertical" style="color:#2e7d32;"></i> Height: <?php echo $stats['avg_height'];?> cm avg
                        </p>
                    </div>
                <?php endif;?>
            </div>

        <?php elseif (!empty($children_list)):?>
            <div class="no-data" style="background:white; border-radius:12px; padding:40px; box-shadow:0 2px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-child" style="font-size:3rem; color:#fce4ec;"></i>
                <h3 style="margin-top:10px;">No Child Selected</h3>
                <p style="color:#999;">Please select a child from the dropdown above to view their growth records.</p>
            </div>
        <?php else:?>
            <div class="no-data" style="background:white; border-radius:12px; padding:40px; box-shadow:0 2px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-child" style="font-size:3rem; color:#fce4ec;"></i>
                <h3 style="margin-top:10px;">No Children Registered</h3>
                <p style="color:#999;">You need to <a href="register_child.php" style="color:#ad1457; font-weight:600;">register a child</a> first to track growth.</p>
            </div>
        <?php endif;?>
    </div>
</body>
</html>