<?php
// webauthn_register.php - Register a fingerprint credential (WITH LIBRARY)
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(array('error' => 'Not logged in'));
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ---------- Challenge storage ----------
function store_challenge($key, $value) {
    $file = __DIR__ . '/webauthn_challenges.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : array();
    $data[$key] = $value;
    file_put_contents($file, json_encode($data));
}
function get_stored_challenge($key) {
    $file = __DIR__ . '/webauthn_challenges.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return isset($data[$key]) ? $data[$key] : null;
}
function delete_challenge($key) {
    $file = __DIR__ . '/webauthn_challenges.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    unset($data[$key]);
    file_put_contents($file, json_encode($data));
}

// ---------- Step 1: Challenge ----------
if ($action === 'challenge') {
    $challenge = random_bytes(32);
    $challenge_b64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');
    store_challenge('reg_' . $user_id, $challenge_b64);

    $stmt = $db->prepare("SELECT credential_id FROM user_credentials WHERE user_id = ?");
    $stmt->execute(array($user_id));
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $exclude = array();
    foreach ($existing as $id) {
        $exclude[] = array('type' => 'public-key', 'id' => $id);
    }

    $host = $_SERVER['HTTP_HOST'];
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }

    echo json_encode(array(
        'challenge' => $challenge_b64,
        'rp' => array(
            'name' => 'MA-LIFE Health System',
            'id'   => $host,
        ),
        'user' => array(
            'id'          => rtrim(strtr(base64_encode('user_' . $user_id), '+/', '-_'), '='),
            'name'        => $user['email'],
            'displayName' => $user['full_name'],
        ),
        'pubKeyCredParams' => array(
            array('type' => 'public-key', 'alg' => -7),
            array('type' => 'public-key', 'alg' => -257),
        ),
        'authenticatorSelection' => array(
            'authenticatorAttachment' => 'platform',
            'userVerification'        => 'required',
        ),
        'timeout'             => 60000,
        'attestation'         => 'none',
        'excludeCredentials'  => $exclude,
    ));
    exit;
}

// ---------- Step 2: Verify ----------
if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['id']) || !isset($data['response'])) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid payload'));
        exit;
    }

    $challenge_b64 = get_stored_challenge('reg_' . $user_id);
    if (!$challenge_b64) {
        http_response_code(400);
        echo json_encode(array('error' => 'No challenge on record. Please try again.'));
        exit;
    }

    try {
        // Verify the challenge in clientDataJSON
        $clientDataRaw = $data['response']['clientDataJSON'];
        // Fix base64url padding
        $padding = 4 - (strlen($clientDataRaw) % 4);
        if ($padding < 4) $clientDataRaw .= str_repeat('=', $padding);
        $clientDataJSON = json_decode(base64_decode(strtr($clientDataRaw, '-_', '+/')), true);

        if (!isset($clientDataJSON['challenge']) || $clientDataJSON['challenge'] !== $challenge_b64) {
            throw new Exception('Challenge mismatch');
        }

        // Verify origin
        $expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        if (isset($clientDataJSON['origin']) && $clientDataJSON['origin'] !== $expectedOrigin) {
            if (strpos($clientDataJSON['origin'], 'localhost') === false) {
                throw new Exception('Origin mismatch: ' . $clientDataJSON['origin']);
            }
        }

        // Store the credential
        $credential_id = $data['id'];
        $public_key = $data['response']['attestationObject'];

        $stmt = $db->prepare("
            INSERT INTO user_credentials (user_id, credential_id, public_key, device_name, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array($user_id, $credential_id, $public_key, 'My Fingerprint'));

        delete_challenge('reg_' . $user_id);

        echo json_encode(array('success' => true, 'message' => 'Fingerprint registered'));
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(array('error' => $e->getMessage()));
    }
    exit;
}

http_response_code(400);
echo json_encode(array('error' => 'Unknown action'));
?>