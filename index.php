<?php
// index.php - Main Landing Page with System Access
require_once 'config.php';

if(isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: " . BASE_URL . getDashboardUrl($role));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MA-LIFE · Maternal & Child Health System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary-teal: #0A4C54;
        --secondary-teal: #146b76;
        --accent-coral: #E8725A;
        --bg-light: #F5F1EB;
        --text-dark: #1a1a1a;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg-light); }
    .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 4rem; background: white; box-shadow: 0 2px 20px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 100; }
    .navbar .brand { display: flex; align-items: center; gap: 12px; font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--primary-teal); text-decoration: none; }
    .navbar .brand i { font-size: 2rem; color: var(--accent-coral); }
    .nav-links { display: flex; gap: 30px; align-items: center; }
    .nav-links a { text-decoration: none; color: #555; font-weight: 500; transition: 0.3s; }
    .nav-links a:hover { color: var(--primary-teal); }
    .hero { display: flex; align-items: center; padding: 4rem 4rem 3rem; background: linear-gradient(135deg, #eaf4f5 0%, #f5f1eb 100%); min-height: 80vh; gap: 3rem; }
    .hero-content { flex: 1; }
    .hero-content .tag { display: inline-block; background: var(--primary-teal); color: white; padding: 4px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; letter-spacing: 1px; margin-bottom: 1rem; }
    .hero-content h1 { font-family: 'Poppins', sans-serif; font-size: 3.5rem; color: var(--primary-teal); line-height: 1.2; margin-bottom: 1.2rem; }
    .hero-content h1 span { color: var(--accent-coral); }
    .hero-content p { font-size: 1.1rem; color: #555; max-width: 500px; margin-bottom: 2rem; line-height: 1.6; }
    .system-access { background: white; border-radius: 16px; padding: 30px 35px; margin-bottom: 25px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-left: 4px solid var(--accent-coral); }
    .system-access h3 { color: var(--primary-teal); font-family: 'Poppins', sans-serif; margin-bottom: 5px; font-size: 1.2rem; }
    .system-access h3 i { color: var(--accent-coral); }
    .system-access p { color: #666; font-size: 0.9rem; margin-bottom: 18px; }
    .system-access .btn-group { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .system-access .btn-access { display: inline-block; padding: 12px 30px; background: var(--primary-teal); color: white; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; }
    .system-access .btn-access:hover { background: var(--secondary-teal); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(10, 76, 84, 0.3); }
    .system-access .btn-access i { margin-right: 8px; }
    .system-access .btn-signup { display: inline-block; padding: 12px 30px; background: var(--accent-coral); color: white; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; }
    .system-access .btn-signup:hover { background: #d65a43; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(232, 114, 90, 0.3); }
    .system-access .btn-signup i { margin-right: 8px; }
    .system-access .signup-text { color: #999; font-size: 0.85rem; margin-left: 5px; }
    .system-access .signup-text a { color: var(--primary-teal); font-weight: 600; text-decoration: none; }
    .system-access .signup-text a:hover { text-decoration: underline; }
    .hero-image { flex: 1; text-align: center; }
    .hero-image .image-placeholder { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
    .hero-image .image-placeholder i { font-size: 8rem; color: var(--primary-teal); display: block; margin-bottom: 1rem; }
    .hero-image .image-placeholder p { color: #666; font-size: 0.9rem; }
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; padding: 0 4rem 3rem; background: linear-gradient(135deg, #eaf4f5 0%, #f5f1eb 100%); }
    .stat-item { text-align: center; padding: 1.5rem; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .stat-item .number { font-size: 2.5rem; font-weight: 700; color: var(--primary-teal); }
    .stat-item .label { color: #666; font-size: 0.9rem; margin-top: 5px; }
    .features { padding: 4rem; background: white; text-align: center; }
    .features h2 { font-family: 'Poppins', sans-serif; font-size: 2.5rem; color: var(--primary-teal); margin-bottom: 0.5rem; }
    .features .subtitle { color: #666; margin-bottom: 2.5rem; }
    .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; max-width: 1100px; margin: 0 auto; }
    .feature-card { background: var(--bg-light); padding: 2rem 1.5rem; border-radius: 16px; transition: 0.3s; text-align: center; }
    .feature-card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); }
    .feature-card i { font-size: 2.5rem; color: var(--primary-teal); background: white; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .feature-card h3 { font-family: 'Poppins', sans-serif; color: var(--primary-teal); margin-bottom: 0.5rem; font-size: 1.1rem; }
    .feature-card p { color: #666; font-size: 0.9rem; line-height: 1.5; }
    .footer { background: var(--primary-teal); color: white; padding: 2rem 4rem; text-align: center; }
    .footer .links { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1rem; }
    .footer .links a { color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; }
    .footer .links a:hover { color: white; }
    .footer p { opacity: 0.7; font-size: 0.9rem; }
    @media (max-width: 768px) {
        .navbar { padding: 1rem; flex-wrap: wrap; gap: 1rem; }
        .nav-links { flex-wrap: wrap; justify-content: center; gap: 15px; }
        .hero { flex-direction: column; padding: 2rem 1.5rem; text-align: center; }
        .hero-content h1 { font-size: 2.2rem; }
        .hero-content p { margin: 0 auto 1.5rem; }
        .system-access { margin: 0 auto 20px; text-align: center; }
        .system-access .btn-group { justify-content: center; }
        .stats { grid-template-columns: repeat(2, 1fr); padding: 1.5rem; }
        .features { padding: 2rem 1.5rem; }
        .features h2 { font-size: 1.8rem; }
        .footer { padding: 1.5rem; }
        .footer .links { flex-wrap: wrap; gap: 1rem; }
    }
    @media (max-width: 550px) {
        .system-access { padding: 20px; }
        .system-access .btn-group { flex-direction: column; }
        .system-access .btn-access, 
        .system-access .btn-signup { width: 100%; text-align: center; justify-content: center; }
        .system-access .signup-text { display: block; margin-top: 10px; }
    }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="brand">
            <i class="fas fa-heartbeat"></i> MA-LIFE
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <a href="login.php" style="padding: 10px 24px; background: var(--primary-teal); color: white; border-radius: 8px; font-weight: 600; transition: 0.3s;">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <span class="tag">🏥 Luzira Division</span>
            <h1>Ensuring Safe Motherhood &<br><span>Healthy Childhood</span></h1>
            <p>Through innovative digital health solutions for maternal and child healthcare in Luzira Division, Kampala.</p>
            
            <div class="system-access">
                <h3><i class="fas fa-heartbeat"></i> Access MA-LIFE System</h3>
                <p>Login to your dashboard to manage maternal and child health records.</p>
                
                <div class="btn-group">
                    <a href="login.php" class="btn-access">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="signup.php" class="btn-signup">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </a>
                    <span class="signup-text">
                        New user? <a href="signup.php">Sign up</a>
                    </span>
                </div>
            </div>
        </div>
        <div class="hero-image">
            <div class="image-placeholder">
                <i class="fas fa-heartbeat"></i>
                <p style="font-weight:600; color: var(--primary-teal);">MA-LIFE Health System</p>
                <p>Maternal & Child Health Electronic System</p>
                <div style="margin-top:15px; padding-top:15px; border-top:1px solid #eee;">
                    <a href="login.php" style="color:var(--primary-teal); font-weight:600;">
                        <i class="fas fa-arrow-right"></i> Click to Access System
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="stats">
        <div class="stat-item">
            <div class="number">15K+</div>
            <div class="label">Mothers Registered</div>
        </div>
        <div class="stat-item">
            <div class="number">22K+</div>
            <div class="label">Children Enrolled</div>
        </div>
        <div class="stat-item">
            <div class="number">50+</div>
            <div class="label">Health Workers</div>
        </div>
        <div class="stat-item">
            <div class="number">98%</div>
            <div class="label">Patient Satisfaction</div>
        </div>
    </section>

    <section class="features" id="features">
        <h2>Why Choose MA-LIFE?</h2>
        <p class="subtitle">Comprehensive digital health solutions for maternal and child care</p>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-database"></i>
                <h3>Electronic Records</h3>
                <p>Secure digital records for mothers and children accessible anytime, anywhere.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Appointment Management</h3>
                <p>Automated scheduling and reminders for antenatal care and immunizations.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Health Monitoring</h3>
                <p>Track growth, immunization schedules, and maternal health indicators in real-time.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Data Security</h3>
                <p>End-to-end encryption and role-based access to protect sensitive health information.</p>
            </div>
        </div>
    </section>

    <footer class="footer" id="about">
        <div class="links">
            <a href="#">About</a>
            <a href="#">Services</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Use</a>
            <a href="#">Contact</a>
            <a href="login.php" style="color:rgba(255,255,255,0.9); font-weight:600;">
                <i class="fas fa-sign-in-alt"></i> System Login
            </a>
        </div>
        <p>© 2026 MA-LIFE Health System · Maternal and Child Care Platform · Luzira Division, Kampala</p>
    </footer>

</body>
</html>