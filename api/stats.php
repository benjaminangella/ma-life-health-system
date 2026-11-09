<?php
// api/stats.php
require_once '../config.php';
requireLogin();

$db = getDB();

$stats = [];

// Total mothers
$stmt = $db->query("SELECT COUNT(*) FROM mothers");
$stats['mothers'] = $stmt->fetchColumn();

// Total children
$stmt = $db->query("SELECT COUNT(*) FROM children");
$stats['children'] = $stmt->fetchColumn();

// Total health workers
$stmt = $db->query("SELECT COUNT(*) FROM health_workers");
$stats['workers'] = $stmt->fetchColumn();

// Today's appointments
$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status = 'scheduled'");
$stmt->execute();
$stats['appointments'] = $stmt->fetchColumn();

jsonResponse($stats);
?>