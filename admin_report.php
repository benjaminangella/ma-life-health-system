<?php
require_once __DIR__ . '/config.php';
requireRole('admin');
header('Location: reports_admin.php', true, 302);
exit;
