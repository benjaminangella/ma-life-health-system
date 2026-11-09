<?php
// health_worker_details.php - View and Edit Health Worker Profile for PHP 5.3.5
require_once 'config.php';
requireRole('healthworker');

// session_start() is already in config.php
$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get health worker details with user info
$stmt = $db->prepare("
    SELECT u.*, hw.* 
    FROM users u 
    JOIN health_workers hw ON u.id = hw.user_id 
    WHERE u.id = ?
");
$stmt->execute(array($user_id));
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    die('Profile not found.');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
    $facility_name = isset($_POST['facility_name']) ? trim($_POST['facility_name']) : '';
    $license_number = isset($_POST['license_number']) ? trim($_POST['license_number']) : '';
    $years_of_experience = isset($_POST['years_of_experience']) ? $_POST['years_of_experience'] : '';
    
    try {
        // Update users table
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute(array($full_name, $phone, $address, $user_id));
        
        // Update health_workers table
        $stmt = $db->prepare("
            UPDATE health_workers SET 
                specialization = ?, 
                facility_name = ?, 
                license_number = ?, 
                years_of_experience = ? 
            WHERE user_id = ?
        ");
        $stmt->execute(array($specialization, $facility_name, $license_number, $years_of_experience, $user_id));
        
        $message = 'Profile updated successfully!';
        
        // Refresh profile data
        $stmt = $db->prepare("
            SELECT u.*, hw.* 
            FROM users u 
            JOIN health_workers hw ON u.id = hw.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute(array($user_id));
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update session name
        $_SESSION['user_name'] = $full_name;
        
    } catch (Exception $e) {
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Worker Details - MA-LIFE</title>
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
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-header { background: white; border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #e0f2f1; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2.5rem; color: #00796b; border: 4px solid #00796b; }
        .profile-header h2 { color: #333; }
        .profile-header .email { color: #666; }
        .profile-header .worker-id { display: inline-block; background: #00796b; color: white; padding: 4px 16px; border-radius: 20px; font-size: 0.85rem; margin-top: 8px; }
        .profile-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .profile-card .section-title { color: #00796b; font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e0f2f1; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s ease; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #00796b; outline: none; background: white; box-shadow: 0 0 0 4px rgba(0, 121, 107, 0.1);
        }
        .form-group input:disabled { background: #f5f5f5; cursor: not-allowed; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save { padding: 12px 35px; background: #00796b; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #004d40; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 121, 107, 0.3); }
        .btn-cancel { padding: 12px 25px; background: #f5f5f5; color: #666; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-cancel:hover { background: #e0e0e0; }
        .form-actions { display: flex; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar { margin-left: 200px; padding: 0 20px; }
            .main { margin-left: 200px; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions button { width: 100%; justify-content: center; }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar { margin-left: 0; padding: 0 15px; }
            .main { margin-left: 0; padding: 10px; }
            .header h2 { font-size: 1rem; }
        }
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
                <a href="health_worker_details.php" class="active"><i class="fas fa-user-md"></i> My Details</a>
                <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Health Records</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-user-md"></i> MY DETAILS</h2>
        </div>
    </div>

    <div class="main">
        <div class="profile-container">
            <?php if (!empty($message)): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($profile['full_name'], 0, 2)); ?>
                </div>
                <h2><?php echo htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="worker-id"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($profile['worker_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                <p style="color:#999; font-size:0.9rem; margin-top:10px;">
                    <i class="fas fa-calendar"></i> Member since <?php echo date('M d, Y', strtotime($profile['created_at'])); ?>
                </p>
            </div>

            <div class="profile-card">
                <form method="POST" action="">
                    <div class="section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars(isset($profile['phone']) ? $profile['phone'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+256 700 000 000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars(isset($profile['address']) ? $profile['address'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Kawempe, Kampala">
                    </div>

                    <div class="section-title" style="margin-top:25px;">
                        <i class="fas fa-briefcase"></i> Professional Information
                    </div>

                    <div class="form-group">
                        <label for="specialization">Specialization</label>
                        <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars(isset($profile['specialization']) ? $profile['specialization'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Midwifery, Pediatrics">
                    </div>

                    <div class="form-group">
                        <label for="facility_name">Health Facility</label>
                        <input type="text" id="facility_name" name="facility_name" value="<?php echo htmlspecialchars(isset($profile['facility_name']) ? $profile['facility_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Kawempe Health Centre">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars(isset($profile['license_number']) ? $profile['license_number'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="License number">
                        </div>
                        <div class="form-group">
                            <label for="years_of_experience">Years of Experience</label>
                            <input type="number" id="years_of_experience" name="years_of_experience" value="<?php echo htmlspecialchars(isset($profile['years_of_experience']) ? $profile['years_of_experience'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <button type="reset" class="btn-cancel">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>