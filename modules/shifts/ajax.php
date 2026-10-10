<?php
/**
 * Shifts & Cash Management AJAX Handler
 * إدارة الورديات والخزينة
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالدخول']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // 1. فتح وردية جديدة
        case 'open_shift':
            if (!hasPermission('shifts.open_close')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية فتح وردية']);
                exit;
            }

            // التأكد من عدم وجود وردية مفتوحة للمستخدم
            $existing = getRow("SELECT id, shift_number FROM shifts WHERE user_id = ? AND status = 'open'", [getCurrentUserId()]);
            if ($existing) {
                echo json_encode(['success' => false, 'error' => "لديك بالفعل وردية مفتوحة حالياً برقم #{$existing['shift_number']}!"]);
                exit;
            }

            $openingBalance = max(0, floatval($_POST['opening_balance'] ?? 0));
            $shiftNumber = 'SHF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $shiftId = insert(
                "INSERT INTO shifts (user_id, shift_number, opened_at, status, opening_balance)
                 VALUES (?, ?, NOW(), 'open', ?)",
                [getCurrentUserId(), $shiftNumber, $openingBalance]
            );

            // تسجيل حركة نقدية للرصيد الافتتاحي
            if ($openingBalance > 0) {
                insert(
                    "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, created_by)
                     VALUES (?, 'cash_in', ?, 'cash', 'رصيد افتتاحي لبداية الوردية', ?)",
                    [$shiftId, $openingBalance, getCurrentUserId()]
                );
            }

            echo json_encode(['success' => true, 'shift_id' => $shiftId, 'message' => 'تم فتح الوردية بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 2. تسجيل حركة نقدية في الوردية (إيداع / سحب)
        case 'record_cash_movement':
            if (!hasPermission('shifts.cash_movement')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [getCurrentUserId()]);
            if (!$currentShift) {
                echo json_encode(['success' => false, 'error' => 'لا توجد وردية مفتوحة حالياً']);
                exit;
            }

            $type = $_POST['movement_type'] ?? 'cash_in'; // cash_in or cash_out
            $amount = max(0, floatval($_POST['amount'] ?? 0));
            $description = trim($_POST['description'] ?? '');

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'يرجى إدخال مبلغ صحيح']);
                exit;
            }

            beginTransaction();

            insert(
                "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, created_by)
                 VALUES (?, ?, ?, 'cash', ?, ?)",
                [$currentShift['id'], $type, $amount, $description, getCurrentUserId()]
            );

            if ($type === 'cash_in') {
                execute("UPDATE shifts SET total_cash_in = total_cash_in + ? WHERE id = ?", [$amount, $currentShift['id']]);
            } else {
                execute("UPDATE shifts SET total_cash_out = total_cash_out + ? WHERE id = ?", [$amount, $currentShift['id']]);
            }

            commit();

            echo json_encode(['success' => true, 'message' => 'تم تسجيل الحركة النقدية بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 3. تقفيل وإغلاق الوردية
        case 'close_shift':
            if (!hasPermission('shifts.open_close')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $currentShift = getRow("SELECT * FROM shifts WHERE user_id = ? AND status = 'open'", [getCurrentUserId()]);
            if (!$currentShift) {
                echo json_encode(['success' => false, 'error' => 'لا توجد وردية مفتوحة للإغلاق']);
                exit;
            }

            $actualCash = floatval($_POST['actual_cash'] ?? 0);
            $closingNotes = trim($_POST['closing_notes'] ?? '');
            $varianceReason = trim($_POST['variance_reason'] ?? '');

            // حساب النقدية المتوقعة في الدرج بدقة:
            // opening_balance + total_sales_cash + total_cash_in - total_cash_out - expenses
            $opening = floatval($currentShift['opening_balance']);
            $salesCash = floatval($currentShift['total_sales_cash']);
            $cashIn = floatval($currentShift['total_cash_in']);
            $cashOut = floatval($currentShift['total_cash_out']);
            $expenses = floatval($currentShift['total_expenses']);

            $expectedCash = ($opening + $salesCash + $cashIn) - ($cashOut + $expenses);
            $variance = $actualCash - $expectedCash; // سالب = عجز، موجب = زيادة

            execute(
                "UPDATE shifts SET 
                    closed_at = NOW(),
                    status = 'closed',
                    expected_cash = ?,
                    actual_cash = ?,
                    cash_variance = ?,
                    variance_reason = ?,
                    closing_notes = ?,
                    closed_by = ?
                 WHERE id = ?",
                [
                    $expectedCash, $actualCash, $variance, $varianceReason,
                    $closingNotes, getCurrentUserId(), $currentShift['id']
                ]
            );

            echo json_encode([
                'success' => true,
                'shift_id' => $currentShift['id'],
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'variance' => $variance,
                'message' => 'تم تقفيل الوردية بنجاح'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'خطأ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
