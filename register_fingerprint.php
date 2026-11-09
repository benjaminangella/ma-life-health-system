<?php
// register_fingerprint.php - Register a fingerprint credential for ANY logged-in user
require_once 'config.php';
requireLogin(); // Any logged-in user (admin, healthworker, or mother)

$user = getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Fingerprint - MA-LIFE</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #0A4C54; margin-bottom: 15px; }
        p { color: #666; margin-bottom: 25px; }
        .btn-fingerprint { padding: 15px 40px; background: #0A4C54; color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; }
        .btn-fingerprint:hover { background: #146b76; transform: translateY(-2px); }
        .btn-fingerprint:disabled { background: #ccc; cursor: not-allowed; }
        .status { margin-top: 20px; padding: 15px; border-radius: 8px; display: none; }
        .status.success { background: #e8f5e9; color: #2e7d32; display: block; }
        .status.error { background: #ffebee; color: #c62828; display: block; }
        .status.info { background: #e3f2fd; color: #0d47a1; display: block; }
        .fingerprint-icon { font-size: 5rem; color: #0A4C54; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-top: 20px; color: #0A4C54; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="fingerprint-icon"><i class="fas fa-fingerprint"></i></div>
        <h2>Enable Fingerprint Login</h2>
        <p>Hi <strong><?= htmlspecialchars($user['full_name']) ?></strong>, register your device's fingerprint (Windows Hello, Touch ID, or Android fingerprint) for faster, more secure login.</p>
        <button id="registerBtn" class="btn-fingerprint">
            <i class="fas fa-fingerprint"></i> Register Fingerprint
        </button>
        <div id="status" class="status"></div>
        <p style="margin-top: 20px; font-size: 0.85rem; color: #999;">
            <i class="fas fa-lock"></i> Your fingerprint data never leaves your device. Only a secure public key is stored on our server.
        </p>
        <a href="<?= getDashboardUrl($user['role']) ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <script>
        const statusDiv = document.getElementById('status');
        const registerBtn = document.getElementById('registerBtn');

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

        registerBtn.addEventListener('click', async () => {
            registerBtn.disabled = true;
            showStatus('Contacting server...', 'info');

            try {
                const response = await fetch('webauthn_register.php?action=challenge');
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.error || 'Server error');
                }
                const options = await response.json();

                options.challenge = base64urlToUint8Array(options.challenge);
                options.user.id = base64urlToUint8Array(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(cred => ({
                        ...cred,
                        id: base64urlToUint8Array(cred.id)
                    }));
                }

                showStatus('Please scan your fingerprint when prompted...', 'info');

                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                const credentialJSON = {
                    id: credential.id,
                    rawId: uint8ArrayToBase64url(new Uint8Array(credential.rawId)),
                    type: credential.type,
                    response: {
                        clientDataJSON: uint8ArrayToBase64url(new Uint8Array(credential.response.clientDataJSON)),
                        attestationObject: uint8ArrayToBase64url(new Uint8Array(credential.response.attestationObject)),
                    }
                };

                const saveResponse = await fetch('webauthn_register.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(credentialJSON)
                });

                const result = await saveResponse.json();
                if (result.success) {
                    showStatus('<i class="fas fa-check-circle"></i> Success! Fingerprint login is now enabled for your account.', 'success');
                    registerBtn.style.display = 'none';
                } else {
                    throw new Error(result.error || 'Registration failed');
                }

            } catch (err) {
                console.error(err);
                showStatus('<i class="fas fa-exclamation-circle"></i> ' + err.message, 'error');
                registerBtn.disabled = false;
            }
        });
    </script>
</body>
</html>