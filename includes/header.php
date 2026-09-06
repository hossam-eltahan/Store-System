<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for all pages that include header
if (!defined('SKIP_AUTH')) {
    require_once __DIR__ . '/../config/auth.php';
    requireLogin();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'نظام إدارة المحل'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/print.css" media="print">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Toast Notification Styles -->
    <style>
    #simple-toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        animation: toastPop 0.3s ease-out;
        max-width: 90%;
    }
    
    #simple-toast.success {
        background: #10b981;
        color: white;
    }
    
    #simple-toast.error {
        background: #dc2626;
        color: white;
    }
    
    #simple-toast .toast-text {
        flex: 1;
    }
    
    #simple-toast .toast-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }
    
    #simple-toast .toast-close:hover {
        background: rgba(255,255,255,0.4);
    }
    
    @keyframes toastPop {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes toastFade {
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
    }
    
    #simple-toast.hiding {
        animation: toastFade 0.3s ease-out forwards;
    }
    
    @media print {
        #simple-toast { display: none !important; }
    }
    </style>
</head>
<body>
    <?php
    // Display success message - stays visible until manually closed
    $success = getSuccess();
    if ($success): ?>
        <div id="simple-toast" class="success">
            <span>✅</span>
            <span class="toast-text"><?php echo $success; ?></span>
            <button class="toast-close" onclick="hideToast()">×</button>
        </div>
        <script>
        function hideToast() {
            var t = document.getElementById('simple-toast');
            if(t) { t.classList.add('hiding'); setTimeout(function(){ t.remove(); }, 300); }
        }
        </script>
    <?php endif; ?>
    
    <?php
    // Display error message - stays visible until manually closed
    $error = getError();
    if ($error): ?>
        <div id="simple-toast" class="error">
            <span>❌</span>
            <span class="toast-text"><?php echo $error; ?></span>
            <button class="toast-close" onclick="hideToast()">×</button>
        </div>
        <script>
        function hideToast() {
            var t = document.getElementById('simple-toast');
            if(t) { t.classList.add('hiding'); setTimeout(function(){ t.remove(); }, 300); }
        }
        </script>
    <?php endif; ?>
