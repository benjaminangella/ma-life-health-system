<?php
/**
 * upload_helper.php — Shared upload + download utilities for MA-LIFE
 */

/**
 * Upload a profile picture (for users, mothers, children, health_workers).
 * Returns ['success' => true, 'filename' => '...'] or ['success' => false, 'error' => '...']
 */
function upload_image($file, $prefix = 'img', $max_size = 3 * 1024 * 1024) {
    if (empty($file['name'])) {
        return array('success' => false, 'error' => 'No file provided');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'error' => 'Upload error code: ' . $file['error']);
    }
    if ($file['size'] > $max_size) {
        return array('success' => false, 'error' => 'File too large (max ' . round($max_size / 1024 / 1024, 1) . ' MB)');
    }

    $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
    if (!isset($allowed[$file['type']])) {
        return array('success' => false, 'error' => 'Only JPG, PNG, GIF, WebP allowed');
    }

    $ext = $allowed[$file['type']];
    $new_name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $upload_dir = __DIR__ . '/uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
        return array('success' => false, 'error' => 'Could not save file. Check permissions.');
    }

    return array('success' => true, 'filename' => $new_name);
}

/**
 * Upload a document (PDF, image, Word).
 * Returns ['success' => true, 'filename' => '...', 'original' => '...', 'size' => 1234, 'mime' => '...'] or ['success' => false, 'error' => '...']
 */
function upload_document($file, $max_size = 10 * 1024 * 1024) {
    if (empty($file['name'])) {
        return array('success' => false, 'error' => 'No file provided');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'error' => 'Upload error code: ' . $file['error']);
    }
    if ($file['size'] > $max_size) {
        return array('success' => false, 'error' => 'File too large (max ' . round($max_size / 1024 / 1024) . ' MB)');
    }

    $allowed = array(
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    );

    if (!isset($allowed[$file['type']])) {
        return array('success' => false, 'error' => 'File type not allowed. Use PDF, image, Word, or Excel.');
    }

    $ext = $allowed[$file['type']];
    $safe_original = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
    $new_name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $upload_dir = __DIR__ . '/uploads/documents/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
        return array('success' => false, 'error' => 'Could not save file. Check permissions.');
    }

    return array(
        'success'  => true,
        'filename' => $new_name,
        'original' => $safe_original,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    );
}

/**
 * Save an uploaded document record to the database.
 */
function save_document_record($db, $entity_type, $entity_id, $doc_type, $doc_title, $file_info, $uploaded_by, $notes = null) {
    $stmt = $db->prepare("
        INSERT INTO documents
        (entity_type, entity_id, doc_type, doc_title, file_name, file_path, file_size, file_mime, notes, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(array(
        $entity_type, $entity_id, $doc_type, $doc_title,
        $file_info['original'], $file_info['filename'],
        $file_info['size'], $file_info['mime'],
        $notes, $uploaded_by
    ));
    return $db->lastInsertId();
}

/**
 * Fetch all documents for a given entity.
 */
function get_documents($db, $entity_type, $entity_id) {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM documents d
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.entity_type = ? AND d.entity_id = ?
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute(array($entity_type, $entity_id));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Return an icon class based on file extension.
 */
function document_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return 'fa-file-pdf';
    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) return 'fa-file-image';
    if (in_array($ext, array('doc', 'docx'))) return 'fa-file-word';
    if (in_array($ext, array('xls', 'xlsx'))) return 'fa-file-excel';
    return 'fa-file';
}
?>