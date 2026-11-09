<?php
// signup.php - Registration Page
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    
    $errors = array();
    if (strlen($full_name) < 3) $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (!in_array($role, array('mother'))) $errors[] = 'Please select a valid role.';
    
    if (getUserByEmail($email)) {
        $errors[] = 'Email already registered. Please login.';
    }
    
    if (empty($errors)) {
        $user_id = createUser(array(
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'phone' => isset($_POST['phone']) && $_POST['phone'] !== '' ? $_POST['phone'] : null,
            'date_of_birth' => isset($_POST['date_of_birth']) && $_POST['date_of_birth'] !== '' ? $_POST['date_of_birth'] : null,
            'gender' => isset($_POST['gender']) && $_POST['gender'] !== '' ? $_POST['gender'] : null,
            'address' => isset($_POST['address']) && $_POST['address'] !== '' ? $_POST['address'] : null
        ));
        
        $db = getDB();
        if ($role === 'mother') {
            $stmt = $db->prepare("INSERT INTO mothers (user_id) VALUES (?)");
            $stmt->execute(array($user_id));
        } elseif ($role === 'healthworker') {
            $stmt = $db->prepare("INSERT INTO health_workers (user_id, worker_id, specialization) VALUES (?, ?, ?)");
            $worker_id = 'HW' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            $specialization = isset($_POST['specialization']) && $_POST['specialization'] !== '' ? $_POST['specialization'] : 'General';
            $stmt->execute(array($user_id, $worker_id, $specialization));
        }
        
        $success = 'Account created successfully! Redirecting to login...';
        header('refresh:2;url=login.php');
        exit;
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - MA-LIFE Health System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container { min-height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(135deg, #0A4C54 0%, #146b76 50%, #1a8a96 100%); padding: 20px; }
        .auth-card { background: white; padding: 2.5rem; border-radius: 20px; width: 100%; max-width: 550px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.6s ease-out; max-height: 95vh; overflow-y: auto; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .auth-header { text-align: center; margin-bottom: 1.5rem; }
        .auth-header .logo-img { width: 70px; height: 70px; border-radius: 50%; background: #0A4C54; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem; font-size: 2rem; color: white; }
        .auth-header h1 { font-family: 'Poppins', sans-serif; color: #0A4C54; font-size: 1.6rem; }
        .auth-header p { color: #666; font-size: 0.9rem; }
        .input-group { margin-bottom: 1.2rem; position: relative; }
        .input-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; font-size: 0.85rem; color: #333; }
        .input-group .input-icon { position: absolute; left: 12px; top: 36px; color: #999; }
        .input-group input, .input-group select {
            width: 100%; padding: 10px 12px 10px 38px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s; background: #f8f9fa;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #0A4C54; outline: none; background: white; box-shadow: 0 0 0 4px rgba(10, 76, 84, 0.1);
        }
        .input-group select { padding-left: 38px; appearance: none; }
        .btn-primary { width: 100%; padding: 14px; background: #0A4C54; color: white; border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary:hover { background: #146b76; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(10, 76, 84, 0.3); }
        .error-message { background: #fee; color: #c0392b; padding: 10px 15px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; border-left: 4px solid #c0392b; }
        .success-message { background: #e8f5e9; color: #2e7d32; padding: 10px 15px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; border-left: 4px solid #2e7d32; }
        .auth-footer { text-align: center; margin-top: 1.5rem; padding-top: 1.2rem; border-top: 1px solid #eee; }
        .auth-footer a { color: #0A4C54; text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .auth-card { padding: 1.5rem; } }
        .password-hint { font-size: 0.8rem; color: #999; margin-top: 3px; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #0A4C54; border-radius: 10px; }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-img">👶</div>
                <h1>MA-LIFE</h1>
                <p>Maternal & Child Health System · Luzira</p>
            </div>
            
            <h2 style="text-align:center; color:#333; margin-bottom:1.2rem; font-size:1.3rem;">Create Account</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label for="full_name">Full Name *</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="full_name" name="full_name" placeholder="e.g., Grace Nakamya" required value="<?php echo htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="input-group">
                    <label for="email">Email Address *</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" placeholder="grace@email.com" required value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-row">
                    <div class="input-group">
                        <label for="password">Password *</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Min 8 characters" required>
                        <div class="password-hint">At least 8 characters</div>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <i class="fas fa-check-circle input-icon"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="role">Select Role *</label>
                    <i class="fas fa-user-tag input-icon"></i>
                    <select id="role" name="role" required>
                        <option value="">Select your role...</option>
                        <option value="mother" <?php echo (isset($_POST['role']) && $_POST['role'] === 'mother') ? 'selected' : ''; ?>>Mother / Guardian</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="input-group">
                        <label for="phone">Phone Number</label>
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" id="phone" name="phone" placeholder="+256 700 000 000" value="<?php echo htmlspecialchars(isset($_POST['phone']) ? $_POST['phone'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <i class="fas fa-calendar input-icon"></i>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-group">
                        <label for="gender">Gender</label>
                        <i class="fas fa-venus-mars input-icon"></i>
                        <select id="gender" name="gender">
                            <option value="">Select gender</option>
                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="address">Address</label>
                        <i class="fas fa-home input-icon"></i>
                        <input type="text" id="address" name="address" placeholder="Luzira, Kampala" value="<?php echo htmlspecialchars(isset($_POST['address']) ? $_POST['address'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                
                <div class="input-group" id="specializationGroup" style="display:<?php echo (isset($_POST['role']) && $_POST['role'] === 'healthworker') ? 'block' : 'none'; ?>;">
                    <label for="specialization">Specialization</label>
                    <i class="fas fa-stethoscope input-icon"></i>
                    <input type="text" id="specialization" name="specialization" placeholder="e.g., Midwifery, Pediatrics" value="<?php echo htmlspecialchars(isset($_POST['specialization']) ? $_POST['specialization'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login Here</a></p>
                <p style="margin-top:8px; font-size:0.8rem; color:#999;">
                    <i class="fas fa-shield-alt"></i> Your data is secure and encrypted
                </p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('role').addEventListener('change', function() {
            const group = document.getElementById('specializationGroup');
            if (this.value === 'healthworker') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
        
        window.onload = function() {
            if (document.getElementById('role').value === 'healthworker') {
                document.getElementById('specializationGroup').style.display = 'block';
            }
        }
    </script>
</body>
</html>