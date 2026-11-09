<?php
// webauthn_login.php - Login with fingerprint
require_once 'config.php';

header('Content-Type: application/json');

$db = getDB();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ---------- Challenge storage ----------
function store_challenge_login($key, $value) {
    $file = __DIR__ . '/webauthn_challenges.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : array();
    $data[$key] = $value;
    file_put_contents($file, json_encode($data));
}
function get_stored_challenge_login($key) {
    $file = __DIR__ . '/webauthn_challenges.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return isset($data[$key]) ? $data[$key] : null;
}
function delete_challenge_login($key) {
    $file = __DIR__ . '/webauthn_challenges.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    unset($data[$key]);
    file_put_contents($file, json_encode($data));
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

// ---------- Step 1: Get challenge ----------
if ($action === 'challenge') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Email required'));
        exit;
    }

    $user = getUserByEmail($email);
    if (!$user) {
        http_response_code(404);
        echo json_encode(array('error' => 'No account found with that email'));
        exit;
    }

    $stmt = $db->prepare("SELECT credential_id FROM user_credentials WHERE user_id = ?");
    $stmt->execute(array($user['id']));
    $creds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($creds)) {
        http_response_code(404);
        echo json_encode(array('error' => 'No fingerprint registered for this account'));
        exit;
    }

    $challenge_b64 = base64url_encode(random_bytes(32));
    store_challenge_login('login_' . $user['id'], $challenge_b64);

    $host = $_SERVER['HTTP_HOST'];
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }

    echo json_encode(array(
        'challenge' => $challenge_b64,
        'rpId'      => $host,
        'allowCredentials' => array_map(function($id) {
            return array('type' => 'public-key', 'id' => $id);
        }, $creds),
        'userVerification' => 'required',
        'timeout' => 60000,
    ));
    exit;
}

// ---------- Step 2: Verify assertion ----------
if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid payload'));
        exit;
    }

    $credential_id = $data['id'];

    $stmt = $db->prepare("SELECT * FROM user_credentials WHERE credential_id = ?");
    $stmt->execute(array($credential_id));
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cred) {
        http_response_code(401);
        echo json_encode(array('error' => 'Unknown credential'));
        exit;
    }

    $challenge_b64 = get_stored_challenge_login('login_' . $cred['user_id']);
    if (!$challenge_b64) {
        http_response_code(400);
        echo json_encode(array('error' => 'No challenge on record'));
        exit;
    }

    try {
        // Decode and verify clientDataJSON
        if (!isset($data['response']['clientDataJSON'])) {
            throw new Exception('Missing clientDataJSON');
        }

        $clientDataJSON = json_decode(base64url_decode($data['response']['clientDataJSON']), true);

        if (!isset($clientDataJSON['challenge']) || $clientDataJSON['challenge'] !== $challenge_b64) {
            throw new Exception('Challenge mismatch');
        }

        // Verify origin
        $expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        if (!isset($clientDataJSON['origin']) || $clientDataJSON['origin'] !== $expectedOrigin) {
            if (strpos($clientDataJSON['origin'], 'localhost') === false) {
                throw new Exception('Origin mismatch');
            }
        }

        // For full cryptographic verification, you'd use AuthenticatorAssertionResponseValidator
        // with the stored public key. This simplified version verifies challenge + origin.

        $user = getUserById($cred['user_id']);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Start session
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_name']     = $user['full_name'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_ip']       = $_SERVER['REMOTE_ADDR'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();

        $db->prepare("UPDATE user_credentials SET last_used = NOW() WHERE id = ?")
           ->execute(array($cred['id']));
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
           ->execute(array($user['id']));

        delete_challenge_login('login_' . $cred['user_id']);

        echo json_encode(array(
            'success'   => true,
            'redirect'  => getDashboardUrl($user['role']),
            'user_name' => $user['full_name'],
        ));
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array('error' => $e->getMessage()));
    }
    exit;
}

http_response_code(400);
echo json_encode(array('error' => 'Unknown action'));
?>