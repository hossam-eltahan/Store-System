<?php
/**
 * Billiards Session Thermal Receipt (80mm)
 * بون جلسة بلياردو كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$sessionId = (int)($_GET['id'] ?? 0);

$session = getRow(
    "SELECT bs.*, bt.display_name as table_name, bt.table_type,
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
    die("الجلسة غير موجودة");
}

$cafeOrders = getRows("SELECT * FROM orders WHERE billiard_session_id = ?", [$sessionId]);
$allOrderItems = [];
foreach ($cafeOrders as $ord) {
    $items = getRows("SELECT * FROM order_items WHERE order_id = ?", [$ord['id']]);
    $allOrderItems = array_merge($allOrderItems, $items);
}

$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storePhone = getSetting('store_phone', '');
$receiptFooter = getSetting('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً');

$isHourly = ($session['billing_mode'] === 'hourly');
$minutes = (int)$session['billable_minutes'];
$hours = floor($minutes / 60);
$remMinutes = $minutes % 60;
$durationFormatted = ($hours > 0 ? "$hours ساعة و " : "") . "$remMinutes دقيقة";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة بلياردو - <?php echo htmlspecialchars($session['table_name']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 12px; line-height: 1.4; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .double-divider { border-top: 2px solid #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; }
        .badge { display: inline-block; padding: 3px 8px; background: #000; color: #fff; font-size: 12px; font-weight: bold; border-radius: 4px; margin: 4px 0; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table td { padding: 4px 2px; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 16px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; margin-bottom: 10px; }
        .btn-print { background: #059669; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
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
        <div class="badge">🎱 فاتورة صالة البلياردو</div>
        <?php if ($storePhone): ?><p style="font-size:11px;">هاتف: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">الطاولة:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($session['table_name']); ?></td>
        </tr>
        <tr>
            <td>نظام الحساب:</td>
            <td class="text-left fw-bold"><?php echo $isHourly ? 'بالساعة (تايمر)' : "بالجيم ({$session['game_count']} جيم)"; ?></td>
        </tr>
        <tr>
            <td>وقت البدء:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($session['started_at'])); ?></td>
        </tr>
        <tr>
            <td>وقت الانتهاء:</td>
            <td class="text-left"><?php echo date('h:i A', strtotime($session['ended_at'])); ?></td>
        </tr>
        <?php if ($isHourly): ?>
        <tr>
            <td>إجمالي وقت اللعب:</td>
            <td class="text-left fw-bold"><?php echo $durationFormatted; ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($session['customer_name'])): ?>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($session['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="double-divider"></div>

    <table class="totals-table">
        <tr>
            <td class="fw-bold">حساب طاولة البلياردو:</td>
            <td class="text-left fw-bold"><?php echo number_format($session['gaming_charge'], 2); ?> ج.م</td>
        </tr>
        <?php if ($session['cafe_orders_total'] > 0): ?>
        <tr>
            <td>مشروبات وطلبات كافيه:</td>
            <td class="text-left"><?php echo number_format($session['cafe_orders_total'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($session['discount_amount'] > 0): ?>
        <tr style="color:#dc2626;">
            <td>الخصم:</td>
            <td class="text-left">-<?php echo number_format($session['discount_amount'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>الإجمالي المطلوب:</td>
            <td class="text-left"><?php echo number_format($session['total_amount'], 2); ?> ج.م</td>
        </tr>
    </table>

    <?php if (!empty($allOrderItems)): ?>
    <div class="divider"></div>
    <div style="font-size:11px; font-weight:bold; margin-bottom:4px;">تفاصيل المشروبات المضافة:</div>
    <table class="items-table">
        <?php foreach ($allOrderItems as $it): ?>
        <tr>
            <td>• <?php echo htmlspecialchars($it['product_name']); ?></td>
            <td class="text-center">x<?php echo $it['quantity']; ?></td>
            <td class="text-left"><?php echo number_format($it['line_total'], 2); ?> ج.م</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <div class="divider"></div>
    <div style="text-align:center; font-size:11px; margin-top:6px;">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:3px;">كاشير: <?php echo htmlspecialchars($session['closed_by_name'] ?? 'الكاشير'); ?></p>
    </div>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 300); };
    </script>
</body>
</html>
