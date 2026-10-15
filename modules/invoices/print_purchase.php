<?php
/**
 * Print Purchase Invoice (Thermal 80mm)
 * بون طباعة فاتورة شراء — يفتح داخل نافذة openReceiptModal مثل باقي الفواتير
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['invoices.purchase.view', 'invoices.purchase.create', 'reports.view']);

$id = (int)($_GET['id'] ?? 0);

$invoice = getRow(
    "SELECT i.*,
            w.name as warehouse_name,
            s.name as supplier_name_db,
            s.phone as supplier_phone_db
     FROM invoices i
     LEFT JOIN suppliers s ON i.supplier_id = s.id
     LEFT JOIN warehouses w ON i.warehouse_id = w.id
     WHERE i.id = ? AND i.type = 'purchase'",
    [$id]
);

if (!$invoice) {
    die('الفاتورة غير موجودة');
}

$items = getRows("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$id]);

$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$storePhone2 = getSetting('store_phone2', '');
$receiptFooter = getSetting('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً');
$currency = getSetting('currency', 'ج.م');

$supplierName = $invoice['supplier_name_db'] ?? $invoice['customer_name'] ?: '-';
$supplierPhone = $invoice['supplier_phone_db'] ?? $invoice['customer_phone'] ?: '-';

$oldDebt = floatval($invoice['old_balance'] ?? 0) > 0 ? floatval($invoice['old_balance']) : 0;
$totalRemaining = (float)$invoice['remaining_amount'] + $oldDebt;
$isDraft = (($invoice['status'] ?? 'approved') === 'draft');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة شراء #<?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
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
        .status-line { text-align: center; font-size: 11px; font-weight: 700; margin-bottom: 4px; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 2px; font-size: 11px; }
        .items-table td { padding: 4px 2px; vertical-align: top; }
        .item-row { border-bottom: 1px dotted #ccc; }
        .unit-tag { font-size: 10.5px; color: #444; display: block; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 15px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; }
        .footer { margin-top: 10px; font-size: 10.5px; text-align: center; }
        .sig-line { display: flex; justify-content: space-between; margin-top: 8px; font-size: 10.5px; }
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

    <div class="doc-title">📦 فاتورة شراء - <?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
    <div class="status-line"><?php echo $isDraft ? '📝 مسودة (بدون حركة مخزون)' : '✅ معتمدة'; ?></div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">المورد:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($supplierName); ?></td>
        </tr>
        <tr>
            <td>تليفون المورد:</td>
            <td class="text-left"><?php echo htmlspecialchars($supplierPhone); ?></td>
        </tr>
        <tr>
            <td>المخزن الوجهة:</td>
            <td class="text-left"><?php echo htmlspecialchars($invoice['warehouse_name'] ?? 'المخزن الرئيسي'); ?></td>
        </tr>
        <tr>
            <td>التاريخ:</td>
            <td class="text-left"><?php echo date('Y/m/d', strtotime($invoice['date'])); ?></td>
        </tr>
        <tr>
            <td>المستلم:</td>
            <td class="text-left"><?php echo htmlspecialchars($invoice['handled_by'] ?? ''); ?></td>
        </tr>
        <tr>
            <td>الدفع:</td>
            <td class="text-left"><?php echo htmlspecialchars($invoice['payment_method'] ?? ''); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <?php if (!empty($items)): ?>
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
            <?php
                $itemName = $item['product_name'] ?? $item['item_name'] ?? '';
                $pUnit = $item['purchase_unit'] ?? $item['unit'];
                $factorTxt = ($item['conversion_factor'] !== null) ? ' (×' . rtrim(rtrim(number_format((float)$item['conversion_factor'], 4), '0'), '.') . ')' : '';
                $stockTxt = ($item['stock_quantity'] !== null) ? ((int)$item['stock_quantity'] . ' ' . $item['unit']) : '';
            ?>
            <tr class="item-row">
                <td class="text-right">
                    <span class="fw-bold"><?php echo htmlspecialchars($itemName); ?></span>
                    <span class="unit-tag"><?php echo htmlspecialchars($pUnit) . $factorTxt; ?><?php echo $stockTxt !== '' ? ' ← ' . htmlspecialchars($stockTxt) : ''; ?></span>
                </td>
                <td class="text-center fw-bold" style="font-size: 13px;">x<?php echo rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.'); ?></td>
                <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-left fw-bold"><?php echo number_format($item['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center; padding:10px; font-size:12px; color:#666;">لا توجد أصناف في هذه الفاتورة.</div>
    <?php endif; ?>

    <div class="divider"></div>
    <table class="totals-table">
        <tr>
            <td>الإجمالي:</td>
            <td class="text-left"><?php echo number_format($invoice['total_amount'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <?php if ((float)$invoice['discount'] > 0): ?>
        <tr style="color:#dc2626;">
            <td>الخصم:</td>
            <td class="text-left">-<?php echo number_format($invoice['discount'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>الصافي:</td>
            <td class="text-left"><?php echo number_format((float)$invoice['total_amount'] - (float)$invoice['discount'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <tr>
            <td>المدفوع:</td>
            <td class="text-left"><?php echo number_format($invoice['paid_amount'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <tr>
            <td class="fw-bold">الباقي للمورد:</td>
            <td class="text-left fw-bold"><?php echo number_format($totalRemaining, 2); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <?php if (!empty($invoice['notes'])): ?>
        <tr>
            <td>ملاحظات:</td>
            <td class="text-left"><?php echo htmlspecialchars($invoice['notes']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="footer">
        <div class="divider"></div>
        <div class="sig-line">
            <span>توقيع المورد: ـــــــــ</span>
            <span>توقيع المستلم: ـــــــــ</span>
        </div>
        <p style="margin-top:6px;"><?php echo htmlspecialchars($receiptFooter); ?></p>
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
