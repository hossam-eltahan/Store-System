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

// Perform deletion.
// Devices with past sessions cannot be hard-deleted (FK from gaming_sessions),
// so they are deactivated (hidden from the cashier screen) instead.
$history = getRow("SELECT COUNT(*) AS c FROM gaming_sessions WHERE device_id = ?", [$deviceId]);
$historyCount = (int)($history['c'] ?? 0);

beginTransaction();

if ($device['is_active']) {
    logActivity('حذف جهاز بلايستيشن', "تم حذف الجهاز: {$device['display_name']}");
}

if ($historyCount > 0) {
    // Has session history (receipts/reports reference it) → soft delete
    $result = execute("UPDATE gaming_devices SET is_active = 0 WHERE id = ?", [$deviceId]);
    if ($result !== false) {
        commit();
        setSuccess('تم إيقاف الجهاز وإخفاؤه من القائمة (له سجل جلسات سابقة ولا يمكن حذفه نهائياً)');
        redirect('index.php');
    }
} else {
    // No history → safe to hard delete
    $result = execute("DELETE FROM gaming_devices WHERE id = ?", [$deviceId]);
    if ($result) {
        commit();
        setSuccess('تم حذف الجهاز بنجاح');
        redirect('index.php');
    }
}

rollback();
setError('حدث خطأ أثناء حذف الجهاز');
redirect('index.php');
