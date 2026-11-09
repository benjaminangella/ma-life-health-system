<?php
// logout.php
require_once 'config.php';
logoutUser();
header('Location: index.php');
exit();
?>