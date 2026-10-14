<?php
/**
 * Delete Table - حذف طاولة
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('tables.manage');

// Get table ID
$tableId = (int)($_GET['id'] ?? 0);
$table = getRow("SELECT * FROM cafe_tables WHERE id = ?", [$tableId]);

if (!$table) {
    setError('الطاولة غير موجودة');
    redirect('index.php');
}

// Check if table is in use (has active session)
$activeSession = getRow(
    "SELECT ts.* FROM table_sessions ts
     WHERE ts.table_id = ? AND ts.status = 'active'
     LIMIT 1",
    [$tableId]
);

if ($activeSession) {
    setError("لا يمكن حذف الطاولة لأنها مشغولة حالياً في جلسة نشطة");
    redirect('index.php');
}

// Perform deletion
beginTransaction();

// Delete all sessions for this table first
execute("DELETE FROM table_sessions WHERE table_id = ?", [$tableId]);

// Log activity if table was active
if ($table['is_active']) {
    logActivity('حذف طاولة', "تم حذف الطاولة: {$table['display_name']} ({$table['capacity']} أشخاص)");
}

// Delete the table
$result = execute("DELETE FROM cafe_tables WHERE id = ?", [$tableId]);

if ($result) {
    commit();
    setSuccess('تم حذف الطاولة بنجاح');
    redirect('index.php');
} else {
    rollback();
    setError('حدث خطأ أثناء حذف الطاولة');
    redirect('index.php');
}
