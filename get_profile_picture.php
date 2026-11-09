<?php
// get_profile_picture.php - Returns HTML for the profile picture widget
// Usage: include this file inside a sidebar or topbar
// Requires: $user or $_SESSION['user_id'] to be set

if (!isset($user)) {
    $user = getUserById($_SESSION['user_id']);
}

$profile_pic = isset($user['profile_picture']) ? $user['profile_picture'] : '';
$profile_pic_path = ($profile_pic && file_exists(__DIR__ . '/uploads/profiles/' . $profile_pic))
    ? 'uploads/profiles/' . $profile_pic
    : '';
$initial = strtoupper(substr($user['full_name'], 0, 1));
?>
<div class="profile-picture-widget" style="position: relative; display: inline-block;">
    <div class="profile-picture-circle" id="profilePictureCircle" style="
        width: 100px; height: 100px; border-radius: 50%;
        background: #fff; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; font-weight: 700; color: #0A4C54;
        border: 4px solid rgba(255,255,255,0.8);
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        cursor: pointer;
    ">
        <?php if ($profile_pic_path): ?>
            <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover;">
        <?php else: ?>
            <?php echo htmlspecialchars($initial); ?>
        <?php endif; ?>
    </div>
    <label for="profilePictureInput" class="camera-btn" title="Change profile picture" style="
        position: absolute; bottom: 0; right: 0;
        width: 32px; height: 32px; border-radius: 50%;
        background: #0A4C54; color: #fff;
        border: 3px solid #fff;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 0.8rem;
        transition: 0.3s;
    ">
        <i class="fas fa-camera"></i>
    </label>
    <form id="profilePictureForm" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*">
    </form>
</div>

<script>
(function() {
    var input = document.getElementById('profilePictureInput');
    var circle = document.getElementById('profilePictureCircle');
    
    if (input) {
        input.addEventListener('change', function() {
            if (!this.files || !this.files.length) return;
            
            var formData = new FormData();
            formData.append('profile_picture', this.files[0]);
            
            // Show loading state
            circle.style.opacity = '0.5';
            
            fetch('upload_profile_picture.php', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                circle.style.opacity = '1';
                if (data.success) {
                    // Replace contents with new image
                    circle.innerHTML = '<img src="' + data.url + '?t=' + Date.now() + '" style="width:100%; height:100%; object-fit:cover;" alt="Profile">';
                } else {
                    alert('Upload failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function(err) {
                circle.style.opacity = '1';
                alert('Upload failed: ' + err.message);
            });
        });
    }
})();
</script>