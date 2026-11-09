<?php
// register_child.php - Register a New Child for PHP 5.3.5
require_once 'config.php';
requireLogin();
$isAdmin = isRole('admin');
if (!$isAdmin) requireRole('mother');
$_SESSION['register_child_csrf'] ??= bin2hex(random_bytes(32));

// session_start() is in config.php
$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$mothers = [];
if ($isAdmin) {
    $mothers = $db->query("SELECT m.id, u.full_name FROM mothers m JOIN users u ON u.id = m.user_id ORDER BY u.full_name")->fetchAll(PDO::FETCH_KEY_PAIR);
    $mother_id = filter_input(INPUT_POST, 'mother_id', FILTER_VALIDATE_INT) ?: 0;
} else {
    $stmt = $db->prepare("SELECT id FROM mothers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $mother_id = (int) $stmt->fetchColumn();
    if (!$mother_id) {
        header('Location: mother_profile.php', true, 302);
        exit;
    }
}

// Handle child registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $birth_weight = isset($_POST['birth_weight']) && $_POST['birth_weight'] !== '' ? $_POST['birth_weight'] : null;
    $birth_length = isset($_POST['birth_length']) && $_POST['birth_length'] !== '' ? $_POST['birth_length'] : null;
    $place_of_birth = isset($_POST['place_of_birth']) ? trim($_POST['place_of_birth']) : '';
    $delivery_type = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : '';
    
    // Validation
    $errors = array();
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['register_child_csrf'], $token)) $errors[] = 'Reload the form and try again.';
    if ($isAdmin && !isset($mothers[$mother_id])) $errors[] = 'Select a mother or guardian.';
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date_of_birth);
    if (!$birthDate || $birthDate->format('Y-m-d') !== $date_of_birth || $date_of_birth > date('Y-m-d')) $errors[] = 'Enter a valid date of birth.';
    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) $errors[] = 'Child\'s name is required.';
    if (empty($date_of_birth)) $errors[] = 'Date of birth is required.';
    if (!in_array($gender, ['male','female'], true)) $errors[] = 'Gender is required.';
    if ($birth_weight !== null && !is_numeric($birth_weight)) $errors[] = 'Birth weight must be a number.';
    if ($birth_length !== null && !is_numeric($birth_length)) $errors[] = 'Birth length must be a number.';
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO children (
                    mother_id, full_name, date_of_birth, gender, 
                    birth_weight, birth_length, place_of_birth, delivery_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $mother_id,
                $full_name,
                $date_of_birth,
                $gender,
                $birth_weight,
                $birth_length,
                $place_of_birth !== '' ? $place_of_birth : null,
                $delivery_type !== '' ? $delivery_type : null
            ));
            
            header('Location: ' . ($isAdmin ? 'children_admin.php' : 'child_records.php'), true, 303);
            exit;

        } catch (Exception $e) {
            error_log('MA-LIFE child registration failed: ' . $e->getMessage());
            $error = 'The child record could not be saved.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get existing children count
$stmt = $db->prepare("SELECT COUNT(*) FROM children WHERE mother_id = ?");
$stmt->execute(array($mother_id));
$child_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Child - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: #fce4ec; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #ad1457; color: white; overflow-y: auto; z-index: 100; }
        .nav h2 { text-align: center; padding: 20px; background: #880e4f; font-size: 20px; }
        .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
        .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 15px 20px; color: white; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.15); transition: 0.3s; }
        nav a:hover { background: #880e4f; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: #880e4f; border-left: 4px solid #ffb74d; }
        .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h2 { padding: 20px; color: #ad1457; }
        .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
        .form-container { max-width: 700px; margin: 0 auto; }
        .form-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .form-card .card-title { color: #ad1457; font-size: 1.3rem; font-weight: 600; margin-bottom: 5px; }
        .form-card .card-subtitle { color: #666; font-size: 0.9rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #fce4ec; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group label .required { color: #c62828; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s ease; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #ad1457; outline: none; background: white; box-shadow: 0 0 0 4px rgba(173, 20, 87, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-submit { padding: 12px 35px; background: #ad1457; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #880e4f; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(173, 20, 87, 0.3); }
        .btn-reset { padding: 12px 25px; background: #f5f5f5; color: #666; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-reset:hover { background: #e0e0e0; }
        .form-actions { display: flex; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .info-box { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .info-box i { color: #ad1457; font-size: 1.2rem; }
        .info-box span { color: #666; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar { margin-left: 200px; }
            .main { margin-left: 200px; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions button { width: 100%; justify-content: center; }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar { margin-left: 0; }
            .main { margin-left: 0; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="img/child1.jpg" alt="MA-Life Logo">
            </div>
            <h2>Mother Portal</h2>
            <nav>
                <?php if ($isAdmin): ?>
                    <a href="children_admin.php">Back to child records</a>
                <?php else: ?>
                <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a href="register_child.php" class="active"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php endif; ?>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-plus-circle"></i> REGISTER CHILD</h2>
        </div>
    </div>

    <div class="main">
        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-title"><i class="fas fa-child"></i> Register a New Child</div>
                <div class="card-subtitle">Enter your child's details to add them to the system.</div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <span>You have registered <strong><?php echo $child_count; ?></strong> child(ren) so far.</span>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['register_child_csrf']) ?>">
                    <?php if ($isAdmin): ?>
                        <div class="form-group">
                            <label for="mother_id">Mother or guardian</label>
                            <select id="mother_id" name="mother_id" required>
                                <option value="">Select a mother or guardian</option>
                                <?php foreach ($mothers as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= $mother_id === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="full_name">Child's Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g., Sarah Nakamya" value="<?php echo htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_weight">Birth Weight (kg)</label>
                            <input type="number" id="birth_weight" name="birth_weight" step="0.01" placeholder="e.g., 3.2" value="<?php echo htmlspecialchars(isset($_POST['birth_weight']) ? htmlspecialchars($_POST['birth_weight']) : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="birth_length">Birth Length (cm)</label>
                            <input type="number" id="birth_length" name="birth_length" step="0.1" placeholder="e.g., 50.5" value="<?php echo htmlspecialchars(isset($_POST['birth_length']) ? htmlspecialchars($_POST['birth_length']) : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="place_of_birth">Place of Birth</label>
                            <input type="text" id="place_of_birth" name="place_of_birth" placeholder="e.g., Kawempe Health Centre" value="<?php echo htmlspecialchars(isset($_POST['place_of_birth']) ? $_POST['place_of_birth'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="delivery_type">Delivery Type</label>
                            <select id="delivery_type" name="delivery_type">
                                <option value="">Select type</option>
                                <option value="normal" <?php echo (isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="cesarean" <?php echo (isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'cesarean') ? 'selected' : ''; ?>>Cesarean</option>
                                <option value="assisted" <?php echo (isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'assisted') ? 'selected' : ''; ?>>Assisted</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Register Child
                        </button>
                        <button type="reset" class="btn-reset">
                            <i class="fas fa-undo"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>