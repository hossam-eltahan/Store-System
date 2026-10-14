<?php
/**
 * Delete PlayStation Device - حذف جهاز بلايستيشن
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('gaming.devices');

// Get device ID
$deviceId = (int)($_GET['id'] ?? 0);
$device = getRow("SELECT * FROM gaming_devices WHERE id = ?", [$deviceId]);

if (!$device) {
    setError('الجهاز غير موجود');
    redirect('index.php');
}

// Check if device is in use (has active session)
$activeSession = getRow(
    "SELECT gs.*, gd.display_name
     FROM gaming_sessions gs
     JOIN gaming_devices gd ON gs.device_id = gd.id
     WHERE gd.id = ? AND gs.status IN ('active', 'paused')
     LIMIT 1",
    [$deviceId]
);

if ($activeSession) {
    setError("لا يمكن حذف الجهاز لأنه يستخدم حالياً في جلسة نشطة");
    redirect('index.php');
}

// Perform deletion
beginTransaction();

if ($device['is_active']) {
    logActivity('حذف جهاز بلايستيشن', "تم حذف الجهاز: {$device['display_name']}");
}

$result = execute("DELETE FROM gaming_devices WHERE id = ?", [$deviceId]);

if ($result) {
    commit();
    setSuccess('تم حذف الجهاز بنجاح');
    redirect('index.php');
} else {
    rollback();
    setError('حدث خطأ أثناء حذف الجهاز');
    redirect('index.php');
}
