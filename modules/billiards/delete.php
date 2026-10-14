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

// Perform deletion.
// Tables with past sessions cannot be hard-deleted (FK from billiard_sessions),
// so they are deactivated (hidden from the cashier screen) instead.
$history = getRow("SELECT COUNT(*) AS c FROM billiard_sessions WHERE table_id = ?", [$tableId]);
$historyCount = (int)($history['c'] ?? 0);

beginTransaction();

if ($table['is_active']) {
    logActivity('حذف طاولة بلياردو', "تم حذف الطاولة: {$table['display_name']}");
}

if ($historyCount > 0) {
    // Has session history (receipts/reports reference it) → soft delete
    $result = execute("UPDATE billiard_tables SET is_active = 0 WHERE id = ?", [$tableId]);
    if ($result !== false) {
        commit();
        setSuccess('تم إيقاف الطاولة وإخفاؤها من القائمة (لها سجل جلسات سابقة ولا يمكن حذفها نهائياً)');
        redirect('index.php');
    }
} else {
    // No history → safe to hard delete
    $result = execute("DELETE FROM billiard_tables WHERE id = ?", [$tableId]);
    if ($result) {
        commit();
        setSuccess('تم حذف الطاولة بنجاح');
        redirect('index.php');
    }
}

rollback();
setError('حدث خطأ أثناء حذف الطاولة');
redirect('index.php');
