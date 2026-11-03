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

// Print format: 'a4' (default, identical to the old invoice) or 'thermal' (80mm).
$format = (($_GET['format'] ?? '') === 'thermal') ? 'thermal' : 'a4';
$cashReceived = isset($invoice['cash_received']) && $invoice['cash_received'] !== null
    ? (float)$invoice['cash_received']
    : null;
$changeDue = ($cashReceived !== null && $cashReceived > (float)$invoice['paid_amount'])
    ? $cashReceived - (float)$invoice['paid_amount']
    : 0;
$formatUrl = function ($fmt) use ($id) {
    return 'print.php?id=' . (int)$id . '&format=' . $fmt;
};
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

        /* ---- Thermal 80mm receipt ---- */
        .thermal-area {
            width: 80mm; max-width: 100%; margin: 10px auto; background: #fff;
            padding: 4mm; font-size: 11px; line-height: 1.5; color: #000;
            border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        .thermal-area .t-store { text-align: center; }
        .thermal-area .t-store-name { font-size: 17px; font-weight: 900; margin-bottom: 2px; }
        .thermal-area .t-store-info { font-size: 9.5px; }
        .thermal-area .t-sep { border-top: 1px dashed #000; margin: 6px 0; }
        .thermal-area .t-meta { font-size: 10px; }
        .thermal-area .t-meta div { display: flex; justify-content: space-between; gap: 8px; }
        .thermal-area .t-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .thermal-area .t-table th {
            border-bottom: 1px solid #000; padding: 3px 2px; text-align: right; font-size: 9.5px;
        }
        .thermal-area .t-table td { padding: 3px 2px; text-align: center; vertical-align: top; }
        .thermal-area .t-table td.t-name { text-align: right; }
        .thermal-area .t-totals { font-size: 11px; }
        .thermal-area .t-totals div { display: flex; justify-content: space-between; padding: 2px 0; }
        .thermal-area .t-grand {
            border-top: 2px solid #000; border-bottom: 2px solid #000;
            margin: 4px 0; padding: 4px 0 !important; font-weight: 900; font-size: 13px;
        }
        .thermal-area .t-thanks { text-align: center; font-size: 10px; font-weight: 700; margin-top: 6px; }
        .thermal-area .t-barcode { text-align: center; font-size: 10px; letter-spacing: 2px; margin-top: 4px; }

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

            /* Thermal: no page margins, roll width only */
            body.thermal { width: 80mm; margin: 0; padding: 0; }
            .thermal-area {
                width: 80mm; margin: 0; padding: 2mm 3mm; box-shadow: none; border-radius: 0;
            }
        }
        <?php if ($format === 'thermal'): ?>
        @media print {
            @page { size: 80mm auto; margin: 2mm; }
        }
        <?php endif; ?>
    </style>
</head>
<body class="<?php echo $format === 'thermal' ? 'thermal' : ''; ?>">
    <div class="no-print">
        <span style="display:inline-flex;gap:4px;vertical-align:middle;">
            <a href="<?php echo $formatUrl('a4'); ?>" class="btn"
               style="background:<?php echo $format === 'a4' ? '#0f172a' : '#e2e8f0'; ?>;color:<?php echo $format === 'a4' ? '#fff' : '#0f172a'; ?>;">📄 ورقة A4</a>
            <a href="<?php echo $formatUrl('thermal'); ?>" class="btn"
               style="background:<?php echo $format === 'thermal' ? '#0f172a' : '#e2e8f0'; ?>;color:<?php echo $format === 'thermal' ? '#fff' : '#0f172a'; ?>;">🧻 حراري 80مم</a>
        </span>
    </div>
    
    <?php if ($format === 'a4'): ?>
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
    <?php else: ?>
    <div class="thermal-area">
        <div class="t-store">
            <div class="t-store-name"><?php echo $storeName; ?></div>
            <div class="t-store-info">
                <?php if ($storeAddress): ?><?php echo $storeAddress; ?><br><?php endif; ?>
                <?php if ($storePhone): ?>ت: <?php echo $storePhone; ?><?php endif; ?>
                <?php if ($storePhone2): ?> - <?php echo $storePhone2; ?><?php endif; ?>
            </div>
        </div>

        <div class="t-sep"></div>

        <div class="t-meta">
            <div><span>فاتورة بيع</span><strong><?php echo $invoice['invoice_number']; ?></strong></div>
            <div><span>التاريخ:</span><span><?php echo date('Y/m/d h:i A', strtotime($invoice['created_at'] ?? $invoice['date'])); ?></span></div>
            <div><span>العميل:</span><span><?php echo $customerName; ?></span></div>
            <?php if ($customerPhone && $customerPhone !== '-'): ?>
            <div><span>التليفون:</span><span><?php echo $customerPhone; ?></span></div>
            <?php endif; ?>
            <div><span>الكاشير:</span><span><?php echo $invoice['handled_by']; ?></span></div>
        </div>

        <div class="t-sep"></div>

        <table class="t-table">
            <thead>
                <tr>
                    <th style="width:38%;">الصنف</th>
                    <th style="width:14%;">كمية</th>
                    <th style="width:22%;">سعر</th>
                    <th style="width:26%;">إجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="t-name"><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="t-sep"></div>

        <div class="t-totals">
            <div><span>الإجمالي:</span><span><?php echo number_format($invoice['total_amount'], 2); ?> <?php echo $currency; ?></span></div>
            <?php if ($invoice['discount'] > 0): ?>
            <div><span>الخصم:</span><span>- <?php echo number_format($invoice['discount'], 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
            <div class="t-grand"><span>الصافي:</span><span><?php echo number_format($invoice['total_amount'] - $invoice['discount'], 2); ?> <?php echo $currency; ?></span></div>
            <div><span>المدفوع:</span><span><?php echo number_format($invoice['paid_amount'], 2); ?> <?php echo $currency; ?></span></div>
            <?php if ($cashReceived !== null): ?>
            <div><span>المستلم:</span><span><?php echo number_format($cashReceived, 2); ?> <?php echo $currency; ?></span></div>
            <?php if ($changeDue > 0): ?>
            <div><span>الباقي للعميل:</span><span><?php echo number_format($changeDue, 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($invoiceRemaining > 0): ?>
            <div><span>المتبقي على الفاتورة:</span><span><?php echo number_format($invoiceRemaining, 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
            <?php if ($oldDebt > 0): ?>
            <div><span>رصيد قديم:</span><span><?php echo number_format($oldDebt, 2); ?> <?php echo $currency; ?></span></div>
            <?php endif; ?>
        </div>

        <div class="t-sep"></div>
        <div class="t-thanks">شكراً لتعاملكم معنا 🌟</div>
        <div class="t-thanks" style="font-weight:400;font-size:9px;">الاستبدال خلال 14 يوم بشرط الفاتورة</div>
    </div>
    <?php endif; ?>
    
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
    if (!printArea) return;
    var pageHeight = 277;
    var contentHeight = printArea.scrollHeight * 0.264583;
    var totalPages = Math.max(1, Math.ceil(contentHeight / pageHeight));
    document.querySelectorAll('.page-num').forEach(function(el) {
        el.textContent = '1 من ' + totalPages;
    });
});
</script>
</body>
</html>
