<?php
/**
 * Gaming Session Thermal Receipt (80mm)
 * بون جلسة بلايستيشن كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$sessionId = (int)($_GET['id'] ?? 0);

$session = getRow(
        "SELECT gs.*, gd.display_name as device_name, gd.device_type, gd.room_type,
            gd.min_billable_minutes,
            u_open.full_name as opened_by_name,
            u_close.full_name as closed_by_name
     FROM gaming_sessions gs 
     JOIN gaming_devices gd ON gs.device_id = gd.id 
     LEFT JOIN users u_open ON gs.opened_by = u_open.id 
     LEFT JOIN users u_close ON gs.closed_by = u_close.id 
     WHERE gs.id = ?",
    [$sessionId]
);

if (!$session) {
    die("الجلسة غير موجودة");
}

$cafeOrders = getRows("SELECT * FROM orders WHERE gaming_session_id = ?", [$sessionId]);
$allOrderItems = [];
$cafeGrossTotal = 0;
$prepaidCafeTotal = 0;
foreach ($cafeOrders as $ord) {
    $cafeGrossTotal += (float)$ord['total_amount'];
    $orderPayment = getRow(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'order' AND source_id = ? AND payment_type = 'payment'",
        [$ord['id']]
    );
    $prepaidCafeTotal += (float)($orderPayment['total'] ?? 0);
    $items = getRows("SELECT * FROM order_items WHERE order_id = ?", [$ord['id']]);
    $allOrderItems = array_merge($allOrderItems, $items);
}

$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$receiptFooter = getSetting('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً');
$gamingCharge = (float)($session['gaming_charge'] ?? 0);
$discountAmount = (float)($session['discount_amount'] ?? 0);
$sessionTotal = (float)($session['total_amount'] ?? 0);
$isCompleted = $session['status'] === 'completed';

// Calculate a live preview when the receipt is opened before the session is closed.
if (!$isCompleted) {
    $startedTimestamp = strtotime($session['started_at']);
    $elapsedSeconds = max(0, time() - $startedTimestamp);
    $pausedSeconds = (int)($session['total_paused_seconds'] ?? 0);
    if ($session['status'] === 'paused' && !empty($session['paused_at'])) {
        $pausedSeconds += max(0, time() - strtotime($session['paused_at']));
    }

    $billableMinutes = (int)ceil(max(0, $elapsedSeconds - $pausedSeconds) / 60);
    $billableMinutes = max((int)($session['min_billable_minutes'] ?? 15), $billableMinutes);
    $gamingCharge = round(($billableMinutes / 60) * (float)$session['applied_rate'], 2);
}

$endedAtLabel = !empty($session['ended_at'])
    ? date('h:i A', strtotime($session['ended_at']))
    : 'لم تُغلق بعد';

// حساب الوقت بالساعات والدقائق
$minutes = $isCompleted ? (int)($session['billable_minutes'] ?? 0) : $billableMinutes;
$hours = floor($minutes / 60);
$remMinutes = $minutes % 60;
$durationFormatted = ($hours > 0 ? "$hours ساعة و " : "") . "$remMinutes دقيقة";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة بلايستيشن - <?php echo htmlspecialchars($session['device_name']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 11.5px; line-height: 1.35; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .double-divider { border-top: 2px solid #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; }
        .badge { display: inline-block; padding: 3px 8px; background: #000; color: #fff; font-size: 12px; font-weight: bold; border-radius: 4px; margin: 4px 0; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 2px; font-size: 11px; }
        .items-table td { padding: 4px 2px; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 12.5px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; white-space: nowrap; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; margin-bottom: 10px; }
        .btn-print { background: #ea580c; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ طباعة الفاتورة الآن</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">✖️ إغلاق</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <div class="badge">🎮 فاتورة ألعاب بلايستيشن</div>
        <?php if ($storePhone): ?><p style="font-size:11px;">هاتف: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">الجهاز:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($session['device_name']); ?></td>
        </tr>
        <tr>
            <td>نوع اللعب:</td>
            <td class="text-left fw-bold"><?php echo $session['session_type'] === 'multi' ? 'جماعي (مالتي)' : 'فردي (سنجل)'; ?></td>
        </tr>
        <tr>
            <td>وقت البدء:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($session['started_at'])); ?></td>
        </tr>
        <tr>
            <td>وقت الانتهاء:</td>
            <td class="text-left"><?php echo $endedAtLabel; ?></td>
        </tr>
        <tr>
            <td>إجمالي مدة اللعب:</td>
            <td class="text-left fw-bold"><?php echo $durationFormatted; ?></td>
        </tr>
        <tr>
            <td>سعر الساعة المطبق:</td>
            <td class="text-left"><?php echo number_format($session['applied_rate'], 2); ?> ج.م / ساعة</td>
        </tr>
        <?php if (!empty($session['customer_name'])): ?>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($session['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="double-divider"></div>

    <?php if (!empty($allOrderItems)): ?>
    <div class="divider"></div>
    <div style="font-size:11px; font-weight:bold; margin-bottom:4px;">تفاصيل طلبات الكافيه المضافة:</div>
    <table class="items-table">
        <?php foreach ($allOrderItems as $it): ?>
        <tr>
            <td>• <?php echo htmlspecialchars($it['product_name']); ?> <?php echo $it['variant_name'] ? "({$it['variant_name']})" : ""; ?></td>
            <td class="text-center">x<?php echo $it['quantity']; ?></td>
            <td class="text-left"><?php echo number_format($it['line_total'], 2); ?> ج.م</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php
    $grossTotal = $gamingCharge + $cafeGrossTotal - $discountAmount;
    $remainingTotal = $isCompleted
        ? max(0, $sessionTotal)
        : max(0, $grossTotal - $prepaidCafeTotal);
    ?>

    <div class="double-divider"></div>
    <table class="totals-table">
        <tr>
            <td class="fw-bold">حساب وقت اللعب:</td>
            <td class="text-left fw-bold"><?php echo number_format($gamingCharge, 2); ?> ج.م</td>
        </tr>
        <?php if ($cafeGrossTotal > 0): ?>
        <tr>
            <td>مشروبات وطلبات كافيه:</td>
            <td class="text-left"><?php echo number_format($cafeGrossTotal, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($discountAmount > 0): ?>
        <tr style="color:#dc2626;">
            <td>الخصم:</td>
            <td class="text-left">-<?php echo number_format($discountAmount, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>الإجمالي المطلوب:</td>
            <td class="text-left"><?php echo number_format($grossTotal, 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>المدفوع مسبقاً:</td>
            <td class="text-left"><?php echo number_format($prepaidCafeTotal, 2); ?> ج.م</td>
        </tr>
        <tr style="font-weight:900; color:<?php echo $remainingTotal > 0 ? '#d97706' : '#059669'; ?>;">
            <td>المتبقي للتحصيل:</td>
            <td class="text-left"><?php echo number_format($remainingTotal, 2); ?> ج.م</td>
        </tr>
    </table>

    <div class="divider"></div>
    <div style="text-align:center; font-size:11px; margin-top:6px;">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:3px;">كاشير: <?php echo htmlspecialchars($session['closed_by_name'] ?? 'الكاشير'); ?></p>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 300);
        };
        window.onafterprint = function() {
            setTimeout(function() { try { window.close(); } catch (e) {} }, 100);
        };
    </script>
</body>
</html>
