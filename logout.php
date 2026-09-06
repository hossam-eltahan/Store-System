<?php
/**
 * Logout Handler
 * تسجيل الخروج
 */

require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'config/auth.php';

if (isLoggedIn()) {
    logActivity('تسجيل خروج', 'تسجيل خروج من النظام', getCurrentUserName());
    performLogout();
}

redirect(BASE_URL . 'login.php');
