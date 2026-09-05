<?php
/**
 * Print Customer Return Receipt - Auto Page Break
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$id = $_GET['id'] ?? 0;

$return = getRow(
    "SELECT r.*, i.invoice_number as original_invoice, c.name as customer_name_db, c.phone as customer_phone
     FROM returns r
     LEFT JOIN invoices i ON r.original_invoice_id = i.id
     LEFT JOIN customers c ON r.customer_id = c.id
     WHERE r.id = ?",
    [$id]
);

if (!$return) {
    die('المرتجع غير موجود');
}

$items = getRows("SELECT * FROM return_items WHERE return_id = ?", [$id]);

// Get settings
$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'المحل';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storePhone2 = $settings['store_phone2'] ?? '';
$storeLogo = $settings['store_logo'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

// Customer info
$customerName = $return['customer_name_db'] ?? $return['customer_name'] ?? '-';
$customerPhone = $return['customer_phone'] ?? '-';

// Refund info
$totalAmount = floatval($return['total_amount'] ?? 0);
$deductedFromBalance = floatval($return['deducted_from_balance'] ?? 0);
$cashRefund = $totalAmount - $deductedFromBalance;

if ($deductedFromBalance > 0 && $cashRefund > 0) {
    $refundMethod = 'mixed';
} elseif ($deductedFromBalance > 0 && $cashRefund <= 0) {
    $refundMethod = 'deducted';
    $cashRefund = 0;
} else {
    $refundMethod = 'cash';
    $cashRefund = $totalAmount;
}

$totalItems = count($items);
$pageTitle = 'إيصال مرتجع ' . $return['return_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; direction: rtl; font-size: 12px; }
        
        .no-print { text-align: center; padding: 8px; background: #f59e0b; }
        .no-print .btn { display: inline-block; padding: 6px 15px; margin: 0 3px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; color: white; }
        .btn-print { background: #3b82f6; }
        .btn-close { background: #ef4444; }
        
        /* Narrower width for better print margins */
        .print-area { max-width: 190mm; margin: 10px auto; background: white; padding: 5mm; }
        
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table thead { display: table-header-group; }
        .invoice-table tbody { display: table-row-group; }
        
        .header-cell { padding: 0 0 6px 0; border-bottom: 2px solid #333; }
        .header-content { display: flex; align-items: center; justify-content: center; gap: 15px; }
        .header-logo { width: 55px; height: 55px; object-fit: contain; }
        .header-text { text-align: center; }
        .store-name { font-size: 22px; font-weight: bold; color: #1f2937; }
        .header-info { font-size: 11px; color: #666; }
        
        .invoice-type { display: flex; justify-content: space-between; align-items: center; border: 2px solid #f59e0b; background: #fef3c7; padding: 5px 10px; margin: 5px 0; font-size: 13px; font-weight: bold; }
        .page-num { font-size: 11px; color: #f59e0b; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 6px; border: 1px solid #333; font-size: 11px; }
        .info-row { display: flex; border-bottom: 1px solid #333; }
        .info-row:last-child { border-bottom: none; }
        .info-label { background: #f5f5f5; font-weight: bold; padding: 4px 6px; width: 80px; border-left: 1px solid #333; }
        .info-value { padding: 4px 6px; flex: 1; }
        .info-left { border-left: 1px solid #333; }
        
        .items-header { background: #f59e0b; color: white; }
        .items-header td { padding: 5px; text-align: center; border: 1px solid #333; font-weight: bold; }
        
        .item-row td { padding: 4px; text-align: center; border: 1px solid #333; border-top: none; }
        .item-row:nth-child(even) { background: #f9fafb; }
        
        .summary { font-size: 11px; margin-top: 6px; }
        .summary-row { display: flex; justify-content: space-between; padding: 3px 5px; border-bottom: 1px solid #eee; }
        .summary-row.total { font-weight: bold; font-size: 13px; background: #f59e0b; color: white; padding: 5px; margin: 3px 0; }
        
        .refund-box { color: white; padding: 8px; border-radius: 6px; text-align: center; margin: 8px 0; font-size: 12px; }
        .refund-box.cash { background: #10b981; }
        .refund-box.mixed { background: linear-gradient(135deg, #f59e0b, #10b981); }
        .refund-box.deducted { background: #3b82f6; }
        
        .footer-sig { text-align: center; padding-top: 10px; border-top: 2px solid #333; margin-top: 10px; font-size: 11px; }
        
        .page-info { text-align: center; font-size: 9px; color: #666; margin-top: 5px; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 10px; }
            .print-area { max-width: 100%; margin: 0; padding: 3mm; }
            .invoice-table { page-break-inside: auto; }
            .invoice-table thead { display: table-header-group; }
            .invoice-table tbody tr { page-break-inside: avoid; page-break-after: auto; }
            @page { 
                margin: 8mm;
                @bottom-center {
                    content: "صفحة " counter(page) " من " counter(pages);
                }
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ طباعة</button>
        <button class="btn btn-close" onclick="closePage()">✕ إغلاق</button>
    </div>
    
    <div class="print-area">
        <table class="invoice-table">
            <thead>
                <tr>
                    <td colspan="7" class="header-cell">
                        <div class="header-content">
                            <?php if ($storeLogo): ?>
                            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="Logo" class="header-logo">
                            <?php endif; ?>
                            <div class="header-text">
                                <div class="store-name"><?php echo $storeName; ?></div>
                                <div class="header-info">
                                    <?php if ($storeAddress): ?>العنوان: <?php echo $storeAddress; ?><?php endif; ?>
                                    <?php if ($storePhone): ?> | تليفون: <?php echo $storePhone; ?><?php endif; ?>
                                    <?php if ($storePhone2): ?> - <?php echo $storePhone2; ?><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($storeLogo): ?>
                            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="" class="header-logo" style="visibility:hidden;">
                            <?php endif; ?>
                        </div>
                        
                        <div class="invoice-type">
                            <span>🔄 إيصال مرتجع - <?php echo $return['return_number']; ?></span>
                            <span class="page-num"></span>
                        </div>
                        
                        <div class="info-grid">
                            <div>
                                <div class="info-row">
                                    <span class="info-label">رقم المرتجع:</span>
                                    <span class="info-value"><?php echo $return['return_number']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">الفاتورة:</span>
                                    <span class="info-value"><?php echo $return['original_invoice'] ?? '-'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">العميل:</span>
                                    <span class="info-value"><?php echo $customerName; ?></span>
                                </div>
                            </div>
                            <div class="info-left">
                                <div class="info-row">
                                    <span class="info-label">التاريخ:</span>
                                    <span class="info-value"><?php echo date('Y/m/d', strtotime($return['return_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">المسؤول:</span>
                                    <span class="info-value"><?php echo $return['handled_by']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">التليفون:</span>
                                    <span class="info-value"><?php echo $customerPhone; ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr class="items-header">
                    <td style="width:25px">م</td>
                    <td>الكود</td>
                    <td>الصنف</td>
                    <td>الوحدة</td>
                    <td>الكمية</td>
                    <td>السعر</td>
                    <td>الإجمالي</td>
                </tr>
            </thead>
            
            <tbody>
                <?php $num = 1; foreach ($items as $item): ?>
                <tr class="item-row">
                    <td><?php echo $num++; ?></td>
                    <td><?php echo $item['product_code']; ?></td>
                    <td><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <div class="summary-row total"><span>إجمالي المرتجع:</span><span><?php echo number_format($totalAmount, 2); ?> <?php echo $currency; ?></span></div>
            
            <?php if ($refundMethod == 'cash'): ?>
            <div class="summary-row"><span>استلم العميل كاش:</span><span><?php echo number_format($cashRefund, 2); ?> <?php echo $currency; ?></span></div>
            <?php elseif ($refundMethod == 'deducted'): ?>
            <div class="summary-row"><span>تم خصمه من حساب العميل:</span><span><?php echo number_format($deductedFromBalance, 2); ?> <?php echo $currency; ?></span></div>
            <?php elseif ($refundMethod == 'mixed'): ?>
            <div class="summary-row"><span>تم خصمه من الحساب:</span><span><?php echo number_format($deductedFromBalance, 2); ?> <?php echo $currency; ?></span></div>
            <div class="summary-row"><span>استلم العميل كاش:</span><span><?php echo number_format($cashRefund, 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
        </div>
        
        <div class="refund-box <?php echo $refundMethod; ?>">
            <?php if ($refundMethod == 'cash'): ?>
                💵 تم تسليم <?php echo number_format($cashRefund, 2); ?> <?php echo $currency; ?> كاش للعميل
            <?php elseif ($refundMethod == 'deducted'): ?>
                📝 تم خصم <?php echo number_format($deductedFromBalance, 2); ?> <?php echo $currency; ?> من حساب العميل
            <?php elseif ($refundMethod == 'mixed'): ?>
                📝💵 تم خصم <?php echo number_format($deductedFromBalance, 2); ?> من الحساب + تسليم <?php echo number_format($cashRefund, 2); ?> كاش
            <?php endif; ?>
        </div>
        
        <div class="footer-sig">
            توقيع العميل: _______________ &nbsp;&nbsp;&nbsp; توقيع المسؤول: _______________
        </div>
        

    </div>

<script>
window.addEventListener('beforeprint', function() {
    var printArea = document.querySelector('.print-area');
    var pageHeight = 277;
    var contentHeight = printArea.scrollHeight * 0.264583;
    var totalPages = Math.max(1, Math.ceil(contentHeight / pageHeight));
    document.querySelectorAll('.page-num').forEach(function(el) {
        el.textContent = '1 من ' + totalPages;
    });
});
</script>
</body>
<script>
function closePage() {
    window.close();
    setTimeout(function() {
        window.location.href = 'index.php';
    }, 150);
}
</script>
</html>
