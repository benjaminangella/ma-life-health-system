<?php
// settings.php - System Settings for PHP 5.3.5
require_once 'config.php';
requireRole('admin');

// session_start() is in config.php
$db = getDB();
$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute(array($value, $key));
        }
        $message = 'Settings updated successfully!';
    } catch (Exception $e) {
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get all settings
$stmt = $db->query("SELECT * FROM system_settings ORDER BY setting_key");
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group settings by category
$grouped_settings = array();
$categories = array(
    'system' => array('system_name', 'system_version', 'maintenance_mode'),
    'security' => array('allow_registration', 'session_timeout', 'max_login_attempts'),
    'email' => array('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'),
    'notifications' => array('appointment_reminder_days', 'immunization_reminder_days'),
    'general' => array('default_language', 'timezone', 'date_format')
);

foreach ($settings as $setting) {
    $found = false;
    foreach ($categories as $category => $keys) {
        if (in_array($setting['setting_key'], $keys)) {
            if (!isset($grouped_settings[$category])) {
                $grouped_settings[$category] = array();
            }
            $grouped_settings[$category][] = $setting;
            $found = true;
            break;
        }
    }
    if (!$found) {
        if (!isset($grouped_settings['other'])) {
            $grouped_settings['other'] = array();
        }
        $grouped_settings['other'][] = $setting;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - MA-LIFE</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { color: #37474f; }
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .settings-section { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .settings-section .section-header { display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; }
        .settings-section .section-header h3 { color: #37474f; font-size: 1.1rem; }
        .settings-section .section-header i { font-size: 1.2rem; color: #00897b; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group label .desc { font-weight: 400; color: #999; font-size: 0.8rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; transition: all 0.3s ease; background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #00897b; outline: none; background: white; box-shadow: 0 0 0 4px rgba(0, 137, 123, 0.1);
        }
        .form-group .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .form-group .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .btn-save { padding: 12px 35px; background: #00897b; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #00695c; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 137, 123, 0.3); }
        .btn-reset { padding: 12px 25px; background: #ffebee; color: #c62828; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-reset:hover { background: #ffcdd2; }
        .btn { padding: 10px 20px; background: #00897b; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; border: none; cursor: pointer; }
        .btn-primary { background: #00897b; }
        .btn-primary:hover { background: #00695c; }
        .btn-outline { background: #f5f5f5; color: #666; }
        .form-actions { display: flex; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        @media (max-width: 992px) { .settings-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
            .form-actions { flex-direction: column; }
            .form-actions button { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="nav">
            <div class="logo">
                <img src="img/settings1.png" alt="MA-Life Logo">
            </div>
            <h2>Administrator</h2>
            <nav>
                <a href="settings.php" class="active"><i class="fas fa-cog"></i> System Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </div>

    <div class="topbar">
        <div class="header">
            <h2><i class="fas fa-cog"></i> SYSTEM SETTINGS</h2>
        </div>
    </div>

    <div class="main">
        <div class="page-header">
                <div>
                    <a href="administrator.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
        </div>
        <div class="page-header">
            <h2>System Configuration</h2>
            <span style="color:#666; font-size:0.9rem;">
                <i class="fas fa-info-circle"></i> Configure system preferences
            </span>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="settings-grid">
                <?php foreach ($grouped_settings as $category => $settings_list): ?>
                    <div class="settings-section">
                        <div class="section-header">
                            <?php
                            $icons = array(
                                'system' => 'fa-server',
                                'security' => 'fa-shield-alt',
                                'email' => 'fa-envelope',
                                'notifications' => 'fa-bell',
                                'general' => 'fa-sliders-h',
                                'other' => 'fa-cogs'
                            );
                            $labels = array(
                                'system' => 'System Settings',
                                'security' => 'Security & Access',
                                'email' => 'Email Configuration',
                                'notifications' => 'Notification Settings',
                                'general' => 'General Settings',
                                'other' => 'Other Settings'
                            );
                            $icon = isset($icons[$category]) ? $icons[$category] : 'fa-cog';
                            $label = isset($labels[$category]) ? $labels[$category] : ucfirst($category);
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                            <h3><?php echo $label; ?></h3>
                        </div>

                        <?php foreach ($settings_list as $setting): ?>
                            <div class="form-group">
                                <label for="<?php echo $setting['setting_key']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                    <span class="desc"><?php echo htmlspecialchars(isset($setting['description']) ? $setting['description'] : '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </label>
                                
                                <?php if ($setting['setting_key'] === 'maintenance_mode' || $setting['setting_key'] === 'allow_registration'): ?>
                                    <div class="checkbox-group">
                                        <input type="hidden" name="settings[<?php echo $setting['setting_key']; ?>]" value="false">
                                        <input type="checkbox" 
                                            id="<?php echo $setting['setting_key']; ?>" 
                                            name="settings[<?php echo $setting['setting_key']; ?>]" 
                                            value="true"
                                            <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>>
                                        <label for="<?php echo $setting['setting_key']; ?>" style="font-weight:400; cursor:pointer;">
                                            <?php echo $setting['setting_value'] === 'true' ? 'Enabled' : 'Disabled'; ?>
                                        </label>
                                    </div>
                                <?php elseif (in_array($setting['setting_key'], array('default_language', 'timezone', 'date_format'))): ?>
                                    <select name="settings[<?php echo $setting['setting_key']; ?>]" id="<?php echo $setting['setting_key']; ?>">
                                        <?php if ($setting['setting_key'] === 'default_language'): ?>
                                            <option value="en" <?php echo $setting['setting_value'] === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="sw" <?php echo $setting['setting_value'] === 'sw' ? 'selected' : ''; ?>>Swahili</option>
                                            <option value="lg" <?php echo $setting['setting_value'] === 'lg' ? 'selected' : ''; ?>>Luganda</option>
                                        <?php elseif ($setting['setting_key'] === 'timezone'): ?>
                                            <option value="UTC" <?php echo $setting['setting_value'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            <option value="Africa/Kampala" <?php echo $setting['setting_value'] === 'Africa/Kampala' ? 'selected' : ''; ?>>Africa/Kampala</option>
                                            <option value="Africa/Nairobi" <?php echo $setting['setting_value'] === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                                        <?php elseif ($setting['setting_key'] === 'date_format'): ?>
                                            <option value="Y-m-d" <?php echo $setting['setting_value'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                            <option value="d-m-Y" <?php echo $setting['setting_value'] === 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY</option>
                                            <option value="m/d/Y" <?php echo $setting['setting_value'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" 
                                        id="<?php echo $setting['setting_key']; ?>" 
                                        name="settings[<?php echo $setting['setting_key']; ?>]" 
                                        value="<?php echo htmlspecialchars($setting['setting_value'], ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="Enter value...">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <button type="reset" class="btn-reset">
                    <i class="fas fa-undo"></i> Reset Changes
                </button>
                <button type="button" class="btn btn-outline" onclick="if(confirm('Reset all settings to default?')){ alert('Restore defaults not implemented yet'); }">
                    <i class="fas fa-history"></i> Restore Defaults
                </button>
            </div>
        </form>
    </div>
</body>
</html>