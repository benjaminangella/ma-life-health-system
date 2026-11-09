<?php
// add_appointment.php - Add New Appointment
require_once 'config.php';
requireRole('admin');

$db = getDB();
$message = '';
$error = '';

// ==========================================
// 1. FETCH ONLY MOTHERS FOR THE PATIENT LIST
// ==========================================
$stmt = $db->query("
    SELECT id, full_name 
    FROM users 
    WHERE role = 'mother' AND status = 'active' 
    ORDER BY full_name
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get health workers
$stmt = $db->query("
    SELECT u.id, u.full_name, hw.worker_id
    FROM users u
    JOIN health_workers hw ON u.id = hw.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
");
$health_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all children
$stmt = $db->query("
    SELECT c.id, c.full_name, u.full_name AS mother_name
    FROM children c
    JOIN mothers m ON c.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    ORDER BY c.full_name
");
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 2. HANDLE SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $child_id = isset($_POST['child_id']) ? (int)$_POST['child_id'] : 0;
    $appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
    $appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'scheduled';
    $health_worker_id = isset($_POST['health_worker_id']) ? (int)$_POST['health_worker_id'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $facility = isset($_POST['facility']) ? trim($_POST['facility']) : '';

    $errors = array();
    if ($user_id == 0) $errors[] = 'Please select a mother.';
    if (empty($appointment_date)) $errors[] = 'Please select appointment date.';
    if (empty($appointment_time)) $errors[] = 'Please select appointment time.';
    if (empty($reason)) $errors[] = 'Please enter the reason for appointment.';

    if (empty($errors)) {
        try {
            $appointment_id = 'APT' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO appointments
                (appointment_id, user_id, child_id, appointment_date, appointment_time,
                 reason, status, health_worker_id, notes, facility)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $appointment_id,
                $user_id,
                $child_id > 0 ? $child_id : null,
                $appointment_date,
                $appointment_time,
                $reason,
                $status,
                $health_worker_id > 0 ? $health_worker_id : null,
                $notes != '' ? $notes : null,
                $facility != '' ? $facility : null
            ));

            // Send SMS via queue
            require_once 'sms_queue.php';
            $stmt = $db->prepare("SELECT phone, full_name FROM users WHERE id = ?");
            $stmt->execute(array($user_id));
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['phone'])) {
                $msg = "MA-LIFE: Appointment booked.\n"
                     . "Date: " . date('d M Y', strtotime($appointment_date)) . "\n"
                     . "Time: " . date('h:i A', strtotime($appointment_time)) . "\n"
                     . "Reason: $reason\n"
                     . ($facility ? "Location: $facility\n" : '')
                     . "Please arrive 15 minutes early.";
                ma_life_queue_sms($db, $u['phone'], $msg, 'appointment_booked', $user_id);
            }

            $message = "Appointment scheduled successfully! ID: " . $appointment_id;
            $_POST = array();

        } catch (Exception $e) {
            $error = 'Error scheduling appointment: ' . $e->getMessage();
        }
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
    <title>Add Appointment - MA-LIFE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CORE RESET & LAYOUT --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f4f7f6; height: 100vh; overflow: hidden; }

        .app-container { display: flex; height: 100vh; width: 100vw; padding: 15px; gap: 20px; }

        /* --- SIDEBAR --- */
        .sidebar { width: 260px; background: #1e293b; border-radius: 16px; display: flex; flex-direction: column; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-y: auto; flex-shrink: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
        
        .brand { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .brand h2 { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: #fff; text-transform: uppercase; }

        .nav-group { padding: 20px 10px 0 10px; }
        .nav-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 10px; padding-left: 10px; font-weight: 700; }
        
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #cbd5e1; text-decoration: none; border-radius: 10px; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; margin-bottom: 3px; position: relative; }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-link.active { background: #0ea5e9; color: #fff; box-shadow: 0 4px 15px rgba(14, 165, 233, 0.4); }
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding-right: 5px; }
        .main-content::-webkit-scrollbar { width: 6px; }
        .main-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 5px; }
        .page-header h2 { color: #1e293b; font-size: 1.6rem; font-weight: 700; }
        
        .btn { padding: 10px 20px; background: #0ea5e9; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.2); }
        .btn:hover { background: #0284c7; }
        .btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        .btn-outline:hover { background: #f1f5f9; }

        /* --- FORM STYLING --- */
        .form-container { max-width: 800px; margin: 0 auto; width: 100%; }
        .form-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
        .form-card .card-title { color: #0ea5e9; font-size: 1.3rem; font-weight: 700; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .form-card .card-subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group label .required { color: #ef4444; }
        
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #f8fafc; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #0ea5e9; outline: none; background: white; box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
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
            <h2>Administrator</h2>
        </div>

        <div class="nav-group">
            <div class="nav-label">Main Menu</div>
            <a href="administrator.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="register_fingerprint.php" class="nav-link"><i class="fas fa-fingerprint"></i> Fingerprint</a>
            <a href="admin_notes.php" class="nav-link"><i class="fas fa-sticky-note"></i> Notes & Files</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">Management</div>
            <a href="manage_users_admin.php" class="nav-link"><i class="fas fa-users"></i> System Users</a>
            <a href="appointments_admin.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> Appointments</a>
            <a href="admin_documents.php" class="nav-link"><i class="fas fa-folder-open"></i> All Documents</a>
            <a href="admin_notifications.php" class="nav-link"><i class="fas fa-bell"></i> Notifications</a>
        </div>

        <div class="nav-group">
            <div class="nav-label">System</div>
            <a href="reports_admin.php" class="nav-link"><i class="fas fa-chart-pie"></i> System Reports</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h2>Add Appointment</h2>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">Schedule a new visit for a mother or child.</p>
            </div>
            <a href="appointments_admin.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-title"><i class="fas fa-calendar-plus"></i> Schedule New Appointment</div>
                <div class="card-subtitle">Fill in the details below to book an appointment.</div>

                <form method="POST">
                    
                    <div class="form-group">
                        <label>Mother (Patient) <span class="required">*</span></label>
                        <select name="user_id" required>
                            <option value="">Select mother...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px;">Only mothers are listed here. Health workers and admins cannot be booked as patients.</p>
                    </div>

                    <div class="form-group">
                        <label>Child (Optional)</label>
                        <select name="child_id">
                            <option value="">No child (Mother's general appointment)</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>" <?php echo (isset($_POST['child_id']) && $_POST['child_id'] == $child['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($child['full_name']); ?> (Mother: <?php echo htmlspecialchars($child['mother_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Appointment Date <span class="required">*</span></label>
                            <input type="date" name="appointment_date" value="<?php echo isset($_POST['appointment_date']) ? $_POST['appointment_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Appointment Time <span class="required">*</span></label>
                            <input type="time" name="appointment_time" value="<?php echo isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '09:00'; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason for Visit <span class="required">*</span></label>
                        <input type="text" name="reason" placeholder="e.g., Antenatal checkup, Immunization" value="<?php echo htmlspecialchars(isset($_POST['reason']) ? $_POST['reason'] : ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Assign Health Worker</label>
                        <select name="health_worker_id">
                            <option value="">Select health worker...</option>
                            <?php foreach ($health_workers as $worker): ?>
                                <option value="<?php echo $worker['id']; ?>" <?php echo (isset($_POST['health_worker_id']) && $_POST['health_worker_id'] == $worker['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($worker['full_name']); ?> (<?php echo htmlspecialchars($worker['worker_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="scheduled">Scheduled</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Facility / Location</label>
                            <input type="text" name="facility" placeholder="e.g., Kawempe Health Centre" value="<?php echo htmlspecialchars(isset($_POST['facility']) ? $_POST['facility'] : ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes (Internal)</label>
                        <textarea name="notes" placeholder="Any additional notes for the health worker..."><?php echo htmlspecialchars(isset($_POST['notes']) ? $_POST['notes'] : ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Schedule Appointment</button>
                        <a href="appointments_admin.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>