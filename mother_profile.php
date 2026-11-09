<?php
// mother_profile.php - View and Edit Mother Profile
require_once 'config.php';
requireRole('mother');

$db = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $db->prepare("
    SELECT u.*, m.* 
    FROM users u 
    JOIN mothers m ON u.id = m.user_id 
    WHERE u.id = ?
");
$stmt->execute(array($user_id));
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    die('Profile not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    $marital_status = isset($_POST['marital_status']) ? $_POST['marital_status'] : '';
    $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
    $next_of_kin = isset($_POST['next_of_kin']) ? trim($_POST['next_of_kin']) : '';
    $next_of_kin_phone = isset($_POST['next_of_kin_phone']) ? trim($_POST['next_of_kin_phone']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    
    try {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute(array($full_name, $phone, $address, $user_id));
        
        $stmt = $db->prepare("
            UPDATE mothers SET 
                national_id = ?, 
                marital_status = ?, 
                occupation = ?, 
                next_of_kin = ?, 
                next_of_kin_phone = ?, 
                emergency_contact = ? 
            WHERE user_id = ?
        ");
        $stmt->execute(array($national_id, $marital_status, $occupation, $next_of_kin, $next_of_kin_phone, $emergency_contact, $user_id));
        
        $message = 'Profile updated successfully!';
        
        $stmt = $db->prepare("
            SELECT u.*, m.* 
            FROM users u 
            JOIN mothers m ON u.id = m.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute(array($user_id));
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
    <title>My Profile - MA-LIFE</title>
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
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-header { background: white; border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #fce4ec; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2.5rem; color: #ad1457; border: 4px solid #ad1457; }
        .profile-header h2 { color: #333; }
        .profile-header .email { color: #666; }
        .profile-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .profile-card .section-title { color: #ad1457; font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #fce4ec; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s ease; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #ad1457; outline: none; background: white; box-shadow: 0 0 0 4px rgba(173, 20, 87, 0.1);
        }
        .form-group input:disabled { background: #f5f5f5; cursor: not-allowed; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save { padding: 12px 35px; background: #ad1457; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #880e4f; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(173, 20, 87, 0.3); }
        .btn-cancel { padding: 12px 25px; background: #f5f5f5; color: #666; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-cancel:hover { background: #e0e0e0; }
        .form-actions { display: flex; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
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
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="img/img3.jpg" alt="MA-Life Logo">
            </div>
            <h2>Mother Portal</h2>
            <nav>
                <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="register_fingerprint.php"><i class="fas fa-fingerprint"></i> Register Fingerprint</a>
                <a href="mother_profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
                <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                <a href="child_records.php"><i class="fas fa-child"></i> Child Records</a>
                <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-user"></i> MY PROFILE</h2>
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
                <p style="color:#999; font-size:0.9rem;">
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
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars(isset($profile['address']) ? $profile['address'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Luzira, Kampala">
                    </div>

                    <div class="section-title" style="margin-top:25px;">
                        <i class="fas fa-id-card"></i> Mother's Details
                    </div>

                    <div class="form-group">
                        <label for="national_id">National ID</label>
                        <input type="text" id="national_id" name="national_id" value="<?php echo htmlspecialchars(isset($profile['national_id']) ? $profile['national_id'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="CM123456789">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marital_status">Marital Status</label>
                            <select id="marital_status" name="marital_status">
                                <option value="">Select status</option>
                                <option value="single" <?php echo (isset($profile['marital_status']) && $profile['marital_status'] === 'single') ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo (isset($profile['marital_status']) && $profile['marital_status'] === 'married') ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo (isset($profile['marital_status']) && $profile['marital_status'] === 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo (isset($profile['marital_status']) && $profile['marital_status'] === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="occupation">Occupation</label>
                            <input type="text" id="occupation" name="occupation" value="<?php echo htmlspecialchars(isset($profile['occupation']) ? $profile['occupation'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Teacher, Business">
                        </div>
                    </div>

                    <div class="section-title" style="margin-top:25px;">
                        <i class="fas fa-phone"></i> Emergency Contacts
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="next_of_kin">Next of Kin</label>
                            <input type="text" id="next_of_kin" name="next_of_kin" value="<?php echo htmlspecialchars(isset($profile['next_of_kin']) ? $profile['next_of_kin'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Full name">
                        </div>
                        <div class="form-group">
                            <label for="next_of_kin_phone">Next of Kin Phone</label>
                            <input type="tel" id="next_of_kin_phone" name="next_of_kin_phone" value="<?php echo htmlspecialchars(isset($profile['next_of_kin_phone']) ? $profile['next_of_kin_phone'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+256 700 000 000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact</label>
                        <input type="tel" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars(isset($profile['emergency_contact']) ? $profile['emergency_contact'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+256 700 000 000">
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

    <!-- SESSION GUARD -->
    <script>
        (function() {
            fetch('session_check.php?_=' + Date.now(), { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) {
                        window.location.replace('login.php?expired=1');
                    } else if (data.role !== 'mother') {
                        window.location.replace('login.php?security=1');
                    }
                })
                .catch(err => console.error('Session check failed:', err));
        })();
    </script>
</body>
</html>