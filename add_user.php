<?php
// add_user.php - Add New User
// Enforces: only ONE active administrator allowed
require_once 'config.php';
requireRole('admin');

$db = getDB();
$message = '';
$error = '';

// Available roles for dropdown
$roles = ['admin', 'healthworker', 'mother', 'child'];

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role             = $_POST['role'] ?? '';
    $phone            = trim($_POST['phone'] ?? '');
    $date_of_birth    = $_POST['date_of_birth'] ?? '';
    $gender           = $_POST['gender'] ?? '';
    $address          = trim($_POST['address'] ?? '');

    // Role-specific fields
    $national_id         = trim($_POST['national_id'] ?? '');
    $marital_status      = $_POST['marital_status'] ?? '';
    $occupation          = trim($_POST['occupation'] ?? '');
    $next_of_kin         = trim($_POST['next_of_kin'] ?? '');
    $next_of_kin_phone   = trim($_POST['next_of_kin_phone'] ?? '');
    $emergency_contact   = trim($_POST['emergency_contact'] ?? '');
    $specialization      = trim($_POST['specialization'] ?? '');
    $facility_name       = trim($_POST['facility_name'] ?? '');
    $license_number      = trim($_POST['license_number'] ?? '');
    $years_of_experience = $_POST['years_of_experience'] ?? '';

    // ---- Validation ----
    $errors = [];
    if (strlen($full_name) < 3) $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (!in_array($role, $roles)) $errors[] = 'Please select a valid role.';

    // Check if email exists
    if (getUserByEmail($email)) {
        $errors[] = 'Email already registered.';
    }

    // ============================================================
    // ADMIN GUARD — Only one admin allowed
    // ============================================================
    $is_primary = 0;
    if ($role === 'admin') {
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_primary=1");
        $existing_admins = (int)$stmt->fetchColumn();

        if ($existing_admins >= 1) {
            $errors[] = 'MA-LIFE only allows one system administrator. ' .
                        'An admin account already exists. Please promote an existing user ' .
                        'or transfer the admin role instead.';
        } else {
            // This new admin will be the primary admin
            $is_primary = 1;
        }
    }

    // ---- Save if no errors ----
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Create the user (createUser helper handles password hashing)
            // NOTE: If createUser() doesn't accept is_primary, we update after.
            $user_id = createUser([
                'full_name'     => $full_name,
                'email'         => $email,
                'password'      => $password,
                'role'          => $role,
                'phone'         => $phone,
                'date_of_birth' => $date_of_birth ?: null,
                'gender'        => $gender ?: null,
                'address'       => $address ?: null
            ]);

            // If this new user is the primary admin, mark them
            if ($is_primary === 1) {
                $stmt = $db->prepare("UPDATE users SET is_primary = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$user_id]);
            }

            // Create role-specific record
            if ($role === 'mother') {
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
            } elseif ($role === 'healthworker') {
                $worker_id = 'HW' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("
                    INSERT INTO health_workers (user_id, worker_id, specialization,
                    facility_name, license_number, years_of_experience)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $worker_id,
                    $specialization ?: null,
                    $facility_name ?: null,
                    $license_number ?: null,
                    $years_of_experience ?: null
                ]);
            }

            $db->commit();

            $message = "User <strong>" . htmlspecialchars($full_name) . "</strong> added successfully!";
            if ($is_primary === 1) {
                $message .= " <em>(This user is the system administrator.)</em>";
            }

            // Clear form fields
            $_POST = [];

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error adding user: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// ============================================================
// STATS FOR DISPLAY
// ============================================================
$stmt = $db->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_stats = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $role_stats[$row['role']] = $row['count'];
}

// Whether an admin already exists (for UI hints)
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_primary=1");
$admin_exists = ((int)$stmt->fetchColumn()) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .breadcrumb { padding: 10px 0 20px 0; font-size: 0.95rem; color: #666; }
        .breadcrumb a { color: #00897b; text-decoration: none; font-weight: 600; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb .separator { margin: 0 8px; color: #ccc; }
        .breadcrumb .current { color: #333; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .form-container { max-width: 900px; margin: 0 auto; }
        .form-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .form-card .card-title { color: #37474f; font-size: 1.2rem; font-weight: 600; margin-bottom: 5px; }
        .form-card .card-subtitle { color: #666; font-size: 0.9rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group label .required { color: #c62828; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s ease; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #00897b; outline: none; background: white;
            box-shadow: 0 0 0 4px rgba(0, 137, 123, 0.1);
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-group .helper-text { font-size: 0.8rem; color: #999; margin-top: 3px; }
        .section-title {
            font-size: 1rem; font-weight: 600; color: #00897b; margin: 25px 0 15px 0;
            padding-bottom: 10px; border-bottom: 2px solid #e0f2f1;
        }
        .section-title i { margin-right: 8px; }
        .form-actions { display: flex; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .btn-submit {
            padding: 12px 35px; background: #00897b; color: white; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-submit:hover { background: #00695c; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 137, 123, 0.3); }
        .btn-submit:disabled { background: #bdbdbd; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-reset { padding: 12px 25px; background: #f5f5f5; color: #666; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-reset:hover { background: #e0e0e0; }
        .btn-cancel { padding: 12px 25px; background: #ffebee; color: #c62828; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-cancel:hover { background: #ffcdd2; }
        .role-specific-fields { display: none; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-top: 10px; border-left: 4px solid #00897b; }
        .role-specific-fields.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 1.5rem; font-weight: 700; color: #37474f; }
        .stat-box .label { color: #666; font-size: 0.8rem; }
        .stat-box .icon { font-size: 1.2rem; color: #00897b; margin-bottom: 5px; }
        .admin-warning {
            background: #fff8e1; border-left: 4px solid #ffa000; color: #6d4c41;
            padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions button, .form-actions a { width: 100%; justify-content: center; }
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="nav">
            <div class="logo"><img src="img/logo.png" alt="MA-Life Logo"></div>
            <h2>Administrator</h2>
            <nav>
                <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="manage_users_admin.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
                <a href="health_workers.php"><i class="fas fa-user-md"></i> Health Workers</a>
                <a href="admin_report.php"><i class="fas fa-chart-bar"></i> System Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <!-- Top Bar -->
    <div class="topbar">
        <div class="header"><h2><i class="fas fa-user-plus"></i> ADD USER</h2></div>
    </div>

    <!-- Main Content -->
    <div class="main">
        <div class="breadcrumb">
            <a href="administrator.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="separator">›</span>
            <a href="manage_users_admin.php">Manage Users</a>
            <span class="separator">›</span>
            <span class="current">Add User</span>
        </div>

        <div class="page-header">
            <h2>Add New User</h2>
            <a href="manage_users_admin.php" class="btn-cancel">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if ($admin_exists): ?>
            <div class="admin-warning">
                <i class="fas fa-shield-alt"></i>
                <strong>Single-Administrator Policy:</strong> An administrator already exists in MA-LIFE.
                Creating another admin is blocked. Only one system administrator may be active at a time.
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box"><div class="icon"><i class="fas fa-users"></i></div><div class="number"><?= $total_users ?></div><div class="label">Total Users</div></div>
            <div class="stat-box"><div class="icon"><i class="fas fa-user-shield"></i></div><div class="number"><?= $role_stats['admin'] ?? 0 ?></div><div class="label">Administrators</div></div>
            <div class="stat-box"><div class="icon"><i class="fas fa-user-md"></i></div><div class="number"><?= $role_stats['healthworker'] ?? 0 ?></div><div class="label">Health Workers</div></div>
            <div class="stat-box"><div class="icon"><i class="fas fa-female"></i></div><div class="number"><?= $role_stats['mother'] ?? 0 ?></div><div class="label">Mothers</div></div>
        </div>

        <!-- Add User Form -->
        <div class="form-container">
            <div class="form-card">
                <div class="card-title"><i class="fas fa-user-plus" style="color:#00897b;"></i> User Registration Form</div>
                <div class="card-subtitle">Fill in the details below to create a new user account with role-specific information.</div>

                <form method="POST" action="" id="userForm">
                    <!-- Basic Information -->
                    <div class="section-title"><i class="fas fa-user-circle"></i> Basic Information</div>

                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g., Grace Nakamya"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" placeholder="user@malife.org"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" placeholder="+256 7XX XXX XXX"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <input type="password" id="password" name="password" placeholder="Min 8 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                   value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select --</option>
                                <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= (($_POST['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" placeholder="e.g., Luzira, Kampala"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <!-- Role -->
                    <div class="section-title"><i class="fas fa-user-tag"></i> Role</div>
                    <div class="form-group">
                        <label for="role">User Role <span class="required">*</span></label>
                        <select id="role" name="role" required onchange="toggleRoleFields()">
                            <option value="">-- Select Role --</option>
                            <option value="admin"         <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>         <?= $admin_exists ? 'disabled' : '' ?>>
                                Administrator <?= $admin_exists ? '(already exists — disabled)' : '' ?>
                            </option>
                            <option value="healthworker"  <?= (($_POST['role'] ?? '') === 'healthworker') ? 'selected' : '' ?>>Health Worker</option>
                            <option value="mother"        <?= (($_POST['role'] ?? '') === 'mother') ? 'selected' : '' ?>>Mother</option>
                            <option value="child"         <?= (($_POST['role'] ?? '') === 'child') ? 'selected' : '' ?>>Child</option>
                        </select>
                        <div class="helper-text">
                            MA-LIFE allows only one administrator account.
                            <?= $admin_exists ? 'Contact the existing admin to transfer the role.' : '' ?>
                        </div>
                    </div>

                    <!-- Role-specific fields -->
                    <div id="fields_mother" class="role-specific-fields">
                        <div class="section-title"><i class="fas fa-female"></i> Mother Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="national_id">National ID</label>
                                <input type="text" id="national_id" name="national_id" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="marital_status">Marital Status</label>
                                <select id="marital_status" name="marital_status">
                                    <option value="">-- Select --</option>
                                    <option value="single"   <?= (($_POST['marital_status'] ?? '') === 'single') ? 'selected' : '' ?>>Single</option>
                                    <option value="married"  <?= (($_POST['marital_status'] ?? '') === 'married') ? 'selected' : '' ?>>Married</option>
                                    <option value="divorced" <?= (($_POST['marital_status'] ?? '') === 'divorced') ? 'selected' : '' ?>>Divorced</option>
                                    <option value="widowed"  <?= (($_POST['marital_status'] ?? '') === 'widowed') ? 'selected' : '' ?>>Widowed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="occupation">Occupation</label>
                                <input type="text" id="occupation" name="occupation" value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact</label>
                                <input type="text" id="emergency_contact" name="emergency_contact" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="next_of_kin">Next of Kin</label>
                                <input type="text" id="next_of_kin" name="next_of_kin" value="<?= htmlspecialchars($_POST['next_of_kin'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="next_of_kin_phone">Next of Kin Phone</label>
                                <input type="text" id="next_of_kin_phone" name="next_of_kin_phone" value="<?= htmlspecialchars($_POST['next_of_kin_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div id="fields_healthworker" class="role-specific-fields">
                        <div class="section-title"><i class="fas fa-user-md"></i> Health Worker Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="facility_name">Facility Name</label>
                                <input type="text" id="facility_name" name="facility_name" value="<?= htmlspecialchars($_POST['facility_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($_POST['license_number'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="years_of_experience">Years of Experience</label>
                                <input type="number" id="years_of_experience" name="years_of_experience" min="0"
                                       value="<?= htmlspecialchars($_POST['years_of_experience'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Create User
                        </button>
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Reset</button>
                        <a href="manage_users_admin.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleRoleFields() {
            var role = document.getElementById('role').value;
            document.getElementById('fields_mother').classList.toggle('active', role === 'mother');
            document.getElementById('fields_healthworker').classList.toggle('active', role === 'healthworker');
        }
        // Run on page load (keeps selected role after validation error)
        document.addEventListener('DOMContentLoaded', toggleRoleFields);
    </script>
</body>
</html>