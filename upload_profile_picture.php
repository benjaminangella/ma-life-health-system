<?php
// upload_profile_picture.php - Handle profile picture uploads for all roles
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

if (empty($_FILES['profile_picture']['name'])) {
    http_response_code(400);
    echo json_encode(array('error' => 'No file uploaded'));
    exit;
}

$file = $_FILES['profile_picture'];
$allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
$max_size = 3 * 1024 * 1024; // 3 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(array('error' => 'Upload error code: ' . $file['error']));
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(array('error' => 'Only JPG, PNG, GIF, or WebP images allowed'));
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(array('error' => 'File too large (max 3 MB)'));
    exit;
}

// Ensure upload folder exists
$upload_dir = __DIR__ . '/uploads/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$new_name = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
$dest = $upload_dir . $new_name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(array('error' => 'Failed to save file. Check folder permissions.'));
    exit;
}

$db = getDB();

// Delete old profile picture
$stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute(array($_SESSION['user_id']));
$old = $stmt->fetchColumn();
if ($old && file_exists($upload_dir . $old)) {
    @unlink($upload_dir . $old);
}

// Update database
$stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$stmt->execute(array($new_name, $_SESSION['user_id']));

echo json_encode(array(
    'success'   => true,
    'url'       => 'uploads/profiles/' . $new_name,
    'filename'  => $new_name,
    'message'   => 'Profile picture updated!'
));
exit;
?>