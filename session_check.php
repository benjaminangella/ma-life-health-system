<?php
// session_check.php - Lightweight endpoint to verify session status
require_once 'config.php';
header('Content-Type: application/json');
echo json_encode(array(
    'logged_in' => isLoggedIn(),
    'role'      => isLoggedIn() ? $_SESSION['user_role'] : null,
));
?>