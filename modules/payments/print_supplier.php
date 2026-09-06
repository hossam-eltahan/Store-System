<?php
/**
 * Print Supplier Payment Receipt - Compact Layout
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['payments.supplier', 'payments.view']);

$id = $_GET['id'] ?? 0;
$returnUrl = hasPermission('payments.view') ? 'index.php' : (hasPermission('payments.supplier') ? 'pay_supplier.php' : '../../index.php');

$payment = getRow(
    "SELECT p.*, s.phone as supplier_phone
     FROM payments p
     LEFT JOIN suppliers s ON p.entity_id = s.id
     WHERE p.id = ? AND p.type = 'supplier'",
    [$id]
);

if (!$payment) {
    die('الإيصال غير موجود');
}

// Get settings
$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'المحل';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storePhone2 = $settings['store_phone2'] ?? '';
$storeLogo = $settings['store_logo'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

$pageTitle = 'إيصال دفع ' . $payment['payment_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; direction: rtl; font-size: 11px; }
        
        .no-print { text-align: center; padding: 8px; background: #10b981; }
        .no-print .btn { display: inline-block; padding: 6px 15px; margin: 0 3px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; color: white; }
        .btn-print { background: white; color: #10b981; }
        .btn-close { background: #dc2626; }
        .btn-new { background: #f59e0b; }
        
        .print-area { max-width: 190mm; margin: 10px auto; background: white; padding: 5mm; }
        
        .header { display: flex; align-items: center; justify-content: center; gap: 12px; padding-bottom: 5px; border-bottom: 2px solid #10b981; margin-bottom: 5px; }
        .header-logo { width: 50px; height: 50px; object-fit: contain; }
        .header-text { text-align: center; }
        .store-name { font-size: 18px; font-weight: bold; color: #10b981; }
        .header-info { font-size: 10px; color: #666; }
        
        .receipt-type { text-align: center; border: 2px solid #10b981; background: #d1fae5; padding: 5px; margin: 5px 0; font-size: 14px; font-weight: bold; color: #065f46; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { padding: 4px 6px; border: 1px solid #10b981; font-size: 11px; }
        .info-table .label { font-weight: bold; background: #d1fae5; width: 100px; }
        
        .amount-box { background: #3b82f6; color: white; padding: 10px; border-radius: 6px; text-align: center; margin: 8px 0; }
        .amount-box .title { font-size: 11px; margin-bottom: 3px; }
        .amount-box .amount { font-size: 24px; font-weight: bold; }
        
        .balance-section { display: flex; gap: 10px; margin: 8px 0; }
        .balance-box { flex: 1; padding: 8px; border-radius: 6px; text-align: center; font-size: 11px; }
        .balance-before { background: #fee2e2; border: 1px solid #dc2626; }
        .balance-after { background: #d1fae5; border: 1px solid #10b981; }
        .balance-box .amount { font-size: 16px; font-weight: bold; margin-top: 3px; }
        
        .notes-box { background: #f3f4f6; padding: 6px; border-radius: 4px; margin-bottom: 8px; font-size: 10px; }
        
        .footer-sig { text-align: center; padding-top: 8px; border-top: 2px dashed #10b981; margin-top: 8px; font-size: 10px; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .print-area { max-width: 100%; margin: 0; padding: 4mm; box-shadow: none; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ طباعة</button>
        <button class="btn btn-close" onclick="goBack()">❌ إغلاق</button>
        <a href="pay_supplier.php" class="btn btn-new">➕ دفع جديد</a>
    </div>
    
    <div class="print-area">
        <div class="header">
            <?php if ($storeLogo): ?>
            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="Logo" class="header-logo">
            <?php endif; ?>
            <div class="header-text">
                <div class="store-name"><?php echo $storeName; ?></div>
                <div class="header-info">
                    <?php if ($storeAddress): ?>العنوان: <?php echo $storeAddress; ?><?php endif; ?>
                    <?php if ($storePhone): ?> | ت: <?php echo $storePhone; ?><?php endif; ?>
                    <?php if ($storePhone2): ?> - <?php echo $storePhone2; ?><?php endif; ?>
                </div>
            </div>
            <?php if ($storeLogo): ?>
            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="" class="header-logo" style="visibility:hidden;">
            <?php endif; ?>
        </div>
        
        <div class="receipt-type">💸 إيصال دفع للمورد</div>
        
        <table class="info-table">
            <tr>
                <td class="label">رقم الإيصال:</td>
                <td><strong><?php echo $payment['payment_number']; ?></strong></td>
                <td class="label">التاريخ:</td>
                <td><?php echo date('Y/m/d', strtotime($payment['payment_date'])); ?></td>
            </tr>
            <tr>
                <td class="label">المورد:</td>
                <td><strong><?php echo $payment['entity_name']; ?></strong></td>
                <td class="label">التليفون:</td>
                <td><?php echo $payment['supplier_phone'] ?? '-'; ?></td>
            </tr>
            <tr>
                <td class="label">طريقة الدفع:</td>
                <td><?php echo $payment['payment_method']; ?></td>
                <td class="label">المسؤول:</td>
                <td><?php echo $payment['handled_by']; ?></td>
            </tr>
            <?php if ($payment['reference']): ?>
            <tr>
                <td class="label">رقم المرجع:</td>
                <td colspan="3"><?php echo $payment['reference']; ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div class="amount-box">
            <div class="title">المبلغ المدفوع</div>
            <div class="amount"><?php echo number_format($payment['amount'], 2); ?> <?php echo $currency; ?></div>
        </div>
        
        <div class="balance-section">
            <div class="balance-box balance-before">
                <div>المستحق للمورد قبل الدفع</div>
                <div class="amount" style="color: #dc2626;"><?php echo number_format(abs($payment['old_balance']), 2); ?> <?php echo $currency; ?></div>
            </div>
            <div class="balance-box balance-after">
                <div>المستحق للمورد بعد الدفع</div>
                <div class="amount" style="color: <?php echo $payment['new_balance'] > 0 ? '#dc2626' : '#10b981'; ?>;">
                    <?php echo number_format(abs($payment['new_balance']), 2); ?> <?php echo $currency; ?>
                    <?php if ($payment['new_balance'] <= 0): ?> ✅<?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($payment['notes']): ?>
        <div class="notes-box">
            <strong>ملاحظات:</strong> <?php echo $payment['notes']; ?>
        </div>
        <?php endif; ?>
        
        <div class="footer-sig">
            <p>شكراً لتعاملكم معنا</p>
            <p style="margin-top: 15px;">
                توقيع المورد: _______________ &nbsp;&nbsp;&nbsp;&nbsp; 
                توقيع المسؤول: _______________
            </p>
        </div>
    </div>
    
    <script>
    function goBack() {
        if (window.opener && !window.opener.closed) {
            window.close();
        } else {
            window.location.href = <?php echo json_encode($returnUrl); ?>;
        }
    }
    </script>
</body>
</html>
