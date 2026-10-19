<?php
/**
 * Settlement Receipt — إيصال محاسبة فواتير عميل (مدمج) — Thermal 80mm (same shape as POS receipts)
 * يعرض كل عناصر التسوية من customer_settlements.per_item في بون حراري موحد.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['customers.view', 'payments.customer', 'payments.view']);

$id = (int)($_GET['id'] ?? 0);

$settlement = getRow(
    "SELECT s.*, c.name AS customer_name, c.phone AS customer_phone
     FROM customer_settlements s
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE s.id = ?",
    [$id]
);

if (!$settlement) {
    die('إيصال المحاسبة غير موجود');
}

$perItem = json_decode((string)$settlement['per_item'], true);
if (!is_array($perItem)) {
    $perItem = [];
}

$leftTotal = 0.0;
foreach ($perItem as $it) {
    $leftTotal = max($leftTotal, 0.0) + max(0, (float)($it['total_amount'] ?? 0) - (float)($it['applied'] ?? 0));
}
$leftTotal = round($leftTotal, 2);

$storeName = getSetting('store_name', 'المحل');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$receiptHeader = getSetting('receipt_header', '');
$receiptFooter = getSetting('receipt_footer', 'شكراً لتعاملكم معنا');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال محاسبة #<?php echo htmlspecialchars($settlement['settlement_number']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 12px; line-height: 1.4; direction: rtl; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; margin-bottom: 3px; }
        .header p { font-size: 11px; color: #222; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 2px; font-size: 10.5px; }
        .items-table td { padding: 4px 2px; vertical-align: top; text-align: center; }
        .items-table .item-name { text-align: right; }
        .items-table .item-row { border-bottom: 1px dotted #ccc; }
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
        <button class="btn-print" style="background:#475569;" onclick="if(window.self===window.top){window.close();}else if(window.parent&&typeof window.parent.closeReceiptModal==='function'){window.parent.closeReceiptModal();}">إغلاق</button>
    </div>

    <div class="header text-center">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <?php if ($storeAddress): ?><p><?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
        <?php if ($storePhone): ?><p>هاتف: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
        <?php if ($receiptHeader): ?><p style="margin-top:3px;"><?php echo htmlspecialchars($receiptHeader); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">إيصال محاسبة فواتير:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($settlement['settlement_number']); ?></td>
        </tr>
        <tr>
            <td>التاريخ والوقت:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($settlement['created_at'])); ?></td>
        </tr>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($settlement['customer_name'] ?: '-'); ?></td>
        </tr>
        <?php if (!empty($settlement['customer_phone'])): ?>
        <tr>
            <td>التليفون:</td>
            <td class="text-left"><?php echo htmlspecialchars($settlement['customer_phone']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>عدد الفواتير:</td>
            <td class="text-left"><?php echo count($perItem); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>الفاتورة / الطلب</th>
                <th>الإجمالي</th>
                <th>المحصل عليه</th>
                <th>المتبقي</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($perItem as $it): ?>
            <tr class="item-row">
                <td><?php echo $i++; ?></td>
                <td class="item-name" style="font-size:10.5px;">
                    <?php echo htmlspecialchars(($it['label'] ?? '') . ' — ' . ($it['ref_number'] ?? '')); ?>
                    <span style="font-size:9.5px; color:#444; display:block;"><?php echo date('d/m/Y h:i A', strtotime($it['item_date'] ?? $settlement['created_at'])); ?></span>
                </td>
                <td><?php echo number_format((float)($it['total_amount'] ?? 0), 2); ?></td>
                <td class="fw-bold"><?php echo number_format((float)($it['applied'] ?? 0), 2); ?></td>
                <td><?php echo number_format((float)($it['left_after'] ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>إجمالي الفواتير المحسوبة:</td>
            <td class="text-left"><?php echo number_format((float)$settlement['total_amount'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>المتبقي غير محاسب:</td>
            <td class="text-left"><?php echo number_format($leftTotal, 2); ?> ج.م</td>
        </tr>
        <tr class="grand-total">
            <td>إجمالي المحصل:</td>
            <td class="text-left"><?php echo number_format((float)$settlement['paid_amount'], 2); ?> ج.م</td>
        </tr>
    </table>

    <?php if (!empty($settlement['notes'])): ?>
    <div class="divider"></div>
    <p style="font-size:10.5px;"><?php echo htmlspecialchars((string)$settlement['notes']); ?></p>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="footer">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="margin-top:4px;">توقيع العميل: ______________</p>
    </div>
</body>
</html>
