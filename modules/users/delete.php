<?php
/**
 * Delete User
 * حذف مستخدم
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { redirect('index.php'); }

$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) { setError('المستخدم غير موجود'); redirect('index.php'); }

// Prevent deleting yourself
if ($userId == getCurrentUserId()) {
    setError('لا يمكنك حذف حسابك الخاص');
    redirect('index.php');
}

// Prevent deleting last admin
if ($user['role'] === 'admin') {
    $adminCount = getRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
    if ($adminCount['cnt'] <= 1) {
        setError('لا يمكن حذف آخر مدير في النظام');
        redirect('index.php');
    }
}

// Delete user
execute("DELETE FROM users WHERE id = ?", [$userId]);

logActivity('حذف مستخدم', "تم حذف المستخدم: {$user['full_name']} ({$user['username']})", getCurrentUserName());
setSuccess("تم حذف المستخدم \"{$user['full_name']}\" بنجاح");
redirect('index.php');
