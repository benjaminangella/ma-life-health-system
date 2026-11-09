<?php
// dashboard.php - Direct System Access Point
require_once 'config.php';

// If user is logged in, redirect to their dashboard
if(isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: " . BASE_URL . getDashboardUrl($role));
    exit();
}

// If not logged in, redirect to login
header("Location: " . BASE_URL . "login.php");
exit();
?>