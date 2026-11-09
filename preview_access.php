<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$user = getenv('PREVIEW_USER') ?: '';
$passwordHash = getenv('PREVIEW_PASSWORD_HASH') ?: '';
$key = getenv('PREVIEW_SIGNING_KEY') ?: '';
if ($user === '' || $passwordHash === '' || strlen($key) < 32) {
    http_response_code(503);
    exit('Preview access is temporarily unavailable.');
}

$cookie = $_COOKIE['malife_preview'] ?? '';
[$expires, $signature] = array_pad(explode('.', is_string($cookie) ? $cookie : '', 2), 2, '');
$allowed = ctype_digit($expires) && (int) $expires >= time()
    && hash_equals(hash_hmac('sha256', $expires, $key), $signature);

if (isset($_GET['check'])) {
    http_response_code($allowed ? 204 : 403);
    exit;
}

if ($allowed) {
    header('Location: /login.php', true, 303);
    exit;
}

$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (is_string($name) && is_string($password) && strlen($password) <= 72
        && password_verify($password, $passwordHash) && hash_equals($user, $name)) {
        $expires = (string) (time() + 8 * 60 * 60);
        $signature = hash_hmac('sha256', $expires, $key);
        setcookie('malife_preview', $expires . '.' . $signature, [
            'expires' => (int) $expires, 'path' => '/', 'secure' => true,
            'httponly' => true, 'samesite' => 'Strict',
        ]);
        header('Location: /login.php', true, 303);
        exit;
    }
    $error = true;
    http_response_code(422);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview access | MA-LIFE</title>
    <link rel="stylesheet" href="/interface.css">
    <script src="/interface.js" defer></script>
</head>
<body class="ml-auth">
<main class="ml-auth-layout">
    <div class="ml-auth-shell">
        <aside class="ml-auth-brand">
            <div>
                <img src="/img/logo.png" alt="">
                <strong>MA-LIFE</strong>
                <p>Maternal and Child Health System</p>
            </div>
            <p>Luzira, Kampala</p>
        </aside>
        <section class="ml-auth-form" aria-labelledby="preview-title">
            <h1 id="preview-title">Open the MA-LIFE preview</h1>
            <p>Enter your preview credentials to continue to the MA-LIFE sign-in page.</p>
            <?php if ($error): ?>
                <p class="ml-error" role="alert">The preview username or password is incorrect.</p>
            <?php endif; ?>
            <form method="post" action="/preview_access.php">
                <div>
                    <label for="preview-user">Preview username</label>
                    <input id="preview-user" name="username" autocomplete="username" required>
                </div>
                <div>
                    <label for="preview-password">Preview password</label>
                    <div class="ml-password">
                        <input type="password" id="preview-password" name="password" autocomplete="current-password" required>
                        <button type="button" class="ml-button" data-password-toggle aria-controls="preview-password" aria-pressed="false" hidden>Show</button>
                    </div>
                </div>
                <button type="submit" class="ml-button ml-button-primary">Continue</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
