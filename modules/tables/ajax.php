<?php
/**
 * Tables Backend Handler (AJAX)
 * طاولات صالة كافيه عروسة النيل
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
        // 1. جلب الطاولات والجلسات
        case 'get_tables':
            if (!hasPermission('tables.view')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية عرض الطاولات']);
                exit;
            }

            $tables = getRows("SELECT * FROM cafe_tables WHERE is_active = 1 ORDER BY display_order ASC");

            $activeSessions = getRows(
                "SELECT ts.*, u.full_name as opened_by_name,
                        TIMESTAMPDIFF(MINUTE, ts.opened_at, NOW()) as elapsed_minutes
                 FROM table_sessions ts 
                 LEFT JOIN users u ON ts.opened_by = u.id 
                 WHERE ts.status = 'active'"
            );

            $sessionMap = [];
            foreach ($activeSessions as $sess) {
                $orders = getRows("SELECT id, order_number, total_amount, paid_amount, payment_status FROM orders WHERE table_session_id = ?", [$sess['id']]);
                $grossTotal = 0;
                $paidTotal = 0;
                $dueTotal = 0;
                foreach ($orders as $order) {
                    $orderTotal = (float)$order['total_amount'];
                    $orderPaid = $order['payment_status'] === 'paid'
                        ? $orderTotal
                        : min($orderTotal, max(0, (float)$order['paid_amount']));
                    $grossTotal += $orderTotal;
                    $paidTotal += $orderPaid;
                    $dueTotal += max(0, $orderTotal - $orderPaid);
                }
                $sess['orders'] = $orders;
                $sess['gross_total'] = $grossTotal;
                $sess['paid_total'] = $paidTotal;
                $sess['due_total'] = $dueTotal;
                $sessionMap[$sess['table_id']] = $sess;
            }

            foreach ($tables as &$tbl) {
                $tbl['current_session'] = $sessionMap[$tbl['id']] ?? null;
            }

            echo json_encode(['success' => true, 'tables' => $tables], JSON_UNESCAPED_UNICODE);
            break;

        // 2. فتح طاولة جديدة
        case 'open_table':
            if (!hasPermission('tables.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية فتح الطاولات']);
                exit;
            }

            $tableId = (int)($_POST['table_id'] ?? 0);
            $guestCount = max(1, (int)($_POST['guest_count'] ?? 1));
            $customerName = trim($_POST['customer_name'] ?? '');

            $table = getRow("SELECT * FROM cafe_tables WHERE id = ? AND is_active = 1", [$tableId]);
            if (!$table) {
                echo json_encode(['success' => false, 'error' => 'الطاولة غير موجودة']);
                exit;
            }

            $existing = getRow("SELECT id FROM table_sessions WHERE table_id = ? AND status = 'active'", [$tableId]);
            if ($existing) {
                echo json_encode(['success' => false, 'error' => 'الطاولة مشغولة بالفعل']);
                exit;
            }

            beginTransaction();

            $sessionId = insert(
                "INSERT INTO table_sessions (table_id, opened_at, status, customer_name, guest_count, opened_by)
                 VALUES (?, NOW(), 'active', ?, ?, ?)",
                [$tableId, $customerName, $guestCount, getCurrentUserId()]
            );

            execute("UPDATE cafe_tables SET status = 'occupied' WHERE id = ?", [$tableId]);

            commit();

            echo json_encode(['success' => true, 'session_id' => $sessionId, 'message' => 'تم فتح الطاولة بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 3. نقل جلسة إلى طاولة شاغرة
        case 'transfer_table':
            if (!hasPermission('tables.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية نقل جلسات الطاولات']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $targetTableId = (int)($_POST['target_table_id'] ?? 0);
            $session = getRow("SELECT * FROM table_sessions WHERE id = ? AND status = 'active'", [$sessionId]);
            $targetTable = getRow("SELECT * FROM cafe_tables WHERE id = ? AND is_active = 1 AND status = 'available'", [$targetTableId]);

            if (!$session || !$targetTable || (int)$session['table_id'] === $targetTableId) {
                echo json_encode(['success' => false, 'error' => 'الطاولة الهدف غير متاحة للنقل']);
                exit;
            }

            beginTransaction();
            execute("UPDATE table_sessions SET table_id = ? WHERE id = ?", [$targetTableId, $sessionId]);
            execute("UPDATE cafe_tables SET status = 'available' WHERE id = ?", [$session['table_id']]);
            execute("UPDATE cafe_tables SET status = 'occupied' WHERE id = ?", [$targetTableId]);
            commit();

            echo json_encode(['success' => true, 'message' => 'تم نقل الجلسة بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 4. إنهاء وإغلاق الطاولة
        case 'close_table':
            if (!hasPermission('tables.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية إغلاق الطاولات']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            $requestedDiscount = max(0, (float)($_POST['discount_amount'] ?? 0));

            $session = getRow("SELECT * FROM table_sessions WHERE id = ? AND status = 'active'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            beginTransaction();

            // فحص طلبات الطاولة
            $orders = getRows("SELECT id, total_amount, paid_amount, payment_status FROM orders WHERE table_session_id = ?", [$sessionId]);
            $ordersTotal = 0;
            $paidPreviously = 0;
            $amountDue = 0;
            foreach ($orders as $o) {
                $orderTotal = (float)$o['total_amount'];
                $orderPaid = $o['payment_status'] === 'paid'
                    ? $orderTotal
                    : min($orderTotal, max(0, (float)$o['paid_amount']));
                $ordersTotal += $orderTotal;
                $paidPreviously += $orderPaid;
                $amountDue += max(0, $orderTotal - $orderPaid);
                if ($orderTotal > $orderPaid) {
                    execute("UPDATE orders SET payment_status = 'paid', status = 'completed', paid_amount = total_amount WHERE id = ?", [$o['id']]);
                }
            }
            $discountAmount = min($requestedDiscount, $amountDue);
            $amountToCollect = max(0, $amountDue - $discountAmount);

            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
            $shiftId = $currentShift ? $currentShift['id'] : null;

            if ($amountToCollect > 0) {
                $payNumber = 'PAY-TBL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                insert(
                    "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                     VALUES (?, 'table_session', ?, ?, ?, 'payment', ?, ?)",
                    [$payNumber, $sessionId, $amountToCollect, $paymentMethod, $shiftId, getCurrentUserId()]
                );

                if ($shiftId) {
                    insert(
                        "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                         VALUES (?, 'sale', ?, ?, 'تحصيل طاولة صالة', 'table_session', ?, ?)",
                        [$shiftId, $amountToCollect, $paymentMethod, $sessionId, getCurrentUserId()]
                    );

                    if ($paymentMethod === 'cash') {
                        execute("UPDATE shifts SET total_sales_cash = total_sales_cash + ? WHERE id = ?", [$amountToCollect, $shiftId]);
                    } elseif ($paymentMethod === 'card') {
                        execute("UPDATE shifts SET total_sales_card = total_sales_card + ? WHERE id = ?", [$amountToCollect, $shiftId]);
                    } else {
                        execute("UPDATE shifts SET total_sales_wallet = total_sales_wallet + ? WHERE id = ?", [$amountToCollect, $shiftId]);
                    }
                }
            }

            execute(
                "UPDATE table_sessions SET closed_at = NOW(), status = 'closed', closed_by = ?, total_amount = ?, discount_amount = ? WHERE id = ?",
                [getCurrentUserId(), $ordersTotal, $discountAmount, $sessionId]
            );

            execute("UPDATE cafe_tables SET status = 'available' WHERE id = ?", [$session['table_id']]);

            commit();

            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'orders_total' => $ordersTotal,
                'paid_previously' => $paidPreviously,
                'amount_due' => $amountToCollect,
                'discount_amount' => $discountAmount,
                'orders_count' => count($orders),
                'message' => 'تم إنهاء وتحصيل المبلغ المتبقي للطاولة بنجاح'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        rollback();
    }
    echo json_encode(['success' => false, 'error' => 'خطأ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
