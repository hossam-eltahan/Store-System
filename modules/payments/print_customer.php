<?php
/**
 * Print Customer Payment Receipt — Thermal 80mm (same shape as POS/gaming/billiards receipts)
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['payments.customer', 'payments.view']);

$id = $_GET['id'] ?? 0;

$payment = getRow(
    "SELECT p.*, c.phone as customer_phone
     FROM payments p
     LEFT JOIN customers c ON p.entity_id = c.id
     WHERE p.id = ? AND p.type = 'customer'",
    [$id]
);

if (!$payment) {
    die('الإيصال غير موجود');
}

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
    <title>إيصال #<?php echo htmlspecialchars($payment['payment_number']); ?></title>
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
        .paid-box { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 6px 0; text-align: center; margin: 6px 0; }
        .paid-box .title { font-size: 11px; }
        .paid-box .amount { font-size: 18px; font-weight: 900; }
        .balance-table { width: 100%; font-size: 11.5px; margin-top: 4px; }
        .balance-table td { padding: 2px 0; }
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
            <td class="fw-bold">إيصال تحصيل من عميل:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($payment['payment_number']); ?></td>
        </tr>
        <tr>
            <td>التاريخ والوقت:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($payment['created_at'])); ?></td>
        </tr>
        <tr>
            <td>العميل:</td>
            <td class="text-left"><?php echo htmlspecialchars($payment['entity_name'] ?: '-'); ?></td>
        </tr>
        <?php if (!empty($payment['customer_phone'])): ?>
        <tr>
            <td>التليفون:</td>
            <td class="text-left"><?php echo htmlspecialchars($payment['customer_phone']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>طريقة الدفع:</td>
            <td class="text-left"><?php echo htmlspecialchars((string)$payment['payment_method']); ?></td>
        </tr>
        <tr>
            <td>المسؤول:</td>
            <td class="text-left"><?php echo htmlspecialchars($payment['handled_by'] ?: '-'); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="paid-box">
        <div class="title">المبلغ المدفوع</div>
        <div class="amount"><?php echo number_format((float)$payment['amount'], 2); ?> ج.م</div>
    </div>

    <table class="balance-table">
        <tr>
            <td>المديونية قبل الدفع:</td>
            <td class="text-left"><?php echo number_format(abs((float)$payment['old_balance']), 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>المديونية بعد الدفع:</td>
            <td class="text-left fw-bold"><?php echo number_format(abs((float)$payment['new_balance']), 2); ?> ج.م<?php echo (float)$payment['new_balance'] >= 0 ? ' ✓' : ''; ?></td>
        </tr>
    </table>

    <?php if (!empty($payment['notes'])): ?>
    <div class="divider"></div>
    <p style="font-size:10.5px;"><?php echo htmlspecialchars((string)$payment['notes']); ?></p>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="footer">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="margin-top:4px;">توقيع العميل: ______________</p>
    </div>
</body>
</html>
