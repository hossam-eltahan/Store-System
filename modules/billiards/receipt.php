<?php
/**
 * Billiards Session Thermal Receipt (80mm)
 * Ø¨ÙˆÙ† Ø¬Ù„Ø³Ø© Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$sessionId = (int)($_GET['id'] ?? 0);

$session = getRow(
        "SELECT bs.*, bt.display_name as table_name, bt.table_type,
            bt.min_billable_minutes,
            u_open.full_name as opened_by_name,
            u_close.full_name as closed_by_name
     FROM billiard_sessions bs 
     JOIN billiard_tables bt ON bs.table_id = bt.id 
     LEFT JOIN users u_open ON bs.opened_by = u_open.id 
     LEFT JOIN users u_close ON bs.closed_by = u_close.id 
     WHERE bs.id = ?",
    [$sessionId]
);

if (!$session) {
    die("Ø§Ù„Ø¬Ù„Ø³Ø© ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯Ø©");
}

$cafeOrders = getRows("SELECT * FROM orders WHERE billiard_session_id = ?", [$sessionId]);
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

$storeName = getSetting('store_name', 'ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$receiptHeader = getSetting('receipt_header', '');
$receiptFooter = getSetting('receipt_footer', 'Ø´ÙƒØ±Ø§Ù‹ Ù„Ø²ÙŠØ§Ø±ØªÙƒÙ… - Ù†ØªÙ…Ù†Ù‰ Ù„ÙƒÙ… ÙˆÙ‚ØªØ§Ù‹ Ù…Ù…ØªØ¹Ø§Ù‹');

$isHourly = ($session['billing_mode'] === 'hourly');
$isCompleted = $session['status'] === 'completed';
$gamingCharge = (float)($session['gaming_charge'] ?? 0);
$discountAmount = (float)($session['discount_amount'] ?? 0);
$sessionTotal = (float)($session['total_amount'] ?? 0);

if (!$isCompleted) {
    if ($isHourly) {
        $startedTimestamp = strtotime($session['started_at']);
        $elapsedSeconds = max(0, time() - $startedTimestamp);
        $pausedSeconds = (int)($session['total_paused_seconds'] ?? 0);
        if ($session['status'] === 'paused' && !empty($session['paused_at'])) {
            $pausedSeconds += max(0, time() - strtotime($session['paused_at']));
        }
        $minutes = (int)ceil(max(0, $elapsedSeconds - $pausedSeconds) / 60);
        $minutes = max((int)($session['min_billable_minutes'] ?? 15), $minutes);
        $gamingCharge = round(($minutes / 60) * (float)$session['applied_rate'], 2);
    } else {
        $minutes = 0;
        $gamingCharge = (int)($session['game_count'] ?? 0) * (float)$session['applied_rate'];
    }
} else {
    $minutes = (int)($session['billable_minutes'] ?? 0);
}

$grossTotal = max(0, $gamingCharge + $cafeGrossTotal - $discountAmount);
$displayTotal = $isCompleted ? $sessionTotal : $grossTotal;
$endedAtLabel = !empty($session['ended_at'])
    ? date('h:i A', strtotime($session['ended_at']))
    : 'Ù„Ù… ØªÙØºÙ„Ù‚ Ø¨Ø¹Ø¯';
$hours = floor($minutes / 60);
$remMinutes = $minutes % 60;
$durationFormatted = ($hours > 0 ? $hours . " hr " : "") . $remMinutes . " min";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال بلياردو - <?php echo htmlspecialchars($session['table_name']); ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', 'Courier New', monospace, sans-serif;
            -webkit-print-color-adjust: exact;
        }
        body {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 8px 10px;
            background: #fff;
            color: #000;
            font-size: 12px;
            line-height: 1.4;
            direction: rtl;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .header {
            margin-bottom: 8px;
            text-align: center;
        }
        .header h1 {
            font-size: 17px;
            font-weight: 900;
            margin-bottom: 3px;
        }
        .header p {
            font-size: 11px;
            color: #222;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            background: #000;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            margin: 4px 0;
        }
        .meta-table {
            width: 100%;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 4px 2px;
            font-size: 11px;
        }
        .items-table td {
            padding: 4px 2px;
            vertical-align: top;
        }
        .item-row {
            border-bottom: 1px dotted #ccc;
        }
        .variant-tag {
            font-size: 10.5px;
            color: #444;
            display: block;
        }
        .mod-tag {
            font-size: 10px;
            color: #555;
            padding-right: 8px;
            display: block;
        }
        .note-tag {
            font-size: 10px;
            font-style: italic;
            color: #d32f2f;
            display: block;
        }
        .totals-table {
            width: 100%;
            margin-top: 6px;
            font-size: 12px;
        }
        .totals-table td {
            padding: 2px 0;
        }
        .grand-total {
            font-size: 15px;
            font-weight: 900;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0 !important;
        }
        .footer {
            margin-top: 10px;
            font-size: 10.5px;
            text-align: center;
        }
        .order-separator {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin: 4px 0;
            border-top: 1px dotted #999;
            padding-top: 3px;
        }
        .no-print {
            background: #f1f5f9;
            padding: 10px;
            text-align: center;
            border-bottom: 2px solid #cbd5e1;
            margin-bottom: 10px;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-print:hover {
            opacity: 0.9;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">طباعة</button>
        <button class="btn-print" style="background:#475569;" onclick="if(window.self===window.top){window.close();}else if(window.parent&&typeof window.parent.closeReceiptModal==='function'){window.parent.closeReceiptModal();}">إغلاق</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <?php if ($storeAddress): ?><p><?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
        <?php if ($storePhone): ?><p><?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
        <?php if ($receiptHeader): ?><p style="margin-top:3px;"><?php echo htmlspecialchars($receiptHeader); ?></p><?php endif; ?>
    </div>

    <div style="border-top:1px dashed #000;margin:6px 0;"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">الطاولة:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($session['table_name']); ?></td>
        </tr>
        <tr>
            <td>نظام الحساب:</td>
            <td class="text-left"><?php echo $isHourly ? 'بالساعة' : 'بالجيم'; ?></td>
        </tr>
        <?php if ($isHourly): ?>
        <tr>
            <td>وقت البدء:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($session['started_at'])); ?></td>
        </tr>
        <tr>
            <td>الوقت (دقيقة):</td>
            <td class="text-left fw-bold"><?php echo (int)$minutes; ?> دقيقة</td>
        </tr>
        <tr>
            <td>السعر/ساعة:</td>
            <td class="text-left"><?php echo number_format($session['applied_rate'], 2); ?> ج.م</td>
        </tr>
        <?php else: ?>
        <tr>
            <td>عدد الأجوام:</td>
            <td class="text-left fw-bold"><?php echo (int)$session['game_count']; ?></td>
        </tr>
        <tr>
            <td>السعر/جيم:</td>
            <td class="text-left"><?php echo number_format($session['applied_rate'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($session['customer_name'])): ?>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($session['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div style="border-top:2px solid #000;margin:6px 0;"></div>

    <?php if (!empty($allOrderItems)): ?>
    <div style="font-size:12px; font-weight:bold; margin-bottom:5px; text-align:center;">طلبات كافيه</div>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-right">الصنف</th>
                <th class="text-center">العدد</th>
                <th class="text-center">السعر</th>
                <th class="text-left">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allOrderItems as $it): ?>
            <tr>
                <td class="text-right"><?php echo htmlspecialchars($it['product_name']); ?></td>
                <td class="text-center">x<?php echo $it['quantity']; ?></td>
                <td class="text-center"><?php echo number_format($it['unit_price'], 2); ?></td>
                <td class="text-left"><?php echo number_format($it['line_total'], 2); ?> ج.م</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="border-top:2px solid #000;margin:6px 0;"></div>
    <?php endif; ?>

    <table class="totals-table">
        <tr>
            <td>حساب الطاولة:</td>
            <td class="text-left"><?php echo number_format($gamingCharge, 2); ?> ج.م</td>
        </tr>
        <?php if ($cafeGrossTotal > 0): ?>
        <tr>
            <td>طلبات كافيه:</td>
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
            <td>الإجمالي:</td>
            <td class="text-left"><?php echo number_format($displayTotal, 2); ?> ج.م</td>
        </tr>
        <?php if ($prepaidCafeTotal > 0): ?>
        <tr>
            <td>مدفوع مسبقاً:</td>
            <td class="text-left"><?php echo number_format($prepaidCafeTotal, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
    </table>

    <div style="text-align:center; font-size:11px; margin-top:6px;">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:3px;">الكاشير: <?php echo htmlspecialchars($session['closed_by_name'] ?? $session['opened_by_name'] ?? 'الكاشير'); ?> | نظام كاشير عروسة النيل v2.0</p>
    </div>

    <script>
        if (window.self === window.top) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            };
        }
        window.onafterprint = function() {
            setTimeout(function() {
                try {
                    if (window.self === window.top) {
                        window.close();
                    } else if (window.parent && typeof window.parent.closeReceiptModal === 'function') {
                        window.parent.closeReceiptModal();
                    }
                } catch (e) {}
            }, 100);
        };
    </script>
</body>
</html>
