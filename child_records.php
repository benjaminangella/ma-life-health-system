<?php
// child_records.php - View Children Records for PHP 5.3.5
require_once 'config.php';

// session_start();  // REMOVED - already in config.php

// Allow both mothers and health workers to access
$allowed_roles = array('mother', 'healthworker');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}

// Check if user has an allowed role
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    die('Access Denied. You do not have permission to view this page.');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Different query based on role
if ($user_role === 'mother') {
    // Mother: Get her own children
    $stmt = $db->prepare("SELECT id FROM mothers WHERE user_id = ?");
    $stmt->execute(array($user_id));
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mother) {
        die('Mother profile not found.');
    }

    $mother_id = $mother['id'];

    // Get children for this mother
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u.full_name as mother_name,
            (SELECT COUNT(*) FROM immunizations WHERE child_id = c.id) as immunization_count,
            (SELECT COUNT(*) FROM growth_records WHERE child_id = c.id) as growth_count
        FROM children c
        JOIN mothers m ON c.mother_id = m.id
        JOIN users u ON m.user_id = u.id
        WHERE c.mother_id = ?
        ORDER BY c.id DESC
    ");
    $stmt->execute(array($mother_id));
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Health Worker: Get all children
    $stmt = $db->query("
        SELECT 
            c.*,
            u.full_name as mother_name,
            u.email as mother_email,
            (SELECT COUNT(*) FROM immunizations WHERE child_id = c.id) as immunization_count,
            (SELECT COUNT(*) FROM growth_records WHERE child_id = c.id) as growth_count
        FROM children c
        JOIN mothers m ON c.mother_id = m.id
        JOIN users u ON m.user_id = u.id
        ORDER BY c.id DESC
    ");
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total count for stats
$total_children = count($children);

// Calculate age in years, months, days
function calculateAge($birth_date) {
    $birth = new DateTime($birth_date);
    $now = new DateTime();
    $diff = $birth->diff($now);
    $text = '';
    if ($diff->y > 0) $text .= $diff->y . ' years, ';
    if ($diff->m > 0) $text .= $diff->m . ' months, ';
    $text .= $diff->d . ' days';
    return array(
        'years' => $diff->y,
        'months' => $diff->m,
        'days' => $diff->d,
        'text' => $text
    );
}

// Helper to count by gender - PHP 5.3 doesn't have arrow functions
function countByGender($children, $gender) {
    $count = 0;
    foreach($children as $c) {
        if (isset($c['gender']) && $c['gender'] === $gender) {
            $count++;
        }
    }
    return $count;
}

$male_count = countByGender($children, 'male');
$female_count = countByGender($children, 'female');

// Theme colors based on role
$bg_color = ($user_role === 'mother') ? '#fce4ec' : '#e0f7fa';
$sidebar_bg = ($user_role === 'mother') ? '#ad1457' : '#00796b';
$sidebar_hover = ($user_role === 'mother') ? '#880e4f' : '#004d40';
$primary_color = ($user_role === 'mother') ? '#ad1457' : '#00796b';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Records - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: <?php echo $bg_color; ?>; }
        
        .sidebar {
            position: fixed; width: 250px; height: 100vh; background: <?php echo $sidebar_bg; ?>;
            color: white; overflow-y: auto; z-index: 100;
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: <?php echo $sidebar_hover; ?>; }
        .sidebar::-webkit-scrollbar-thumb { background: <?php echo ($user_role === 'mother') ? '#f06292' : '#4db6ac'; ?>; border-radius: 10px; }
        .nav h2 { text-align: center; padding: 20px; background: <?php echo $sidebar_hover; ?>; font-size: 20px; }
        .logo { display: flex; justify-content: center; align-items: center; padding: 20px; }
        .logo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
        nav a { display: block; padding: 14px 20px; color: #e0e0e0; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.08); transition: 0.3s; }
        nav a:hover { background: <?php echo $sidebar_hover; ?>; color: white; padding-left: 28px; }
        nav a i { width: 25px; }
        nav a.active { background: <?php echo $sidebar_hover; ?>; color: white; border-left: 4px solid #ffb74d; }
        .topbar { margin-left: 250px; height: 70px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 30px; }
        .header h2 { color: <?php echo $primary_color; ?>; font-weight: 500; font-size: 1.2rem; }
        .main { margin-left: 250px; padding: 25px 30px; min-height: calc(100vh - 70px); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #333; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 1.5rem; font-weight: 700; color: <?php echo $primary_color; ?>; }
        .stat-box .label { color: #666; font-size: 0.85rem; }
        .stat-box .icon { font-size: 1.5rem; color: <?php echo $primary_color; ?>; margin-bottom: 5px; }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: 10px; }
        .role-badge.mother { background: #fce4ec; color: #ad1457; }
        .role-badge.healthworker { background: #e0f2f1; color: #00796b; }
        .child-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .child-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .child-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .child-card .card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .child-card .avatar { width: 60px; height: 60px; border-radius: 50%; background: <?php echo ($user_role === 'mother') ? '#fce4ec' : '#e0f2f1'; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: <?php echo $primary_color; ?>; }
        .child-card .card-header .info h3 { color: #333; font-size: 1.1rem; }
        .child-card .card-header .info .age { color: #666; font-size: 0.85rem; }
        .child-card .card-header .info .mother-name { font-size: 0.8rem; color: #666; }
        .child-card .details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .child-card .details .detail-item { padding: 8px 12px; background: #f5f5f5; border-radius: 6px; text-align: center; }
        .child-card .details .detail-item .label { font-size: 0.7rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
        .child-card .details .detail-item .value { font-weight: 600; color: #333; font-size: 0.95rem; }
        .child-card .card-actions { display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 2px solid #f0f0f0; flex-wrap: wrap; }
        .btn-sm { padding: 8px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; }
        .btn-primary-sm { background: <?php echo $primary_color; ?>; color: white; }
        .btn-primary-sm:hover { background: <?php echo $sidebar_hover; ?>; transform: translateY(-2px); }
        .btn-outline-sm { background: transparent; color: <?php echo $primary_color; ?>; border: 2px solid <?php echo $primary_color; ?>; }
        .btn-outline-sm:hover { background: <?php echo $primary_color; ?>; color: white; }
        .btn-info-sm { background: #e3f2fd; color: #0d47a1; }
        .btn-info-sm:hover { background: #bbdefb; }
        .no-children { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; }
        .no-children i { font-size: 4rem; color: #f0f0f0; margin-bottom: 15px; }
        .no-children h3 { color: #666; margin-bottom: 10px; }
        .no-children p { color: #999; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-male { background: #e3f2fd; color: #0d47a1; }
        .badge-female { background: #fce4ec; color: #ad1457; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .topbar { margin-left: 200px; padding: 0 20px; }
            .main { margin-left: 200px; padding: 15px; }
            .child-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
            .stats-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 550px) {
            .sidebar { width: 0; overflow: hidden; }
            .topbar { margin-left: 0; padding: 0 15px; }
            .main { margin-left: 0; padding: 10px; }
            .header h2 { font-size: 1rem; }
        }
    </style>
    <link rel="stylesheet" href="interface.css">
    <script src="interface.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="image/logo.png" alt="MA-Life Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23ffffff%22/%3E%3Cpath d=%22M30 30 L70 30 L70 70 L30 70 Z%22 fill=%22%23ad1457%22/%3E%3C/svg%3E'">
            </div>
            <h2><?php echo ($user_role === 'mother') ? 'Mother Portal' : 'Health Worker'; ?></h2>
            <nav>
                <?php if ($user_role === 'mother'): ?>
                    <a href="mother_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="mother_profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="register_child.php"><i class="fas fa-plus-circle"></i> Register Child</a>
                    <a href="child_records.php" class="active"><i class="fas fa-child"></i> Child Records</a>
                    <a href="immunization.php"><i class="fas fa-syringe"></i> Immunization</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php else: ?>
                    <a href="healthworker.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="health_worker_details.php"><i class="fas fa-user-md"></i> My Details</a>
                    <a href="register_mother.php"><i class="fas fa-plus-circle"></i> Register Mother</a>
                    <a href="mother_records.php"><i class="fas fa-female"></i> Mother Records</a>
                    <a href="antenatal.php"><i class="fas fa-heartbeat"></i> Antenatal Care</a>
                    <a href="child_records.php" class="active"><i class="fas fa-child"></i> Child Health Records</a>
                    <a href="appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Health Reports</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-child"></i> <?php echo ($user_role === 'mother') ? 'CHILD RECORDS' : 'CHILD HEALTH RECORDS'; ?></h2>
        </div>
    </div>

    <div class="main">
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="icon"><i class="fas fa-child"></i></div>
                <div class="number"><?php echo $total_children; ?></div>
                <div class="label">Total Children</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-male"></i></div>
                <div class="number"><?php echo $male_count; ?></div>
                <div class="label">Male Children</div>
            </div>
            <div class="stat-box">
                <div class="icon"><i class="fas fa-female"></i></div>
                <div class="number"><?php echo $female_count; ?></div>
                <div class="label">Female Children</div>
            </div>
        </div>

        <div class="page-header">
            <h2>
                <?php echo ($user_role === 'mother') ? 'My Children' : 'All Children'; ?>
                (<strong><?php echo $total_children; ?></strong>)
                <?php if ($user_role === 'healthworker'): ?>
                    <span class="role-badge healthworker">
                        <i class="fas fa-user-md"></i> Health Worker View
                    </span>
                <?php else: ?>
                    <span class="role-badge mother">
                        <i class="fas fa-female"></i> Mother View
                    </span>
                <?php endif; ?>
            </h2>
            <?php if ($user_role === 'mother'): ?>
                <a href="register_child.php" class="btn-sm btn-primary-sm" style="padding: 10px 20px; font-size:0.95rem;">
                    <i class="fas fa-plus-circle"></i> Register New Child
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($children)): ?>
            <div class="no-children">
                <i class="fas fa-child"></i>
                <h3>No Children Registered</h3>
                <p>
                    <?php if ($user_role === 'mother'): ?>
                        You haven't registered any children yet. Click the button above to register your child.
                    <?php else: ?>
                        No children have been registered in the system yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="child-grid">
                <?php foreach ($children as $child): 
                    $age = calculateAge($child['date_of_birth']);
                ?>
                    <div class="child-card">
                        <div class="card-header">
                            <div class="avatar">
                                <?php echo strtoupper(substr($child['full_name'], 0, 2)); ?>
                            </div>
                            <div class="info">
                                <h3><?php echo htmlspecialchars($child['full_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="age">
                                    <i class="fas fa-clock"></i> <?php echo $age['text']; ?>
                                </div>
                                <?php if ($user_role === 'healthworker' && isset($child['mother_name'])): ?>
                                    <div class="mother-name">
                                        <i class="fas fa-female"></i> Mother: <?php echo htmlspecialchars($child['mother_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="badge badge-<?php echo $child['gender']; ?>">
                                    <i class="fas fa-<?php echo ($child['gender'] === 'male') ? 'male' : 'female'; ?>"></i>
                                    <?php echo ucfirst($child['gender']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="details">
                            <div class="detail-item">
                                <div class="label">Birth Weight</div>
                                <div class="value"><?php echo (isset($child['birth_weight']) && $child['birth_weight']) ? $child['birth_weight'] . ' kg' : 'N/A'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Birth Length</div>
                                <div class="value"><?php echo (isset($child['birth_length']) && $child['birth_length']) ? $child['birth_length'] . ' cm' : 'N/A'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Immunizations</div>
                                <div class="value"><?php echo isset($child['immunization_count']) ? $child['immunization_count'] : 0; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Growth Records</div>
                                <div class="value"><?php echo isset($child['growth_count']) ? $child['growth_count'] : 0; ?></div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="view_child.php?id=<?php echo $child['id']; ?>" class="btn-sm btn-info-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="immunization.php?child_id=<?php echo $child['id']; ?>" class="btn-sm btn-primary-sm">
                                <i class="fas fa-syringe"></i> Immunizations
                            </a>
                            <a href="growth_monitoring.php?child_id=<?php echo $child['id']; ?>" class="btn-sm btn-outline-sm">
                                <i class="fas fa-chart-line"></i> Growth
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>