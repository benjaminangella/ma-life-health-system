<?php
// sms_helper.php - SMS dispatcher for Africa's Talking
// MODE: Set to 'sandbox' for testing, 'production' for live SMS

/**
 * Normalize Ugandan phone number to +256XXXXXXXXX format
 */
function ma_life_normalize_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    if (substr($phone, 0, 1) === '0') {
        $phone = '+256' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) === '256') {
        $phone = '+' . $phone;
    } elseif (strlen($phone) === 9) {
        $phone = '+256' . $phone;
    }
    return $phone;
}

/**
 * Send an SMS via Africa's Talking
 *
 * @param string $phone   Recipient phone number
 * @param string $message Message body
 * @return bool           True on success, false on failure
 */
function ma_life_send_sms($phone, $message) {
    $phone = ma_life_normalize_phone($phone);
    if (strlen($phone) < 10) return false;

    // ============================================================
    // CONFIGURATION — EDIT THESE VALUES
    // ============================================================
    // Set to 'sandbox' for testing (simulator only, no real SMS)
    // Set to 'production' for live SMS to real phones
    $mode = getenv('SMS_MODE') ?: ''; 

    // Sandbox credentials (only used if $mode = 'sandbox')
    $sandbox_username = getenv('SMS_SANDBOX_USERNAME') ?: ''; 
    $sandbox_api_key  = getenv('SMS_SANDBOX_API_KEY') ?: ''; 

    // Production credentials (only used if $mode = 'production')
    $prod_username = getenv('SMS_PROD_USERNAME') ?: ''; 
    $prod_api_key  = getenv('SMS_PROD_API_KEY') ?: ''; 

    // Sender ID — leave empty to use default shortcode
    // NOTE: Alphanumeric Sender IDs require approval in Uganda.
    // Until approved, set this to '' (empty string).
    $from = '';  // e.g. 'MA-LIFE' once approved
    // ============================================================

    // Pick credentials based on mode
    if ($mode === 'sandbox') {
        $username  = $sandbox_username;
        $api_key   = $sandbox_api_key;
        $api_url   = 'https://api.sandbox.africastalking.com/version1/messaging';
    } else {
        $username  = $prod_username;
        $api_key   = $prod_api_key;
        $api_url   = 'https://api.africastalking.com/version1/messaging';
    }

    // Guard: not configured
    if (empty($username) || empty($api_key) || strpos($api_key, 'YOUR_') === 0 || strpos($api_key, 'PASTE_') === 0) {
        @file_put_contents(
            __DIR__ . '/sms_log.txt',
            date('Y-m-d H:i:s') . " | [NOT CONFIGURED] Mode: $mode | To: $phone | Msg: $message\n",
            FILE_APPEND
        );
        return false;
    }

    // Build POST fields
    $post_fields = array(
        'username' => $username,
        'to'       => $phone,
        'message'  => $message,
    );
    if (!empty($from)) {
        $post_fields['from'] = $from;
    }

    // Build the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apiKey: ' . $api_key,
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Execute
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    // Parse the response to check for API-level success
    $api_ok = false;
    if ($http_code == 200 || $http_code == 201) {
        $decoded = json_decode($response, true);
        if (isset($decoded['SMSMessageData']['Recipients'][0]['status'])) {
            $status = strtolower($decoded['SMSMessageData']['Recipients'][0]['status']);
            $api_ok = ($status === 'success');
        } else {
            // No recipient info; treat HTTP 200/201 as ok
            $api_ok = true;
        }
    }

    // Log everything
    @file_put_contents(
        __DIR__ . '/sms_log.txt',
        date('Y-m-d H:i:s') . " | Mode: $mode | To: $phone | HTTP: $http_code | OK: " . ($api_ok ? '1' : '0') .
        " | Err: $curl_err | Resp: " . substr($response, 0, 500) . "\n",
        FILE_APPEND
    );

    return $api_ok;
}

/**
 * Log a notification record to the database
 */
function ma_life_log_notification($db, $user_id, $phone, $type, $message, $status = 'sent') {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications_log
            (user_id, phone, notification_type, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array($user_id, $phone, $type, $message, $status));
    } catch (Exception $e) {
        error_log('MA-LIFE notify log error: ' . $e->getMessage());
    }
}
?>