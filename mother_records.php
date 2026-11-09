<?php
// antenatal.php - Antenatal Care Management for PHP 5.3.5
require_once 'config.php';
requireRole('healthworker');

// session_start() is in config.php
$db = getDB();
$message = '';
$error = '';

// Get all mothers for dropdown
$stmt = $db->query("
    SELECT m.id, u.full_name
    FROM mothers m
    JOIN users u ON m.user_id = u.id
    ORDER BY u.full_name
");
$mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected mother ID
$mother_id = 0;
if (isset($_GET['mother_id'])) {
    $mother_id = (int)$_GET['mother_id'];
} else if (isset($_POST['mother_id'])) {
    $mother_id = (int)$_POST['mother_id'];
}
$mother_name = '';

// Get mother name
if ($mother_id) {
    $stmt = $db->prepare("SELECT u.full_name FROM mothers m JOIN users u ON m.user_id = u.id WHERE m.id =?");
    $stmt->execute(array($mother_id));
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mother) {
        $mother_name = $mother['full_name'];
    }
}

// Handle adding antenatal visit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_antenatal'])) {
    $visit_date = isset($_POST['visit_date'])? $_POST['visit_date'] : '';
    $visit_number = isset($_POST['visit_number'])? $_POST['visit_number'] : 1;
    $gestational_age = isset($_POST['gestational_age']) && $_POST['gestational_age']!== ''? $_POST['gestational_age'] : null;
    $weight = isset($_POST['weight']) && $_POST['weight']!== ''? $_POST['weight'] : null;
    $blood_pressure = isset($_POST['blood_pressure'])? trim($_POST['blood_pressure']) : '';
    $hemoglobin = isset($_POST['hemoglobin']) && $_POST['hemoglobin']!== ''? $_POST['hemoglobin'] : null;
    $urine_protein = isset($_POST['urine_protein'])? trim($_POST['urine_protein']) : '';
    $urine_sugar = isset($_POST['urine_sugar'])? trim($_POST['urine_sugar']) : '';
    $fetal_heart_rate = isset($_POST['fetal_heart_rate']) && $_POST['fetal_heart_rate']!== ''? $_POST['fetal_heart_rate'] : null;
    $fundal_height = isset($_POST['fundal_height']) && $_POST['fundal_height']!== ''? $_POST['fundal_height'] : null;
    $presentation = isset($_POST['presentation'])? trim($_POST['presentation']) : '';
    $next_visit_date = isset($_POST['next_visit_date']) && $_POST['next_visit_date']!== ''? $_POST['next_visit_date'] : null;
    $notes = isset($_POST['notes'])? trim($_POST['notes']) : '';
    $mother_id = isset($_POST['mother_id'])? (int)$_POST['mother_id'] : 0;

    if ($mother_id && $visit_date) {
        try {
            $stmt = $db->prepare("
                INSERT INTO antenatal_visits (
                    mother_id, visit_number, visit_date, gestational_age,
                    weight, blood_pressure, hemoglobin, urine_protein,
                    urine_sugar, fetal_heart_rate, fundal_height,
                    presentation, next_visit_date, notes
                ) VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute(array(
                $mother_id,
                $visit_number,
                $visit_date,
                $gestational_age,
                $weight,
                $blood_pressure? $blood_pressure : null,
                $hemoglobin,
                $urine_protein? $urine_protein : null,
                $urine_sugar? $urine_sugar : null,
                $fetal_heart_rate,
                $fundal_height,
                $presentation? $presentation : null,
                $next_visit_date,
                $notes? $notes : null
            ));
            $message = "Antenatal visit recorded successfully!";

            // Clear form
            $_POST = array();

        } catch (Exception $e) {
            $error = 'Error recording antenatal visit: '. $e->getMessage();
        }
    } else {
        $error = 'Please select a mother and enter a visit date.';
    }
}

// Handle delete visit
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $visit_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM antenatal_visits WHERE id =?");
    $stmt->execute(array($visit_id));
    $message = "Antenatal visit deleted successfully!";
}

// Get antenatal visits for selected mother
$visits = array();
if ($mother_id) {
    $stmt = $db->prepare("
        SELECT * FROM antenatal_visits
        WHERE mother_id =?
        ORDER BY visit_date DESC
    ");
    $stmt->execute(array($mother_id));
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get visit statistics
$stats = array('total' => 0, 'avg_weight' => 0, 'latest' => null);
if ($visits) {
    $stats['total'] = count($visits);

    // Manual array_column for PHP 5.3
    $weights = array();
    foreach ($visits as $v) {
        if (isset($v['weight']) && $v['weight']!== null && $v['weight']!== '') {
            $weights[] = $v['weight'];
        }
    }
    if (count($weights) > 0) {
        $stats['avg_weight'] = round(array_sum($weights) / count($weights), 2);
    }
    $stats['latest'] = isset($visits[0])? $visits[0] : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antenatal Care - MA-LIFE</title>
    <link rel="stylesheet" href="health.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: #e0f7fa; }
       .sidebar { position: fixed; width: 250px; height: 100vh; background: #00796b; color: white; overflow-y: auto; z-index: 100; }
       .sidebar::-webkit-scrollbar { width: 5px; }
       .sidebar::-webkit-scrollbar-track { background: #00695c; }
       .sidebar::-webkit-scrollbar-thumb { background: #4db6ac; border-radius: 10px; }
       .nav h2 { text-align: center; padding: 20px; background: #00695c; font-size: 20px; }
       .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
       .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 14px 20px; color: #e0e0e0; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.08); transition: 0.3s; }
        nav a:hover { background: #004d40; color: white; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: #004d40; color: white; border-left: 4px solid #ffb74d; }
       .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 30px; }
       .header h2 { color: #00796b; font-weight: 500; font-size: 1.2rem; }
       .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
       .filter-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
       .filter-section label { font-weight: 600; color: #333; }
       .filter-section select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: #fafafa; min-width: 200px; }
       .filter-section select:focus { border-color: #00796b; outline: none; }
       .filter-section.mother-info { color: #666; font-size: 0.9rem; margin-left: auto; }
       .filter-section.mother-info strong { color: #00796b; }
       .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
       .stat-box { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: 0.3s; }
       .stat-box:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
       .stat-box.number { font-size: 1.5rem; font-weight: 700; color: #00796b; }
       .stat-box.label { color: #666; font-size: 0.8rem; margin-top: 3px; }
       .stat-box i { font-size: 1.2rem; color: #00796b; margin-bottom: 5px; }
       .antenatal-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
       .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
       .card.card-title { color: #00796b; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0f2f1; }
       .card.card-title i { margin-right: 8px; }
       .visit-list { max-height: 400px; overflow-y: auto; }
       .visit-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
       .visit-item:last-child { border-bottom: none; }
       .visit-item.date { font-weight: 600; color: #333; min-width: 100px; }
       .visit-item.details { font-size: 0.85rem; color: #666; }
       .visit-item.details span { display: inline-block; margin-right: 10px; }
       .visit-item.actions { display: flex; gap: 5px; }
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
            border-color: #00796b; outline: none; background: white; box-shadow: 0 0 0 4px rgba(0, 121, 107, 0.1);
        }
       .form-group textarea { resize: vertical; min-height: 60px; }
       .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
       .btn-submit { padding: 10px 25px; background: #00796b; color: white; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: 0.3s; width: 100%; }
       .btn-submit:hover { background: #004d40; transform: translateY(-2px); }
       .message { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; }
       .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
       .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
       .no-data { text-align: center; padding: 30px; color: #999; }
       .no-data i { font-size: 2rem; display: block; margin-bottom: 10px; }
        @media (max-width: 1024px) {.antenatal-grid { grid-template-columns: 1fr; }.stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {.sidebar { width: 200px; }.topbar { margin-left: 200px; padding: 0 20px; }.main { margin-left: 200px; padding: 15px; }.filter-section { flex-direction: column; align-items: stretch; }.filter-section select { width: 100%; }.filter-section.mother-info { margin-left: 0; }.form-row { grid-template-columns: 1fr; }.stats-row { grid-template-columns: 1fr; } }
        @media (max-width: 550px) {.sidebar { width: 0; overflow: hidden; }.topbar { margin-left: 0; padding: 0 15px; }.main { margin-left: 0; padding: 10px; }.header h2 { font-size: 1rem; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="image/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%2300809b%22/%3E%3C/svg%3E'">
            </div>
            <h2>Health Worker</h2>
            <nav>
                <a href="healthworker.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
                <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                <a href="antenatal.php" class="active"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Health Records</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-heartbeat"></i> ANTENATAL CARE</h2>
        </div>
    </div>

    <div class="main">
        <?php if (!empty($message)):?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');?></div>
        <?php endif;?>
        <?php if (!empty($error)):?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></div>
        <?php endif;?>

        <div class="filter-section">
            <label for="mother_select"><i class="fas fa-female"></i> Select Mother:</label>
            <form method="GET" action="" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <select id="mother_select" name="mother_id" onchange="this.form.submit()">
                    <option value="">Select a mother...</option>
                    <?php foreach ($mothers as $mother):?>
                        <option value="<?php echo $mother['id'];?>" <?php echo $mother_id == $mother['id']? 'selected' : '';?>>
                            <?php echo htmlspecialchars($mother['full_name'], ENT_QUOTES, 'UTF-8');?>
                        </option>
                    <?php endforeach;?>
                </select>
                <noscript><button type="submit" class="btn-sm btn-primary-sm">Go</button></noscript>
            </form>
            <?php if ($mother_id && $mother_name):?>
                <span class="mother-info">
                    <i class="fas fa-info-circle"></i> Currently viewing: <strong><?php echo htmlspecialchars($mother_name, ENT_QUOTES, 'UTF-8');?></strong>
                </span>
            <?php endif;?>
        </div>

        <?php if ($mother_id):?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <i class="fas fa-clock"></i>
                    <div class="number"><?php echo $stats['total'];?></div>
                    <div class="label">Total Visits</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-weight-scale"></i>
                    <div class="number"><?php echo $stats['avg_weight']? $stats['avg_weight']. ' kg' : 'N/A';?></div>
                    <div class="label">Avg Weight</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-calendar"></i>
                    <div class="number"><?php echo $stats['latest']? date('M d', strtotime($stats['latest']['visit_date'])) : 'N/A';?></div>
                    <div class="label">Latest Visit</div>
                </div>
            </div>

            <div class="antenatal-grid">
                <!-- Visit List -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-list"></i> Antenatal Visits
                    </div>
                    <div class="visit-list">
                        <?php if (empty($visits)):?>
                            <div class="no-data">
                                <i class="fas fa-heartbeat"></i>
                                <p>No antenatal visits recorded for this mother.</p>
                            </div>
                        <?php else:?>
                            <?php foreach ($visits as $visit):?>
                                <div class="visit-item">
                                    <div>
                                        <div class="date"><?php echo date('M d, Y', strtotime($visit['visit_date']));?></div>
                                        <div class="details">
                                            <?php if (!empty($visit['gestational_age'])):?>
                                                <span><i class="fas fa-clock"></i> <?php echo $visit['gestational_age'];?> wks</span>
                                            <?php endif;?>
                                            <?php if (!empty($visit['weight'])):?>
                                                <span><i class="fas fa-weight-scale"></i> <?php echo $visit['weight'];?> kg</span>
                                            <?php endif;?>
                                            <?php if (!empty($visit['blood_pressure'])):?>
                                                <span><i class="fas fa-heart"></i> <?php echo htmlspecialchars($visit['blood_pressure'], ENT_QUOTES, 'UTF-8');?></span>
                                            <?php endif;?>
                                        </div>
                                    </div>
                                    <div class="actions">
                                        <a href="?delete=1&id=<?php echo $visit['id'];?>&mother_id=<?php echo $mother_id;?>" class="btn-sm btn-danger-sm" onclick="return confirm('Delete this visit record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach;?>
                        <?php endif;?>
                    </div>
                </div>

                <!-- Add Visit Form -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-plus-circle"></i> Add Visit
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="mother_id" value="<?php echo $mother_id;?>">
                        <input type="hidden" name="add_antenatal" value="1">

                        <div class="form-group">
                            <label for="visit_date">Visit Date <span class="required">*</span></label>
                            <input type="date" id="visit_date" name="visit_date" value="<?php echo date('Y-m-d');?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="visit_number">Visit Number</label>
                                <input type="number" id="visit_number" name="visit_number" value="<?php echo $stats['total'] + 1;?>" min="1">
                            </div>
                            <div class="form-group">
                                <label for="gestational_age">Gestational Age (weeks)</label>
                                <input type="number" id="gestational_age" name="gestational_age" step="0.5" placeholder="e.g., 20">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.1" placeholder="e.g., 65.5">
                            </div>
                            <div class="form-group">
                                <label for="blood_pressure">Blood Pressure</label>
                                <input type="text" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="hemoglobin">Hemoglobin (g/dL)</label>
                                <input type="number" id="hemoglobin" name="hemoglobin" step="0.1" placeholder="e.g., 12.5">
                            </div>
                            <div class="form-group">
                                <label for="urine_protein">Urine Protein</label>
                                <select id="urine_protein" name="urine_protein">
                                    <option value="">Select</option>
                                    <option value="negative">Negative</option>
                                    <option value="trace">Trace</option>
                                    <option value="+">+</option>
                                    <option value="++">++</option>
                                    <option value="+++">+++</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fetal_heart_rate">Fetal Heart Rate (bpm)</label>
                                <input type="number" id="fetal_heart_rate" name="fetal_heart_rate" placeholder="e.g., 140">
                            </div>
                            <div class="form-group">
                                <label for="fundal_height">Fundal Height (cm)</label>
                                <input type="number" id="fundal_height" name="fundal_height" step="0.5" placeholder="e.g., 24">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="presentation">Presentation</label>
                            <select id="presentation" name="presentation">
                                <option value="">Select</option>
                                <option value="cephalic">Cephalic</option>
                                <option value="breech">Breech</option>
                                <option value="transverse">Transverse</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="next_visit_date">Next Visit Date</label>
                            <input type="date" id="next_visit_date" name="next_visit_date">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Any observations or comments..."></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Record Visit
                        </button>
                    </form>
                </div>
            </div>

        <?php else:?>
            <div class="no-data" style="background:white; border-radius:12px; padding:40px; box-shadow:0 2px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-female" style="font-size:3rem; color:#e0f2f1;"></i>
                <h3 style="margin-top:10px;">Select a Mother</h3>
                <p style="color:#999;">Please select a mother from the dropdown above to manage her antenatal care.</p>
            </div>
        <?php endif;?>
    </div>
</body>
</html>