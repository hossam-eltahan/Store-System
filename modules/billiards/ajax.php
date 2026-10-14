<?php
/**
 * Billiards Backend Handler (AJAX)
 * صالة بلياردو وسنوكر كافيه عروسة النيل
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
        // 1. جلب طاولات البلياردو وحالتها
        case 'get_tables':
            $tables = getRows("SELECT * FROM billiard_tables WHERE is_active = 1 ORDER BY display_order ASC");

            $activeSessions = getRows(
                "SELECT bs.*, u.full_name as opened_by_name,
                        TIMESTAMPDIFF(SECOND, bs.started_at, NOW()) as total_seconds_from_start,
                        IF(bs.status = 'paused' AND bs.paused_at IS NOT NULL, TIMESTAMPDIFF(SECOND, bs.paused_at, NOW()), 0) as current_pause_seconds
                 FROM billiard_sessions bs 
                 LEFT JOIN users u ON bs.opened_by = u.id 
                 WHERE bs.status IN ('active', 'paused')"
            );

            $sessionMap = [];
            foreach ($activeSessions as $sess) {
                if (($sess['status'] ?? '') === 'paused') {
                    $sess['total_paused_seconds'] = (int)$sess['total_paused_seconds'] + (int)($sess['current_pause_seconds'] ?? 0);
                }
                $orders = getRows("SELECT id, order_number, total_amount FROM orders WHERE billiard_session_id = ?", [$sess['id']]);
                $sess['orders'] = $orders;
                $sessionMap[$sess['table_id']] = $sess;
            }

            foreach ($tables as &$tbl) {
                $tbl['current_session'] = $sessionMap[$tbl['id']] ?? null;
            }

            echo json_encode(['success' => true, 'tables' => $tables], JSON_UNESCAPED_UNICODE);
            break;

        // 2. بدء جلسة بلياردو
        case 'start_session':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية بدء جلسات بلياردو']);
                exit;
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $tableId = (int)($data['table_id'] ?? 0);
            $billingMode = $data['billing_mode'] ?? 'hourly'; // hourly, per_game
            $customerName = trim($data['customer_name'] ?? '');
            $timeMode = $data['time_mode'] ?? 'open';
            $presetMinutes = !empty($data['preset_minutes']) ? (int)$data['preset_minutes'] : null;

            $table = getRow("SELECT * FROM billiard_tables WHERE id = ? AND is_active = 1", [$tableId]);
            if (!$table) {
                echo json_encode(['success' => false, 'error' => 'الطاولة غير موجودة']);
                exit;
            }

            $existing = getRow("SELECT id FROM billiard_sessions WHERE table_id = ? AND status IN ('active', 'paused')", [$tableId]);
            if ($existing) {
                echo json_encode(['success' => false, 'error' => 'الطاولة مشغولة بجلسة قائمة!']);
                exit;
            }

            $defaultHourRate = (float)getSetting('billiard_default_rate_hour', $table['rate_per_hour']);
            $defaultGameRate = (float)getSetting('billiard_default_rate_game', $table['rate_per_game']);
            $appliedRate = ($billingMode === 'hourly') ? $defaultHourRate : $defaultGameRate;
            $initialGameCount = ($billingMode === 'per_game') ? 1 : 0;

            $sessionId = insert(
                "INSERT INTO billiard_sessions (table_id, billing_mode, started_at, game_count, applied_rate, status, opened_by, customer_name, time_mode, preset_minutes)
                 VALUES (?, ?, NOW(), ?, ?, 'active', ?, ?, ?, ?)",
                [$tableId, $billingMode, $initialGameCount, $appliedRate, getCurrentUserId(), $customerName, $timeMode, $presetMinutes]
            );

            echo json_encode(['success' => true, 'session_id' => $sessionId, 'message' => 'تم بدء جلسة البلياردو بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 3. إضافة جيم إضافي (في نظام الحساب بالجيم)
        case 'increment_game':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية تعديل جلسات البلياردو']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $session = getRow("SELECT * FROM billiard_sessions WHERE id = ? AND status = 'active' AND billing_mode = 'per_game'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير صحيحة أو ليست بنظام الجيم']);
                exit;
            }

            execute("UPDATE billiard_sessions SET game_count = game_count + 1 WHERE id = ?", [$sessionId]);
            echo json_encode(['success' => true, 'new_count' => $session['game_count'] + 1], JSON_UNESCAPED_UNICODE);
            break;

        // 4. إيقاف مؤقت واستئناف
        case 'pause_session':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية إيقاف جلسات البلياردو']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            beginTransaction();
            execute("UPDATE billiard_sessions SET status = 'paused', paused_at = NOW() WHERE id = ?", [$sessionId]);
            insert("INSERT INTO billiard_session_pauses (session_id, paused_at) VALUES (?, NOW())", [$sessionId]);
            commit();
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'resume_session':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية استئناف جلسات البلياردو']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            beginTransaction();
            $lastPause = getRow("SELECT id, TIMESTAMPDIFF(SECOND, paused_at, NOW()) as pause_duration FROM billiard_session_pauses WHERE session_id = ? AND resumed_at IS NULL ORDER BY id DESC LIMIT 1", [$sessionId]);
            $addedSeconds = $lastPause ? (int)$lastPause['pause_duration'] : 0;
            if ($lastPause) {
                execute("UPDATE billiard_session_pauses SET resumed_at = NOW(), duration_seconds = ? WHERE id = ?", [$addedSeconds, $lastPause['id']]);
            }
            execute("UPDATE billiard_sessions SET status = 'active', paused_at = NULL, total_paused_seconds = total_paused_seconds + ? WHERE id = ?", [$addedSeconds, $sessionId]);
            commit();
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        // 5. معاينة حساب البلياردو
        case 'preview_bill':
            $sessionId = (int)($_GET['session_id'] ?? 0);
            $session = getRow(
                "SELECT bs.*, bt.display_name, bt.min_billable_minutes,
                        TIMESTAMPDIFF(SECOND, bs.started_at, NOW()) as raw_seconds
                 FROM billiard_sessions bs 
                 JOIN billiard_tables bt ON bs.table_id = bt.id 
                 WHERE bs.id = ?",
                [$sessionId]
            );

            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            $gamingCharge = 0;
            $billableMinutes = 0;

            if ($session['billing_mode'] === 'hourly') {
                $totalPaused = (int)$session['total_paused_seconds'];
                if (($session['status'] ?? '') === 'paused' && !empty($session['paused_at'])) {
                    $pauseRow = getRow("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as d", [$session['paused_at']]);
                    $totalPaused += (int)($pauseRow['d'] ?? 0);
                }
                $netSeconds = max(0, $session['raw_seconds'] - $totalPaused);
                $netMinutes = ceil($netSeconds / 60);
                $billableMinutes = max((int)$session['min_billable_minutes'], $netMinutes);
                $gamingCharge = round(($billableMinutes / 60) * floatval($session['applied_rate']), 2);
            } else {
                $gamingCharge = $session['game_count'] * floatval($session['applied_rate']);
            }

            // طلبات الكافيه
            $cafeOrders = getRows("SELECT * FROM orders WHERE billiard_session_id = ?", [$sessionId]);
            $cafeTotal = 0;
            $cafePaid = 0;
            foreach ($cafeOrders as $ord) {
                $cafeTotal += floatval($ord['total_amount']);
                $orderPayment = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'order' AND source_id = ? AND payment_type = 'payment'", [$ord['id']]);
                $cafePaid += (float)($orderPayment['total'] ?? 0);
            }

            $grandTotal = $gamingCharge + $cafeTotal;
            $amountDue = max(0, $gamingCharge + $cafeTotal - $cafePaid);

            echo json_encode([
                'success' => true,
                'session' => $session,
                'billable_minutes' => $billableMinutes,
                'gaming_charge' => $gamingCharge,
                'cafe_orders' => $cafeOrders,
                'cafe_total' => $cafeTotal,
                'cafe_paid' => $cafePaid,
                'amount_due' => $amountDue,
                'grand_total' => $grandTotal
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 6. إنهاء الجلسة والتحصيل
        case 'end_session':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $sessionId = (int)($data['session_id'] ?? 0);
            $discountAmount = max(0, floatval($data['discount_amount'] ?? 0));
            $paymentMethod = $data['payment_method'] ?? 'cash';

            $session = getRow(
                "SELECT bs.*, bt.display_name, bt.min_billable_minutes,
                        TIMESTAMPDIFF(SECOND, bs.started_at, NOW()) as raw_seconds
                 FROM billiard_sessions bs 
                 JOIN billiard_tables bt ON bs.table_id = bt.id 
                 WHERE bs.id = ? AND bs.status IN ('active', 'paused')",
                [$sessionId]
            );

            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            beginTransaction();

            $gamingCharge = 0;
            $billableMinutes = 0;

            if ($session['billing_mode'] === 'hourly') {
                $totalPaused = (int)$session['total_paused_seconds'];
                if (($session['status'] ?? '') === 'paused' && !empty($session['paused_at'])) {
                    $pauseRow = getRow("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as d", [$session['paused_at']]);
                    $totalPaused += (int)($pauseRow['d'] ?? 0);
                }
                $netSeconds = max(0, $session['raw_seconds'] - $totalPaused);
                $netMinutes = ceil($netSeconds / 60);
                $billableMinutes = max((int)$session['min_billable_minutes'], $netMinutes);
                $gamingCharge = round(($billableMinutes / 60) * floatval($session['applied_rate']), 2);
            } else {
                $gamingCharge = $session['game_count'] * floatval($session['applied_rate']);
            }

            $cafeOrders = getRows("SELECT id, total_amount, paid_amount FROM orders WHERE billiard_session_id = ?", [$sessionId]);
            $cafeTotal = 0;
            $cafeDue = 0;
            foreach ($cafeOrders as $o) {
                $orderTotal = (float)$o['total_amount'];
                $orderPaid = ($o['payment_status'] ?? '') === 'paid'
                    ? $orderTotal
                    : min($orderTotal, max(0, (float)$o['paid_amount']));
                $cafeTotal += $orderTotal;
                $cafeDue += max(0, $orderTotal - $orderPaid);
                if ($orderTotal > $orderPaid) {
                    execute("UPDATE orders SET payment_status = 'paid', status = 'completed', paid_amount = total_amount WHERE id = ?", [$o['id']]);
                }
            }

            $discountAmount = min($discountAmount, $gamingCharge + $cafeDue);
            $totalAmount = max(0, ($gamingCharge + $cafeDue) - $discountAmount);

            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
            $shiftId = $currentShift ? $currentShift['id'] : null;

            execute(
                "UPDATE billiard_sessions SET 
                    ended_at = NOW(),
                    billable_minutes = ?,
                    gaming_charge = ?,
                    cafe_orders_total = ?,
                    discount_amount = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    status = 'completed',
                    closed_by = ?
                 WHERE id = ?",
                [$billableMinutes, $gamingCharge, $cafeTotal, $discountAmount, $totalAmount, $totalAmount, getCurrentUserId(), $sessionId]
            );

            $payNumber = 'PAY-BIL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            insert(
                "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                 VALUES (?, 'billiard_session', ?, ?, ?, 'payment', ?, ?)",
                [$payNumber, $sessionId, $totalAmount, $paymentMethod, $shiftId, getCurrentUserId()]
            );

            if ($shiftId) {
                insert(
                    "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                     VALUES (?, 'sale', ?, ?, ?, 'billiard_session', ?, ?)",
                    [$shiftId, $totalAmount, $paymentMethod, "تحصيل جلسة بلياردو {$session['display_name']}", $sessionId, getCurrentUserId()]
                );

                execute("UPDATE shifts SET total_billiard_revenue = total_billiard_revenue + ? WHERE id = ?", [$gamingCharge, $shiftId]);

                if ($paymentMethod === 'cash') {
                    execute("UPDATE shifts SET total_sales_cash = total_sales_cash + ? WHERE id = ?", [$totalAmount, $shiftId]);
                } elseif ($paymentMethod === 'card') {
                    execute("UPDATE shifts SET total_sales_card = total_sales_card + ? WHERE id = ?", [$totalAmount, $shiftId]);
                } else {
                    execute("UPDATE shifts SET total_sales_wallet = total_sales_wallet + ? WHERE id = ?", [$totalAmount, $shiftId]);
                }
            }

            commit();

            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'total_amount' => $totalAmount,
                'message' => 'تم إنهاء الجلسة وتسجيل الفاتورة بنجاح'
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 7. Mark session as notified (for countdown timer alerts)
        case 'mark_notified':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $pdo->exec("CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` ENUM('gaming', 'billiards') NOT NULL,
                `session_id` INT NOT NULL,
                `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`entity_type`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("INSERT IGNORE INTO session_timer_notifications (entity_type, session_id) VALUES ('billiards', $sessionId)");
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        // 8. Extend session time (add minutes to countdown)
        case 'extend_session':
            if (!hasPermission('billiards.manage')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $addMinutes = max(1, (int)($_POST['add_minutes'] ?? 30));

            $session = getRow("SELECT bs.*, bt.display_name, TIMESTAMPDIFF(SECOND, bs.started_at, NOW()) as raw_seconds FROM billiard_sessions bs JOIN billiard_tables bt ON bs.table_id = bt.id WHERE bs.id = ? AND bs.status IN ('active', 'paused') AND bs.time_mode = 'countdown'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة أو غير نشطة']);
                exit;
            }

            $rawSeconds = max(0, (int)($session['raw_seconds'] ?? 0));
            $totalPaused = (int)($session['total_paused_seconds'] ?? 0);
            if (($session['status'] ?? '') === 'paused' && !empty($session['paused_at'])) {
                $pauseRow = getRow("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as d", [$session['paused_at']]);
                $totalPaused += (int)($pauseRow['d'] ?? 0);
            }
            $netSeconds = max(0, $rawSeconds - $totalPaused);
            $netMinutes = (int)ceil($netSeconds / 60);
            $oldPreset = (int)$session['preset_minutes'];
            $newPreset = max($oldPreset, $netMinutes) + $addMinutes;

            execute("UPDATE billiard_sessions SET preset_minutes = ? WHERE id = ?", [$newPreset, $sessionId]);
            $pdo->exec("CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` ENUM('gaming', 'billiards') NOT NULL,
                `session_id` INT NOT NULL,
                `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`entity_type`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("DELETE FROM session_timer_notifications WHERE entity_type = 'billiards' AND session_id = {$sessionId}");

            echo json_encode(['success' => true, 'message' => "تم إضافة {$addMinutes} دقيقة إلى طاولة {$session['display_name']}", 'new_preset_minutes' => $newPreset], JSON_UNESCAPED_UNICODE);
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
