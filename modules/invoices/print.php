<?php
/**
 * Print Sale Invoice - Auto Page Break
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['invoices.sale.view', 'invoices.sale.create', 'reports.view']);

$id = $_GET['id'] ?? 0;

// Determine safe return URL based on permissions and referer
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'reports') !== false) {
    $returnUrl = $_SERVER['HTTP_REFERER'];
} elseif (hasPermission('invoices.sale.view')) {
    $returnUrl = 'list.php';
} elseif (hasPermission('invoices.sale.create')) {
    $returnUrl = 'sale.php';
} else {
    $returnUrl = '../../index.php';
}

$invoice = getRow(
    "SELECT i.*, 
            w.name as warehouse_name,
            c.name as customer_name_db, 
            c.phone as customer_phone_db, 
            c.balance as customer_balance
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id = c.id
     LEFT JOIN warehouses w ON i.warehouse_id = w.id
     WHERE i.id = ? AND i.type = 'sale'",
    [$id]
);

if (!$invoice) {
    die('الفاتورة غير موجودة');
}

$items = getRows("SELECT * FROM invoice_items WHERE invoice_id = ?", [$id]);

// Get settings
$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'المحل';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storePhone2 = $settings['store_phone2'] ?? '';
$storeLogo = $settings['store_logo'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

// Customer info
$customerName = $invoice['customer_name_db'] ?? $invoice['customer_name'] ?: '-';
$customerPhone = $invoice['customer_phone_db'] ?? $invoice['customer_phone'] ?: '-';

// Balance calculations
$oldBalance = floatval($invoice['old_balance'] ?? 0);
$oldDebt = $oldBalance < 0 ? abs($oldBalance) : 0;
$invoiceRemaining = $invoice['remaining_amount'];
$totalRemaining = $invoiceRemaining + $oldDebt;

$totalItems = count($items);
$pageTitle = 'فاتورة بيع ' . $invoice['invoice_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; direction: rtl; font-size: 11px; }
        
        .no-print { text-align: center; padding: 8px; background: #3b82f6; }
        .no-print .btn { display: inline-block; padding: 6px 15px; margin: 0 3px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; color: white; }
        .btn-print { background: #10b981; }
        .btn-close { background: #ef4444; }
        .btn-new { background: #8b5cf6; }
        
        /* Narrower width for better print margins */
        .print-area { max-width: 190mm; margin: 10px auto; background: white; padding: 5mm; }
        
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table thead { display: table-header-group; }
        .invoice-table tbody { display: table-row-group; }
        
        .header-cell { padding: 0 0 5px 0; border-bottom: 2px solid #333; }
        .header-content { display: flex; align-items: center; justify-content: center; gap: 12px; }
        .header-logo { width: 50px; height: 50px; object-fit: contain; }
        .header-text { text-align: center; }
        .store-name { font-size: 20px; font-weight: bold; color: #1f2937; }
        .header-info { font-size: 10px; color: #666; }
        
        .invoice-type { display: flex; justify-content: space-between; align-items: center; border: 2px solid #3b82f6; background: #eff6ff; padding: 5px 10px; margin: 5px 0; font-size: 13px; font-weight: bold; }
        .page-num { font-size: 11px; color: #3b82f6; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 5px; border: 1px solid #333; font-size: 10px; }
        .info-row { display: flex; border-bottom: 1px solid #333; }
        .info-row:last-child { border-bottom: none; }
        .info-label { background: #f5f5f5; font-weight: bold; padding: 3px 5px; width: 70px; border-left: 1px solid #333; }
        .info-value { padding: 3px 5px; flex: 1; }
        .info-left { border-left: 1px solid #333; }
        
        .items-header { background: #3b82f6; color: white; }
        .items-header td { padding: 4px; text-align: center; border: 1px solid #333; font-weight: bold; font-size: 10px; }
        
        .item-row td { padding: 3px; text-align: center; border: 1px solid #333; border-top: none; font-size: 10px; }
        .item-row:nth-child(even) { background: #f9fafb; }
        
        .summary { font-size: 10px; margin-top: 5px; }
        .summary-row { display: flex; justify-content: space-between; padding: 2px 5px; border-bottom: 1px solid #eee; }
        .summary-row.total { font-weight: bold; font-size: 12px; background: #3b82f6; color: white; padding: 4px 5px; margin: 2px 0; }
        
        .footer-sig { text-align: center; padding-top: 8px; border-top: 2px solid #333; margin-top: 8px; font-size: 10px; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 10px; }
            .print-area { max-width: 100%; margin: 0; padding: 3mm; box-shadow: none; }
            
            .invoice-table { page-break-inside: auto; }
            .invoice-table thead { display: table-header-group; }
            .invoice-table tbody tr { page-break-inside: avoid; page-break-after: auto; }
            
            @page { 
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ طباعة</button>
        <button class="btn btn-close" onclick="closePage()">↩️ رجوع / إغلاق</button>
        <?php if (hasPermission('invoices.sale.create')): ?>
        <a href="sale.php" class="btn btn-new">✚ فاتورة جديدة</a>
        <?php endif; ?>
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
                                    <?php if ($storePhone): ?> | ت: <?php echo $storePhone; ?><?php endif; ?>
                                    <?php if ($storePhone2): ?> - <?php echo $storePhone2; ?><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($storeLogo): ?>
                            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="" class="header-logo" style="visibility:hidden;">
                            <?php endif; ?>
                        </div>
                        
                        <div class="invoice-type">
                            <span>🧾 فاتورة بيع - <?php echo $invoice['invoice_number']; ?></span>
                            <span class="page-num"></span>
                        </div>
                        
                        <div class="info-grid">
                            <div>
                                <div class="info-row">
                                    <span class="info-label">التاريخ:</span>
                                    <span class="info-value"><?php echo date('Y/m/d', strtotime($invoice['date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">المخزن:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($invoice['warehouse_name'] ?? 'المخزن الرئيسي'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">العميل:</span>
                                    <span class="info-value"><?php echo $customerName; ?></span>
                                </div>
                            </div>
                            <div class="info-left">
                                <div class="info-row">
                                    <span class="info-label">التليفون:</span>
                                    <span class="info-value"><?php echo $customerPhone; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">الدفع:</span>
                                    <span class="info-value"><?php echo $invoice['payment_method']; ?> | <?php echo $invoice['handled_by']; ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr class="items-header">
                    <td style="width:20px">م</td>
                    <td style="width:60px">الكود</td>
                    <td>الصنف</td>
                    <td style="width:40px">الوحدة</td>
                    <td style="width:35px">الكمية</td>
                    <td style="width:55px">السعر</td>
                    <td style="width:60px">الإجمالي</td>
                </tr>
            </thead>
            
            <tbody>
                <?php $num = 1; foreach ($items as $item): ?>
                <tr class="item-row">
                    <td><?php echo $num++; ?></td>
                    <td><?php echo $item['product_code']; ?></td>
                    <td style="text-align:right;padding-right:5px"><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <div class="summary-row"><span>الإجمالي:</span><span><?php echo number_format($invoice['total_amount'], 2); ?> <?php echo $currency; ?></span></div>
            <?php if ($invoice['discount'] > 0): ?>
            <div class="summary-row"><span>الخصم:</span><span><?php echo number_format($invoice['discount'], 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
            <div class="summary-row total"><span>الصافي:</span><span><?php echo number_format($invoice['total_amount'] - $invoice['discount'], 2); ?> <?php echo $currency; ?></span></div>
            <div class="summary-row"><span>المدفوع:</span><span><?php echo number_format($invoice['paid_amount'], 2); ?> <?php echo $currency; ?></span></div>
            <div class="summary-row" style="font-weight:bold"><span>إجمالي الباقي:</span><span><?php echo number_format($totalRemaining, 2); ?> <?php echo $currency; ?></span></div>
        </div>
        
        <div class="footer-sig">
            توقيع العميل: _______________ &nbsp;&nbsp;&nbsp; توقيع البائع: _______________
        </div>
    </div>
    
    <?php if (isset($_GET['show_installment']) && $totalRemaining > 0 && $invoice['customer_id']): ?>
    <div class="no-print" style="max-width:190mm;margin:10px auto;padding:15px;background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;text-align:center;">
        <p>💡 يوجد باقي على العميل: <strong><?php echo number_format($totalRemaining, 2); ?></strong> <?php echo $currency; ?></p>
        <a href="../installments/create.php?type=customer&entity_id=<?php echo $invoice['customer_id']; ?>&amount=<?php echo $totalRemaining; ?>" 
           style="background:#8b5cf6;color:white;padding:8px 20px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px;">📅 تقسيط هذا المبلغ</a>
    </div>
    <?php endif; ?>
    
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
    if (window.opener && !window.opener.closed) {
        window.close();
    } else {
        window.location.href = <?php echo json_encode($returnUrl); ?>;
    }
}
</script>
</html>
