<?php
/**
 * System Activation & Subscription Renewal Page
 * صفحة تفعيل وترخيص النظام
 */

require_once __DIR__ . '/config/license.php';

$storeName = 'نظام إدارة المحل والأجهزة المنزلية';
$hwid = getMachineFingerprint();
$licenseInfo = getLicenseDetails();

$reason = $_GET['reason'] ?? ($licenseInfo['status'] !== 'valid' ? $licenseInfo['status'] : '');
$message = '';
$messageType = '';

// Allow opening activate.php anytime for early renewal or extension

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['activation_code'] ?? '');
    if (empty($code)) {
        $message = 'يرجى إدخال كود التفعيل أولاً.';
        $messageType = 'error';
    } else {
        $actResult = activateLicense($code);
        if ($actResult['success']) {
            $message = $actResult['message'];
            $messageType = 'success';
            $licenseInfo = getLicenseDetails();
            $reason = '';
        } else {
            $message = $actResult['message'];
            $messageType = 'error';
        }
    }
}

// Developer WhatsApp contact info
$devPhone = '201271191616';
$waText = urlencode("مرحباً، أود الحصول على كود تفعيل/تجديد لنظام إدارة المحل.\nبصمة جهازي: " . $hwid);
$waUrl = "https://wa.me/{$devPhone}?text={$waText}";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل النظام - <?php echo htmlspecialchars($storeName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0e1a;
            --bg-card: rgba(18, 26, 47, 0.88);
            --border-card: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --accent-blue-hover: #2563eb;
            --accent-cyan: #06b6d4;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow-glow: 0 0 50px rgba(59, 130, 246, 0.15);
        }

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
            background: var(--bg-primary);
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
            padding: 24px 16px;
        }

        /* Ambient Glow Circles */
        .ambient-glow-1 {
            position: fixed;
            top: -120px;
            right: -100px;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.22) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .ambient-glow-2 {
            position: fixed;
            bottom: -150px;
            left: -120px;
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.18) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 520px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 40px 32px;
            box-shadow: var(--shadow-glow), 0 20px 40px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(18px);
            position: relative;
            z-index: 1;
            animation: fadeIn 0.45s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-section {
            text-align: center;
            margin-bottom: 28px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .badge-expired {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-clock {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-unlicensed {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.35);
        }

        .logo-icon svg {
            width: 34px;
            height: 34px;
            fill: #ffffff;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .alert-box {
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.28);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.28);
            color: #6ee7b7;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.28);
            color: #93c5fd;
        }

        /* Safe reassurance card */
        .reassurance-card {
            background: rgba(16, 185, 129, 0.07);
            border: 1px dashed rgba(16, 185, 129, 0.35);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #a7f3d0;
        }

        /* Hardware ID Box */
        .hwid-container {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .hwid-info {
            text-align: right;
            overflow: hidden;
        }

        .hwid-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .hwid-value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            font-weight: 700;
            color: var(--accent-cyan);
            letter-spacing: 1px;
        }

        .btn-copy {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: #93c5fd;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-copy:hover {
            background: var(--accent-blue);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.7);
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-md);
            color: #ffffff;
            font-family: 'Courier New', Courier, monospace;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.25s;
            text-align: center;
            direction: ltr;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
            background: rgba(15, 23, 42, 0.9);
        }

        .form-input::placeholder {
            color: #475569;
            font-family: 'Cairo', sans-serif;
            font-size: 13px;
            letter-spacing: normal;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: var(--radius-md);
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Support Card */
        .support-card {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
        }

        .support-text {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(37, 211, 102, 0.12);
            border: 1px solid rgba(37, 211, 102, 0.3);
            color: #4ade80;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-whatsapp:hover {
            background: #25d366;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.35);
            transform: translateY(-1px);
        }

        .btn-continue {
            display: inline-block;
            margin-top: 16px;
            color: var(--accent-blue);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-continue:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="container">
        <div class="header-section">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                </svg>
            </div>

            <?php if ($licenseInfo['status'] === 'valid'): ?>
                <div class="badge-status badge-active">
                    <span>●</span> الترخيص نشط (<?php echo htmlspecialchars($licenseInfo['expires_at']); ?>)
                </div>
                <h1 class="title">الترخيص مفعل بنجاح</h1>
                <p class="subtitle">نظامك مرخص وجاهز للعمل، يمكنك تمديد الاشتراك في أي وقت بإدخال كود جديد.</p>
            <?php elseif ($reason === 'expired'): ?>
                <div class="badge-status badge-expired">
                    <span>⚠️</span> انتهت فترة الاشتراك
                </div>
                <h1 class="title">تجديد الاشتراك</h1>
                <p class="subtitle">انتهت مدة الترخيص السابقة. أدخل كود التجديد للاستمرار فوراً.</p>
            <?php elseif ($reason === 'clock_error'): ?>
                <div class="badge-status badge-clock">
                    <span>⏰</span> تعارض في ساعة الجهاز
                </div>
                <h1 class="title">ضبط التاريخ والوقت</h1>
                <p class="subtitle">تم رصد تراجع في ساعة وتاريخ الكمبيوتر. يرجى ضبط الساعة بالوقت الصحيح ثم إعادة المحاولة.</p>
            <?php else: ?>
                <div class="badge-status badge-unlicensed">
                    <span>🔑</span> مطلوب التفعيل
                </div>
                <h1 class="title">تفعيل النظام</h1>
                <p class="subtitle">أهلاً بك في نظام إدارة المحل! أدخل كود التفعيل الممنوح لك لبدء العمل.</p>
            <?php endif; ?>
        </div>

        <!-- Safe Data Reassurance Alert -->
        <div class="reassurance-card">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <div>
                <strong>بياناتك محفوظة بأمان تام:</strong> فواتيرك، مخازنك وحساباتك محفوظة بالكامل وستعود للعمل فوراً عند التفعيل.
            </div>
        </div>

        <!-- Hardware Fingerprint Display -->
        <div class="hwid-container">
            <div class="hwid-info">
                <div class="hwid-label">بصمة هذا الجهاز (Hardware ID)</div>
                <div class="hwid-value" id="hwidText"><?php echo htmlspecialchars($hwid); ?></div>
            </div>
            <button type="button" class="btn-copy" onclick="copyHwid()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                </svg>
                <span id="copyBtnText">نسخ البصمة</span>
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php if ($messageType === 'success'): ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?php endif; ?>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
            <?php if ($messageType === 'success'): ?>
                <script>
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 2200);
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Activation Form -->
        <form method="POST" action="" id="activationForm">
            <div class="form-group">
                <label class="form-label" for="activation_code">كود التفعيل / التجديد</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="activation_code"
                        name="activation_code" 
                        class="form-input" 
                        placeholder="SS-XXXX-XXXX-XXXX-XXXX"
                        autocomplete="off"
                        autofocus
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
                <span>تفعيل النظام الآن</span>
            </button>
        </form>

        <?php if ($licenseInfo['status'] === 'valid'): ?>
            <div style="text-align: center;">
                <a href="index.php" class="btn-continue">العودة إلى لوحة التحكم ←</a>
            </div>
        <?php endif; ?>

        <!-- Support / WhatsApp Card -->
        <div class="support-card">
            <div class="support-text">للحصول على كود تفعيل أو تجديد الاشتراك، تواصل معنا مباشرة:</div>
            <a href="<?php echo htmlspecialchars($waUrl); ?>" target="_blank" class="btn-whatsapp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.15-.25-.02-.39.11-.51.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.71 4.3 3.8 2.52 1.09 2.52.73 2.98.69.45-.04 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29z"/>
                </svg>
                <span>تواصل عبر واتساب للتفعيل</span>
            </a>
        </div>
    </div>

    <script>
        function copyHwid() {
            const hwid = document.getElementById('hwidText').innerText;
            navigator.clipboard.writeText(hwid).then(() => {
                const btnText = document.getElementById('copyBtnText');
                const originalText = btnText.innerText;
                btnText.innerText = 'تم النسخ بنجاح!';
                setTimeout(() => {
                    btnText.innerText = originalText;
                }, 2000);
            });
        }

        // Automatic dash formatting for the activation code input
        const codeInput = document.getElementById('activation_code');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                let val = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (val.startsWith('SS')) {
                    val = val.substring(2);
                }
                
                let chunks = [];
                for (let i = 0; i < val.length; i += 4) {
                    chunks.push(val.substring(i, i + 4));
                }
                
                if (chunks.length > 0 && chunks[0] !== '') {
                    this.value = 'SS-' + chunks.join('-');
                }
            });
        }
    </script>
</body>
</html>