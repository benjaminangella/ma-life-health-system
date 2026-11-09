<?php
// download_document.php - Force-download any document, note attachment, or profile picture
require_once 'config.php';
requireLogin();

$db = getDB();
$type = isset($_GET['type']) ? $_GET['type'] : 'document';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id < 1) { http_response_code(400); die('Invalid ID'); }

// ============================================
// NOTE ATTACHMENT (admin_note_attachments table)
// ============================================
if ($type === 'note_attachment') {
    $stmt = $db->prepare("SELECT * FROM admin_note_attachments WHERE id = ?");
    $stmt->execute(array($id));
    $att = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        http_response_code(404);
        die('Note attachment not found');
    }

    $path = __DIR__ . '/uploads/documents/' . $att['file_path'];
    if (!file_exists($path)) {
        http_response_code(404);
        die('File missing on server');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($att['file_mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($att['file_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($path);
    exit;
}

// ============================================
// REGULAR DOCUMENT (documents table)
// ============================================
if ($type === 'document') {
    // First try documents table
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute(array($id));
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not found, try admin_note_attachments (fallback)
    if (!$doc) {
        $stmt = $db->prepare("SELECT * FROM admin_note_attachments WHERE id = ?");
        $stmt->execute(array($id));
        $note_att = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($note_att) {
            // Redirect to the proper handler
            header('Location: download_document.php?type=note_attachment&id=' . $id);
            exit;
        }
        http_response_code(404);
        die('Document not found (checked documents and note attachments for id=' . $id . ')');
    }

    $path = __DIR__ . '/uploads/documents/' . $doc['file_path'];
    if (!file_exists($path)) {
        http_response_code(404);
        die('File missing on server: ' . $doc['file_path']);
    }

    // Log the download
    try {
        $log = $db->prepare("INSERT INTO notifications_log (user_id, phone, notification_type, message, status, created_at) VALUES (?, '', 'download', ?, 'sent', NOW())");
        $log->execute(array($_SESSION['user_id'], 'Downloaded: ' . $doc['doc_title']));
    } catch (Exception $e) { /* ignore */ }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($doc['file_mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($path);
    exit;
}

// ============================================
// PROFILE PICTURE
// ============================================
if ($type === 'profile') {
    $stmt = $db->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
    $stmt->execute(array($id));
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u || empty($u['profile_picture'])) {
        http_response_code(404);
        die('Profile picture not found');
    }

    $path = __DIR__ . '/uploads/profiles/' . $u['profile_picture'];
    if (!file_exists($path)) {
        http_response_code(404);
        die('File missing on server');
    }

    $ext = pathinfo($u['profile_picture'], PATHINFO_EXTENSION);
    $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9]/', '_', $u['full_name']) . '_profile.' . $ext . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

http_response_code(400);
die('Unknown download type');
?>