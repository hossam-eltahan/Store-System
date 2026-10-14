<?php
/**
 * Table Session Combined Receipt (Thermal 80mm)
 * بون فاتورة طاولة كافيه عروسة النيل - يجمع كل الطلبات في فاتورة واحدة
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$sessionId = (int)($_GET['id'] ?? 0);

// جلب بيانات الجلسة
$session = getRow(
    "SELECT ts.*, 
            ct.display_name as table_name, ct.section,
            u_open.full_name as opened_by_name,
            u_close.full_name as closed_by_name
     FROM table_sessions ts 
     JOIN cafe_tables ct ON ts.table_id = ct.id 
     LEFT JOIN users u_open ON ts.opened_by = u_open.id 
     LEFT JOIN users u_close ON ts.closed_by = u_close.id 
     WHERE ts.id = ?",
    [$sessionId]
);

if (!$session) {
    die("الجلسة غير موجودة");
}

// جلب كل الطلبات المرتبطة بالجلسة
$orders = getRows(
    "SELECT o.*, u.full_name as cashier_name 
     FROM orders o 
     LEFT JOIN users u ON o.created_by = u.id 
     WHERE o.table_session_id = ? 
     ORDER BY o.created_at ASC",
    [$sessionId]
);
$firstOrder = $orders[0] ?? null;

// جلب كل الأصناف من جميع الطلبات
$allItems = [];
$grandSubtotal = 0;
$grandDiscount = 0;
$grandTax = 0;
$grandPaid = 0;
$grandTotal = 0;

foreach ($orders as $ord) {
    $items = getRows("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC", [$ord['id']]);
    foreach ($items as &$it) {
        $it['modifiers'] = getRows("SELECT * FROM order_item_modifiers WHERE order_item_id = ?", [$it['id']]);
        $it['order_number'] = $ord['order_number'];
    }
    $allItems = array_merge($allItems, $items);
    $grandSubtotal += floatval($ord['subtotal']);
    $grandDiscount += floatval($ord['discount_amount']);
    $grandTax += floatval($ord['tax_amount'] ?? 0);
    $orderPayment = getRow(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'order' AND source_id = ? AND payment_type = 'payment'",
        [$ord['id']]
    );
    $grandPaid += (float)($orderPayment['total'] ?? 0);
    $grandTotal += floatval($ord['total_amount']);
}
$sessionDiscount = (float)($session['discount_amount'] ?? 0);
$grandTotalAfterDiscount = max(0, $grandTotal - $sessionDiscount);
$sessionPayment = getRow(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'table_session' AND source_id = ? AND payment_type = 'payment'",
    [$sessionId]
);
$paidAtClose = (float)($sessionPayment['total'] ?? 0);
$grandDue = max(0, $grandTotalAfterDiscount - $grandPaid - $paidAtClose);

// إعدادات المتجر
$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$receiptHeader = getSetting('receipt_header', 'أهلاً بكم في كافيه عروسة النيل');
$receiptFooter = getSetting('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة طاولة - <?php echo htmlspecialchars($session['table_name']); ?></title>
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
        <button class="btn-print" onclick="window.print()">🖨️ طباعة البون الآن</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">✖️ إغلاق</button>
        <a href="index.php" class="btn-print" style="background:#059669; text-decoration:none; display:inline-block;">🪑 الطاولات</a>
    </div>

    <!-- رأس الفاتورة -->
    <div class="header text-center">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <?php if ($storeAddress): ?><p><?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
        <?php if ($storePhone): ?><p>هاتف: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
        <?php if ($receiptHeader): ?><p style="margin-top:3px;"><?php echo htmlspecialchars($receiptHeader); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">رقم الطلب:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($firstOrder['order_number'] ?? 'TABLE-' . $sessionId); ?></td>
        </tr>
        <tr>
            <td>التاريخ والوقت:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($firstOrder['created_at'] ?? $session['opened_at'])); ?></td>
        </tr>
        <tr>
            <td>الكاشير:</td>
            <td class="text-left"><?php echo htmlspecialchars($firstOrder['cashier_name'] ?? $session['opened_by_name'] ?? 'الكاشير'); ?></td>
        </tr>
        <tr>
            <td>نوع الطلب:</td>
            <td class="text-left fw-bold">صالة (طاولة) 🪑 - <?php echo htmlspecialchars($session['table_name']); ?></td>
        </tr>
        <tr>
            <td>القسم:</td>
            <td class="text-left"><?php echo ($session['section'] === 'outdoor') ? 'خارجي' : 'الصالة الرئيسية'; ?></td>
        </tr>
        <?php if (!empty($session['customer_name'])): ?>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($session['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>عدد الطلبات:</td>
            <td class="text-left fw-bold"><?php echo count($orders); ?> طلب</td>
        </tr>
    </table>

    <div class="double-divider"></div>

    <?php if (!empty($allItems)): ?>
    <!-- تفاصيل جميع الأصناف مجمعة -->
    <div style="font-size:12px; font-weight:bold; margin-bottom:5px; text-align:center;">📋 تفاصيل الطلبات</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;" class="text-right">الصنف</th>
                <th style="width: 15%;" class="text-center">العدد</th>
                <th style="width: 15%;" class="text-center">السعر</th>
                <th style="width: 20%;" class="text-left">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allItems as $item): ?>
            <tr class="item-row">
                <td class="text-right">
                    <span class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                    <?php if (!empty($item['variant_name'])): ?>
                        <span class="variant-tag">(<?php echo htmlspecialchars($item['variant_name']); ?>)</span>
                    <?php endif; ?>
                    <?php if (!empty($item['modifiers'])): ?>
                        <?php foreach ($item['modifiers'] as $mod): ?>
                            <span class="mod-tag">+ <?php echo htmlspecialchars($mod['modifier_name']); ?> (<?php echo number_format($mod['modifier_price'], 2); ?>)</span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($item['item_notes'])): ?>
                        <span class="note-tag">⚠️ <?php echo htmlspecialchars($item['item_notes']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold" style="font-size: 13px;">x<?php echo $item['quantity']; ?></td>
                <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-left fw-bold"><?php echo number_format($item['line_total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center; padding:10px; font-size:12px; color:#666;">
        لا توجد طلبات مسجلة على هذه الطاولة
    </div>
    <?php endif; ?>

    <!-- الإجماليات -->
    <div class="double-divider"></div>
    <table class="totals-table">
        <tr>
            <td>المجموع الفرعي:</td>
            <td class="text-left"><?php echo number_format($grandSubtotal, 2); ?> ج.م</td>
        </tr>
        <?php if ($grandDiscount > 0): ?>
        <tr style="color: #dc2626;">
            <td>الخصم:</td>
            <td class="text-left">-<?php echo number_format($grandDiscount, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($sessionDiscount > 0): ?>
        <tr style="color:#dc2626;">
            <td>خصم الجلسة:</td>
            <td class="text-left">-<?php echo number_format($sessionDiscount, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($grandTax > 0): ?>
        <tr>
            <td>الضريبة والخدمة:</td>
            <td class="text-left"><?php echo number_format($grandTax, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>💰 إجمالي الطلبات:</td>
            <td class="text-left"><?php echo number_format($grandTotalAfterDiscount, 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>المدفوع مسبقاً:</td>
            <td class="text-left"><?php echo number_format($grandPaid, 2); ?> ج.م</td>
        </tr>
        <?php if ($paidAtClose > 0): ?>
        <tr>
            <td>المدفوع عند الإغلاق:</td>
            <td class="text-left"><?php echo number_format($paidAtClose, 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <tr style="font-weight: 900; color: <?php echo $grandDue > 0 ? '#d97706' : '#059669'; ?>;">
            <td>المتبقي للتحصيل:</td>
            <td class="text-left"><?php echo number_format($grandDue, 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>طريقة الدفع:</td>
            <td class="text-left fw-bold">
                <?php 
                $methods = ['cash' => 'نقداً (كاش)', 'card' => 'فيزا / بطاقة', 'vodafone_cash' => 'فودافون كاش', 'instapay' => 'إنستاباي'];
                // get payment method from the latest payment record
                $payRecord = getRow("SELECT payment_method FROM payment_records WHERE source_type = 'table_session' AND source_id = ? ORDER BY id DESC LIMIT 1", [$sessionId]);
                $pm = $payRecord['payment_method'] ?? 'cash';
                echo $methods[$pm] ?? $pm;
                ?>
            </td>
        </tr>
    </table>

    <!-- التذييل -->
    <div class="footer">
        <div class="divider"></div>
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:4px;">
            كاشير: <?php echo htmlspecialchars($session['closed_by_name'] ?? $session['opened_by_name'] ?? 'الكاشير'); ?>
             | نظام كاشير عروسة النيل v2.0
        </p>
    </div>

    <script>
        // طباعة تلقائية عند فتح الصفحة
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
</body>
</html>
