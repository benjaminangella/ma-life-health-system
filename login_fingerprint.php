<?php
// login_fingerprint.php
require_once 'config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['user_role']));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Login - MA-LIFE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container { min-height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(135deg, #0A4C54, #146b76); padding: 20px; }
        .auth-card { background: white; padding: 3rem; border-radius: 20px; width: 100%; max-width: 450px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .fingerprint-icon { font-size: 5rem; color: #0A4C54; margin-bottom: 20px; }
        h1 { color: #0A4C54; font-size: 1.8rem; margin-bottom: 10px; }
        p { color: #666; margin-bottom: 25px; }
        .input-group { margin-bottom: 1.5rem; text-align: left; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #333; }
        .input-group input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; }
        .input-group input:focus { border-color: #0A4C54; outline: none; }
        .btn-fingerprint { width: 100%; padding: 15px; background: #0A4C54; color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; margin-top: 10px; }
        .btn-fingerprint:hover { background: #146b76; transform: translateY(-2px); }
        .btn-fingerprint:disabled { background: #ccc; cursor: not-allowed; }
        .status { margin-top: 20px; padding: 15px; border-radius: 8px; display: none; }
        .status.success { background: #e8f5e9; color: #2e7d32; display: block; }
        .status.error { background: #ffebee; color: #c62828; display: block; }
        .status.info { background: #e3f2fd; color: #0d47a1; display: block; }
        .back-link { display: block; margin-top: 20px; color: #0A4C54; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="fingerprint-icon"><i class="fas fa-fingerprint"></i></div>
            <h1>Fingerprint Login</h1>
            <p>Enter your email, then scan your fingerprint to log in.</p>

            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" placeholder="Enter your registered email" required>
            </div>

            <button id="loginBtn" class="btn-fingerprint">
                <i class="fas fa-fingerprint"></i> Login with Fingerprint
            </button>

            <div id="status" class="status"></div>

            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Password Login</a>
        </div>
    </div>

    <script>
        const statusDiv = document.getElementById('status');
        const loginBtn = document.getElementById('loginBtn');
        const emailInput = document.getElementById('email');

        function showStatus(message, type) {
            statusDiv.className = 'status ' + type;
            statusDiv.innerHTML = message;
        }

        function base64urlToUint8Array(base64url) {
            const padding = '='.repeat((4 - base64url.length % 4) % 4);
            const base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function uint8ArrayToBase64url(uint8Array) {
            let binary = '';
            const len = uint8Array.byteLength;
            for (let i = 0; i < len; i++) {
                binary += String.fromCharCode(uint8Array[i]);
            }
            return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        loginBtn.addEventListener('click', async () => {
            const email = emailInput.value.trim();
            if (!email) {
                showStatus('Please enter your email address.', 'error');
                return;
            }

            loginBtn.disabled = true;
            showStatus('Contacting server...', 'info');

            try {
                // 1. Get challenge from server
                const response = await fetch('webauthn_login.php?action=challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email)
                });
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.error || 'Server error');
                }
                const options = await response.json();

                // 2. Prepare options for the browser
                options.challenge = base64urlToUint8Array(options.challenge);
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: base64urlToUint8Array(cred.id)
                }));

                showStatus('Please scan your fingerprint when prompted...', 'info');

                // 3. Ask the browser/OS to get the credential
                const assertion = await navigator.credentials.get({
                    publicKey: options
                });

                // 4. Send the assertion back to the server
                const assertionJSON = {
                    id: assertion.id,
                    rawId: uint8ArrayToBase64url(new Uint8Array(assertion.rawId)),
                    type: assertion.type,
                    response: {
                        clientDataJSON: uint8ArrayToBase64url(new Uint8Array(assertion.response.clientDataJSON)),
                        authenticatorData: uint8ArrayToBase64url(new Uint8Array(assertion.response.authenticatorData)),
                        signature: uint8ArrayToBase64url(new Uint8Array(assertion.response.signature)),
                        userHandle: assertion.response.userHandle ? uint8ArrayToBase64url(new Uint8Array(assertion.response.userHandle)) : null
                    }
                };

                const verifyResponse = await fetch('webauthn_login.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(assertionJSON)
                });

                const result = await verifyResponse.json();
                if (result.success) {
                    showStatus('<i class="fas fa-check-circle"></i> Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                } else {
                    throw new Error(result.error || 'Login failed');
                }

            } catch (err) {
                console.error(err);
                showStatus('<i class="fas fa-exclamation-circle"></i> ' + err.message, 'error');
                loginBtn.disabled = false;
            }
        });
    </script>
</body>
</html>