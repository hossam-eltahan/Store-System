<?php
/**
 * Toggle User Active Status
 * تفعيل/تعطيل مستخدم
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { redirect('index.php'); }

$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) { setError('المستخدم غير موجود'); redirect('index.php'); }

// Prevent deactivating yourself
if ($userId == getCurrentUserId()) {
    setError('لا يمكنك تعطيل حسابك الخاص');
    redirect('index.php');
}

// Prevent deactivating last admin
if ($user['role'] === 'admin' && $user['is_active']) {
    $activeAdminCount = getRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND is_active = 1 AND id != ?", [$userId]);
    if ($activeAdminCount['cnt'] == 0) {
        setError('لا يمكن تعطيل آخر مدير نشط في النظام');
        redirect('index.php');
    }
}

$newStatus = $user['is_active'] ? 0 : 1;
execute("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $userId]);

// If deactivating, invalidate their sessions
if (!$newStatus) {
    execute("UPDATE user_sessions SET is_active = 0, logout_at = NOW() WHERE user_id = ? AND is_active = 1", [$userId]);
}

$action = $newStatus ? 'تفعيل' : 'تعطيل';
logActivity("$action مستخدم", "تم $action المستخدم: {$user['full_name']}", getCurrentUserName());
setSuccess("تم $action المستخدم \"{$user['full_name']}\" بنجاح");
redirect('index.php');
