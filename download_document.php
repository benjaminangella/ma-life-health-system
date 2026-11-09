<?php
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$type = $_GET['type'] ?? 'document';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$user = (int) $_SESSION['user_id'];
$isStaff = isRole('admin') || isRole('healthworker');

if (!$id) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$directory = __DIR__ . '/uploads/documents';
if ($type === 'child_document') {
    $stmt = $db->prepare('SELECT d.file_name, d.file_path FROM child_documents d
        JOIN children c ON c.id = d.child_id JOIN mothers m ON m.id = c.mother_id
        WHERE d.id = ? AND (? = 1 OR m.user_id = ?)');
    $stmt->execute([$id, (int) $isStaff, $user]);
} elseif ($type === 'note_attachment') {
    $stmt = $db->prepare('SELECT a.file_name, a.file_path FROM admin_note_attachments a
        JOIN admin_notes n ON n.id = a.note_id
        WHERE a.id = ? AND (n.user_id = ? OR n.shared_with_id = ?)');
    $stmt->execute([$id, $user, $user]);
} elseif ($type === 'document') {
    $stmt = $db->prepare('SELECT file_name, file_path FROM documents WHERE id = ? AND (? = 1 OR uploaded_by = ?)');
    $stmt->execute([$id, (int) $isStaff, $user]);
} elseif ($type === 'profile') {
    $directory = __DIR__ . '/uploads/profiles';
    $stmt = $db->prepare('SELECT profile_picture AS file_name, profile_picture AS file_path
        FROM users WHERE id = ? AND (? = 1 OR id = ?)');
    $stmt->execute([$id, (int) isRole('admin'), $user]);
} else {
    http_response_code(400);
    exit('Unknown document type.');
}

$file = $stmt->fetch(PDO::FETCH_ASSOC);
$root = realpath($directory);
$path = $file && $root && $file['file_path'] ? realpath($root . '/' . $file['file_path']) : false;
if (!$path || !str_starts_with($path, $root . '/') || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Document not found.');
}

$name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['file_name']));
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$canPreview = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
$disposition = isset($_GET['inline']) && $canPreview ? 'inline' : 'attachment';
header('Content-Type: ' . ($canPreview ? $mime : 'application/octet-stream'));
header('Content-Disposition: ' . $disposition . '; filename="' . ($name ?: 'document') . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
