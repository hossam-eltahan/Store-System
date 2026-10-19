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
                $sess['due_total'] = $dueTotal + (float)($sess['transferred_amount'] ?? 0);
                $sess['transferred_amount'] = (float)($sess['transferred_amount'] ?? 0);
                $sessionMap[$sess['table_id']] = $sess;
            }

            foreach ($tables as &$tbl) {
                $tbl['current_session'] = $sessionMap[$tbl['id']] ?? null;
            }

            echo json_encode(['success' => true, 'tables' => $tables], JSON_UNESCAPED_UNICODE);
            break;

        // 1b. قوائم الوجهات الشاغرة للنقل بين الصالات
        case 'cross_targets':
            if (!hasPermission('tables.manage') || !hasAnyPermission(['gaming.manage', 'billiards.manage'])) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية النقل بين الصالات']);
                exit;
            }

            $devices = [];
            $btables = [];
            if (hasPermission('gaming.manage')) {
                $devices = getRows(
                    "SELECT gd.id, gd.display_name FROM gaming_devices gd
                      WHERE gd.is_active = 1 AND NOT EXISTS (
                          SELECT 1 FROM gaming_sessions gs WHERE gs.device_id = gd.id AND gs.status IN ('active', 'paused')
                      ) ORDER BY gd.display_order ASC"
                );
            }
            if (hasPermission('billiards.manage')) {
                $btables = getRows(
                    "SELECT bt.id, bt.display_name FROM billiard_tables bt
                      WHERE bt.is_active = 1 AND NOT EXISTS (
                          SELECT 1 FROM billiard_sessions bs WHERE bs.table_id = bt.id AND bs.status IN ('active', 'paused')
                      ) ORDER BY bt.display_name ASC"
                );
            }

            echo json_encode(['success' => true, 'devices' => $devices, 'btables' => $btables], JSON_UNESCAPED_UNICODE);
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

            // ربط/تسجيل العميل (من المحدد، أو تسجيل تلقائي لاسم جديد، أو بدون ربط)
            require_once __DIR__ . '/../../config/customer_accounts.php';
            $tableCustomer = resolveCustomerInput((int)($_POST['customer_id'] ?? 0), $customerName);
            if ((int)($_POST['customer_id'] ?? 0) > 0 && !$tableCustomer) {
                echo json_encode(['success' => false, 'error' => 'العميل المحدد غير موجود'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $tableCustomerId = $tableCustomer ? (int)$tableCustomer['id'] : null;
            if ($tableCustomer) $customerName = $tableCustomer['name'];

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
                "INSERT INTO table_sessions (table_id, opened_at, status, customer_id, customer_name, guest_count, opened_by)
                 VALUES (?, NOW(), 'active', ?, ?, ?, ?)",
                [$tableId, $tableCustomerId, $customerName, $guestCount, getCurrentUserId()]
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

        // 3b. نقل جلسة الطاولة إلى بلايستيشن (cross-hall)
        case 'transfer_to_gaming':
            if (!hasPermission('tables.manage') || !hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية النقل بين الصالات']);
                exit;
            }

            $result = transferSessionAcrossHalls('tables', (int)($_POST['session_id'] ?? 0), 'gaming', (int)($_POST['target_device_id'] ?? 0), [
                'session_type' => $_POST['session_type'] ?? 'single',
                'customer_name' => trim($_POST['customer_name'] ?? ''),
            ]);
            if (!$result['success']) {
                echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(['success' => true, 'new_session_id' => $result['new_session_id'], 'carried_amount' => $result['carried_amount'], 'message' => 'تم نقل الجلسة إلى البلايستيشن بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 3c. نقل جلسة الطاولة إلى بلياردو (cross-hall)
        case 'transfer_to_billiards':
            if (!hasPermission('tables.manage') || !hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية النقل بين الصالات']);
                exit;
            }

            $result = transferSessionAcrossHalls('tables', (int)($_POST['session_id'] ?? 0), 'billiards', (int)($_POST['target_table_id'] ?? 0), [
                'billing_mode' => $_POST['billing_mode'] ?? 'hourly',
                'customer_name' => trim($_POST['customer_name'] ?? ''),
            ]);
            if (!$result['success']) {
                echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(['success' => true, 'new_session_id' => $result['new_session_id'], 'carried_amount' => $result['carried_amount'], 'message' => 'تم نقل الجلسة إلى البلياردو بنجاح'], JSON_UNESCAPED_UNICODE);
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
            // المحصل الآن (null = تحصيل كامل كما من قبل)
            $collectedInput = isset($_POST['paid_amount']) && $_POST['paid_amount'] !== ''
                ? max(0.0, round((float)$_POST['paid_amount'], 2)) : null;

            $session = getRow("SELECT * FROM table_sessions WHERE id = ? AND status = 'active'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            // ربط/تسجيل العميل وقت القفل (إجباري عند التحصيل الجزئي)
            require_once __DIR__ . '/../../config/customer_accounts.php';
            $customerAtClose = resolveCustomerInput((int)($_POST['customer_id'] ?? 0), trim($_POST['customer_name'] ?? ''));
            if ((int)($_POST['customer_id'] ?? 0) > 0 && !$customerAtClose) {
                echo json_encode(['success' => false, 'error' => 'العميل المحدد غير موجود'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$customerAtClose && !empty($session['customer_id'])) {
                $customerAtClose = getRow("SELECT * FROM customers WHERE id = ?", [$session['customer_id']]);
            }

            beginTransaction();

            // فحص طلبات الطاولة (دون وسمها "مدفوعة" الآن — التوزيع يحدث لاحقاً)
            $orders = getRows("SELECT id, total_amount, paid_amount, payment_status, created_at FROM orders WHERE table_session_id = ? ORDER BY created_at ASC, id ASC", [$sessionId]);
            $ordersTotal = 0;
            $paidPreviously = 0;
            $amountDue = 0;
            $unpaidOrders = [];
            foreach ($orders as $o) {
                $orderTotal = (float)$o['total_amount'];
                $orderPaid = $o['payment_status'] === 'paid'
                    ? $orderTotal
                    : min($orderTotal, max(0, (float)$o['paid_amount']));
                $ordersTotal += $orderTotal;
                $paidPreviously += $orderPaid;
                $orderDue = max(0, $orderTotal - $orderPaid);
                $amountDue += $orderDue;
                if ($orderDue > 0.009) {
                    $unpaidOrders[] = ['id' => (int)$o['id'], 'due' => $orderDue, 'paid' => $orderPaid, 'total' => $orderTotal];
                }
            }
            // Balance carried in from another hall (cross-hall transfer) settles here
            $carriedAmount = (float)($session['transferred_amount'] ?? 0);
            $amountDue += $carriedAmount;
            $discountAmount = min($requestedDiscount, $amountDue);
            $amountToCollect = max(0, $amountDue - $discountAmount);

            // قرار التحصيل: كامل (افتراضي) أو جزئي مع عميل مربوط
            if ($collectedInput !== null && $collectedInput < $amountToCollect - 0.009 && !$customerAtClose) {
                rollback();
                echo json_encode(['success' => false, 'error' => 'لازم تسجل العميل — اكتب اسمه في خانة العميل في نافذة الإغلاق، الباقي هيتحسب على حسابه'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $collected = ($customerAtClose && $collectedInput !== null) ? min($collectedInput, $amountToCollect) : $amountToCollect;
            $collected = min($collected, $amountToCollect);
            $tableRemainingDue = round(max(0.0, $amountToCollect - $collected), 2);

            // التحصيل الجزئي: الرصيد المرحَّل يُحسب أولاً ثم طلبات الطاولة من الأقدم
            if ($collected < $amountToCollect - 0.009) {
                $left = $collected;
                $carriedCovered = min($carriedAmount, $left);
                $left = round($left - $carriedCovered, 2);
                foreach ($unpaidOrders as $uo) {
                    if ($left <= 0.009) break;
                    $take = round(min($uo['due'], $left), 2);
                    if ($take <= 0) continue;
                    $newPaid = round($uo['paid'] + $take, 2);
                    $newStatus = ($newPaid >= round($uo['total'] - 0.009, 2)) ? 'paid' : 'partial';
                    execute("UPDATE orders SET paid_amount = ?, payment_status = ?, status = 'completed' WHERE id = ?", [$newPaid, $newStatus, $uo['id']]);
                    $left = round($left - $take, 2);
                }
            } else {
                // تحصيل كامل: كل طلبات الطاولة الصافية توضع "مدفوعة" كما من قبل
                foreach ($unpaidOrders as $uo) {
                    execute("UPDATE orders SET payment_status = 'paid', status = 'completed', paid_amount = total_amount WHERE id = ?", [$uo['id']]);
                }
            }

            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
            $shiftId = $currentShift ? $currentShift['id'] : null;

            if ($collected > 0.009) {
                $payNumber = 'PAY-TBL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                insert(
                    "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                     VALUES (?, 'table_session', ?, ?, ?, 'payment', ?, ?)",
                    [$payNumber, $sessionId, $collected, $paymentMethod, $shiftId, getCurrentUserId()]
                );

                if ($shiftId) {
                    insert(
                        "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                         VALUES (?, 'sale', ?, ?, 'تحصيل طاولة صالة', 'table_session', ?, ?)",
                        [$shiftId, $collected, $paymentMethod, $sessionId, getCurrentUserId()]
                    );

                    if ($paymentMethod === 'cash') {
                        execute("UPDATE shifts SET total_sales_cash = total_sales_cash + ? WHERE id = ?", [$collected, $shiftId]);
                    } elseif ($paymentMethod === 'card') {
                        execute("UPDATE shifts SET total_sales_card = total_sales_card + ? WHERE id = ?", [$collected, $shiftId]);
                    } else {
                        execute("UPDATE shifts SET total_sales_wallet = total_sales_wallet + ? WHERE id = ?", [$collected, $shiftId]);
                    }
                }
            }

            // الباقي غير المحصل → دين على حساب العميل المربوط
            if ($tableRemainingDue > 0.009 && $customerAtClose) {
                execute("UPDATE customers SET balance = balance - ? WHERE id = ?", [$tableRemainingDue, $customerAtClose['id']]);
                logActivity('مديونية طاولة صالة', 'تبقى ' . number_format($tableRemainingDue, 2) . ' ج.م على ' . $customerAtClose['name'] . ' من إغلاق طاولة (جلسة #' . $sessionId . ')');
            }

            execute(
                "UPDATE table_sessions SET closed_at = NOW(), status = 'closed', closed_by = ?, customer_id = ?, total_amount = ?, discount_amount = ? WHERE id = ?",
                [getCurrentUserId(), $customerAtClose ? (int)$customerAtClose['id'] : ($session['customer_id'] ?? null), $ordersTotal, $discountAmount, $sessionId]
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
