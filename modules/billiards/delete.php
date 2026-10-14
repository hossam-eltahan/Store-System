<?php
/**
 * Delete Billiard Table - حذف طاولة بلياردو
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('billiards.tables');

// Get table ID
$tableId = (int)($_GET['id'] ?? 0);
$table = getRow("SELECT * FROM billiard_tables WHERE id = ?", [$tableId]);

if (!$table) {
    setError('الطاولة غير موجودة');
    redirect('index.php');
}

// Check if table is in use (has active session)
$activeSession = getRow(
    "SELECT bs.*, bt.display_name
     FROM billiard_sessions bs
     JOIN billiard_tables bt ON bs.table_id = bt.id
     WHERE bt.id = ? AND bs.status IN ('active', 'paused')
     LIMIT 1",
    [$tableId]
);

if ($activeSession) {
    setError("لا يمكن حذف الطاولة لأنها مشغولة حالياً في جلسة نشطة");
    redirect('index.php');
}

// Perform deletion
beginTransaction();

if ($table['is_active']) {
    logActivity('حذف طاولة بلياردو', "تم حذف الطاولة: {$table['display_name']}");
}

$result = execute("DELETE FROM billiard_tables WHERE id = ?", [$tableId]);

if ($result) {
    commit();
    setSuccess('تم حذف الطاولة بنجاح');
    redirect('index.php');
} else {
    rollback();
    setError('حدث خطأ أثناء حذف الطاولة');
    redirect('index.php');
}
