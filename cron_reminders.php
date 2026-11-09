<?php
// cron_reminders.php - Background automation for reminders
require_once 'config.php';
require_once 'sms_queue.php';

set_time_limit(300); 

$db = getDB();
$queued_count = 0;

// ==========================================
// 1. ANTENATAL APPOINTMENT REMINDERS (3 days before)
// ==========================================
$target_date = date('Y-m-d', strtotime('+3 days'));

$stmt = $db->prepare("
    SELECT a.id, a.user_id, u.phone, u.full_name, a.appointment_date
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE DATE(a.appointment_date) = ? 
      AND a.status = 'scheduled'
      AND a.reminder_sent = 0
");
$stmt->execute([$target_date]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($appointments as $apt) {
    $msg = "MA-LIFE Reminder: Hello {$apt['full_name']}, you have an Antenatal appointment in 3 days on " . date('d M Y', strtotime($apt['appointment_date'])) . ". Please come to the clinic.";
    
    try {
        // Insert into queue
        $insert = $db->prepare("INSERT INTO notifications_log (user_id, phone, notification_type, message, status, created_at) VALUES (?, ?, ?, ?, 'queued', NOW())");
        $insert->execute(array($apt['user_id'], ma_life_normalize_phone($apt['phone']), 'appointment_reminder', $msg));
        
        // Mark as processed so it doesn't repeat
        $update = $db->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
        $update->execute([$apt['id']]);
        $queued_count++;
    } catch (Exception $e) {
        // Ignore if it's a duplicate
    }
}

// ==========================================
// 2. CHILD IMMUNIZATION REMINDERS (3 days before)
// ==========================================
$stmt = $db->prepare("
    SELECT i.id, m.user_id, u.phone, u.full_name, c.full_name AS child_name, i.vaccine_name, i.due_date
    FROM immunizations i
    JOIN children c ON i.child_id = c.id
    JOIN mothers m ON c.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE DATE(i.due_date) = ? 
      AND i.status = 'pending'
      AND i.reminder_sent = 0
");
$stmt->execute([$target_date]);
$immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($immunizations as $imm) {
    $msg = "MA-LIFE Reminder: Hello {$imm['full_name']}, your child {$imm['child_name']} is due for their {$imm['vaccine_name']} immunization on " . date('d M Y', strtotime($imm['due_date'])) . ". Please visit the clinic.";
    
    try {
        $insert = $db->prepare("INSERT INTO notifications_log (user_id, phone, notification_type, message, status, created_at) VALUES (?, ?, ?, ?, 'queued', NOW())");
        $insert->execute(array($imm['user_id'], ma_life_normalize_phone($imm['phone']), 'immunization_reminder', $msg));
        
        $update = $db->prepare("UPDATE immunizations SET reminder_sent = 1 WHERE id = ?");
        $update->execute([$imm['id']]);
        $queued_count++;
    } catch (Exception $e) {}
}

// ==========================================
// 3. SAFE CHUNKED FLUSHING
// ==========================================
$total_sent = 0;
$chunk_size = 20;   
$max_time = 20;     
$start_time = time();

while (true) {
    // Stop if taking too long
    if ((time() - $start_time) >= $max_time) {
        break; 
    }

    // Stop if no messages left
    $stmt = $db->query("SELECT COUNT(*) FROM notifications_log WHERE status = 'queued'");
    if ($stmt->fetchColumn() == 0) {
        break; 
    }

    // Send chunk
    $sent_now = ma_life_flush_queue($db, $chunk_size);
    $total_sent += $sent_now;

    // If API fails, stop looping
    if ($sent_now == 0) {
        break;
    }
}

echo "<h3>Cron Job Complete</h3>";
echo "<p><strong>New messages queued:</strong> $queued_count</p>";
echo "<p><strong>Messages sent this run:</strong> $total_sent</p>";
?>