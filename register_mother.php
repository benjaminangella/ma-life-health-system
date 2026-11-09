<?php
// register_mother.php - Register a New Mother (Health Worker View)
require_once 'config.php';
require_once 'sms_queue.php';
requireRole('healthworker');

$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    // Gender is forced to Female
    $gender = 'Female'; 
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    $marital_status = isset($_POST['marital_status']) ? $_POST['marital_status'] : '';
    $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
    $next_of_kin = isset($_POST['next_of_kin']) ? trim($_POST['next_of_kin']) : '';
    $next_of_kin_phone = isset($_POST['next_of_kin_phone']) ? trim($_POST['next_of_kin_phone']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';

    $errors = array();
    if (strlen($full_name) < 3) $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    
    if (getUserByEmail($email)) {
        $errors[] = 'Email already registered.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // 1. Create User
            $user_id = createUser([
                'full_name'     => $full_name,
                'email'         => $email,
                'password'      => $password,
                'role'          => 'mother',
                'phone'         => $phone,
                'date_of_birth' => $date_of_birth,
                'gender'        => $gender,
                'address'       => $address
            ]);

            // 2. Create Mother Profile
            $stmt = $db->prepare("
                INSERT INTO mothers (user_id, national_id, marital_status, occupation, 
                next_of_kin, next_of_kin_phone, emergency_contact) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $national_id ?: null,
                $marital_status ?: null,
                $occupation ?: null,
                $next_of_kin ?: null,
                $next_of_kin_phone ?: null,
                $emergency_contact ?: null
            ]);

            $db->commit();

            // 3. Send SMS Welcome Message
            if (!empty($phone)) {
                $msg = "MA-LIFE: Hello $full_name, your Mother Profile has been created successfully at Luzira Health Centre. You can now log in to your Mother Portal to view appointments and child records.";
                ma_life_queue_sms($db, $phone, $msg, 'mother_registration', $user_id);
            }

            $message = "Mother <strong>" . htmlspecialchars($full_name) . "</strong> registered successfully!";
            $_POST = array(); // Clear form

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error registering mother: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Count total mothers registered
$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$total_mothers = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Mother - MA-LIFE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CORE RESET & LAYOUT --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f4f7f6; height: 100vh; overflow: hidden; }

        .app-container { display: flex; height: 100vh; width: 100vw; padding: 15px; gap: 20px; }

        /* --- SIDEBAR (Teal for Health Worker) --- */
        .sidebar { width: 260px; background: #0f766e; border-radius: 16px; display: flex; flex-direction: column; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-y: auto; flex-shrink: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #115e59; border-radius: 10px; }
        
        .brand { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .brand h2 { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: #fff; text-transform: uppercase; }

        .nav-group { padding: 20px 10px 0 10px; }
        .nav-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #99f6e4; margin-bottom: 10px; padding-left: 10px; font-weight: 700; }
        
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #ccfbf1; text-decoration: none; border-radius: 10px; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; margin-bottom: 3px; position: relative; }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-link.active { background: #14b8a6; color: #fff; box-shadow: 0 4px 15px rgba(20, 184, 166, 0.4); }
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding-right: 5px; }
        .main-content::-webkit-scrollbar { width: 6px; }
        .main-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 5px; }
        .page-header h2 { color: #1e293b; font-size: 1.6rem; font-weight: 700; }
        
        .btn { padding: 10px 20px; background: #0f766e; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(15, 118, 110, 0.2); }
        .btn:hover { background: #115e59; }
        .btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        .btn-outline:hover { background: #f1f5f9; }

        /* --- FORM STYLING --- */
        .form-container { max-width: 900px; margin: 0 auto; width: 100%; }
        .form-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; margin-bottom: 30px; }
        
        .section-title { font-size: 1.1rem; font-weight: 700; color: #0f766e; margin: 25px 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .section-title:first-child { margin-top: 0; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group label .required { color: #ef4444; }
        
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #f8fafc; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #0f766e; outline: none; background: white; box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        
        /* Read-only Field Styling */
        .form-group input[readonly] { background: #e2e8f0; color: #64748b; cursor: not-allowed; font-weight: 600; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .message.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .message.error { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        @media (max-width: 768px) {
            .app-container { padding: 0; gap: 0; }
            .sidebar { display: none; }
            .main-content { padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>

<div class="app-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="brand">
            <img src="img/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; background: white;" onerror="this.style.display='none'">
            <h2>Health Worker</h2>
        </div>

        <div class="nav-group">
            <div class="nav-label">Main Menu</div>
            <a href="healthworker.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="register_fingerprint.php" class="nav-link"><i class="fas fa-fingerprint"></i> Fingerprint</a>
            <a href="admin_notes.php" class="nav-link"><i class="fas fa-sticky-note"></i> Notes & Files</a>
            <a href="health_worker_details.php" class="nav-link"><i class="fas fa-user-md"></i> My Details</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">Clinical</div>
            <a href="register_mother.php" class="nav-link active"><i class="fas fa-female"></i> Register Mother</a>
            <a href="mother_records.php" class="nav-link"><i class="fas fa-folder-open"></i> Mother Records</a>
            <a href="child_records.php" class="nav-link"><i class="fas fa-child"></i> Child Records</a>
            <a href="antenatal.php" class="nav-link"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
            <a href="appointments.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Appointments</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">System</div>
            <a href="reports.php" class="nav-link"><i class="fas fa-chart-pie"></i> Health Reports</a>
            <a href="logout.php" class="nav-link" style="color: #fca5a5; margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h2>Register New Mother</h2>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">Create a new Mother Profile in the system. Total registered: <?= $total_mothers ?></p>
            </div>
            <a href="healthworker.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Personal Information -->
                <div class="form-card">
                    <div class="section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                    
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" placeholder="e.g., Grace Nakamya" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" placeholder="mother@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" placeholder="e.g., 0777840016" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" placeholder="Min 8 characters" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <!-- LOCKED TO FEMALE -->
                            <input type="text" value="Female" readonly style="background-color: #f1f5f9; color: #475569;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" placeholder="e.g., Luzira, Kampala" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                </div>

                <!-- Additional Mother Details -->
                <div class="form-card">
                    <div class="section-title"><i class="fas fa-heartbeat"></i> Mother Details</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>National ID (NIN)</label>
                            <input type="text" name="national_id" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Marital Status</label>
                            <select name="marital_status">
                                <option value="">-- Select --</option>
                                <option value="single" <?= (($_POST['marital_status'] ?? '') === 'single') ? 'selected' : '' ?>>Single</option>
                                <option value="married" <?= (($_POST['marital_status'] ?? '') === 'married') ? 'selected' : '' ?>>Married</option>
                                <option value="divorced" <?= (($_POST['marital_status'] ?? '') === 'divorced') ? 'selected' : '' ?>>Divorced</option>
                                <option value="widowed" <?= (($_POST['marital_status'] ?? '') === 'widowed') ? 'selected' : '' ?>>Widowed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Emergency Contact Number</label>
                            <input type="text" name="emergency_contact" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Next of Kin Name</label>
                            <input type="text" name="next_of_kin" value="<?= htmlspecialchars($_POST['next_of_kin'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Next of Kin Phone</label>
                            <input type="text" name="next_of_kin_phone" value="<?= htmlspecialchars($_POST['next_of_kin_phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions" style="border: none; padding: 0;">
                    <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Register Mother</button>
                    <a href="healthworker.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>

        </div>
    </div>
</div>

</body>
</html>