<?php
// sms_queue.php - Offline-safe SMS queue

require_once 'sms_helper.php';

/**
 * Check if we have internet connectivity to Africa's Talking
 */
function ma_life_has_internet() {
    $cache = sys_get_temp_dir() . '/malife_net.txt';
    if (file_exists($cache) && (time() - filemtime($cache)) < 60) {
        return trim(file_get_contents($cache)) === '1';
    }

    $online = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.africastalking.com');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $online = (curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0);
        curl_close($ch);
    } else {
        $fp = @fsockopen('www.google.com', 80, $errno, $errstr, 3);
        if ($fp) { $online = true; fclose($fp); }
    }

    @file_put_contents($cache, $online ? '1' : '0');
    return $online;
}

/**
 * Queue an SMS — try to send immediately if online, otherwise queue
 */
function ma_life_queue_sms($db, $phone, $message, $type = 'general', $user_id = 0) {
    if (empty($phone)) return false;
    $phone = ma_life_normalize_phone($phone);

    $sent = false;
    if (ma_life_has_internet() && function_exists('curl_init')) {
        $sent = ma_life_send_sms($phone, $message);
    }

    $status = $sent ? 'sent' : 'queued';
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications_log
            (user_id, phone, notification_type, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array($user_id, $phone, $type, $message, $status));
    } catch (Exception $e) {}

    @file_put_contents(__DIR__ . '/sms_queue_log.txt',
        date('Y-m-d H:i:s') . " | $status | To: $phone | Type: $type | Msg: " . substr($message, 0, 80) . "\n",
        FILE_APPEND);

    return $sent;
}

/**
 * Flush queued messages to Africa's Talking
 */
function ma_life_flush_queue($db, $limit = 50) {
    if (!ma_life_has_internet() || !function_exists('curl_init')) return 0;

    try {
        $stmt = $db->prepare("SELECT * FROM notifications_log WHERE status = 'queued' ORDER BY created_at ASC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return 0; }

    $n = 0;
    foreach ($rows as $row) {
        // Expire messages older than 7 days
        if ((time() - strtotime($row['created_at'])) / 86400 > 7) {
            $db->prepare("UPDATE notifications_log SET status='failed', error_message='Expired' WHERE id=?")
               ->execute(array($row['id']));
            continue;
        }

        if (ma_life_send_sms($row['phone'], $row['message'])) {
            $db->prepare("UPDATE notifications_log SET status='sent' WHERE id=?")
               ->execute(array($row['id']));
            $n++;
        } else {
            // Record the failure but keep processing the rest
            $db->prepare("UPDATE notifications_log SET status='failed', error_message='Send failed' WHERE id=?")
               ->execute(array($row['id']));
        }
    }
    return $n;
}

/**
 * Get queue statistics
 */
function ma_life_queue_stats($db) {
    try {
        $stmt = $db->query("
            SELECT
                SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed
            FROM notifications_log
        ");
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return array(
            'queued' => (int)$r['queued'],
            'sent'   => (int)$r['sent'],
            'failed' => (int)$r['failed']
        );
    } catch (Exception $e) {
        return array('queued' => 0, 'sent' => 0, 'failed' => 0);
    }
}
?>