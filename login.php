<?php
/**
 * Login Page
 * صفحة تسجيل الدخول
 */

require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'config/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        $result = attemptLogin($username, $password);
        if ($result['success']) {
            logActivity('تسجيل دخول', 'تسجيل دخول بنجاح', getCurrentUserName());
            
            // Redirect to original page or dashboard
            $redirectTo = $_POST['redirect'] ?? '';
            if (!empty($redirectTo) && strpos($redirectTo, 'login.php') === false) {
                header("Location: " . $redirectTo);
            } else {
                redirect(BASE_URL . 'index.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$storeName = getSetting('store_name', 'نظام إدارة المحل');
$storeLogo = getSetting('store_logo', '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo $storeName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0e1a;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .bg-animation .gradient-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f36 30%, #0f1628 60%, #0a0e1a 100%);
        }

        /* Floating Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(35px);
            opacity: 0.35;
            animation: floatOrb 15s ease-in-out infinite;
            will-change: transform;
            transform: translateZ(0);
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #3b82f6, transparent);
            top: -10%;
            right: -5%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, #8b5cf6, transparent);
            bottom: -15%;
            left: -5%;
            animation-delay: -5s;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, #06b6d4, transparent);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(15px, 35px) scale(1.05); }
        }

        /* Floating Particles */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: floatParticle linear infinite;
        }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) translateX(100px); opacity: 0; }
        }

        /* Grid Lines */
        .grid-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* Login Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.5),
                0 0 40px rgba(59, 130, 246, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
            will-change: transform, opacity;
        }

        @keyframes cardAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Logo & Header */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            object-fit: cover;
            margin-bottom: 15px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.15);
            animation: logoFloat 3s ease-in-out infinite;
        }

        .login-logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            font-size: 40px;
            margin-bottom: 15px;
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.25);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .login-title {
            font-size: 1.6em;
            font-weight: 800;
            background: linear-gradient(135deg, #e2e8f0, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 0.9em;
            color: #64748b;
            font-weight: 400;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9em;
            font-weight: 600;
            color: #94a3b8;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1em;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 14px 44px 14px 14px;
            background: rgba(30, 41, 59, 0.6);
            border: 1.5px solid rgba(71, 85, 105, 0.4);
            border-radius: 12px;
            font-family: 'Cairo', sans-serif;
            font-size: 1em;
            color: #e2e8f0;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input::placeholder {
            color: #475569;
        }

        .form-input:focus {
            border-color: #3b82f6;
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15), 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .form-input:focus + .input-icon,
        .form-input:focus ~ .input-icon {
            color: #3b82f6;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1.1em;
            padding: 4px;
            transition: color 0.3s;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #3b82f6;
        }

        /* Submit Button */
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 12px;
            color: white;
            font-family: 'Cairo', sans-serif;
            font-size: 1.05em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 5px;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.35);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #fca5a5;
            animation: errorShake 0.4s ease-in-out;
        }

        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(71, 85, 105, 0.2);
        }

        .login-footer p {
            font-size: 0.8em;
            color: #475569;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 35px 25px;
                border-radius: 18px;
            }
            .login-title {
                font-size: 1.3em;
            }
        }

        /* Loading State */
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .login-btn.loading .btn-content {
            opacity: 0;
        }

        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            top: 50%;
            left: 50%;
            margin: -12px 0 0 -12px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="gradient-bg"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="grid-overlay"></div>
        <div class="particles" id="particles"></div>
    </div>

    <!-- Login Card -->
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <?php if ($storeLogo && file_exists('assets/uploads/' . $storeLogo)): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo $storeLogo; ?>" alt="Logo" class="login-logo">
                <?php else: ?>
                    <div class="login-logo-icon">🏪</div>
                <?php endif; ?>
                <h1 class="login-title"><?php echo htmlspecialchars($storeName); ?></h1>
                <p class="login-subtitle">تسجيل الدخول إلى نظام الإدارة</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <span>⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username">اسم المستخدم</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="أدخل اسم المستخدم"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            autocomplete="username"
                            autofocus
                            required
                        >
                        <span class="input-icon">👤</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="أدخل كلمة المرور"
                            autocomplete="current-password"
                            required
                        >
                        <span class="input-icon">🔒</span>
                        <button type="button" class="password-toggle" onclick="togglePassword()" id="toggleBtn">👁️</button>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-content">
                        <span>🔑</span>
                        <span>تسجيل الدخول</span>
                    </span>
                </button>
            </form>

            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($storeName); ?> — نظام الإدارة</p>
            </div>
        </div>
    </div>

    <script>
        // Generate floating particles
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 15 + 10) + 's';
            particle.style.animationDelay = (Math.random() * 10) + 's';
            particle.style.width = (Math.random() * 3 + 1) + 'px';
            particle.style.height = particle.style.width;
            particle.style.opacity = Math.random() * 0.5 + 0.1;
            particlesContainer.appendChild(particle);
        }

        // Password toggle
        function togglePassword() {
            const passInput = document.getElementById('password');
            const toggleBtn = document.getElementById('toggleBtn');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleBtn.textContent = '🔒';
            } else {
                passInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
        });
    </script>
</body>
</html>
