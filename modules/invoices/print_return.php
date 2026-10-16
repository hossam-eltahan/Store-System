<?php
/**
 * Print Return Receipt (Thermal 80mm)
 * بون مرتجع — يفتح داخل نافذة openReceiptModal مثل باقي الفواتير
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['returns.view', 'returns.create', 'invoices.purchase.view', 'pos.view']);

$id = (int)($_GET['id'] ?? 0);

$ret = getRow(
    "SELECT r.*, u.full_name AS user_name
     FROM invoice_returns r
     LEFT JOIN users u ON u.id = r.created_by
     WHERE r.id = ?",
    [$id]
);
if (!$ret) {
    die('المرتجع غير موجود');
}

if ($ret['invoice_kind'] === 'pos') {
    $doc = getRow("SELECT order_number AS num FROM orders WHERE id = ?", [(int)$ret['invoice_id']]);
    $docLabel = 'طلب كاشير';
} else {
    $doc = getRow("SELECT invoice_number AS num FROM invoices WHERE id = ?", [(int)$ret['invoice_id']]);
    $docLabel = $ret['invoice_kind'] === 'purchase' ? 'فاتورة شراء' : 'فاتورة بيع';
}
$docNum = $doc['num'] ?? ('#' . $ret['invoice_id']);

$items = getRows("SELECT * FROM invoice_return_items WHERE return_id = ? ORDER BY id ASC", [$id]);

$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$storePhone2 = getSetting('store_phone2', '');
$receiptFooter = getSetting('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً');
$currency = getSetting('currency', 'ج.م');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مرتجع #<?php echo htmlspecialchars($ret['return_number']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 12px; line-height: 1.4; direction: rtl; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; margin-bottom: 3px; }
        .header p { font-size: 11px; color: #222; }
        .doc-title { text-align: center; font-size: 14px; font-weight: 900; margin: 6px 0; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 2px; font-size: 11px; }
        .items-table td { padding: 4px 2px; vertical-align: top; }
        .item-row { border-bottom: 1px dotted #ccc; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 15px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; }
        .footer { margin-top: 10px; font-size: 10.5px; text-align: center; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; border-bottom: 2px solid #cbd5e1; margin-bottom: 10px; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">طباعة</button>
        <button class="btn-print" style="background:#475569;" onclick="if(window.self!==window.top&&window.parent&&typeof window.parent.closeReceiptModal==='function'){window.parent.closeReceiptModal();}else if(window.history.length>1){window.history.back();}else{window.close();}">إغلاق</button>
    </div>

    <div class="header text-center">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <?php if ($storeAddress): ?><p>العنوان: <?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
        <?php if ($storePhone): ?><p>تليفون: <?php echo htmlspecialchars($storePhone); ?><?php echo $storePhone2 ? ' - ' . htmlspecialchars($storePhone2) : ''; ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <div class="doc-title">↩️ بون مرتجع - <?php echo htmlspecialchars($ret['return_number']); ?></div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">مرجع الإرجاع:</td>
            <td class="text-left"><?php echo htmlspecialchars($docLabel . ' ' . $docNum); ?></td>
        </tr>
        <tr>
            <td>التاريخ:</td>
            <td class="text-left"><?php echo htmlspecialchars($ret['return_date']); ?></td>
        </tr>
        <tr>
            <td>المستخدم:</td>
            <td class="text-left"><?php echo htmlspecialchars($ret['user_name'] ?? ''); ?></td>
        </tr>
        <?php if (!empty($ret['notes'])): ?>
        <tr>
            <td>ملاحظات:</td>
            <td class="text-left"><?php echo htmlspecialchars($ret['notes']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="divider"></div>

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
            <?php foreach ($items as $item): ?>
            <tr class="item-row">
                <td class="text-right fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td class="text-center fw-bold">x<?php echo rtrim(rtrim(number_format((float)$item['quantity'], 3), '0'), '.'); ?></td>
                <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-left fw-bold"><?php echo number_format($item['line_total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>
    <table class="totals-table">
        <tr class="grand-total">
            <td>إجمالي المرتجع:</td>
            <td class="text-left"><?php echo number_format($ret['total_amount'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
    </table>

    <div class="footer">
        <div class="divider"></div>
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
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
