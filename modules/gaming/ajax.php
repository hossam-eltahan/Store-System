<?php
/**
 * Gaming (PlayStation) Backend Handler (AJAX)
 * صالة بلايستيشن كافيه عروسة النيل
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
        // 1. جلب قائمة الأجهزة وحالتها المباشرة
        case 'get_devices':
            $devices = getRows("SELECT * FROM gaming_devices WHERE is_active = 1 ORDER BY display_order ASC");

            // جلب الجلسات النشطة الحالية
            $activeSessions = getRows(
                "SELECT gs.*, u.full_name as opened_by_name,
                        TIMESTAMPDIFF(SECOND, gs.started_at, NOW()) as total_seconds_from_start,
                        IF(gs.status = 'paused' AND gs.paused_at IS NOT NULL, TIMESTAMPDIFF(SECOND, gs.paused_at, NOW()), 0) as current_pause_seconds
                 FROM gaming_sessions gs 
                 LEFT JOIN users u ON gs.opened_by = u.id 
                 WHERE gs.status IN ('active', 'paused')"
            );

            $sessionMap = [];
            foreach ($activeSessions as $sess) {
                if (($sess['status'] ?? '') === 'paused') {
                    $sess['total_paused_seconds'] = (int)$sess['total_paused_seconds'] + (int)($sess['current_pause_seconds'] ?? 0);
                }
                // جلب طلبات الكافيه المضافة للجلسة
                $orders = getRows("SELECT id, order_number, total_amount FROM orders WHERE gaming_session_id = ?", [$sess['id']]);
                $sess['orders'] = $orders;
                $sessionMap[$sess['device_id']] = $sess;
            }

            foreach ($devices as &$dev) {
                $dev['current_session'] = $sessionMap[$dev['id']] ?? null;
            }

            echo json_encode(['success' => true, 'devices' => $devices], JSON_UNESCAPED_UNICODE);
            break;

        // 2. بدء جلسة جديدة
        case 'start_session':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية بدء جلسات']);
                exit;
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $deviceId = (int)($data['device_id'] ?? 0);
            $sessionType = $data['session_type'] ?? 'single'; // single, multi, vip
            $timeMode = $data['time_mode'] ?? 'open'; // open, countdown
            $presetMinutes = !empty($data['preset_minutes']) ? (int)$data['preset_minutes'] : null;
            $customerName = trim($data['customer_name'] ?? '');

            $device = getRow("SELECT * FROM gaming_devices WHERE id = ? AND is_active = 1", [$deviceId]);
            if (!$device) {
                echo json_encode(['success' => false, 'error' => 'الجهاز غير موجود']);
                exit;
            }

            // التأكد من عدم وجود جلسة نشطة على هذا الجهاز
            $existing = getRow("SELECT id FROM gaming_sessions WHERE device_id = ? AND status IN ('active', 'paused')", [$deviceId]);
            if ($existing) {
                echo json_encode(['success' => false, 'error' => 'الجهاز مشغول بالفعل بجلسة قائمة!']);
                exit;
            }

            // Use cafe defaults for new sessions, with device rates as a fallback.
            $defaultSingleRate = (float)getSetting('ps_default_rate_single', $device['rate_single']);
            $defaultMultiRate = (float)getSetting('ps_default_rate_multi', $device['rate_multi']);
            $appliedRate = ($sessionType === 'multi') ? $defaultMultiRate : $defaultSingleRate;
            if ($sessionType === 'vip' && !empty($device['rate_vip'])) {
                $appliedRate = $device['rate_vip'];
            }

            $sessionId = insert(
                "INSERT INTO gaming_sessions (device_id, session_type, started_at, time_mode, preset_minutes, applied_rate, status, opened_by, customer_name)
                 VALUES (?, ?, NOW(), ?, ?, ?, 'active', ?, ?)",
                [$deviceId, $sessionType, $timeMode, $presetMinutes, $appliedRate, getCurrentUserId(), $customerName]
            );

            echo json_encode(['success' => true, 'session_id' => $sessionId, 'message' => 'تم بدء الجلسة بنجاح'], JSON_UNESCAPED_UNICODE);
            break;

        // 3. إيقاف مؤقت للجلسة
        case 'pause_session':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $session = getRow("SELECT * FROM gaming_sessions WHERE id = ? AND status = 'active'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير نشطة أو غير موجودة']);
                exit;
            }

            beginTransaction();
            execute("UPDATE gaming_sessions SET status = 'paused', paused_at = NOW() WHERE id = ?", [$sessionId]);
            insert("INSERT INTO gaming_session_pauses (session_id, paused_at) VALUES (?, NOW())", [$sessionId]);
            commit();

            echo json_encode(['success' => true, 'message' => 'تم إيقاف الجلسة مؤقتاً'], JSON_UNESCAPED_UNICODE);
            break;

        // 4. استئناف الجلسة بعد الإيقاف المؤقت
        case 'resume_session':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $session = getRow("SELECT * FROM gaming_sessions WHERE id = ? AND status = 'paused'", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة ليست موقوفة مؤقتاً']);
                exit;
            }

            beginTransaction();
            // حساب مدة الإيقاف الأخيرة
            $lastPause = getRow("SELECT id, TIMESTAMPDIFF(SECOND, paused_at, NOW()) as pause_duration FROM gaming_session_pauses WHERE session_id = ? AND resumed_at IS NULL ORDER BY id DESC LIMIT 1", [$sessionId]);
            $addedSeconds = $lastPause ? (int)$lastPause['pause_duration'] : 0;

            if ($lastPause) {
                execute("UPDATE gaming_session_pauses SET resumed_at = NOW(), duration_seconds = ? WHERE id = ?", [$addedSeconds, $lastPause['id']]);
            }

            execute("UPDATE gaming_sessions SET status = 'active', paused_at = NULL, total_paused_seconds = total_paused_seconds + ? WHERE id = ?", [$addedSeconds, $sessionId]);
            commit();

            echo json_encode(['success' => true, 'message' => 'تم استئناف الجلسة'], JSON_UNESCAPED_UNICODE);
            break;

        // 5. تبديل نوع اللعب أثناء الجلسة (Single <-> Multi)
        case 'switch_type':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية تعديل جلسات اللعب']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $newType = $_POST['new_type'] ?? 'multi'; // single, multi

            $session = getRow("SELECT gs.*, gd.rate_single, gd.rate_multi, gd.rate_vip FROM gaming_sessions gs JOIN gaming_devices gd ON gs.device_id = gd.id WHERE gs.id = ? AND gs.status IN ('active', 'paused')", [$sessionId]);
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            $newRate = ($newType === 'multi')
                ? (float)getSetting('ps_default_rate_multi', $session['rate_multi'])
                : (float)getSetting('ps_default_rate_single', $session['rate_single']);
            execute("UPDATE gaming_sessions SET session_type = ?, applied_rate = ? WHERE id = ?", [$newType, $newRate, $sessionId]);

            echo json_encode(['success' => true, 'new_rate' => $newRate, 'message' => "تم التبديل إلى $newType بنجاح"], JSON_UNESCAPED_UNICODE);
            break;

        // 6. نقل الجلسة إلى جهاز آخر
        case 'transfer_device':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية نقل جلسات اللعب']);
                exit;
            }

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $targetDeviceId = (int)($_POST['target_device_id'] ?? 0);

            $session = getRow("SELECT * FROM gaming_sessions WHERE id = ? AND status IN ('active', 'paused')", [$sessionId]);
            $targetDev = getRow("SELECT * FROM gaming_devices WHERE id = ? AND is_active = 1", [$targetDeviceId]);

            if (!$session || !$targetDev) {
                echo json_encode(['success' => false, 'error' => 'بيانات النقل غير صحيحة']);
                exit;
            }

            // فحص الجهاز الهدف
            $targetOccupied = getRow("SELECT id FROM gaming_sessions WHERE device_id = ? AND status IN ('active', 'paused')", [$targetDeviceId]);
            if ($targetOccupied) {
                echo json_encode(['success' => false, 'error' => 'الجهاز الجديد مشغول بالفعل!']);
                exit;
            }

            // حساب السعر الجديد
            $newRate = ($session['session_type'] === 'multi')
                ? (float)getSetting('ps_default_rate_multi', $targetDev['rate_multi'])
                : (float)getSetting('ps_default_rate_single', $targetDev['rate_single']);

            execute("UPDATE gaming_sessions SET device_id = ?, applied_rate = ? WHERE id = ?", [$targetDeviceId, $newRate, $sessionId]);

            echo json_encode(['success' => true, 'message' => "تم نقل الجلسة إلى {$targetDev['display_name']} بنجاح"], JSON_UNESCAPED_UNICODE);
            break;

        // 7. معاينة تفاصيل الفاتورة قبل الإنهاء (Calculate Preview)
        case 'preview_bill':
            $sessionId = (int)($_GET['session_id'] ?? 0);
            $session = getRow(
                "SELECT gs.*, gd.display_name, gd.min_billable_minutes, gd.billing_increment_minutes, gd.rounding_policy,
                        TIMESTAMPDIFF(SECOND, gs.started_at, NOW()) as raw_seconds
                 FROM gaming_sessions gs 
                 JOIN gaming_devices gd ON gs.device_id = gd.id 
                 WHERE gs.id = ?",
                [$sessionId]
            );

            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير موجودة']);
                exit;
            }

            // صافي الثواني (مع احتساب الإيقاف المؤقت الحالي)
            $totalPaused = (int)$session['total_paused_seconds'];
            if (($session['status'] ?? '') === 'paused' && !empty($session['paused_at'])) {
                $pauseRow = getRow("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as d", [$session['paused_at']]);
                $totalPaused += (int)($pauseRow['d'] ?? 0);
            }
            $netSeconds = max(0, $session['raw_seconds'] - $totalPaused);
            $netMinutes = ceil($netSeconds / 60);

            // تطبيق الحد الأدنى
            $billableMinutes = max((int)$session['min_billable_minutes'], $netMinutes);

            // حساب تكلفة الوقت
            $hourlyRate = floatval($session['applied_rate']);
            $timeCharge = round(($billableMinutes / 60) * $hourlyRate, 2);

            // طلبات الكافيه
            $cafeOrders = getRows("SELECT * FROM orders WHERE gaming_session_id = ?", [$sessionId]);
            $cafeTotal = 0;
            $cafePaid = 0;
            $cafeTaxOnDue = 0;
            foreach ($cafeOrders as $ord) {
                $orderTotal = floatval($ord['total_amount']);
                $orderPayment = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'order' AND source_id = ? AND payment_type = 'payment'", [$ord['id']]);
                $orderPaid = (float)($orderPayment['total'] ?? 0);
                $orderDue = max(0, $orderTotal - $orderPaid);
                $cafeTotal += $orderTotal;
                $cafePaid += $orderPaid;
                // Exclude only the tax sitting inside the STILL-DUE portion (paid parts were already taxed on collection)
                if ($orderTotal > 0) {
                    $cafeTaxOnDue += floatval($ord['tax_amount'] ?? 0) * ($orderDue / $orderTotal);
                }
            }

            $grandTotal = $timeCharge + $cafeTotal;
            $amountDue = max(0, $timeCharge + $cafeTotal - $cafePaid);
            // VAT + hall service apply to the session bill, excluding tax already charged inside linked cafe orders
            $taxPct = max(0, (float)getSetting('vat_rate', 0)) + max(0, (float)getSetting('service_charge', 0));
            $previewTax = round(max(0, $amountDue - $cafeTaxOnDue) * ($taxPct / 100), 2);

            echo json_encode([
                'success' => true,
                'session' => $session,
                'net_seconds' => $netSeconds,
                'net_minutes' => $netMinutes,
                'billable_minutes' => $billableMinutes,
                'time_charge' => $timeCharge,
                'cafe_orders' => $cafeOrders,
                'cafe_total' => $cafeTotal,
                'cafe_paid' => $cafePaid,
                'amount_due' => $amountDue,
                'grand_total' => $grandTotal,
                'cafe_tax_on_due' => round($cafeTaxOnDue, 2),
                'tax_pct' => $taxPct,
                'tax_amount' => $previewTax
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 8. إنهاء الجلسة وتسجيل الفاتورة والتحصيل
        case 'end_session':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية إنهاء جلسات']);
                exit;
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $sessionId = (int)($data['session_id'] ?? 0);
            $discountAmount = max(0, floatval($data['discount_amount'] ?? 0));
            $paymentMethod = $data['payment_method'] ?? 'cash';

            $session = getRow(
                "SELECT gs.*, gd.display_name, gd.min_billable_minutes,
                        TIMESTAMPDIFF(SECOND, gs.started_at, NOW()) as raw_seconds
                 FROM gaming_sessions gs 
                 JOIN gaming_devices gd ON gs.device_id = gd.id 
                 WHERE gs.id = ? AND gs.status IN ('active', 'paused')",
                [$sessionId]
            );

            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'الجلسة غير نشطة أو مغلقة بالفعل']);
                exit;
            }

            beginTransaction();

            $totalPaused = (int)$session['total_paused_seconds'];
            if (($session['status'] ?? '') === 'paused' && !empty($session['paused_at'])) {
                $pauseRow = getRow("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as d", [$session['paused_at']]);
                $totalPaused += (int)($pauseRow['d'] ?? 0);
            }
            $netSeconds = max(0, $session['raw_seconds'] - $totalPaused);
            $netMinutes = ceil($netSeconds / 60);
            $billableMinutes = max((int)$session['min_billable_minutes'], $netMinutes);
            $hourlyRate = floatval($session['applied_rate']);
            $gamingCharge = round(($billableMinutes / 60) * $hourlyRate, 2);

            // طلبات الكافيه المرتبطة
            $cafeOrders = getRows("SELECT id, total_amount, tax_amount, paid_amount, payment_status FROM orders WHERE gaming_session_id = ?", [$sessionId]);
            $cafeTotal = 0;
            $cafeDue = 0;
            $cafeTaxOnDue = 0;
            foreach ($cafeOrders as $o) {
                $orderTotal = (float)$o['total_amount'];
                $orderPaid = ($o['payment_status'] ?? '') === 'paid'
                    ? $orderTotal
                    : min($orderTotal, max(0, (float)$o['paid_amount']));
                $orderDue = max(0, $orderTotal - $orderPaid);
                $cafeTotal += $orderTotal;
                if ($orderTotal > 0) {
                    $cafeTaxOnDue += (float)($o['tax_amount'] ?? 0) * ($orderDue / $orderTotal);
                }
                $cafeDue += $orderDue;
                if ($orderTotal > $orderPaid) {
                    execute("UPDATE orders SET payment_status = 'paid', status = 'completed', paid_amount = total_amount WHERE id = ?", [$o['id']]);
                }
            }

            $discountAmount = min($discountAmount, $gamingCharge + $cafeDue);

            // VAT + hall service on the untaxed remainder (time charge + due cafe amounts minus tax already inside the due cafe portion)
            $taxPct = max(0, (float)getSetting('vat_rate', 0)) + max(0, (float)getSetting('service_charge', 0));
            $taxBase = max(0, $gamingCharge + $cafeDue - $cafeTaxOnDue - $discountAmount);
            $taxAmount = round($taxBase * ($taxPct / 100), 2);

            $totalAmount = max(0, ($gamingCharge + $cafeDue) - $discountAmount + $taxAmount);

            // جلب الشيفت المفتوح الحالي
            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
            $shiftId = $currentShift ? $currentShift['id'] : null;

            // تحديث سجل الجلسة
            execute(
                "UPDATE gaming_sessions SET 
                    ended_at = NOW(),
                    billable_minutes = ?,
                    gaming_charge = ?,
                    cafe_orders_total = ?,
                    discount_amount = ?,
                    tax_amount = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    status = 'completed',
                    closed_by = ?
                 WHERE id = ?",
                [
                    $billableMinutes, $gamingCharge, $cafeTotal, $discountAmount,
                    $taxAmount, $totalAmount, $totalAmount, getCurrentUserId(), $sessionId
                ]
            );

            // تسجيل إيصال دفع
            $payNumber = 'PAY-PS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            insert(
                "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                 VALUES (?, 'gaming_session', ?, ?, ?, 'payment', ?, ?)",
                [$payNumber, $sessionId, $totalAmount, $paymentMethod, $shiftId, getCurrentUserId()]
            );

            // تسجيل حركة نقدية في الشيفت
            if ($shiftId) {
                insert(
                    "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                     VALUES (?, 'sale', ?, ?, ?, 'gaming_session', ?, ?)",
                    [$shiftId, $totalAmount, $paymentMethod, "تحصيل جلسة بلايستيشن {$session['display_name']}", $sessionId, getCurrentUserId()]
                );

                execute("UPDATE shifts SET total_gaming_revenue = total_gaming_revenue + ? WHERE id = ?", [$gamingCharge, $shiftId]);

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
            if (!hasPermission('gaming.manage')) {
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
            $pdo->exec("INSERT IGNORE INTO session_timer_notifications (entity_type, session_id) VALUES ('gaming', $sessionId)");
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        // 8. Extend session time (add minutes to countdown)
        case 'extend_session':
            if (!hasPermission('gaming.manage')) {
                echo json_encode(['success' => false, 'error' => 'غير مصرح']);
                exit;
            }
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $addMinutes = max(1, (int)($_POST['add_minutes'] ?? 30));

            $session = getRow("SELECT gs.*, gd.display_name, TIMESTAMPDIFF(SECOND, gs.started_at, NOW()) as raw_seconds FROM gaming_sessions gs JOIN gaming_devices gd ON gs.device_id = gd.id WHERE gs.id = ? AND gs.status IN ('active', 'paused') AND gs.time_mode = 'countdown'", [$sessionId]);
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

            execute("UPDATE gaming_sessions SET preset_minutes = ? WHERE id = ?", [$newPreset, $sessionId]);
            // Clear notification tracking so this session is re-evaluated
            $pdo->exec("CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` ENUM('gaming', 'billiards') NOT NULL,
                `session_id` INT NOT NULL,
                `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`entity_type`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("DELETE FROM session_timer_notifications WHERE entity_type = 'gaming' AND session_id = {$sessionId}");

            echo json_encode(['success' => true, 'message' => "تم إضافة {$addMinutes} دقيقة إلى جهاز {$session['display_name']}", 'new_preset_minutes' => $newPreset], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        rollback();
    }
    echo json_encode(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
