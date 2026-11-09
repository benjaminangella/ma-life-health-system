/**
 * Check if the system already has an active administrator
 */
function ma_life_admin_exists($db, $exclude_user_id = 0) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM users 
        WHERE role = 'admin' 
          AND is_primary = 1 
          AND id != ?
    ");
    $stmt->execute(array($exclude_user_id));
    return $stmt->fetchColumn() > 0;
}

/**
 * Get the current system administrator (the primary one)
 */
function ma_life_get_primary_admin($db) {
    $stmt = $db->query("
        SELECT id, username, full_name, email 
        FROM users 
        WHERE role = 'admin' AND is_primary = 1 
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}